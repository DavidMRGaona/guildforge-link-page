<?php

declare(strict_types=1);

namespace Modules\LinkPage\Domain\ValueObjects;

use App\Domain\ValueObjects\HexColor;
use InvalidArgumentException;
use Modules\LinkPage\Domain\Enums\BackgroundType;
use Modules\LinkPage\Domain\Enums\ButtonStyle;
use Modules\LinkPage\Domain\Enums\ContrastPair;

/**
 * One colour set of the page (the light or the dark one). Every colour is a strict #RRGGBB
 * value, so nothing else can reach the page styles.
 */
final readonly class ColorScheme
{
    public const int MAX_OVERLAY = 90;

    private const string PATTERN = '/^#[0-9A-Fa-f]{6}$/';

    /** How far the selected calendar button moves from the button colour, in OKLCH lightness. */
    private const float STRONG_STEP = 0.08;

    /** Percentage of the text colour in the muted background. */
    private const int MUTED_TEXT = 8;

    /**
     * @param  int  $overlay  percentage of the background colour laid over a background image
     */
    public function __construct(
        public HexColor $background,
        public HexColor $backgroundTo,
        public HexColor $text,
        public HexColor $button,
        public HexColor $buttonText,
        public int $overlay,
    ) {
        if ($overlay < 0 || $overlay > self::MAX_OVERLAY) {
            throw new InvalidArgumentException('The overlay must be a whole percentage from 0 to '.self::MAX_OVERLAY.'.');
        }
    }

    /**
     * @param  array<array-key, mixed>  $values
     *
     * @throws InvalidArgumentException when a colour is not #RRGGBB or the overlay is out of range
     */
    public static function fromArray(array $values): self
    {
        $overlay = $values['overlay'] ?? null;

        if (! is_int($overlay) && (! is_string($overlay) || ! ctype_digit($overlay))) {
            throw new InvalidArgumentException('The overlay must be a whole number.');
        }

        return new self(
            background: self::colour($values, 'background'),
            backgroundTo: self::colour($values, 'background_to'),
            text: self::colour($values, 'text'),
            button: self::colour($values, 'button'),
            buttonText: self::colour($values, 'button_text'),
            overlay: (int) $overlay,
        );
    }

    /**
     * Every colour the page text can sit on. An image is unknown, so it is checked at both
     * extremes, a white and a black pixel, under the overlay: what reads on both reads on any.
     *
     * @return non-empty-list<HexColor>
     */
    public function backgroundsUnderText(BackgroundType $type): array
    {
        return match ($type) {
            BackgroundType::Solid => [$this->background],
            BackgroundType::Gradient => [$this->background, $this->backgroundTo],
            BackgroundType::Image => [
                $this->blendOver(new HexColor('#FFFFFF')),
                $this->blendOver(new HexColor('#000000')),
            ],
        };
    }

    /**
     * The pairs below WCAG AA, in a fixed order (text, button text, button).
     *
     * @return list<ContrastCheck>
     */
    public function contrastFailures(BackgroundType $type, ButtonStyle $style): array
    {
        return array_values(array_filter(
            $this->contrastChecks($type, $style),
            fn (ContrastCheck $check): bool => ! $check->passes(),
        ));
    }

    /**
     * Every pair with its worst ratio, passing or not, in a fixed order (text, button text, button).
     *
     * @return list<ContrastCheck>
     */
    public function contrastChecks(BackgroundType $type, ButtonStyle $style): array
    {
        $backgrounds = $this->backgroundsUnderText($type);

        return [
            new ContrastCheck(ContrastPair::Text, $this->worstRatio($this->text, [...$backgrounds, $this->muted()])),
            new ContrastCheck(ContrastPair::ButtonText, $this->buttonText->contrastRatio($this->button)),
            $style === ButtonStyle::Outline
                ? new ContrastCheck(ContrastPair::ButtonOutline, $this->worstRatio($this->button, $backgrounds))
                : new ContrastCheck(ContrastPair::ButtonEdge, $this->worstRatio($this->button, $backgrounds)),
        ];
    }

    /**
     * The soft background of the calendar (today, the day names): 8 % of the text over the
     * background. The text sits on it too, so it is part of the text check.
     */
    public function muted(): HexColor
    {
        return $this->blend($this->text, $this->background, self::MUTED_TEXT / 100);
    }

    /**
     * The background of the selected calendar view button: the button colour moved away from
     * its text, so it stands apart from the other buttons and keeps the text at 4.5:1.
     */
    public function strongButton(): HexColor
    {
        $darken = $this->buttonText->relativeLuminance() > $this->button->relativeLuminance();
        $candidate = $this->shifted($darken ? -self::STRONG_STEP : self::STRONG_STEP)
            ->readableOn([$this->buttonText], lighten: ! $darken);

        if (! $candidate->equals($this->button)) {
            return $candidate;
        }

        // Already at the end of the range (pure black or white): step the other way if it still reads
        $other = $this->shifted($darken ? self::STRONG_STEP : -self::STRONG_STEP);

        return $other->contrastRatio($this->buttonText) >= 4.5 ? $other : $this->button;
    }

    /**
     * @return array{background: string, background_to: string, text: string, button: string, button_text: string, overlay: int}
     */
    public function toArray(): array
    {
        return [
            'background' => $this->background->value,
            'background_to' => $this->backgroundTo->value,
            'text' => $this->text->value,
            'button' => $this->button->value,
            'button_text' => $this->buttonText->value,
            'overlay' => $this->overlay,
        ];
    }

    /**
     * @param  array<array-key, mixed>  $values
     */
    private static function colour(array $values, string $key): HexColor
    {
        $value = $values[$key] ?? null;

        if (! is_string($value) || preg_match(self::PATTERN, $value) !== 1) {
            throw new InvalidArgumentException("The colour {$key} must be written as #RRGGBB.");
        }

        return new HexColor($value);
    }

    /**
     * @param  non-empty-list<HexColor>  $backgrounds
     */
    private function worstRatio(HexColor $colour, array $backgrounds): float
    {
        return min(array_map($colour->contrastRatio(...), $backgrounds));
    }

    /** The background colour at the overlay opacity laid over a pixel, as the browser composites it. */
    private function blendOver(HexColor $pixel): HexColor
    {
        return $this->blend($this->background, $pixel, $this->overlay / 100);
    }

    /** $top at $alpha opacity over $bottom, per sRGB channel. */
    private function blend(HexColor $top, HexColor $bottom, float $alpha): HexColor
    {
        $top = $top->toRgb();
        $bottom = $bottom->toRgb();

        return HexColor::fromRgb(
            (int) round($alpha * $top['r'] + (1 - $alpha) * $bottom['r']),
            (int) round($alpha * $top['g'] + (1 - $alpha) * $bottom['g']),
            (int) round($alpha * $top['b'] + (1 - $alpha) * $bottom['b']),
        );
    }

    private function shifted(float $step): HexColor
    {
        $oklch = $this->button->toOklch();

        return HexColor::fromOklch(max(0.0, min(1.0, $oklch['l'] + $step)), $oklch['c'], $oklch['h']);
    }
}
