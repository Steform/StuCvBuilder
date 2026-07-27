<?php

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Class PasswordResetRequest.
 */
#[ORM\Entity(repositoryClass: 'App\Repository\PasswordResetRequestRepository')]
#[ORM\Table(name: 'password_reset_request')]
class PasswordResetRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'integer')]
    private int $userId;

    #[ORM\Column(length: 255, unique: true)]
    private string $token;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $expiresAt;

    #[ORM\Column(type: 'boolean')]
    private bool $consumed = false;

    /**
     * @brief Build password reset request.
     * @param int $userId User identifier.
     * @param string $token Reset token.
     * @param DateTimeImmutable $expiresAt Token expiration date.
     * @return void
     * @date 2026-04-22
     * @author Stephane H.
     */
    public function __construct(int $userId, string $token, DateTimeImmutable $expiresAt)
    {
        $this->userId = $userId;
        $this->token = $token;
        $this->expiresAt = $expiresAt;
    }

    /**
     * @brief Return request identifier.
     * @return int|null
     * @date 2026-07-27
     * @author Stephane H.
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @brief Return target user identifier.
     * @return int
     * @date 2026-07-27
     * @author Stephane H.
     */
    public function getUserId(): int
    {
        return $this->userId;
    }

    /**
     * @brief Return stored token hash.
     * @return string
     * @date 2026-07-27
     * @author Stephane H.
     */
    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * @brief Return token expiration datetime.
     * @return DateTimeImmutable
     * @date 2026-07-27
     * @author Stephane H.
     */
    public function getExpiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    /**
     * @brief Whether the reset request was already consumed.
     * @return bool
     * @date 2026-07-27
     * @author Stephane H.
     */
    public function isConsumed(): bool
    {
        return $this->consumed;
    }

    /**
     * @brief Mark request as consumed.
     * @return void
     * @date 2026-04-22
     * @author Stephane H.
     */
    public function consume(): void
    {
        $this->consumed = true;
    }
}
