<?php

declare(strict_types=1);

namespace Modules\LinkPage\Filament\Pages;

use App\Application\Modules\Services\ModuleManagerServiceInterface;
use App\Application\Modules\Services\ModuleSettingsStoreInterface;
use App\Application\Services\EventQueryServiceInterface;
use App\Application\Services\SiteBrandingServiceInterface;
use App\Application\Services\TagQueryServiceInterface;
use App\Application\Services\TimezoneServiceInterface;
use App\Domain\Modules\ValueObjects\ModuleName;
use App\Filament\Concerns\ChecksPermissions;
use App\Filament\Concerns\SafeModuleNavigation;
use BackedEnum;
use Carbon\CarbonImmutable;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Modules\LinkPage\Application\Contracts\LinkPageSettingsInterface;
use Modules\LinkPage\Domain\Enums\BackgroundType;
use Modules\LinkPage\Domain\Enums\CalendarView;
use Modules\LinkPage\Domain\Enums\LogoSource;
use Modules\LinkPage\Filament\Schemas\AppearanceSection;
use Modules\LinkPage\Filament\Support\AppearanceFormState;
use Modules\LinkPage\Filament\Support\AppearancePreview;
use Modules\LinkPage\Http\Presenters\AppearancePresenter;
use Throwable;

/**
 * The settings of /enlaces, open to link-page:manage (admins hold every permission).
 *
 * @property Schema $form
 */
final class LinkPageSettingsPage extends Page implements HasForms
{
    use ChecksPermissions;
    use InteractsWithForms;
    use SafeModuleNavigation;

    private const string MODULE = 'link-page';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-link';

    protected static ?int $navigationSort = 90;

    protected static ?string $slug = 'link-page-settings';

    protected string $view = 'filament.pages.simple-settings';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    /** @var array<string, string>|null */
    private ?array $eventOptions = null;

    /** @var array<string, string>|null */
    private ?array $tagOptions = null;

    public static function getNavigationGroup(): string
    {
        return __('filament.navigation.content');
    }

    public static function getNavigationLabel(): string
    {
        return __('link-page::messages.settings.title');
    }

    public function getTitle(): string
    {
        return __('link-page::messages.settings.title');
    }

    public static function canAccess(): bool
    {
        return self::userCan('link-page:manage');
    }

    public function mount(LinkPageSettingsInterface $settings): void
    {
        // Pinned or hidden events that have ended and tags that no longer exist are dropped,
        // so they neither show as raw ids nor stop the form from saving
        $this->form->fill([
            'enabled' => $settings->isEnabled(),
            'intro' => $settings->intro(),
            'events' => [
                'limit' => $settings->eventsLimit(),
                'within_days' => $settings->eventsWithinDays(),
                'tags' => array_values(array_intersect($settings->eventTagSlugs(), array_keys($this->tagOptions()))),
                'pinned' => array_values(array_intersect($settings->pinnedEventIds(), array_keys($this->eventOptions()))),
                'hidden' => array_values(array_intersect($settings->hiddenEventIds(), array_keys($this->eventOptions()))),
            ],
            'calendar' => [
                'initial_view' => $settings->calendarInitialView()->value,
            ],
            'appearance' => $settings->appearance()->toArray(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('link-page::messages.settings.sections.page'))
                    ->schema([
                        Toggle::make('enabled')
                            ->label(__('link-page::messages.settings.enabled'))
                            ->helperText(__('link-page::messages.settings.enabled_help')),
                        Textarea::make('intro')
                            ->label(__('link-page::messages.settings.intro'))
                            ->helperText(__('link-page::messages.settings.intro_help'))
                            ->maxLength(LinkPageSettingsInterface::MAX_INTRO_LENGTH)
                            ->rows(3),
                    ]),
                Section::make(__('link-page::messages.settings.sections.events'))
                    ->description(__('link-page::messages.settings.sections.events_help'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('events.limit')
                            ->label(__('link-page::messages.settings.events_limit'))
                            ->helperText(__('link-page::messages.settings.events_limit_help'))
                            ->integer()
                            ->minValue(1)
                            ->maxValue(LinkPageSettingsInterface::MAX_EVENTS)
                            ->required(),
                        TextInput::make('events.within_days')
                            ->label(__('link-page::messages.settings.events_within_days'))
                            ->helperText(__('link-page::messages.settings.events_within_days_help'))
                            ->integer()
                            ->minValue(1)
                            ->maxValue(LinkPageSettingsInterface::MAX_WITHIN_DAYS)
                            ->suffix(__('link-page::messages.settings.days'))
                            ->required(),
                        Select::make('events.tags')
                            ->label(__('link-page::messages.settings.events_tags'))
                            ->helperText(__('link-page::messages.settings.events_tags_help'))
                            ->multiple()
                            ->native(false)
                            ->searchable()
                            ->options(fn (): array => $this->tagOptions())
                            ->rules([fn (): Closure => $this->onlyOptions($this->tagOptions(...))])
                            ->columnSpanFull(),
                        Select::make('events.pinned')
                            ->label(__('link-page::messages.settings.events_pinned'))
                            ->helperText(__('link-page::messages.settings.events_pinned_help'))
                            ->multiple()
                            ->native(false)
                            ->searchable()
                            ->options(fn (): array => $this->eventOptions())
                            ->rules([fn (): Closure => $this->onlyOptions($this->eventOptions(...))])
                            ->columnSpanFull(),
                        Select::make('events.hidden')
                            ->label(__('link-page::messages.settings.events_hidden'))
                            ->helperText(__('link-page::messages.settings.events_hidden_help'))
                            ->multiple()
                            ->native(false)
                            ->searchable()
                            ->options(fn (): array => $this->eventOptions())
                            ->rules([fn (): Closure => $this->onlyOptions($this->eventOptions(...))])
                            ->columnSpanFull(),
                    ]),
                Section::make(__('link-page::messages.settings.sections.calendar'))
                    ->schema([
                        Select::make('calendar.initial_view')
                            ->label(__('link-page::messages.settings.calendar_initial_view'))
                            ->helperText(__('link-page::messages.settings.calendar_initial_view_help'))
                            ->options(CalendarView::options())
                            ->native(false)
                            ->selectablePlaceholder(false)
                            ->required(),
                    ]),
                AppearanceSection::make(),
            ])
            ->statePath('data');
    }

