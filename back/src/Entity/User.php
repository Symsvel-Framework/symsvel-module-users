<?php

declare(strict_types=1);

namespace SymSvel\Module\Users\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'symsvel_users')]
#[ORM\UniqueConstraint(name: 'uniq_symsvel_users_email', columns: ['email'])]
class User
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 254)] private string $email;
    #[ORM\Column(name: 'password_hash', length: 255)] private string $passwordHash;
    #[ORM\Column(options: ['default' => true])] private bool $enabled = true;
    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')] private \DateTimeImmutable $createdAt;

    public function __construct(string $email, string $passwordHash)
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 254) { throw new \InvalidArgumentException('Invalid user email.'); }
        if (password_get_info($passwordHash)['algoName'] !== 'argon2id') { throw new \InvalidArgumentException('User password must be hashed with Argon2id.'); }
        $this->email = $email;
        $this->passwordHash = $passwordHash;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getEmail(): string { return $this->email; }
    public function getPasswordHash(): string { return $this->passwordHash; }
    public function isEnabled(): bool { return $this->enabled; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
