<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\OperationLog;
use App\Repository\OperationLogRepository;
use App\Tests\Support\CategoryCode;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;

/**
 * End-to-end cover for the brief's notification requirement: saving a product and
 * its categories must write an operation log and send an e-mail.
 */
final class ProductNotificationTest extends ApiTestCase
{
    use MailerAssertionsTrait;

    // Explicit as of API Platform 4.1; in 5.0 the default flips to false.
    protected static ?bool $alwaysBootKernel = true;

    private const LD_JSON = ['Content-Type' => 'application/ld+json', 'Accept' => 'application/ld+json'];

    public function testCreatingAProductWritesAnOperationLogAndSendsOneEmail(): void
    {
        $client = static::createClient();
        $category = $this->createCategory($client);

        $client->request('POST', '/api/products', [
            'headers' => self::LD_JSON,
            'json' => ['name' => 'Desk Lamp', 'price' => '89.50', 'categories' => [$category]],
        ]);

        self::assertResponseStatusCodeSame(201);
        self::assertEmailCount(1);

        $log = $this->latestOperationLog();
        self::assertNotNull($log, 'Saving a product should have written an operation log.');
        self::assertSame('product.saved', $log->getType());
        self::assertSame('Desk Lamp', $log->getContext()['product_name']);
    }

    public function testTheNotificationEmailIsAddressedFromTheConfiguredEnvelope(): void
    {
        $client = static::createClient();
        $category = $this->createCategory($client);

        $client->request('POST', '/api/products', [
            'headers' => self::LD_JSON,
            'json' => ['name' => 'Desk Lamp', 'price' => '89.50', 'categories' => [$category]],
        ]);

        self::assertResponseStatusCodeSame(201);

        $email = self::getMailerMessage();
        self::assertNotNull($email);
        self::assertEmailHeaderSame($email, 'Subject', 'Product saved: Desk Lamp');
    }

    /**
     * Guards the reason the processor is attached per-operation instead of decorating
     * API Platform's shared persist processor: a category must not look like a
     * saved product.
     */
    public function testCreatingACategoryNotifiesNobody(): void
    {
        $client = static::createClient();

        $this->createCategory($client);

        self::assertEmailCount(0);
        self::assertNull($this->latestOperationLog());
    }

    /**
     * A rejected product never reaches the processor, so nothing should be announced.
     */
    public function testAProductRejectedForHavingNoCategoryNotifiesNobody(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/products', [
            'headers' => self::LD_JSON,
            'json' => ['name' => 'Orphan', 'price' => '10.00', 'categories' => []],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertEmailCount(0);
        self::assertNull($this->latestOperationLog());
    }

    private function createCategory(object $client): string
    {
        $response = $client->request('POST', '/api/categories', [
            'headers' => self::LD_JSON,
            'json' => ['code' => CategoryCode::next()],
        ]);

        self::assertResponseStatusCodeSame(201);

        return $response->toArray()['@id'];
    }

    private function latestOperationLog(): ?OperationLog
    {
        /** @var OperationLogRepository $repository */
        $repository = static::getContainer()->get(OperationLogRepository::class);

        return $repository->findOneBy([], ['id' => 'DESC']);
    }
}
