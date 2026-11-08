<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Feature\Infrastructure;

use App\Application\Services\SettingsServiceInterface;
use Modules\LinkPage\Application\Contracts\SiteSocialProfilesInterface;
use Modules\LinkPage\Domain\Enums\SocialNetwork;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The site keeps its social profiles and contact details once (Sobre nosotros → Redes sociales
 * and Contacto: settings social_<network>, contact_email, contact_phone); the link page reads
 * them from there and never asks for them again.
 */
final class CoreSiteSocialProfilesTest extends ModuleTestCase
{
    protected ?string $moduleName = 'link-page';

    protected bool $autoEnableModule = true;

    public function test_a_site_without_profiles_has_none(): void
    {
        $this->assertSame([], $this->profiles()->all());

        foreach (SocialNetwork::cases() as $network) {
            $this->assertNull($this->profiles()->for($network), $network->value);
        }
    }

    public function test_it_reads_each_profile_and_contact_detail_under_the_network_it_belongs_to(): void
    {
        $this->site([
            'social_facebook' => 'www.facebook.com/example',
            'social_instagram' => 'www.instagram.com/example',
            'social_twitter' => 'x.com/example',
            'social_discord' => 'discord.gg/example',
            'social_tiktok' => 'www.tiktok.com/@example',
            'social_bluesky' => 'bsky.app/profile/example.test',
            'social_telegram' => 't.me/example',
            'social_whatsapp' => 'chat.whatsapp.com/example',
            'social_youtube' => 'www.youtube.com/@example',
            'social_twitch' => 'www.twitch.tv/example',
            'contact_email' => 'info@example.test',
            'contact_phone' => '+34 600 123 456',
        ]);

        $addresses = array_map(fn ($url): string => $url->value, $this->profiles()->all());

        $this->assertSame([
            'instagram' => 'https://www.instagram.com/example',
            'discord' => 'https://discord.gg/example',
            'telegram' => 'https://t.me/example',
            'whatsapp' => 'https://chat.whatsapp.com/example',
            'youtube' => 'https://www.youtube.com/@example',
            'twitch' => 'https://www.twitch.tv/example',
            'x' => 'https://x.com/example',
            'facebook' => 'https://www.facebook.com/example',
            'tiktok' => 'https://www.tiktok.com/@example',
            'bluesky' => 'https://bsky.app/profile/example.test',
            'email' => 'mailto:info@example.test',
            'phone' => 'tel:+34600123456',
        ], $addresses);
    }

    public function test_an_address_already_written_with_https_is_kept(): void
    {
        $this->site(['social_discord' => 'https://discord.gg/example']);

        $this->assertSame('https://discord.gg/example', $this->profiles()->for(SocialNetwork::Discord)?->value);
    }

    public function test_only_the_networks_the_site_has_give_an_address(): void
    {
        $this->site(['social_instagram' => 'www.instagram.com/example']);

        $this->assertNotNull($this->profiles()->for(SocialNetwork::Instagram));
        $this->assertNull($this->profiles()->for(SocialNetwork::Discord));
        $this->assertNull($this->profiles()->for(SocialNetwork::Whatsapp));
        $this->assertNull($this->profiles()->for(SocialNetwork::Email));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unusableAddresses(): array
    {
        return [
            'blank' => ['   '],
            'plain http' => ['http://www.instagram.com/example'],
            'a script' => ['javascript:alert(1)'],
            'a script in capitals' => ['JAVASCRIPT:alert(1)'],
            'with a space inside' => ['www.instagram.com/exa mple'],
            'with credentials' => ['user:secret@www.instagram.com/example'],
        ];
    }

    #[DataProvider('unusableAddresses')]
    public function test_a_saved_value_that_is_not_a_safe_https_address_is_left_out(string $value): void
    {
        $this->site(['social_instagram' => $value, 'social_discord' => 'discord.gg/example']);

        $this->assertNull($this->profiles()->for(SocialNetwork::Instagram));
        $this->assertNotNull($this->profiles()->for(SocialNetwork::Discord));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unusableMailAndPhone(): array
    {
        return [
            'a mail that is not one' => ['not-an-email'],
            'a script as mail' => ['javascript:alert(1)'],
            'two mails' => ['a@example.test,b@example.test'],
        ];
    }

    #[DataProvider('unusableMailAndPhone')]
    public function test_a_contact_mail_that_is_not_an_address_is_left_out(string $value): void
    {
        $this->site(['contact_email' => $value]);

        $this->assertNull($this->profiles()->for(SocialNetwork::Email));
    }

    public function test_a_contact_phone_that_is_not_a_number_is_left_out(): void
    {
        $this->site(['contact_phone' => 'call me maybe']);

        $this->assertNull($this->profiles()->for(SocialNetwork::Phone));
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

    private function profiles(): SiteSocialProfilesInterface
    {
        return app(SiteSocialProfilesInterface::class);
    }
}