    public function save(
        ModuleManagerServiceInterface $moduleManager,
        ModuleSettingsStoreInterface $settingsStore,
        LinkPageSettingsInterface $settings,
    ): void {
        // Livewire methods can be called without the page's button
        abort_unless(self::canAccess(), 403);

        $state = $this->form->getState();
        $events = is_array($state['events'] ?? null) ? $state['events'] : [];

        // Colours below WCAG AA are not saved; the field says which pair fails and by how much
        $storedAppearance = $settings->appearance();
        try {
            $appearance = AppearanceFormState::toAppearance(is_array($state['appearance'] ?? null) ? $state['appearance'] : [], $storedAppearance);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages(['data.appearance.template' => __('validation.in', ['attribute' => __('link-page::messages.appearance.template')])]);
        }
        $contrastErrors = AppearanceFormState::contrastErrors($appearance);
        if ($contrastErrors !== []) {
            throw ValidationException::withMessages($contrastErrors);
        }

        // Keys this form does not show (other releases or pages) keep their stored values
        $moduleManager->updateSettings(new ModuleName(self::MODULE), array_replace($settingsStore->stored(self::MODULE), [
            'enabled' => (bool) ($state['enabled'] ?? false),
            'intro' => is_string($state['intro'] ?? null) && trim($state['intro']) !== '' ? trim($state['intro']) : null,
            'events' => [
                'limit' => (int) ($events['limit'] ?? LinkPageSettingsInterface::DEFAULT_EVENTS),
                'within_days' => (int) ($events['within_days'] ?? LinkPageSettingsInterface::DEFAULT_WITHIN_DAYS),
                'tags' => array_values((array) ($events['tags'] ?? [])),
                'pinned' => array_values((array) ($events['pinned'] ?? [])),
                'hidden' => array_values((array) ($events['hidden'] ?? [])),
            ],
            'calendar' => [
                'initial_view' => is_array($state['calendar'] ?? null) ? ($state['calendar']['initial_view'] ?? null) : null,
            ],
            'appearance' => $appearance->toArray(),
        ]));

        AppearanceFormState::deleteReplacedImages($storedAppearance, $appearance);

        Notification::make()
            ->title(__('link-page::messages.settings.saved'))
            ->success()
            ->send();
    }

