<?php

declare(strict_types=1);

namespace Modules\LinkPage\Infrastructure\Services;

use InvalidArgumentException;
use Modules\LinkPage\Application\Contracts\HighlightsSettingsInterface;
use Modules\LinkPage\Domain\Enums\NoticeTone;
use Modules\LinkPage\Domain\ValueObjects\FeaturedSettings;
use Modules\LinkPage\Domain\ValueObjects\Notice;

/**
 * Reads config('modules.settings.link-page'), as LinkPageSettings does.
 */
final readonly class HighlightsSettings implements HighlightsSettingsInterface
{
    private const string KEY = 'modules.settings.link-page';

    public function notice(): ?Notice
    {
        $stored = $this->stored('notice');

        // Only a real true switches it on
        if (($stored['enabled'] ?? false) !== true) {
            return null;
        }

        try {
            return Notice::fromArray($stored);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    public function noticeForForm(): array
    {
        $stored = $this->stored('notice');
        $tone = $stored['tone'] ?? null;

        return [
            'enabled' => ($stored['enabled'] ?? false) === true,
            'text' => $this->string($stored['text'] ?? null),
            'tone' => is_string($tone) && NoticeTone::tryFrom($tone) !== null ? $tone : NoticeTone::Info->value,
            'link_url' => $this->string($stored['link_url'] ?? null),
            'link_label' => $this->string($stored['link_label'] ?? null),
            'starts_at' => $this->string($stored['starts_at'] ?? null),
            'ends_at' => $this->string($stored['ends_at'] ?? null),
        ];
    }

    public function featured(): FeaturedSettings
    {
        return FeaturedSettings::fromArray($this->stored('featured'));
    }

    /**
     * @return array<array-key, mixed>
     */
    private function stored(string $group): array
    {
        $stored = config(self::KEY.'.'.$group);

        return is_array($stored) ? $stored : [];
    }

    private function string(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
