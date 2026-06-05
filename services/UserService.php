<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Repositories\UserRepository;

final class UserService
{
    public function __construct(private readonly UserRepository $userRepository)
    {
    }

    /**
     * @return array{id: int, email: string, name: string, created_at: string}|null
     */
    public function getProfile(int $userId): ?array
    {
        $user = $this->userRepository->findById($userId);

        if ($user === null) {
            return null;
        }

        return $this->formatProfile($user);
    }

    /**
     * @return array{id: int, email: string, name: string, created_at: string}
     */
    private function formatProfile(User $user): array
    {
        return [
            'id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'created_at' => $user->createdAt,
        ];
    }
}
