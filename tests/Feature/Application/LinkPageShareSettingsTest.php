<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Feature\Application;

use Modules\LinkPage\Application\Contracts\LinkPageSettingsInterface;
use Tests\Support\Modules\ModuleTestCase;

final class LinkPageShareSettingsTest extends ModuleTestCase
{
    protected ?string $moduleName = 'link-page';

    protected bool $autoEnableModule = true;

    public function test_release_defaults_share_the_site_card_and_let_search_engines_in(): void
    {
        $settings = $this->settings();

        $this->assertNull($settings->shareTitle());
        $this->assertNull($settings->shareDescription());
        $this->assertNull($settings->shareImage());
        $this->assertFalse($settings->noindex());
    }

    public function test_saved_values_win(): void
    {
        config()->set('modules.settings.link-page.share', [
            'title' => '  Join the guild  ',
            'description' => '  Games every week.  ',
            'image' => 'link-page/share/2026/11/card.jpg',
            'noindex' => true,
        ]);

        $settings = $this->settings();

        $this->assertSame('Join the guild', $settings->shareTitle());
        $this->assertSame('Games every week.', $settings->shareDescription());
        $this->assertSame('link-page/share/2026/11/card.jpg', $settings->shareImage());
        $this->assertTrue($settings->noindex());
    }

    public function test_blank_or_malformed_values_fall_back_to_the_site_card(): void
    {
        config()->set('modules.settings.link-page.share', [
            'title' => '   ',
            'description' => ['not', 'text'],
            'image' => 'settings/logo.webp',
            'noindex' => 'maybe',
        ]);

        $settings = $this->settings();

        $this->assertNull($settings->shareTitle());
        $this->assertNull($settings->shareDescription());
        $this->assertNull($settings->shareImage(), 'Only PNG or JPG paths of the images disk are used.');
        $this->assertFalse($settings->noindex(), 'Anything but a real true keeps the page indexable.');
    }

    public function test_a_share_block_that_is_not_a_list_of_values_is_ignored(): void
    {
        config()->set('modules.settings.link-page.share', 'broken');

        $settings = $this->settings();

        $this->assertNull($settings->shareTitle());
        $this->assertFalse($settings->noindex());
    }

    public function test_texts_are_cut_to_what_a_card_shows(): void
    {
        config()->set('modules.settings.link-page.share', [
            'title' => str_repeat('t', 200),
            'description' => str_repeat('d', 400),
        ]);

        $settings = $this->settings();

        $this->assertSame(LinkPageSettingsInterface::MAX_SHARE_TITLE_LENGTH, mb_strlen((string) $settings->shareTitle()));
        $this->assertSame(LinkPageSettingsInterface::MAX_SHARE_DESCRIPTION_LENGTH, mb_strlen((string) $settings->shareDescription()));
    }

    private function settings(): LinkPageSettingsInterface
    {
        return app(LinkPageSettingsInterface::class);
    }
}
