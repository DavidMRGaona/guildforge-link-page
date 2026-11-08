<?php

declare(strict_types=1);

namespace Modules\LinkPage\Filament\Resources;

use App\Filament\Actions\RowDetailsAction;
use App\Filament\Resources\BaseResource;
use BackedEnum;
use Closure;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Modules\LinkPage\Domain\Enums\LinkIcon;
use Modules\LinkPage\Domain\Enums\LinkPlacement;
use Modules\LinkPage\Domain\Enums\LinkVisibility;
use Modules\LinkPage\Domain\ValueObjects\LinkUrl;
use Modules\LinkPage\Filament\Resources\LinkResource\Pages\CreateLink;
use Modules\LinkPage\Filament\Resources\LinkResource\Pages\EditLink;
use Modules\LinkPage\Filament\Resources\LinkResource\Pages\ListLinks;
use Modules\LinkPage\Infrastructure\Persistence\Eloquent\Models\LinkModel;

/**
 * The page's own links (Discord, the sign-up form…): buttons placed before or after the
 * events, dragged into order, optionally scheduled in the site's time zone.
 */
final class LinkResource extends BaseResource
{
    protected static ?string $model = LinkModel::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-list-bullet';

    protected static ?int $navigationSort = 92;

    protected static ?string $slug = 'link-page-links';

    public static function getNavigationGroup(): string
    {
        return __('filament.navigation.content');
    }

    public static function getNavigationLabel(): string
    {
        return __('link-page::messages.links.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('link-page::messages.links.model.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('link-page::messages.links.model.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('link-page::messages.links.sections.link'))
                    ->schema([
                        TextInput::make('title')
                            ->label(__('link-page::messages.links.fields.title'))
                            ->required()
                            ->maxLength(LinkModel::MAX_TITLE_LENGTH),
                        TextInput::make('url')
                            ->label(__('link-page::messages.links.fields.url'))
                            ->helperText(__('link-page::messages.links.fields.url_help'))
                            ->placeholder(__('link-page::messages.links.fields.url_placeholder'))
                            ->required()
                            ->maxLength(LinkUrl::MAX_LENGTH)
                            ->rules([self::safeAddress(...)])
                            // What is stored is the normalised address, not what was typed
                            ->dehydrateStateUsing(fn (mixed $state): mixed => is_string($state) ? (LinkUrl::tryFrom($state)->value ?? $state) : $state),
                        TextInput::make('description')
                            ->label(__('link-page::messages.links.fields.description'))
                            ->helperText(__('link-page::messages.links.fields.description_help'))
                            ->maxLength(LinkModel::MAX_DESCRIPTION_LENGTH),
                    ]),
                Section::make(__('link-page::messages.links.sections.appearance'))
                    ->columns(['md' => 2])
                    ->schema([
                        Select::make('icon')
                            ->label(__('link-page::messages.links.fields.icon'))
                            ->helperText(__('link-page::messages.links.fields.icon_help'))
                            ->options(LinkIcon::options())
                            ->native(false)
                            ->searchable(),
                        Select::make('placement')
                            ->label(__('link-page::messages.links.fields.placement'))
                            ->helperText(__('link-page::messages.links.fields.placement_help'))
                            ->options(LinkPlacement::options())
                            ->default(LinkPlacement::Before->value)
                            ->native(false)
                            ->selectablePlaceholder(false)
                            ->required(),
                        Toggle::make('is_featured')
                            ->label(__('link-page::messages.links.fields.is_featured'))
                            ->helperText(__('link-page::messages.links.fields.is_featured_help')),
                        Toggle::make('is_active')
                            ->label(__('link-page::messages.links.fields.is_active'))
                            ->helperText(__('link-page::messages.links.fields.is_active_help'))
                            ->default(true),
                    ]),
                Section::make(__('link-page::messages.links.sections.schedule'))
                    ->description(__('link-page::messages.links.sections.schedule_help'))
                    ->columns(['md' => 2])
                    ->schema([
                        DateTimePicker::make('starts_at')
                            ->label(__('link-page::messages.links.fields.starts_at'))
                            ->native(false)
                            ->displayFormat('d/m/Y H:i')
                            ->seconds(false)
                            ->nullable(),
                        DateTimePicker::make('ends_at')
                            ->label(__('link-page::messages.links.fields.ends_at'))
                            ->native(false)
                            ->displayFormat('d/m/Y H:i')
                            ->seconds(false)
                            ->nullable()
                            ->after('starts_at'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('link-page::messages.links.fields.title'))
                    ->searchable()
                    ->wrap(),
                TextColumn::make('status')
                    ->label(__('link-page::messages.links.fields.status'))
                    ->badge()
                    ->getStateUsing(fn (LinkModel $record): string => $record->visibilityAt(now())->value)
                    ->formatStateUsing(fn (string $state): string => LinkVisibility::from($state)->label())
                    ->color(fn (string $state): string => LinkVisibility::from($state)->color()),
                TextColumn::make('url')
                    ->label(__('link-page::messages.links.fields.url'))
                    ->limit(36)
                    ->searchable()
                    ->visibleFrom('md'),
                TextColumn::make('placement')
                    ->label(__('link-page::messages.links.fields.placement'))
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (LinkPlacement $state): string => $state->label())
                    ->visibleFrom('xl'),
                IconColumn::make('is_featured')
                    ->label(__('link-page::messages.links.fields.is_featured'))
                    ->boolean()
                    ->visibleFrom('xl'),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            // Dragging saves the order of the rows on screen: all of them must be on screen
            ->paginated(false)
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('link-page::messages.links.fields.is_active')),
                SelectFilter::make('placement')
                    ->label(__('link-page::messages.links.fields.placement'))
                    ->options(LinkPlacement::options()),
            ])
            // One menu: two labelled actions would widen the table past its container
            ->recordActions([
                ActionGroup::make([
                    RowDetailsAction::make(),
                    EditAction::make(),
                    self::deleteAction(DeleteAction::make()),
                ]),
            ]);
    }

    /**
     * Irreversible: a modal and a box to tick that says what it means.
     */
    public static function deleteAction(DeleteAction $action): DeleteAction
    {
        return $action
            ->modalDescription(__('link-page::messages.links.actions.delete_description'))
            ->schema([
                Checkbox::make('confirmation')
                    ->label(__('link-page::messages.links.actions.delete_confirmation'))
                    ->accepted()
                    ->required(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLinks::route('/'),
            'create' => CreateLink::route('/create'),
            'edit' => EditLink::route('/{record}/edit'),
        ];
    }

    /**
     * Only https, mailto and tel pass, whatever the casing or the spaces around: the same
     * value object the model and the page use.
     *
     * @return Closure(string, mixed, Closure): void
     */
    private static function safeAddress(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (is_string($value) && trim($value) !== '' && ! LinkUrl::tryFrom($value) instanceof LinkUrl) {
                $fail(__('link-page::messages.links.fields.url_invalid'));
            }
        };
    }
}
