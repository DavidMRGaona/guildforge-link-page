<?php

declare(strict_types=1);

namespace Modules\LinkPage\Infrastructure\Services;

use App\Application\Services\TimezoneServiceInterface;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Modules\LinkPage\Application\Contracts\LinkPageLinksInterface;
use Modules\LinkPage\Application\DTOs\LinkPageLinkDTO;
use Modules\LinkPage\Domain\ValueObjects\LinkUrl;
use Modules\LinkPage\Infrastructure\Persistence\Eloquent\Models\LinkModel;

final readonly class EloquentLinkPageLinks implements LinkPageLinksInterface
{
    public function __construct(private TimezoneServiceInterface $timezone) {}

    public function visible(): array
    {
        // The dates are instants: the bound goes in the storage zone, as the columns are
        $now = $this->timezone->toStorage(CarbonImmutable::now());

        $rows = LinkModel::query()
            ->where('is_active', true)
            ->where(fn (Builder $query): Builder => $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn (Builder $query): Builder => $query->whereNull('ends_at')->orWhere('ends_at', '>', $now))
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get();

        $links = [];

        foreach ($rows as $row) {
            // Never trust what is stored: a row written outside the model may hold anything
            $url = LinkUrl::tryFrom($row->url);

            if (! $url instanceof LinkUrl) {
                continue;
            }

            $links[] = new LinkPageLinkDTO(
                id: $row->id,
                title: $row->title,
                url: $url,
                description: $row->description,
                icon: $row->icon,
                featured: $row->is_featured,
                placement: $row->placement,
            );
        }

        return $links;
    }
}
