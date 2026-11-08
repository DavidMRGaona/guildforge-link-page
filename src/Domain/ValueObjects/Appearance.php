<?php

declare(strict_types=1);

namespace Modules\LinkPage\Domain\ValueObjects;

use InvalidArgumentException;
use Modules\LinkPage\Domain\Enums\AppearanceTemplate;
use Modules\LinkPage\Domain\Enums\BackgroundType;
use Modules\LinkPage\Domain\Enums\ButtonShape;
use Modules\LinkPage\Domain\Enums\ButtonStyle;
use Modules\LinkPage\Domain\Enums\LogoShape;
use Modules\LinkPage\Domain\Enums\LogoSource;
use Modules\LinkPage\Domain\Enums\PageFont;
use Modules\LinkPage\Domain\Enums\ThemeMode;
use ValueError;

/**
 * How the link page looks. Every value comes from a closed list, a strict colour or a path
 * the module itself stored, so nothing free-form reaches the page styles.
 */
final readonly class Appearance
{
    public const int MAX_ANGLE = 360;

    /** Images uploaded from the settings page live under link-page/ on the images disk. */
    private const string IMAGE_PATTERN = '#^link-page/[A-Za-z0-9_\-]+(/[A-Za-z0-9_\-]+)*\.[A-Za-z0-9]+$#';

    public function __construct(
        public AppearanceTemplate $template,
        public ThemeMode $theme,
        public LogoSource $logoSource,
        public ?string $logoImage,
        public LogoShape $logoShape,
        public PageFont $font,
        public ButtonStyle $buttonStyle,
        public ButtonShape $buttonShape,
        public BackgroundType $backgroundType,
        public ?string $backgroundImage,
        public int $gradientAngle,
        public ColorScheme $light,
        public ColorScheme $dark,
    ) {
        if ($gradientAngle < 0 || $gradientAngle > self::MAX_ANGLE) {
            throw new InvalidArgumentException('The gradient angle must be from 0 to '.self::MAX_ANGLE.' degrees.');
        }

        foreach ([$logoImage, $backgroundImage] as $path) {
            if ($path !== null && (str_contains($path, '..') || preg_match(self::IMAGE_PATTERN, $path) !== 1)) {
                throw new InvalidArgumentException('Images must be files the module stored under link-page/.');
            }
        }
    }

    /**
     * The page as the site paints it: site colours, the site logo and body font, bordered buttons.
     */
    public static function defaults(): self
    {
        $minimal = AppearanceTemplate::Minimal->values() ?? [];

        return new self(
            template: AppearanceTemplate::Site,
            theme: ThemeMode::Device,
            logoSource: LogoSource::Site,
            logoImage: null,
            logoShape: LogoShape::Circle,
            font: PageFont::SiteBody,
            buttonStyle: ButtonStyle::Outline,
            buttonShape: ButtonShape::Rounded,
            backgroundType: BackgroundType::Solid,
            backgroundImage: null,
            gradientAngle: 180,
            light: ColorScheme::fromArray($minimal['light'] ?? []),
            dark: ColorScheme::fromArray($minimal['dark'] ?? []),
        );
    }

    /**
     * @param  array<array-key, mixed>  $values  the stored shape (see toArray())
     *
     * @throws InvalidArgumentException when any value is missing or outside its list
     */
    public static function fromArray(array $values): self
    {
        $logo = self::group($values, 'logo');
        $buttons = self::group($values, 'buttons');
        $background = self::group($values, 'background');
        $angle = $background['angle'] ?? null;

        if (! is_int($angle) && (! is_string($angle) || ! ctype_digit($angle))) {
            throw new InvalidArgumentException('The gradient angle must be a whole number.');
        }

        try {
            return new self(
                template: AppearanceTemplate::from(self::string($values, 'template')),
                theme: ThemeMode::from(self::string($values, 'theme')),
                logoSource: LogoSource::from(self::string($logo, 'source')),
                logoImage: self::path($logo, 'image'),
                logoShape: LogoShape::from(self::string($logo, 'shape')),
                font: PageFont::from(self::string($values, 'font')),
                buttonStyle: ButtonStyle::from(self::string($buttons, 'style')),
                buttonShape: ButtonShape::from(self::string($buttons, 'shape')),
                backgroundType: BackgroundType::from(self::string($background, 'type')),
                backgroundImage: self::path($background, 'image'),
                gradientAngle: (int) $angle,
                light: ColorScheme::fromArray(self::group($values, 'light')),
                dark: ColorScheme::fromArray(self::group($values, 'dark')),
            );
        } catch (ValueError $error) {
            throw new InvalidArgumentException($error->getMessage(), $error->getCode(), previous: $error);
        }
    }

    /**
     * The colour sets below WCAG AA, keyed 'light' / 'dark'. Only the sets the theme shows are
     * checked, and none with the site colours (the core keeps those readable).
     *
     * @return array<'light'|'dark', non-empty-list<ContrastCheck>>
     */
    public function contrastFailures(): array
    {
        if ($this->template->usesSiteColors()) {
            return [];
        }

        $failures = [];
        foreach ($this->theme->schemesInUse() as $set) {
            $scheme = $set === 'light' ? $this->light : $this->dark;
            $found = $scheme->contrastFailures($this->effectiveBackgroundType(), $this->buttonStyle);

            if ($found !== []) {
                $failures[$set] = $found;
            }
        }

        return $failures;
    }

    /** An image background without an image is painted as a solid one. */
    public function effectiveBackgroundType(): BackgroundType
    {
        return $this->backgroundType === BackgroundType::Image && $this->backgroundImage === null
            ? BackgroundType::Solid
            : $this->backgroundType;
    }

    /** A custom logo without an image shows the site logo. */
    public function effectiveLogoSource(): LogoSource
    {
        return $this->logoSource === LogoSource::Custom && $this->logoImage === null
            ? LogoSource::Site
            : $this->logoSource;
    }

    /** The same appearance painted with the site colours (for stored colours that no longer pass). */
    public function withSiteColors(): self
    {
        return new self(
            AppearanceTemplate::Site, $this->theme, $this->logoSource, $this->logoImage, $this->logoShape,
            $this->font, $this->buttonStyle, $this->buttonShape, $this->backgroundType, $this->backgroundImage,
            $this->gradientAngle, $this->light, $this->dark,
        );
    }

    /**
     * @return array{
     *     template: string,
     *     theme: string,
     *     logo: array{source: string, image: string|null, shape: string},
     *     font: string,
     *     buttons: array{style: string, shape: string},
     *     background: array{type: string, image: string|null, angle: int},
     *     light: array{background: string, background_to: string, text: string, button: string, button_text: string, overlay: int},
     *     dark: array{background: string, background_to: string, text: string, button: string, button_text: string, overlay: int},
     * }
     */
    public function toArray(): array
    {
        return [
            'template' => $this->template->value,
            'theme' => $this->theme->value,
            'logo' => ['source' => $this->logoSource->value, 'image' => $this->logoImage, 'shape' => $this->logoShape->value],
            'font' => $this->font->value,
            'buttons' => ['style' => $this->buttonStyle->value, 'shape' => $this->buttonShape->value],
            'background' => ['type' => $this->backgroundType->value, 'image' => $this->backgroundImage, 'angle' => $this->gradientAngle],
            'light' => $this->light->toArray(),
            'dark' => $this->dark->toArray(),
        ];
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    private static function group(array $values, string $key): array
    {
        $group = $values[$key] ?? null;

        if (! is_array($group)) {
            throw new InvalidArgumentException("The appearance group {$key} is missing.");
        }

        return $group;
    }

    /**
     * @param  array<array-key, mixed>  $values
     */
    private static function string(array $values, string $key): string
    {
        $value = $values[$key] ?? null;

        if (! is_string($value)) {
            throw new InvalidArgumentException("The appearance value {$key} is missing.");
        }

        return $value;
    }

    /**
     * @param  array<array-key, mixed>  $values
     */
    private static function path(array $values, string $key): ?string
    {
        $value = $values[$key] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException("The image {$key} must be a path.");
        }

        return $value;
    }
}
