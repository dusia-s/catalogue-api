<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\OperationLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * An append-only record of a notified operation.
 *
 * This is the "operation log" the brief asks for. It is written by
 * {@see \App\Notification\Channel\LogNotificationChannel} like any other
 * notification channel, so the log and the e-mail travel the same path.
 */
#[ORM\Entity(repositoryClass: OperationLogRepository::class)]
#[ORM\Table(name: 'operation_log')]
class OperationLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['operation_log:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Groups(['operation_log:read'])]
    private string $type;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['operation_log:read'])]
    private string $message;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(type: Types::JSON)]
    #[Groups(['operation_log:read'])]
    private array $context;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['operation_log:read'])]
    private \DateTimeImmutable $createdAt;

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(string $type, string $message, array $context = [])
    {
        $this->type = $type;
        $this->message = $message;
        $this->context = $context;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
