<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Unit\Domain;

use InvalidArgumentException;
use Modules\LinkPage\Domain\Enums\LinkIcon;
use Modules\LinkPage\Domain\Enums\SocialNetwork;
use Modules\LinkPage\Domain\ValueObjects\LinkUrl;
use Modules\LinkPage\Domain\ValueObjects\SocialLink;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SocialLinkTest extends TestCase
{
    public function test_the_networks_are_the_twelve_of_the_icon_row_and_each_has_its_own_icon(): void
    {
        $this->assertEqualsCanonicalizing(
            ['instagram', 'discord', 'telegram', 'whatsapp', 'youtube', 'twitch', 'x', 'facebook', 'tiktok', 'bluesky', 'email', 'phone'],
            array_map(static fn (SocialNetwork $network): string => $network->value, SocialNetwork::cases()),
        );

        foreach (SocialNetwork::cases() as $network) {
            $this->assertSame($network->value, $network->icon()->value);
        }
    }

    public function test_every_network_is_read_from_a_key_of_the_site_settings(): void
    {
        $keys = [];
        foreach (SocialNetwork::cases() as $network) {
            $keys[$network->value] = $network->siteSettingKey();
        }

        $this->assertSame([
            'instagram' => 'social_instagram',
            'discord' => 'social_discord',
            'telegram' => 'social_telegram',
            'whatsapp' => 'social_whatsapp',
            'youtube' => 'social_youtube',
            'twitch' => 'social_twitch',
            'x' => 'social_twitter',
            'facebook' => 'social_facebook',
            'tiktok' => 'social_tiktok',
            'bluesky' => 'social_bluesky',
            'email' => 'contact_email',
            'phone' => 'contact_phone',
        ], $keys);
    }

    public function test_the_icons_are_the_networks_and_nine_generic_pictograms(): void
    {
        $generic = array_values(array_filter(
            LinkIcon::cases(),
            static fn (LinkIcon $icon): bool => SocialNetwork::tryFrom($icon->value) === null,
        ));

        $this->assertEqualsCanonicalizing(
            ['website', 'link', 'calendar', 'group', 'book', 'dice', 'ticket', 'map_pin', 'form'],
            array_map(static fn (LinkIcon $icon): string => $icon->value, $generic),
        );
    }

    /**
     * @return array<string, array{SocialNetwork, string}>
     */
    public static function acceptedProvider(): array
    {
        return [
            'instagram' => [SocialNetwork::Instagram, 'https://www.instagram.com/example'],
            'discord' => [SocialNetwork::Discord, 'https://discord.gg/example'],
            'whatsapp' => [SocialNetwork::Whatsapp, 'https://chat.whatsapp.com/example'],
            'email' => [SocialNetwork::Email, 'mailto:info@example.test'],
            'phone' => [SocialNetwork::Phone, 'tel:+34600123456'],
            'youtube' => [SocialNetwork::Youtube, 'https://www.youtube.com/@example'],
        ];
    }

    #[DataProvider('acceptedProvider')]
    public function test_a_network_takes_the_scheme_that_fits_it(SocialNetwork $network, string $url): void
    {
        $link = SocialLink::fromArray(['network' => $network->value, 'url' => $url]);

        $this->assertSame($network, $link->network);
        $this->assertSame($url, $link->url->value);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function rejectedProvider(): array
    {
        return [
            'email with a web address' => [['network' => 'email', 'url' => 'https://example.test/']],
            'phone with a mail address' => [['network' => 'phone', 'url' => 'mailto:info@example.test']],
            'instagram with a mail address' => [['network' => 'instagram', 'url' => 'mailto:info@example.test']],
            'twitch with a phone number' => [['network' => 'twitch', 'url' => 'tel:+34600123456']],
            'unknown network' => [['network' => 'myspace', 'url' => 'https://example.test/']],
            'script' => [['network' => 'instagram', 'url' => 'javascript:alert(1)']],
            'no network' => [['url' => 'https://example.test/']],
            'no address' => [['network' => 'instagram']],
            'network is not text' => [['network' => ['instagram'], 'url' => 'https://example.test/']],
            'address is not text' => [['network' => 'instagram', 'url' => 42]],
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     */
    #[DataProvider('rejectedProvider')]
    public function test_it_rejects_an_unknown_network_or_an_address_that_does_not_fit(array $values): void
    {
        $this->expectException(InvalidArgumentException::class);

        SocialLink::fromArray($values);
    }

    public function test_it_gives_a_stable_id_per_network_and_goes_back_to_an_array(): void
    {
        $link = new SocialLink(SocialNetwork::Telegram, LinkUrl::from('https://t.me/example'));

        $this->assertSame('social:telegram', $link->linkId());
        $this->assertSame(['network' => 'telegram', 'url' => 'https://t.me/example'], $link->toArray());
    }

    public function test_the_constructor_also_refuses_an_address_that_does_not_fit(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SocialLink(SocialNetwork::Email, LinkUrl::from('https://example.test/'));
    }
}
