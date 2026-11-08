<?php

declare(strict_types=1);

namespace Modules\LinkPage\Http\Presenters;

use App\Application\Seo\DTOs\SeoPageDTO;
use App\Application\Seo\Services\SeoMetadataFactoryInterface;
use App\Application\Services\SiteBrandingServiceInterface;
use Modules\LinkPage\Application\Contracts\LinkPageSettingsInterface;

/**
 * The card apps build when /enlaces is shared (the `seo` prop, which the core renders into the
 * HTML of the server for crawlers that do not run JavaScript) and what the share button passes
 * on. Metadata is the core's: the module only chooses what goes in.
 */
final readonly class ShareCardPresenter
{
    public function __construct(
        private LinkPageSettingsInterface $settings,
        private SeoMetadataFactoryInterface $seo,
        private SiteBrandingServiceInterface $branding,
    ) {}

    /**
     * Title, description and image come from the panel, else from the site name, the intro and
     * the site logo. The image is a file of the images disk, which the core sizes for previews.
     *
     * @return array{seo: array<string, mixed>, share: array{url: string, title: string, text: string}}
     */
    public function present(?string $intro): array
    {
        $seo = $this->seo->forPage(new SeoPageDTO(
            title: $this->settings->shareTitle() ?? $this->branding->siteName(),
            description: $this->settings->shareDescription() ?? $intro,
            imagePublicId: $this->settings->shareImage(),
            noindex: $this->settings->noindex(),
        ))->toArray();

        // The core writes "<title> - <site name>": a title that is the name would repeat it
        if ($seo['title'] === $seo['siteName']) {
            $seo['fullTitle'] = $seo['title'];
        }

        return [
            'seo' => $seo,
            // The page itself, never the address that brought the visitor (a printed QR code's
            // redirect, tracking parameters)
            'share' => [
                'url' => route('link_page.show'),
                'title' => $seo['title'],
                'text' => $seo['description'],
            ],
        ];
    }
}
