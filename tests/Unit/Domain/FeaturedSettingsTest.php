<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Unit\Domain;

use Modules\LinkPage\Domain\Enums\FeaturedMode;
use Modules\LinkPage\Domain\ValueObjects\FeaturedSettings;
use PHPUnit\Framework\TestCase;

final class FeaturedSettingsTest extends TestCase
{
    public function test_the_defaults_feature_nothing(): void
    {
        $settings = FeaturedSettings::fromArray([]);

        $this->assertSame(FeaturedMode::Off, $settings->mode);
        $this->assertNull($settings->tagSlug);
        $this->assertNull($settings->eventId);
    }

    public function test_it_reads_the_stored_shape(): void
    {
        $settings = FeaturedSettings::fromArray(['mode' => 'auto', 'tag' => ' jornadas ', 'event' => '4b0d57f6-6f69-4a91-8d6e-0d9b9d3f3a11']);

        $this->assertSame(FeaturedMode::Auto, $settings->mode);
        $this->assertSame('jornadas', $settings->tagSlug);
        $this->assertSame('4b0d57f6-6f69-4a91-8d6e-0d9b9d3f3a11', $settings->eventId);
    }

    public function test_an_unknown_mode_features_nothing(): void
    {
        $this->assertSame(FeaturedMode::Off, FeaturedSettings::fromArray(['mode' => 'random'])->mode);
        $this->assertSame(FeaturedMode::Off, FeaturedSettings::fromArray(['mode' => ['auto']])->mode);
    }

    public function test_blank_or_malformed_ids_and_tags_are_dropped(): void
    {
        $settings = FeaturedSettings::fromArray(['mode' => 'manual', 'tag' => '  ', 'event' => 'not-a-uuid']);

        $this->assertNull($settings->tagSlug);
        $this->assertNull($settings->eventId);
    }

    public function test_a_manual_mode_without_event_is_still_manual(): void
    {
        $this->assertSame(FeaturedMode::Manual, FeaturedSettings::fromArray(['mode' => 'manual'])->mode);
    }

    public function test_it_round_trips_through_its_stored_shape(): void
    {
        $settings = FeaturedSettings::fromArray(['mode' => 'auto', 'tag' => 'jornadas', 'event' => null]);

        $this->assertSame(['mode' => 'auto', 'tag' => 'jornadas', 'event' => null], $settings->toArray());
    }
}
