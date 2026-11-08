<?php

declare(strict_types=1);

namespace Modules\LinkPage\Tests\Unit\Domain;

use InvalidArgumentException;
use Modules\LinkPage\Domain\ValueObjects\ScanCode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

final class ScanCodeTest extends TestCase
{
    public function test_a_generated_code_has_six_characters_that_cannot_be_mistaken(): void
    {
        for ($i = 0; $i < 200; $i++) {
            $code = ScanCode::generate()->value;

            $this->assertSame(ScanCode::LENGTH, strlen($code));
            $this->assertMatchesRegularExpression('/^'.ScanCode::PATTERN.'$/', $code);
            $this->assertDoesNotMatchRegularExpression('/[01ilo]/', $code);
        }
    }

    public function test_the_same_random_source_gives_the_same_code(): void
    {
        $first = ScanCode::generate(new Randomizer(new Mt19937(42)));
        $second = ScanCode::generate(new Randomizer(new Mt19937(42)));

        $this->assertSame($first->value, $second->value);
    }

    public function test_a_code_is_read_in_lower_case(): void
    {
        $this->assertSame('k7m2xq', ScanCode::fromString('K7M2XQ')->value);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidCodes(): array
    {
        return [
            'too short' => ['k7m2x'],
            'too long' => ['k7m2xqa'],
            'with a zero' => ['k7m2x0'],
            'with an o' => ['k7m2xo'],
            'with a one' => ['k7m2x1'],
            'with an l' => ['k7m2xl'],
            'with an i' => ['k7m2xi'],
            'with a symbol' => ['k7m2x-'],
            'with a path' => ['../abc'],
            'empty' => [''],
        ];
    }

    #[DataProvider('invalidCodes')]
    public function test_anything_else_is_rejected(string $code): void
    {
        $this->expectException(InvalidArgumentException::class);

        ScanCode::fromString($code);
    }

    #[DataProvider('invalidCodes')]
    public function test_is_valid_says_so_without_throwing(string $code): void
    {
        $this->assertFalse(ScanCode::isValid($code));
    }
}
