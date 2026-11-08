<?php

declare(strict_types=1);

namespace Modules\LinkPage\Domain\ValueObjects;

use InvalidArgumentException;
use Random\Randomizer;

/**
 * The code a printed QR carries in /qr/{code}. It never changes, so posters keep working
 * whatever happens to the link page's address. Lower-case letters and digits without the
 * ones people mix up when they read a poster aloud (0/o, 1/l/i).
 */
final readonly class ScanCode
{
    public const string ALPHABET = '23456789abcdefghjkmnpqrstuvwxyz';

    public const int LENGTH = 6;

    /** Lower-case only: fromString() lower-cases first. Mirrors ALPHABET. */
    public const string PATTERN = '[2-9a-hjkmnp-z]{6}';

    /** For the route: capitals are accepted and lower-cased by the controller. */
    public const string ROUTE_PATTERN = '[2-9a-hjkmnp-zA-HJKMNP-Z]{6}';

    private function __construct(
        public string $value,
    ) {}

    public static function generate(Randomizer $randomizer = new Randomizer): self
    {
        return new self($randomizer->getBytesFromString(self::ALPHABET, self::LENGTH));
    }

    public static function fromString(string $value): self
    {
        $code = strtolower($value);

        if (! self::isValid($code)) {
            throw new InvalidArgumentException('A QR code is six characters from '.self::ALPHABET);
        }

        return new self($code);
    }

    public static function isValid(string $value): bool
    {
        return preg_match('/^'.self::PATTERN.'$/D', strtolower($value)) === 1;
    }
}
