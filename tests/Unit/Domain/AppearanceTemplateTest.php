<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Unit\Domain;

use Modules\LinkPage\Domain\Enums\AppearanceTemplate;
use Modules\LinkPage\Domain\Enums\BackgroundType;
use Modules\LinkPage\Domain\Enums\ButtonShape;
use Modules\LinkPage\Domain\Enums\ButtonStyle;
use Modules\LinkPage\Domain\Enums\PageFont;
use Modules\LinkPage\Domain\ValueObjects\ColorScheme;
use PHPUnit\Framework\TestCase;

final class AppearanceTemplateTest extends TestCase
{
    public function test_the_site_and_custom_templates_fill_nothing(): void
    {
        $this->assertNull(AppearanceTemplate::Site->values());
        $this->assertNull(AppearanceTemplate::Custom->values());
        $this->assertTrue(AppearanceTemplate::Site->usesSiteColors());
        $this->assertFalse(AppearanceTemplate::Custom->usesSiteColors());
    }

    public function test_every_named_template_passes_aa_in_both_colour_sets_with_its_own_style(): void
    {
        foreach (AppearanceTemplate::named() as $template) {
            $values = $template->values();
            $this->assertNotNull($values, $template->value);

            $type = BackgroundType::from($values['background']['type']);
            $style = ButtonStyle::from($values['buttons']['style']);
            ButtonShape::from($values['buttons']['shape']);
            PageFont::from($values['font']);

            foreach (['light', 'dark'] as $set) {
                $failures = ColorScheme::fromArray($values[$set])->contrastFailures($type, $style);
                $this->assertSame([], $failures, "{$template->value} {$set}");
            }
        }
    }

    public function test_every_named_template_also_passes_with_any_button_style(): void
    {
        // Changing only the button style after picking a template should not break the contrast
        foreach (AppearanceTemplate::named() as $template) {
            $values = $template->values();
            $this->assertNotNull($values);
            $type = BackgroundType::from($values['background']['type']);

            foreach (ButtonStyle::cases() as $style) {
                foreach (['light', 'dark'] as $set) {
                    $this->assertSame([], ColorScheme::fromArray($values[$set])->contrastFailures($type, $style), "{$template->value} {$set} {$style->value}");
                }
            }
        }
    }

    public function test_the_named_templates_are_the_five_agreed_ones(): void
    {
        $this->assertSame(
            ['parchment', 'tavern', 'arcane', 'forest', 'minimal'],
            array_map(fn (AppearanceTemplate $template): string => $template->value, AppearanceTemplate::named()),
        );
    }
}
