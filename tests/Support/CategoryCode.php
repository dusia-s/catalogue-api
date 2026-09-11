<?php

declare(strict_types=1);

namespace App\Tests\Support;

/**
 * Generates category codes for tests.
 *
 * Codes are unique and capped at 10 characters, so tests that create several
 * categories cannot collide with each other and trip the uniqueness constraint.
 *
 * This replaces substr(uniqid('C'), -10), which was duplicated across four test files.
 * uniqid() is time-based, so its uniqueness depends on two calls landing in different
 * microseconds — fine in practice, but it is a guarantee about the clock rather than
 * about the value, and it silently weakens if the suite is ever run in parallel.
 */
final class CategoryCode
{
    /**
     * A random code of exactly 10 characters, matching the allowed pattern.
     */
    public static function next(): string
    {
        return 'T'.substr(bin2hex(random_bytes(8)), 0, 9);
    }
}
