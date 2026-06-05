<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\User;
use PDO;
use RuntimeException;

final class UserRepository
{
    private const SELECT_COLUMNS = 'id, email, password_hash, name, created_at, updated_at';

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findByEmail(string $email): ?User
    {
        $statement = $this->pdo->prepare(
            'SELECT ' . self::SELECT_COLUMNS . ' FROM users WHERE email = :email LIMIT 1',
        );
        $statement->execute(['email' => $email]);

        $row = $statement->fetch();

        if ($row === false) {
            return null;
        }

        return User::fromRow($row);
    }

    public function findById(int $id): ?User
    {
        $statement = $this->pdo->prepare(
            'SELECT ' . self::SELECT_COLUMNS . ' FROM users WHERE id = :id LIMIT 1',
        );
        $statement->execute(['id' => $id]);

        $row = $statement->fetch();

        if ($row === false) {
            return null;
        }

        return User::fromRow($row);
    }

    public function create(string $email, string $passwordHash, string $name): User
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO users (email, password_hash, name) VALUES (:email, :password_hash, :name)',
        );
        $statement->execute([
            'email' => $email,
            'password_hash' => $passwordHash,
            'name' => $name,
        ]);

        $user = $this->findById((int) $this->pdo->lastInsertId());

        if ($user === null) {
            throw new RuntimeException('Failed to load user after insert.');
        }

        return $user;
    }
}