    /**
     * What the appearance preview shows, from the form as it is now. A value that is not valid
     * yet (a half-typed colour) shows the saved appearance instead.
     *
     * @return array{valid: bool, siteColors: bool, name: string, intro: string|null, initial: string, logoUrl: string|null, logoIsDark: bool, logoCustom: bool, phones: list<array<string, mixed>>}
     */
    public function appearancePreview(): array
    {
        $settings = app(LinkPageSettingsInterface::class);
        $branding = app(SiteBrandingServiceInterface::class);
        $stored = $settings->appearance();
        $state = is_array($this->data['appearance'] ?? null) ? $this->data['appearance'] : [];

        // Uploads not saved yet are shown from their temporary URL
        $uploads = [];
        foreach (['logo', 'background'] as $group) {
            $upload = $this->previewUpload($state[$group]['image'] ?? null);
            $uploads[$group] = $upload['url'];
            if (is_array($state[$group] ?? null)) {
                $state[$group]['image'] = $upload['path'];
            }
        }

        try {
            $appearance = AppearanceFormState::toAppearance($state, $stored);
            $valid = true;
        } catch (InvalidArgumentException) {
            $appearance = $stored;
            $valid = false;
        }

        $presenter = app(AppearancePresenter::class);
        $logo = $presenter->logo($appearance, $branding->customLogoUrl(), $branding->customLogoIsDark());
        $logoUrl = $valid && $appearance->effectiveLogoSource() === LogoSource::Custom ? ($uploads['logo'] ?? $logo['url']) : $logo['url'];
        $backgroundUrl = $appearance->effectiveBackgroundType() === BackgroundType::Image
            ? ($valid ? $uploads['background'] : null) ?? ($appearance->backgroundImage !== null ? Storage::disk('images')->url($appearance->backgroundImage) : null)
            : null;
        $name = $branding->siteName();
        $intro = is_string($this->data['intro'] ?? null) && trim($this->data['intro']) !== '' ? trim($this->data['intro']) : null;

        return [
            'valid' => $valid,
            'siteColors' => $appearance->template->usesSiteColors(),
            'name' => $name,
            'intro' => $intro,
            'initial' => mb_strtoupper(mb_substr(trim($name), 0, 1)),
            'logoUrl' => $logoUrl,
            'logoIsDark' => $logo['isDark'],
            'logoCustom' => $appearance->effectiveLogoSource() === LogoSource::Custom,
            'phones' => app(AppearancePreview::class)->phones($appearance, $backgroundUrl),
        ];
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label(__('common.save'))
                ->submit('save'),
        ];
    }

    /**
     * Published events that have not ended, up to the longest horizon, labelled with their date.
     *
     * @return array<string, string>
     */
    private function eventOptions(): array
    {
        if ($this->eventOptions !== null) {
            return $this->eventOptions;
        }

        $timezone = app(TimezoneServiceInterface::class);
        $today = CarbonImmutable::instance($timezone->today());
        $now = CarbonImmutable::now();

        $events = app(EventQueryServiceInterface::class)->findByDateRange(
            $today->format('Y-m-d'),
            $today->addDays(LinkPageSettingsInterface::MAX_WITHIN_DAYS + 1)->format('Y-m-d'),
        );

        $options = [];
        foreach ($events as $event) {
            if ($event->endDate > $now) {
                $options[$event->id] = $timezone->format($event->startDate).' · '.$event->title;
            }
        }

        return $this->eventOptions = $options;
    }

    /**
     * @return array<string, string>
     */
    private function tagOptions(): array
    {
        if ($this->tagOptions !== null) {
            return $this->tagOptions;
        }

        $options = [];
        foreach (app(TagQueryServiceInterface::class)->getByType('events') as $tag) {
            $options[$tag->slug] = $tag->name;
        }

        return $this->tagOptions = $options;
    }

    /**
     * A file upload's raw state: the stored path, or a placeholder path and the temporary URL
     * of a file not saved yet.
     *
     * @return array{path: string|null, url: string|null}
     */
    private function previewUpload(mixed $state): array
    {
        $file = is_array($state) ? (array_first($state) ?? null) : $state;

        if (is_string($file) && $file !== '') {
            return ['path' => $file, 'url' => null];
        }

        if ($file instanceof TemporaryUploadedFile) {
            try {
                return ['path' => 'link-page/preview.png', 'url' => $file->temporaryUrl()];
            } catch (Throwable) {
                return ['path' => null, 'url' => null];
            }
        }

        return ['path' => null, 'url' => null];
    }

    /**
     * Filament only checks the options of single selects: multiple ones are checked here.
     *
     * @param  Closure(): array<string, string>  $options
     */
    private function onlyOptions(Closure $options): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail) use ($options): void {
            $allowed = array_map(strval(...), array_keys($options()));
            foreach (is_array($value) ? $value : [$value] as $item) {
                if (! in_array($item, $allowed, true)) {
                    $fail(__('validation.in', ['attribute' => $attribute]));

                    return;
                }
            }
        };
    }
}
