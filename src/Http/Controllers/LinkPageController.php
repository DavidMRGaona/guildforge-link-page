<?php

declare(strict_types=1);

namespace Modules\LinkPage\Http\Controllers;

use App\Application\Seo\DTOs\SeoPageDTO;
use App\Application\Seo\Services\SeoMetadataFactoryInterface;
use App\Application\Services\SiteBrandingServiceInterface;
use App\Application\Services\TimezoneServiceInterface;
use Inertia\Inertia;
use Inertia\Response;
use Modules\LinkPage\Application\Contracts\LinkPageSettingsInterface;
use Modules\LinkPage\Application\DTOs\LinkPageEventDTO;
use Modules\LinkPage\Application\Services\LinkPageEventsService;
use Modules\LinkPage\Domain\Enums\CalendarView;

/**
 * /enlaces: the page a printed QR code leads to, apart from the site layout.
 */
final readonly class LinkPageController
{
    public function __construct(
        private LinkPageSettingsInterface $settings,
        private LinkPageEventsService $events,
        private SiteBrandingServiceInterface $branding,
        private TimezoneServiceInterface $timezone,
        private SeoMetadataFactoryInterface $seo,
    ) {}

    public function __invoke(): Response
    {
        abort_unless($this->settings->isEnabled(), 404);

        $siteName = $this->branding->siteName();
        $intro = $this->settings->intro();

        return Inertia::render('LinkPage/Show', [
            'site' => [
                'name' => $siteName,
                'logoUrl' => $this->branding->customLogoUrl(),
                'logoIsDark' => $this->branding->customLogoIsDark(),
            ],
            'intro' => $intro,
            'events' => array_map($this->eventProps(...), $this->events->upcoming()),
            'calendar' => [
                'initialView' => $this->settings->calendarInitialView()->value,
                'views' => array_map(fn (CalendarView $view): string => $view->value, CalendarView::cases()),
            ],
            'homeUrl' => url('/'),
            'seo' => $this->seo->forPage(new SeoPageDTO(
                title: $siteName,
                description: $intro,
                imagePublicId: $this->branding->customLogoPath(),
            ))->toArray(),
        ]);
    }

    /**
     * @return array{id: string, title: string, url: string, startsAt: string|null, endsAt: string|null, location: string|null, pinned: bool}
     */
    private function eventProps(LinkPageEventDTO $event): array
    {
        return [
            'id' => $event->id,
            'title' => $event->title,
            'url' => $event->url,
            'startsAt' => $this->timezone->iso($event->startsAt),
            'endsAt' => $this->timezone->iso($event->endsAt),
            'location' => $event->location,
            'pinned' => $event->pinned,
        ];
    }
}
