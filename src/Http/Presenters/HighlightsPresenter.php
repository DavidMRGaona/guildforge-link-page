<?php

declare(strict_types=1);

namespace Modules\LinkPage\Http\Presenters;

use App\Application\Services\TimezoneServiceInterface;
use Modules\LinkPage\Application\Contracts\ClockInterface;
use Modules\LinkPage\Application\DTOs\FeaturedEventDTO;
use Modules\LinkPage\Application\Services\NoticeService;
use Modules\LinkPage\Domain\ValueObjects\Notice;
use Modules\LinkPage\Domain\ValueObjects\NoticeLink;

/**
 * The props of the notice and the featured event. Every instant leaves in the display zone as
 * ISO 8601, and with the server's own "now": the countdown counts from that instant, never from
 * the visitor's device clock.
 */
final readonly class HighlightsPresenter
{
    public function __construct(
        private NoticeService $notices,
        private TimezoneServiceInterface $timezone,
        private ClockInterface $clock,
    ) {}

    /**
     * @return array{
     *     serverNow: string|null,
     *     notice: array{text: string, tone: string, link: array{url: string, label: string|null}|null}|null,
     *     featured: array{id: string, title: string, url: string, startsAt: string|null, endsAt: string|null, dayStartsAt: string|null, spansDays: bool, location: string|null, imagePublicId: string|null}|null
     * }
     */
    public function props(?FeaturedEventDTO $featured): array
    {
        $notice = $this->notices->current();

        return [
            'serverNow' => $this->timezone->iso($this->clock->now()),
            'notice' => $notice instanceof Notice ? [
                'text' => $notice->text,
                'tone' => $notice->tone->value,
                'link' => $notice->link instanceof NoticeLink ? ['url' => $notice->link->url, 'label' => $notice->link->label] : null,
            ] : null,
            'featured' => $featured instanceof FeaturedEventDTO ? [
                'id' => $featured->id,
                'title' => $featured->title,
                'url' => $featured->url,
                'startsAt' => $this->timezone->iso($featured->startsAt),
                'endsAt' => $this->timezone->iso($featured->endsAt),
                'dayStartsAt' => $this->timezone->iso($featured->dayStartsAt),
                'spansDays' => $featured->spansDays,
                'location' => $featured->location,
                'imagePublicId' => $featured->imagePublicId,
            ] : null,
        ];
    }
}
