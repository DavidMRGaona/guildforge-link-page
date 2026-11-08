<?php

declare(strict_types=1);

namespace Modules\LinkPage\Http\Controllers;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Calendar\Services\PersonalCalendarServiceInterface;
use App\Application\Calendar\Services\PublicCalendarFeedInterface;
use App\Application\Services\SiteBrandingServiceInterface;
use App\Application\Services\TimezoneServiceInterface;
use App\Domain\Enums\ColorScheme as SiteColorScheme;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\LinkPage\Application\Contracts\LinkPageLinksInterface;
use Modules\LinkPage\Application\Contracts\LinkPageSettingsInterface;
use Modules\LinkPage\Application\DTOs\FeaturedEventDTO;
use Modules\LinkPage\Application\DTOs\LinkPageEventDTO;
use Modules\LinkPage\Application\DTOs\LinkPageLinkDTO;
use Modules\LinkPage\Application\Services\FeaturedEventService;
use Modules\LinkPage\Application\Services\LinkPageEventsService;
use Modules\LinkPage\Domain\Enums\CalendarView;
use Modules\LinkPage\Domain\Enums\LinkPlacement;
use Modules\LinkPage\Domain\ValueObjects\SocialLink;
use Modules\LinkPage\Http\Presenters\AppearancePresenter;
use Modules\LinkPage\Http\Presenters\HighlightsPresenter;
use Modules\LinkPage\Http\Presenters\ShareCardPresenter;

/**
 * /enlaces: the page a printed QR code leads to, apart from the site layout.
 */
final readonly class LinkPageController
{
    public function __construct(
        private LinkPageSettingsInterface $settings,
        private LinkPageEventsService $events,
        private LinkPageLinksInterface $links,
        private SiteBrandingServiceInterface $branding,
        private TimezoneServiceInterface $timezone,
        private AppearancePresenter $appearance,
        private ShareCardPresenter $shareCard,
        private PublicCalendarFeedInterface $calendarFeed,
        private FeaturedEventService $featuredEvent,
        private HighlightsPresenter $highlights,
        private AuthorizationServiceInterface $authorization,
        private PersonalCalendarServiceInterface $personalCalendar,
    ) {}

    public function __invoke(Request $request): Response
    {
        abort_unless($this->settings->isEnabled(), 404);

        $siteName = $this->branding->siteName();
        $intro = $this->settings->intro();
        $appearance = $this->settings->appearance();
        $card = $this->shareCard->present($intro);
        $logos = $this->appearance->logos(
            $appearance,
            $this->branding->customLogoFor(SiteColorScheme::Light),
            $this->branding->customLogoFor(SiteColorScheme::Dark),
        );
        $featured = $this->featuredEvent->current();

        return Inertia::render('LinkPage/Show', [
            ...$this->highlights->props($featured),
            'site' => [
                'name' => $siteName,
                // Show.vue swaps them with the theme: forced in the appearance or the device's
                'logo' => $logos,
            ],
            'appearance' => $this->appearance->props($appearance),
            'intro' => $intro,
            'links' => $this->linkProps(),
            'social' => [
                'position' => $this->settings->socialPosition()->value,
                'links' => array_map($this->socialProps(...), $this->settings->socialLinks()),
            ],
            'events' => array_map($this->eventProps(...), $this->events->upcoming($featured instanceof FeaturedEventDTO ? [$featured->id] : [])),
            'calendar' => [
                'initialView' => $this->settings->calendarInitialView()->value,
                'views' => array_map(fn (CalendarView $view): string => $view->value, CalendarView::cases()),
            ],
            // The core builds the addresses (feed, webcal, Google, Outlook): the module never does
            'calendarFeed' => $this->calendarFeed->subscriptionLinks()->toArray(),
            'personalCalendar' => $this->personalCalendarProps($request),
            'homeUrl' => url('/'),
            'seo' => $card['seo'],
            'share' => $card['share'],
            'stats' => [
                'endpoint' => route('link_page.api.stats'),
                // Whoever runs the panel checks the page often: their visits are not counted
                'enabled' => ! $this->hasPanelAccess($request),
            ],
        ]);
    }

    /**
     * «Mi calendario» for a signed-in person, while the core has a personal calendar at all.
     *
     * The feed address is never here: the core stores only its hash and gives the address once,
     * when the person issues it from their profile (which also revokes the previous one). So the
     * page leads to that tab instead of issuing a token on a visit.
     *
     * @return array{profileUrl: string}|null
     */
    private function personalCalendarProps(Request $request): ?array
    {
        if ($request->user() === null || ! $this->personalCalendar->isAvailable()) {
            return null;
        }

        return ['profileUrl' => route('profile.show', ['tab' => 'calendar'])];
    }

    private function hasPanelAccess(Request $request): bool
    {
        $user = $request->user();

        return $user !== null && $this->authorization->can($user, 'admin.access');
    }

    /**
     * Own links split around the event buttons, each group in its dragged order.
     *
     * @return array{before: list<array<string, mixed>>, after: list<array<string, mixed>>}
     */
    private function linkProps(): array
    {
        $groups = ['before' => [], 'after' => []];

        foreach ($this->links->visible() as $link) {
            $groups[$link->placement === LinkPlacement::After ? 'after' : 'before'][] = $this->linkProp($link);
        }

        return $groups;
    }

    /**
     * @return array{linkId: string, title: string, url: string, description: string|null, icon: string|null, featured: bool, external: bool}
     */
    private function linkProp(LinkPageLinkDTO $link): array
    {
        return [
            'linkId' => $link->linkId(),
            'title' => $link->title,
            'url' => $link->url->value,
            'description' => $link->description,
            'icon' => $link->icon?->value,
            'featured' => $link->featured,
            'external' => $link->url->isExternal(),
        ];
    }

    /**
     * @return array{linkId: string, network: string, label: string, url: string, icon: string, external: bool}
     */
    private function socialProps(SocialLink $link): array
    {
        return [
            'linkId' => $link->linkId(),
            'network' => $link->network->value,
            'label' => $link->network->label(),
            'url' => $link->url->value,
            'icon' => $link->network->icon()->value,
            'external' => $link->url->isExternal(),
        ];
    }

    /**
     * @return array{id: string, linkId: string, title: string, url: string, startsAt: string|null, endsAt: string|null, location: string|null, pinned: bool}
     */
    private function eventProps(LinkPageEventDTO $event): array
    {
        return [
            'id' => $event->id,
            'linkId' => 'event:'.$event->id,
            'title' => $event->title,
            'url' => $event->url,
            'startsAt' => $this->timezone->iso($event->startsAt),
            'endsAt' => $this->timezone->iso($event->endsAt),
            'location' => $event->location,
            'pinned' => $event->pinned,
        ];
    }
}
