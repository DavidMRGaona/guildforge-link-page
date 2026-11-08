<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Feature\Filament;

use App\Application\Modules\Services\ModuleSettingsStoreInterface;
use App\Application\Services\SettingsServiceInterface;
use Livewire\Livewire;
use Modules\LinkPage\Application\Contracts\LinkPageSettingsInterface;
use Modules\LinkPage\Domain\Enums\SocialNetwork;
use Modules\LinkPage\Domain\Enums\SocialPosition;
use Modules\LinkPage\Filament\Pages\LinkPageSettingsPage;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The page only ticks which of the site's networks to show and where the row goes (it never
 * asks for an address), with the rest of the page settings (link-page:manage).
 */
final class LinkPageSocialFormTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'link-page';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel([], [LinkPageSettingsPage::class]);
        $this->actingAsPanelAdmin();
    }

    public function test_the_form_opens_with_every_network_the_site_has_ticked_and_the_row_at_the_bottom(): void
    {
        $this->site(['social_instagram' => 'www.instagram.com/example', 'social_discord' => 'discord.gg/example', 'contact_email' => 'info@example.test']);

        Livewire::test(LinkPageSettingsPage::class)
            ->assertFormSet(['social.position' => 'bottom', 'social.shown' => ['instagram', 'discord', 'email']]);
    }

    public function test_it_opens_with_the_saved_selection_and_no_tick_on_a_network_the_site_lost(): void
    {
        $this->site(['social_discord' => 'discord.gg/example', 'social_instagram' => 'www.instagram.com/example']);
        config()->set('modules.settings.link-page.social', ['position' => 'top', 'shown' => ['discord', 'email']]);

        // The mail was ticked once, but the site has no address for it any more
        Livewire::test(LinkPageSettingsPage::class)
            ->assertFormSet(['social.position' => 'top', 'social.shown' => ['discord']]);
    }

    public function test_it_saves_the_selection_and_the_position(): void
    {
        $this->site(['social_instagram' => 'www.instagram.com/example', 'social_discord' => 'discord.gg/example', 'contact_email' => 'info@example.test']);

        Livewire::test(LinkPageSettingsPage::class)
            ->fillForm(['social.position' => 'top', 'social.shown' => ['email', 'instagram']])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = app(LinkPageSettingsInterface::class);
        $this->assertSame(SocialPosition::Top, $settings->socialPosition());
        $this->assertSame([SocialNetwork::Instagram, SocialNetwork::Email], $settings->shownNetworks());
        $this->assertSame(
            ['https://www.instagram.com/example', 'mailto:info@example.test'],
            array_map(fn ($link): string => $link->url->value, $settings->socialLinks()),
        );
    }

    public function test_unticking_every_network_is_saved_as_an_empty_selection_not_as_every_network(): void
    {
        $this->site(['social_instagram' => 'www.instagram.com/example']);

        Livewire::test(LinkPageSettingsPage::class)
            ->fillForm(['social.shown' => []])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([], app(LinkPageSettingsInterface::class)->shownNetworks());
        $this->assertSame([], app(LinkPageSettingsInterface::class)->socialLinks());
        $this->assertSame([], app(ModuleSettingsStoreInterface::class)->stored('link-page')['social']['shown']);
    }

    public function test_an_address_is_never_stored_by_this_page(): void
    {
        $this->site(['social_instagram' => 'www.instagram.com/example']);

        Livewire::test(LinkPageSettingsPage::class)
            ->fillForm(['social.shown' => ['instagram']])
            ->call('save')
            ->assertHasNoFormErrors();

        $social = app(ModuleSettingsStoreInterface::class)->stored('link-page')['social'];

        $this->assertSame(['position', 'shown'], array_keys($social));
        $this->assertSame(['instagram'], $social['shown']);
    }

    public function test_an_unknown_network_or_position_is_refused(): void
    {
        Livewire::test(LinkPageSettingsPage::class)
            ->fillForm(['social.position' => 'sideways', 'social.shown' => ['myspace']])
            ->call('save')
            ->assertHasFormErrors();
    }

    public function test_the_options_say_what_address_the_site_has_for_each_network(): void
    {
        $this->site(['social_instagram' => 'www.instagram.com/example', 'contact_phone' => '+34 600 123 456']);

        $component = Livewire::test(LinkPageSettingsPage::class);
        $field = $component->instance()->form->getComponent('social.shown');

        $this->assertNotNull($field);
        $descriptions = $field->getDescriptions();

        $this->assertStringContainsString('instagram.com/example', (string) $descriptions['instagram']);
        $this->assertStringContainsString('+34600123456', (string) $descriptions['phone']);
        $this->assertSame(__('link-page::messages.social.no_address'), (string) $descriptions['whatsapp']);
        $this->assertSame(__('link-page::messages.social.no_address'), (string) $descriptions['discord']);
    }

    public function test_a_network_without_an_address_cannot_be_ticked(): void
    {
        $this->site(['social_instagram' => 'www.instagram.com/example']);

        $component = Livewire::test(LinkPageSettingsPage::class);
        $field = $component->instance()->form->getComponent('social.shown');

        $this->assertNotNull($field);
        $this->assertFalse($field->isOptionDisabled('instagram', 'Instagram'));
        $this->assertTrue($field->isOptionDisabled('discord', 'Discord'));
        $this->assertTrue($field->isOptionDisabled('email', 'Correo electrónico'));
    }

    /**
     * @param  array<string, string>  $values
     */
    private function site(array $values): void
    {
        $settings = app(SettingsServiceInterface::class);

        foreach ($values as $key => $value) {
            $settings->set($key, $value);
        }
    }
}
