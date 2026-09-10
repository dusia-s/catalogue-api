<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;

/**
 * The README points a reviewer at /api/docs, so it has to render in a browser.
 *
 * These assertions send `Accept: text/html` deliberately. Requesting the same URL
 * with curl's default wildcard Accept header falls back to JSON-LD and answers 200
 * even when the HTML documentation is broken, which is how a missing Twig install
 * once went unnoticed: every command-line check passed while the browser got a 404.
 */
final class DocumentationTest extends ApiTestCase
{
    protected static ?bool $alwaysBootKernel = true;

    private const BROWSER = ['Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'];
    private const OPENAPI = ['Accept' => 'application/vnd.openapi+json'];

    public function testSwaggerUiRendersForABrowser(): void
    {
        $response = static::createClient()->request('GET', '/api/docs', ['headers' => self::BROWSER]);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('text/html', $response->getHeaders()['content-type'][0]);
        self::assertStringContainsString('swagger-ui', $response->getContent());
    }

    public function testTheApiEntrypointRendersForABrowser(): void
    {
        static::createClient()->request('GET', '/api', ['headers' => self::BROWSER]);

        self::assertResponseIsSuccessful();
    }

    /**
     * `json` is not among the configured formats, so the OpenAPI document lives at
     * `.jsonopenapi` and `/api/docs.json` answers 404. The Accept header is explicit
     * because the test client defaults to `application/ld+json`, which this endpoint
     * refuses with a 406.
     */
    public function testTheOpenApiDocumentIsAvailableAsJson(): void
    {
        $response = static::createClient()->request('GET', '/api/docs.jsonopenapi', [
            'headers' => self::OPENAPI,
        ]);

        self::assertResponseIsSuccessful();

        $openApi = $response->toArray();
        self::assertArrayHasKey('openapi', $openApi);
        self::assertSame('Catalogue API', $openApi['info']['title'], 'The placeholder title must not ship.');
        self::assertArrayHasKey('/api/products', $openApi['paths']);
        self::assertArrayHasKey('/api/categories', $openApi['paths']);
        self::assertArrayHasKey('/api/operation_logs', $openApi['paths']);
    }

    /**
     * Products carry every write verb; the log is read-only and categories stop at
     * create, so the published contract should say exactly that.
     */
    public function testTheDocumentedOperationsMatchWhatTheResourcesExpose(): void
    {
        $paths = static::createClient()
            ->request('GET', '/api/docs.jsonopenapi', ['headers' => self::OPENAPI])
            ->toArray()['paths'];

        self::assertSame(
            ['get', 'post'],
            array_keys($paths['/api/products']),
        );
        self::assertSame(
            ['get', 'put', 'delete', 'patch'],
            array_keys($paths['/api/products/{id}']),
        );
        self::assertSame(['get'], array_keys($paths['/api/operation_logs']));
        self::assertArrayNotHasKey('delete', $paths['/api/categories/{id}']);
    }
}
