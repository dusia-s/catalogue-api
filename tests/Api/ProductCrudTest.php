<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Tests\Support\CategoryCode;

/**
 * HTTP-level cover for the product lifecycle and the validation rules in the brief.
 */
final class ProductCrudTest extends ApiTestCase
{
    protected static ?bool $alwaysBootKernel = true;

    private const LD_JSON = ['Content-Type' => 'application/ld+json', 'Accept' => 'application/ld+json'];
    private const MERGE_PATCH = ['Content-Type' => 'application/merge-patch+json', 'Accept' => 'application/ld+json'];

    public function testAProductCanBeCreatedWithSeveralCategories(): void
    {
        $client = static::createClient();

        $response = $client->request('POST', '/api/products', [
            'headers' => self::LD_JSON,
            'json' => [
                'name' => 'Desk Lamp',
                'price' => '89.50',
                'categories' => [$this->createCategory($client), $this->createCategory($client)],
            ],
        ]);

        self::assertResponseStatusCodeSame(201);

        $product = $response->toArray();
        self::assertSame('Desk Lamp', $product['name']);
        self::assertSame('89.50', $product['price'], 'DECIMAL is exposed as a string to preserve precision.');
        self::assertCount(2, $product['categories']);
        self::assertNotEmpty($product['createdAt']);
        self::assertNotEmpty($product['updatedAt']);
    }

    /**
     * Regression guard. API Platform's standard PUT builds a replacement object from
     * the request body; createdAt is read-only, so nothing populates it and the write
     * dies on a NOT NULL column. The operation sets standard_put => false to populate
     * the loaded entity instead, which also keeps the creation date immutable.
     */
    public function testPutReplacesTheProductWhilePreservingItsCreationDate(): void
    {
        $client = static::createClient();
        $category = $this->createCategory($client);
        $created = $this->createProduct($client, $category);

        $response = $client->request('PUT', $created['@id'], [
            'headers' => self::LD_JSON,
            'json' => ['name' => 'Replaced', 'price' => '9.99', 'categories' => [$category]],
        ]);

        self::assertResponseIsSuccessful();

        $updated = $response->toArray();
        self::assertSame('Replaced', $updated['name']);
        self::assertSame('9.99', $updated['price']);
        self::assertSame($created['createdAt'], $updated['createdAt'], 'PUT must not reset the creation date.');
    }

    public function testPatchUpdatesOnlyTheGivenFields(): void
    {
        $client = static::createClient();
        $category = $this->createCategory($client);
        $created = $this->createProduct($client, $category);

        $response = $client->request('PATCH', $created['@id'], [
            'headers' => self::MERGE_PATCH,
            'json' => ['name' => 'Renamed'],
        ]);

        self::assertResponseIsSuccessful();

        $updated = $response->toArray();
        self::assertSame('Renamed', $updated['name']);
        self::assertSame($created['price'], $updated['price']);
        self::assertSame($created['createdAt'], $updated['createdAt']);
    }

    /**
     * API Platform's PATCH only accepts merge-patch; a plain JSON body is an easy
     * mistake to make and the 415 is worth pinning so the README stays truthful.
     */
    public function testPatchRejectsAPlainJsonContentType(): void
    {
        $client = static::createClient();
        $created = $this->createProduct($client, $this->createCategory($client));

        $client->request('PATCH', $created['@id'], [
            'headers' => self::LD_JSON,
            'json' => ['name' => 'Renamed'],
        ]);

        self::assertResponseStatusCodeSame(415);
    }

    public function testAProductCanBeDeleted(): void
    {
        $client = static::createClient();
        $created = $this->createProduct($client, $this->createCategory($client));

        $client->request('DELETE', $created['@id'], ['headers' => self::LD_JSON]);
        self::assertResponseStatusCodeSame(204);

        $client->request('GET', $created['@id'], ['headers' => self::LD_JSON]);
        self::assertResponseStatusCodeSame(404);
    }

    public function testAProductWithoutACategoryIsRejected(): void
    {
        static::createClient()->request('POST', '/api/products', [
            'headers' => self::LD_JSON,
            'json' => ['name' => 'Orphan', 'price' => '10.00', 'categories' => []],
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testANegativePriceIsRejected(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/products', [
            'headers' => self::LD_JSON,
            'json' => ['name' => 'Cheap', 'price' => '-5.00', 'categories' => [$this->createCategory($client)]],
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testACategoryCodeLongerThanTenCharactersIsRejected(): void
    {
        static::createClient()->request('POST', '/api/categories', [
            'headers' => self::LD_JSON,
            'json' => ['code' => 'ELEVENCHARS'],
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testACategoryCodeOfExactlyTenCharactersIsAccepted(): void
    {
        static::createClient()->request('POST', '/api/categories', [
            'headers' => self::LD_JSON,
            'json' => ['code' => 'TENCHARSXX'],
        ]);

        self::assertResponseStatusCodeSame(201);
    }

    public function testADuplicateCategoryCodeIsRejected(): void
    {
        $client = static::createClient();
        $code = CategoryCode::next();

        foreach ([201, 422] as $expected) {
            $client->request('POST', '/api/categories', [
                'headers' => self::LD_JSON,
                'json' => ['code' => $code],
            ]);

            self::assertResponseStatusCodeSame($expected);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function createProduct(Client $client, string $categoryIri): array
    {
        $response = $client->request('POST', '/api/products', [
            'headers' => self::LD_JSON,
            'json' => ['name' => 'Desk Lamp', 'price' => '89.50', 'categories' => [$categoryIri]],
        ]);

        self::assertResponseStatusCodeSame(201);

        return $response->toArray();
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
