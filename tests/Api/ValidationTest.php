<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Tests\Support\CategoryCode;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Input validation, including the cases that used to reach the database.
 *
 * A malformed price once passed validation and failed on INSERT, answering 500 where
 * a client deserves 422. Anything that can reach the storage layer and blow up there
 * belongs in here.
 */
final class ValidationTest extends ApiTestCase
{
    protected static ?bool $alwaysBootKernel = true;

    private const LD_JSON = ['Content-Type' => 'application/ld+json', 'Accept' => 'application/ld+json'];

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidPrices(): iterable
    {
        // PositiveOrZero compares a string against zero, so these two used to pass
        // validation and then break the INSERT with a 500.
        yield 'not a number' => ['abc'];
        yield 'overflows DECIMAL(10,2)' => ['999999999999.00'];

        // Silently rounded to two decimals by the column before this was pinned down.
        yield 'three decimal places' => ['10.999'];

        yield 'negative' => ['-5.00'];
        yield 'empty string' => [''];
        yield 'scientific notation' => ['1e5'];
        yield 'thousands separator' => ['1,000.00'];
        yield 'leading plus' => ['+5.00'];
        yield 'whitespace padded' => [' 5.00 '];

        // Same unanchored-`$` flaw as the category code. MySQL trimmed this while
        // casting to DECIMAL so nothing malformed was stored, but the constraint
        // should reject it rather than lean on the column to tidy up.
        yield 'trailing newline' => ["12.34\n"];
    }

    #[DataProvider('invalidPrices')]
    public function testAnInvalidPriceIsRejected(string $price): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/products', [
            'headers' => self::LD_JSON,
            'json' => ['name' => 'Test', 'price' => $price, 'categories' => [$this->createCategory($client)]],
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validPrices(): iterable
    {
        yield 'two decimals' => ['89.50'];
        yield 'one decimal' => ['89.5'];
        yield 'no decimals' => ['89'];
        yield 'zero' => ['0'];
        yield 'column maximum' => ['99999999.99'];
    }

    #[DataProvider('validPrices')]
    public function testAValidPriceIsAccepted(string $price): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/products', [
            'headers' => self::LD_JSON,
            'json' => ['name' => 'Test', 'price' => $price, 'categories' => [$this->createCategory($client)]],
        ]);

        self::assertResponseStatusCodeSame(201);
    }

    /**
     * NotBlank treats only null, "" and [] as blank, so without a trim normalizer a
     * name of nothing but spaces counted as filled in.
     */
    public function testAWhitespaceOnlyProductNameIsRejected(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/products', [
            'headers' => self::LD_JSON,
            'json' => ['name' => '   ', 'price' => '1.00', 'categories' => [$this->createCategory($client)]],
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidCategoryCodes(): iterable
    {
        yield 'whitespace only' => ['   '];
        yield 'padded with spaces' => [' PADDED '];
        yield 'contains a newline' => ["AB\nCD"];
        yield 'contains a space' => ['TWO WORDS'];
        yield 'empty' => [''];
        yield 'eleven characters' => ['ELEVENCHARS'];

        // PCRE lets `$` match just before a final newline, so without the D modifier
        // these satisfied the pattern and were stored with the newline intact. With no
        // delete operation on categories, such a row could never be removed again.
        yield 'trailing newline' => ["BIKES\n"];
        yield 'trailing carriage return' => ["BIKES\r"];
        yield 'nothing but a newline' => ["\n"];
    }

    #[DataProvider('invalidCategoryCodes')]
    public function testAnInvalidCategoryCodeIsRejected(string $code): void
    {
        static::createClient()->request('POST', '/api/categories', [
            'headers' => self::LD_JSON,
            'json' => ['code' => $code],
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    /**
     * A malformed relation must be refused rather than reaching the persistence layer.
     */
    public function testAProductReferencingAnUnknownCategoryIsRejected(): void
    {
        static::createClient()->request('POST', '/api/products', [
            'headers' => self::LD_JSON,
            'json' => ['name' => 'Test', 'price' => '1.00', 'categories' => ['/api/categories/999999']],
        ]);

        self::assertResponseStatusCodeSame(400);
    }

    public function testAProductGivenABareCategoryIdRatherThanAnIriIsRejected(): void
    {
        static::createClient()->request('POST', '/api/products', [
            'headers' => self::LD_JSON,
            'json' => ['name' => 'Test', 'price' => '1.00', 'categories' => [1]],
        ]);

        self::assertResponseStatusCodeSame(400);
    }

    /**
     * The join table has a composite primary key, so the same category listed twice
     * stores one link rather than failing or duplicating.
     */
    public function testTheSameCategoryListedTwiceIsStoredOnce(): void
    {
        $client = static::createClient();
        $category = $this->createCategory($client);

        $response = $client->request('POST', '/api/products', [
            'headers' => self::LD_JSON,
            'json' => ['name' => 'Test', 'price' => '1.00', 'categories' => [$category, $category]],
        ]);

        self::assertResponseStatusCodeSame(201);
        self::assertCount(1, $response->toArray()['categories']);
    }

    private function createCategory(Client $client): string
    {
        $response = $client->request('POST', '/api/categories', [
            'headers' => self::LD_JSON,
            'json' => ['code' => CategoryCode::next()],
        ]);

        self::assertResponseStatusCodeSame(201);

        return $response->toArray()['@id'];
    }
}
