<?php

declare(strict_types=1);

namespace Modules\LinkPage\Filament\Media\Sources;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Media\Contracts\MediaUsageSourceInterface;
use App\Application\Media\Contracts\ReleasesMediaThroughCoreInterface;
use App\Application\Media\DTOs\MediaUsageDTO;
use App\Application\Media\Services\MediaFileIdResolverInterface;
use App\Domain\Media\ValueObjects\MediaFileId;
use Modules\LinkPage\Application\Contracts\LinkPageSettingsInterface;
use Modules\LinkPage\Filament\Pages\LinkPageSettingsPage;

/**
 * Images kept in the module settings: the appearance (logo and background, under link-page/<year>/<month>)
 * and the share card image (link-page/share). The record id names the setting.
 */
final readonly class SettingsMediaSource implements MediaUsageSourceInterface, ReleasesMediaThroughCoreInterface
{
    public const string TYPE = 'link-page.settings';

    public const string LOGO = 'appearance.logo';

    public const string BACKGROUND = 'appearance.background';

    public const string SHARE = 'share';

    private const array RECORDS = [self::LOGO, self::BACKGROUND, self::SHARE];

    public function __construct(
        private LinkPageSettingsInterface $settings,
        private MediaFileIdResolverInterface $resolver,
        private AuthorizationServiceInterface $authorization,
    ) {}

    public function type(): string
    {
        return self::TYPE;
    }

    public function label(): string
    {
        return __('link-page::messages.media.sources.settings');
    }

    public function folders(): array
    {
        return ['link-page/'];
    }

    /**
     * The QR logos live in link-page/qr/ and belong to their own source.
     */
    public function excludedSubfolders(): array
    {
        return ['qr/'];
    }

    public function hidden(): bool
    {
        return false;
    }

    /**
     * @return iterable<MediaUsageDTO>
     */
    public function usages(): iterable
    {
        foreach (self::RECORDS as $record) {
            $usage = $this->usageOf($record);

            if ($usage instanceof MediaUsageDTO) {
                yield $usage;
            }
        }
    }

    /**
     * @return list<MediaUsageDTO>
     */
    public function usagesFor(string $recordId): array
    {
        $usage = in_array($recordId, self::RECORDS, true) ? $this->usageOf($recordId) : null;

        return $usage instanceof MediaUsageDTO ? [$usage] : [];
    }

    public function usesFile(MediaFileId $file): bool
    {
        foreach ($this->usages() as $usage) {
            $id = $this->resolver->fromStoredPath($usage->storedPath);

            if ($id instanceof MediaFileId && $id->equals($file)) {
                return true;
            }
        }

        return false;
    }

    public function editUrl(string $recordId, object $user): ?string
    {
        if (! in_array($recordId, self::RECORDS, true)) {
            return null;
        }

        return $this->authorization->can($user, 'link-page:settings.manage') ? LinkPageSettingsPage::getUrl() : null;
    }

    private function usageOf(string $record): ?MediaUsageDTO
    {
        $path = match ($record) {
            self::LOGO => $this->settings->appearance()->logoImage,
            self::BACKGROUND => $this->settings->appearance()->backgroundImage,
            default => $this->settings->shareImage(),
        };

        if ($path === null || trim($path) === '') {
            return null;
        }

        return new MediaUsageDTO(
            storedPath: $path,
            recordId: $record,
            role: __('link-page::messages.media.roles.'.str_replace('.', '_', $record)),
            title: $this->label(),
        );
    }
}
