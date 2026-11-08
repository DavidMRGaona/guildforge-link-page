<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Feature\Http;

use App\Application\Services\SettingsServiceInterface;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The card WhatsApp, Telegram and the social networks build from /enlaces. Crawlers do not run
 * JavaScript, so everything is read from the HTML of the first response, not from the Inertia props.
 */
final class LinkPageShareCardTest extends ModuleTestCase
{
    private const string CARD = 'https://res.cloudinary.com/demo/image/upload/e_trim/w_1200,h_630,c_fill,g_auto,q_auto,f_jpg/guildforge/link-page/share/2026/11/card';

    protected ?string $moduleName = 'link-page';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        app()->setLocale('es');
        Storage::fake('images');
        config([
            'cloudinary.cloud_name' => 'demo',
            'cloudinary.prefix' => 'guildforge',
            'app.description' => 'Asociación de juegos',
        ]);
        app(SettingsServiceInterface::class)->set('guild_name', 'Example guild');
    }

    public function test_the_card_defaults_to_the_site_name_the_intro_and_the_site_logo(): void
    {
        app(SettingsServiceInterface::class)->set('site_logo_light', 'settings/logo.png');
        config()->set('modules.settings.link-page.intro', 'Scan, play, repeat.');

        $response = $this->get('/enlaces')->assertOk();

        // The name alone: "Example guild - Example guild" would read as a mistake
        $response->assertSee('<title>Example guild</title>', false);
        $response->assertSee('<meta property="og:title" content="Example guild" data-seo-server>', false);
        $response->assertSee('<meta name="twitter:title" content="Example guild" data-seo-server>', false);
        $response->assertSee('<meta property="og:description" content="Scan, play, repeat." data-seo-server>', false);
        $response->assertSee('<meta name="twitter:description" content="Scan, play, repeat." data-seo-server>', false);
        $response->assertSee('<meta property="og:url" content="'.url('/enlaces').'" data-seo-server>', false);
        $response->assertSee('<link rel="canonical" href="'.url('/enlaces').'" data-seo-server>', false);
        $response->assertSee('<meta property="og:image" content="https://res.cloudinary.com/demo/image/upload/w_600,h_600,c_pad,b_white,q_auto,f_png/guildforge/settings/logo" data-seo-server>', false);
        $response->assertSee('<meta name="twitter:card" content="summary" data-seo-server>', false);
        $response->assertDontSee('name="robots"', false);
    }

    public function test_without_intro_the_description_is_the_sites(): void
    {
        $this->get('/enlaces')
            ->assertOk()
            ->assertSee('<meta property="og:description" content="Asociación de juegos" data-seo-server>', false);
    }

    public function test_the_panel_values_replace_the_defaults(): void
    {
        config()->set('modules.settings.link-page.intro', 'Scan, play, repeat.');
        config()->set('modules.settings.link-page.share', [
            'title' => 'Join the guild',
            'description' => 'Games every week, all welcome.',
            'image' => 'link-page/share/2026/11/card.jpg',
            'noindex' => false,
        ]);

        $response = $this->get('/enlaces')->assertOk();

        $response->assertSee('<title>Join the guild - Example guild</title>', false);
        $response->assertSee('<meta property="og:title" content="Join the guild" data-seo-server>', false);
        $response->assertSee('<meta name="twitter:title" content="Join the guild" data-seo-server>', false);
        $response->assertSee('<meta property="og:description" content="Games every week, all welcome." data-seo-server>', false);
        $response->assertSee('<meta property="og:site_name" content="Example guild" data-seo-server>', false);
        $response->assertSee('<meta property="og:image" content="'.self::CARD.'" data-seo-server>', false);
        $response->assertSee('<meta name="twitter:image" content="'.self::CARD.'" data-seo-server>', false);
        $response->assertSee('<meta property="og:image:width" content="1200" data-seo-server>', false);
        $response->assertSee('<meta property="og:image:height" content="630" data-seo-server>', false);
        $response->assertSee('<meta name="twitter:card" content="summary_large_image" data-seo-server>', false);
        // The intro still heads the page; only the card changed
        $response->assertSee('Scan, play, repeat.', false);
    }

    public function test_a_title_equal_to_the_site_name_is_not_repeated(): void
    {
        config()->set('modules.settings.link-page.share.title', 'Example guild');

        $this->get('/enlaces')
            ->assertOk()
            ->assertSee('<title>Example guild</title>', false);
    }

    public function test_the_card_text_is_escaped(): void
    {
        config()->set('modules.settings.link-page.share', [
            'title' => 'Rock "n" <b>roll</b>',
            'description' => '"><script>alert(1)</script>',
        ]);

        $response = $this->get('/enlaces')->assertOk();

        $response->assertDontSee('<script>alert(1)</script>', false);
        $response->assertDontSee('<b>roll</b>', false);
    }

    public function test_search_engines_are_kept_out_only_when_asked(): void
    {
        config()->set('modules.settings.link-page.share.noindex', true);

        $this->get('/enlaces')
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, nofollow" data-seo-server>', false);
    }

    public function test_a_foreign_image_path_is_never_used_for_the_card(): void
    {
        config()->set('modules.settings.link-page.share.image', 'events/2026/11/poster.jpg');

        $this->get('/enlaces')
            ->assertOk()
            ->assertDontSee('poster', false);
    }

    public function test_the_share_button_gets_the_address_and_the_text_of_the_card(): void
    {
        config()->set('modules.settings.link-page.share', [
            'title' => 'Join the guild',
            'description' => 'Games every week, all welcome.',
        ]);

        $this->get('/enlaces?utm_source=poster')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('share.url', route('link_page.show'))
                ->where('share.title', 'Join the guild')
                ->where('share.text', 'Games every week, all welcome.')
            );
    }

    public function test_a_disabled_page_still_gives_no_card(): void
    {
        config()->set('modules.settings.link-page.enabled', false);

        $this->get('/enlaces')->assertNotFound();
    }
}
