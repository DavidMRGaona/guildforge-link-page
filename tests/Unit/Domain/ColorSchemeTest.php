<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Unit\Domain;

use App\Domain\ValueObjects\HexColor;
use InvalidArgumentException;
use Modules\LinkPage\Domain\Enums\BackgroundType;
use Modules\LinkPage\Domain\Enums\ButtonStyle;
use Modules\LinkPage\Domain\Enums\ContrastPair;
use Modules\LinkPage\Domain\ValueObjects\ColorScheme;
use Modules\LinkPage\Domain\ValueObjects\ContrastCheck;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ColorSchemeTest extends TestCase
{
    public function test_a_readable_scheme_has_no_failures_in_any_style(): void
    {
        $scheme = $this->scheme();

        foreach (ButtonStyle::cases() as $style) {
            $this->assertSame([], $scheme->contrastFailures(BackgroundType::Solid, $style), $style->value);
        }
    }

    public function test_text_below_four_and_a_half_to_one_on_the_background_fails(): void
    {
        $failures = $this->scheme(text: '#888888')->contrastFailures(BackgroundType::Solid, ButtonStyle::Filled);

        $this->assertSame([ContrastPair::Text], $this->pairs($failures));
        $this->assertEqualsWithDelta(3.25, $failures[0]->ratio, 0.01);
        $this->assertSame(4.5, $failures[0]->minimum);
    }

    public function test_button_text_below_four_and_a_half_to_one_on_the_button_fails(): void
    {
        $failures = $this->scheme(buttonText: '#5A5A5A')->contrastFailures(BackgroundType::Solid, ButtonStyle::Filled);

        $this->assertSame([ContrastPair::ButtonText], $this->pairs($failures));
    }

    public function test_a_filled_button_needs_three_to_one_against_the_background(): void
    {
        // #959595 on white: about 3.0 is the edge; #A0A0A0 is below it, white text on it too
        $scheme = $this->scheme(button: '#A0A0A0', buttonText: '#000000');

        $this->assertSame([ContrastPair::ButtonEdge], $this->pairs($scheme->contrastFailures(BackgroundType::Solid, ButtonStyle::Filled)));
        $this->assertSame([ContrastPair::ButtonEdge], $this->pairs($scheme->contrastFailures(BackgroundType::Solid, ButtonStyle::Shadow)));
    }

    public function test_an_outline_button_carries_its_text_on_the_background_so_it_needs_four_and_a_half(): void
    {
        // #808080 on white is about 3.9:1: enough for an edge, not for text
        $scheme = $this->scheme(button: '#808080', buttonText: '#000000');

        $this->assertSame([], $scheme->contrastFailures(BackgroundType::Solid, ButtonStyle::Filled));
        $this->assertSame([ContrastPair::ButtonOutline], $this->pairs($scheme->contrastFailures(BackgroundType::Solid, ButtonStyle::Outline)));
    }

    public function test_a_gradient_checks_both_colours_and_reports_the_worst(): void
    {
        // Black text reads on white, not on #6A6A6A (about 3.5:1)
        $scheme = $this->scheme(background: '#FFFFFF', backgroundTo: '#6A6A6A', text: '#000000', button: '#000000', buttonText: '#FFFFFF');

        $this->assertSame([], $scheme->contrastFailures(BackgroundType::Solid, ButtonStyle::Filled));

        $failures = $scheme->contrastFailures(BackgroundType::Gradient, ButtonStyle::Filled);
        $this->assertSame([ContrastPair::Text], $this->pairs($failures));
        $this->assertEqualsWithDelta((new HexColor('#6A6A6A'))->contrastRatio(new HexColor('#000000')), $failures[0]->ratio, 0.001);
    }

    public function test_an_image_is_checked_against_white_and_black_under_the_overlay(): void
    {
        // Black text: readable on a white pixel, never on a black one without a light overlay
        $light = $this->scheme(background: '#FFFFFF', text: '#000000', button: '#000000', buttonText: '#FFFFFF', overlay: 0);
        $this->assertContains(ContrastPair::Text, $this->pairs($light->contrastFailures(BackgroundType::Image, ButtonStyle::Filled)));

        $covered = $this->scheme(background: '#FFFFFF', text: '#000000', button: '#000000', buttonText: '#FFFFFF', overlay: 80);
        $this->assertSame([], $covered->contrastFailures(BackgroundType::Image, ButtonStyle::Filled));
    }

    public function test_the_overlay_blends_the_background_over_the_pixel(): void
    {
        $scheme = $this->scheme(background: '#000000', overlay: 50);

        $this->assertSame(['#808080', '#000000'], array_map(
            fn ($colour): string => $colour->value,
            $scheme->backgroundsUnderText(BackgroundType::Image),
        ));
    }

    public function test_the_muted_background_mixes_a_little_text_and_is_part_of_the_text_check(): void
    {
        $scheme = $this->scheme(background: '#FFFFFF', text: '#000000');
        $this->assertSame('#EBEBEB', $scheme->muted()->value);

        // #737373 passes 4.5:1 on white (4.7) but not on its own muted background
        $borderline = $this->scheme(background: '#FFFFFF', text: '#737373');
        $this->assertGreaterThanOrEqual(4.5, $borderline->text->contrastRatio($borderline->background));
        $this->assertSame([ContrastPair::Text], $this->pairs($borderline->contrastFailures(BackgroundType::Solid, ButtonStyle::Filled)));
    }

    public function test_the_strong_button_keeps_the_button_text_readable_and_differs_from_the_button(): void
    {
        foreach ([['#111111', '#FFFFFF'], ['#F5F5F5', '#0A0A0A'], ['#5B2DB3', '#FFFFFF'], ['#E3B66B', '#2A2118']] as [$button, $text]) {
            $scheme = $this->scheme(button: $button, buttonText: $text);
            $strong = $scheme->strongButton();

            $this->assertGreaterThanOrEqual(4.5, $strong->contrastRatio($scheme->buttonText), $button);
            $this->assertNotSame($scheme->button->value, $strong->value, $button);
        }
    }

    public function test_it_round_trips_through_an_array(): void
    {
        $scheme = $this->scheme(overlay: 40);

        $this->assertEquals($scheme, ColorScheme::fromArray($scheme->toArray()));
        $this->assertSame([
            'background' => '#FFFFFF',
            'background_to' => '#EEEEEE',
            'text' => '#111111',
            'button' => '#111111',
            'button_text' => '#FFFFFF',
            'overlay' => 40,
        ], $scheme->toArray());
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function malformed(): iterable
    {
        yield 'not a colour' => [['text' => 'red']];
        yield 'css injection' => [['text' => '#fff;background:url(x)']];
        yield 'missing key' => [['button' => null]];
        yield 'overlay too high' => [['overlay' => 95]];
        yield 'overlay negative' => [['overlay' => -1]];
    }

    /**
     * @param  array<string, mixed>  $override
     */
    #[DataProvider('malformed')]
    public function test_malformed_values_are_refused(array $override): void
    {
        $this->expectException(InvalidArgumentException::class);

        ColorScheme::fromArray(array_replace($this->scheme()->toArray(), $override));
    }

    private function scheme(
        string $background = '#FFFFFF',
        string $backgroundTo = '#EEEEEE',
        string $text = '#111111',
        string $button = '#111111',
        string $buttonText = '#FFFFFF',
        int $overlay = 0,
    ): ColorScheme {
        return ColorScheme::fromArray([
            'background' => $background,
            'background_to' => $backgroundTo,
            'text' => $text,
            'button' => $button,
            'button_text' => $buttonText,
            'overlay' => $overlay,
        ]);
    }

    /**
     * @param  list<ContrastCheck>  $failures
     * @return list<ContrastPair>
     */
    private function pairs(array $failures): array
    {
        return array_map(fn (ContrastCheck $failure): ContrastPair => $failure->pair, $failures);
    }
}
