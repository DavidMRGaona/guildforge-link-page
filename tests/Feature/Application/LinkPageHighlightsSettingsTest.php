<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Feature\Application;

use Modules\LinkPage\Application\Contracts\HighlightsSettingsInterface;
use Modules\LinkPage\Domain\Enums\FeaturedMode;
use Modules\LinkPage\Domain\Enums\NoticeTone;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The notice and the featured event as the settings give them: defaults off, and anything
 * malformed reads as "nothing to show" instead of breaking the page.
 */
final class LinkPageHighlightsSettingsTest extends ModuleTestCase
{
    protected ?string $moduleName = 'link-page';

    protected bool $autoEnableModule = true;

    public function test_by_default_there_is_no_notice_and_no_featured_event(): void
    {
        $this->assertNull($this->settings()->notice());
        $this->assertSame(FeaturedMode::Off, $this->settings()->featured()->mode);
    }

    public function test_it_reads_an_enabled_notice(): void
    {
        config()->set('modules.settings.link-page.notice', [
            'enabled' => true,
            'text' => 'Closed on Saturday.',
            'tone' => 'warning',
            'link_url' => '/eventos',
            'link_label' => 'See events',
            'starts_at' => '2026-11-10T10:00:00+01:00',
            'ends_at' => '2026-11-12T10:00:00+01:00',
        ]);

        $notice = $this->settings()->notice();

        $this->assertNotNull($notice);
        $this->assertSame('Closed on Saturday.', $notice->text);
        $this->assertSame(NoticeTone::Warning, $notice->tone);
        $link = $notice->link;
        $this->assertNotNull($link);
        $this->assertSame('/eventos', $link->url);
        $this->assertSame('See events', $link->label);
    }

    public function test_a_disabled_notice_reads_as_none(): void
    {
        config()->set('modules.settings.link-page.notice', ['enabled' => false, 'text' => 'Hello', 'tone' => 'info']);

        $this->assertNull($this->settings()->notice());
    }

    public function test_only_a_true_flag_enables_the_notice(): void
    {
        config()->set('modules.settings.link-page.notice', ['enabled' => 'yes', 'text' => 'Hello', 'tone' => 'info']);

        $this->assertNull($this->settings()->notice());
    }

    public function test_a_malformed_notice_reads_as_none(): void
    {
        config()->set('modules.settings.link-page.notice', ['enabled' => true, 'text' => 'Hello', 'tone' => 'info', 'link_url' => 'javascript:alert(1)']);
        $this->assertNull($this->settings()->notice());

        config()->set('modules.settings.link-page.notice', 'not an array');
        $this->assertNull($this->settings()->notice());

        config()->set('modules.settings.link-page.notice', ['enabled' => true, 'text' => '   ', 'tone' => 'info']);
        $this->assertNull($this->settings()->notice());
    }

    public function test_it_reads_the_featured_settings(): void
    {
        config()->set('modules.settings.link-page.featured', ['mode' => 'auto', 'tag' => 'jornadas']);

        $featured = $this->settings()->featured();

        $this->assertSame(FeaturedMode::Auto, $featured->mode);
        $this->assertSame('jornadas', $featured->tagSlug);
    }

    public function test_malformed_featured_settings_read_as_off(): void
    {
        config()->set('modules.settings.link-page.featured', 'auto');

        $this->assertSame(FeaturedMode::Off, $this->settings()->featured()->mode);
    }

    public function test_the_stored_notice_text_is_kept_for_the_form_even_when_disabled(): void
    {
        config()->set('modules.settings.link-page.notice', ['enabled' => false, 'text' => 'Hello', 'tone' => 'important']);

        $form = $this->settings()->noticeForForm();

        $this->assertFalse($form['enabled']);
        $this->assertSame('Hello', $form['text']);
        $this->assertSame('important', $form['tone']);
    }

    private function settings(): HighlightsSettingsInterface
    {
        return $this->app->make(HighlightsSettingsInterface::class);
    }
}
