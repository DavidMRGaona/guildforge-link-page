<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Feature\Application;

use App\Application\Services\SettingsServiceInterface;
use Modules\LinkPage\Application\Contracts\LinkPageSettingsInterface;
use Modules\LinkPage\Domain\Enums\SocialNetwork;
use Modules\LinkPage\Domain\Enums\SocialPosition;
use Modules\LinkPage\Domain\ValueObjects\SocialLink;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The social row shows what the site already keeps (Sobre nosotros → Redes sociales and
 * Contacto); this page only decides which of those to show and where.
 */
final class LinkPageSocialSettingsTest extends ModuleTestCase
{
    protected ?string $moduleName = 'link-page';

    protected bool $autoEnableModule = true;

    public function test_a_site_without_profiles_shows_no_row_and_the_row_goes_at_the_bottom(): void
    {
        $settings = $this->settings();

        $this->assertSame([], $settings->socialLinks());
        $this->assertSame(SocialPosition::Bottom, $settings->socialPosition());
    }

    public function test_every_network_the_site_has_is_shown_until_someone_unticks_it(): void
    {
        $this->site(['social_instagram' => 'www.instagram.com/example', 'social_discord' => 'discord.gg/example', 'contact_email' => 'info@example.test']);

        $settings = $this->settings();

        $this->assertSame(SocialNetwork::cases(), $settings->shownNetworks());
        $this->assertSame(['instagram', 'discord', 'email'], $this->networks($settings->socialLinks()));
    }

    public function test_only_the_ticked_networks_are_shown_in_catalogue_order(): void
    {
        $this->site(['social_instagram' => 'www.instagram.com/example', 'social_discord' => 'discord.gg/example', 'contact_phone' => '600123456']);
        config()->set('modules.settings.link-page.social.shown', ['phone', 'discord']);

        $settings = $this->settings();

        $this->assertSame([SocialNetwork::Discord, SocialNetwork::Phone], $settings->shownNetworks());
        $this->assertSame(['discord', 'phone'], $this->networks($settings->socialLinks()));
    }

    public function test_unticking_everything_hides_the_whole_row(): void
    {
        $this->site(['social_instagram' => 'www.instagram.com/example']);
        config()->set('modules.settings.link-page.social.shown', []);

        $this->assertSame([], $this->settings()->shownNetworks());
        $this->assertSame([], $this->settings()->socialLinks());
    }

    public function test_a_ticked_network_the_site_has_no_address_for_shows_nothing(): void
    {
        $this->site(['social_instagram' => 'www.instagram.com/example']);
        config()->set('modules.settings.link-page.social.shown', ['instagram', 'tiktok', 'whatsapp']);

        $this->assertSame(['instagram'], $this->networks($this->settings()->socialLinks()));
    }

    public function test_the_address_follows_the_site_settings(): void
    {
        $this->site(['social_discord' => 'discord.gg/one']);
        $this->assertSame('https://discord.gg/one', $this->settings()->socialLinks()[0]->url->value);

        $this->site(['social_discord' => 'discord.gg/two']);
        $this->assertSame('https://discord.gg/two', $this->settings()->socialLinks()[0]->url->value);
    }

    public function test_an_unsafe_address_the_site_saved_never_reaches_the_row(): void
    {
        $this->site(['social_instagram' => 'javascript:alert(1)', 'social_discord' => 'discord.gg/example', 'contact_email' => 'javascript:alert(1)']);

        $this->assertSame(['discord'], $this->networks($this->settings()->socialLinks()));
    }

    public function test_the_position_comes_from_the_saved_settings(): void
    {
        config()->set('modules.settings.link-page.social.position', 'top');

        $this->assertSame(SocialPosition::Top, $this->settings()->socialPosition());
    }

    public function test_a_malformed_social_block_falls_back_to_every_network_at_the_bottom(): void
    {
        config()->set('modules.settings.link-page.social', 'oops');

        $this->assertSame(SocialNetwork::cases(), $this->settings()->shownNetworks());
        $this->assertSame(SocialPosition::Bottom, $this->settings()->socialPosition());

        config()->set('modules.settings.link-page.social', ['position' => 'sideways', 'shown' => 'oops']);

        $this->assertSame(SocialNetwork::cases(), $this->settings()->shownNetworks());
        $this->assertSame(SocialPosition::Bottom, $this->settings()->socialPosition());
    }

    public function test_unknown_networks_in_the_saved_list_are_ignored(): void
    {
        config()->set('modules.settings.link-page.social.shown', ['discord', 'myspace', 42, ['x']]);

        $this->assertSame([SocialNetwork::Discord], $this->settings()->shownNetworks());
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

    /**
     * @param  list<SocialLink>  $links
     * @return list<string>
     */
    private function networks(array $links): array
    {
        return array_map(fn (SocialLink $link): string => $link->network->value, $links);
    }

    private function settings(): LinkPageSettingsInterface
    {
        return app(LinkPageSettingsInterface::class);
    }
}
