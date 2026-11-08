<?php

declare(strict_types=1);

namespace Modules\LinkPage\Http\Controllers;

use App\Application\Services\SiteBrandingServiceInterface;
use App\Application\Services\TimezoneServiceInterface;
use Inertia\Inertia;
use Inertia\Response;
use Modules\LinkPage\Application\Contracts\LinkPageLinksInterface;
use Modules\LinkPage\Application\Contracts\LinkPageSettingsInterface;
use Modules\LinkPage\Application\DTOs\LinkPageEventDTO;
use Modules\LinkPage\Application\DTOs\LinkPageLinkDTO;
use Modules\LinkPage\Application\Services\LinkPageEventsService;
use Modules\LinkPage\Domain\Enums\CalendarView;
use Modules\LinkPage\Domain\Enums\LinkPlacement;
use Modules\LinkPage\Domain\ValueObjects\SocialLink;
use Modules\LinkPage\Http\Presenters\AppearancePresenter;
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
    ) {}

    public function __invoke(): Response
    {
        abort_unless($this->settings->isEnabled(), 404);

        $siteName = $this->branding->siteName();
        $intro = $this->settings->intro();
        $appearance = $this->settings->appearance();
        $card = $this->shareCard->present($intro);
        $logo = $this->appearance->logo($appearance, $this->branding->customLogoUrl(), $this->branding->customLogoIsDark());

        return Inertia::render('LinkPage/Show', [
            'site' => [
                'name' => $siteName,
                'logoUrl' => $logo['url'],
                'logoIsDark' => $logo['isDark'],
            ],
            'appearance' => $this->appearance->props($appearance),
            'intro' => $intro,
            'links' => $this->linkProps(),
            'social' => [
                'position' => $this->settings->socialPosition()->value,
                'links' => array_map($this->socialProps(...), $this->settings->socialLinks()),
            ],
            'events' => array_map($this->eventProps(...), $this->events->upcoming()),
            'calendar' => [
                'initialView' => $this->settings->calendarInitialView()->value,
                'views' => array_map(fn (CalendarView $view): string => $view->value, CalendarView::cases()),
            ],
            'homeUrl' => url('/'),
            'seo' => $card['seo'],
            'share' => $card['share'],
        ]);
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
