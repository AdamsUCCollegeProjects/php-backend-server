<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Validator;
use App\Models\User;
use App\Repositories\UserRepository;
use Monolog\Logger;

final class AuthService
{
    private const MIN_PASSWORD_LENGTH = 8;
    private const MAX_NAME_LENGTH = 100;
    private const ERROR_VALIDATION = 'Validation failed';
    private const ERROR_DUPLICATE_EMAIL = 'Email already registered';
    private const ERROR_INVALID_CREDENTIALS = 'Invalid credentials';

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly JwtService $jwtService,
        private readonly Validator $validator,
        private readonly Logger $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: true, data: array<string, mixed>}|array{ok: false, status: int, error: string, details?: array<string, string>}
     */
    public function register(array $input): array
    {
        $errors = $this->validator->validate($input, $this->registerRules());

        if ($errors !== []) {
            return $this->validationFailure($errors);
        }

        $email = (string) $input['email'];
        $password = (string) $input['password'];
        $name = (string) $input['name'];

        $this->logger->info('Registration attempt', ['email' => $email]);

        if ($this->userRepository->findByEmail($email) !== null) {
            return [
                'ok' => false,
                'status' => 409,
                'error' => self::ERROR_DUPLICATE_EMAIL,
            ];
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $user = $this->userRepository->create($email, $passwordHash, $name);

        return [
            'ok' => true,
            'data' => $this->formatUser($user),
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: true, data: array{token: string, expires_in: int}}|array{ok: false, status: int, error: string, details?: array<string, string>}
     */
    public function login(array $input): array
    {
        $errors = $this->validator->validate($input, $this->loginRules());

        if ($errors !== []) {
            return $this->validationFailure($errors);
        }

        $email = (string) $input['email'];
        $password = (string) $input['password'];

        $user = $this->userRepository->findByEmail($email);

        if ($user === null || ! password_verify($password, $user->passwordHash)) {
            $this->logger->warning('Login failed', ['email' => $email]);

            return [
                'ok' => false,
                'status' => 401,
                'error' => self::ERROR_INVALID_CREDENTIALS,
            ];
        }

        return [
            'ok' => true,
            'data' => $this->jwtService->generateToken($user->id, $user->email, $user->role),
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: true, data: array<string, mixed>}|array{ok: false, status: int, error: string, details?: array<string, string>}
     */
    public function registerAdmin(array $input): array
    {
        $errors = $this->validator->validate($input, $this->registerRules());

        if ($errors !== []) {
            return $this->validationFailure($errors);
        }

        $email = (string) $input['email'];
        $password = (string) $input['password'];
        $name = (string) $input['name'];

        $this->logger->info('Admin registration attempt', ['email' => $email]);

        if ($this->userRepository->findByEmail($email) !== null) {
            return [
                'ok' => false,
                'status' => 409,
                'error' => self::ERROR_DUPLICATE_EMAIL,
            ];
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $user = $this->userRepository->create($email, $passwordHash, $name, User::ROLE_ADMIN);

        return [
            'ok' => true,
            'data' => $this->formatUser($user),
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function registerRules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'minLength:' . self::MIN_PASSWORD_LENGTH],
            'name' => ['required', 'maxLength:' . self::MAX_NAME_LENGTH],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function loginRules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required'],
        ];
    }

    /**
     * @param array<string, string> $errors
     * @return array{ok: false, status: int, error: string, details: array<string, string>}
     */
    private function validationFailure(array $errors): array
    {
        return [
            'ok' => false,
            'status' => 400,
            'error' => self::ERROR_VALIDATION,
            'details' => $errors,
        ];
    }

    /**
     * @return array{id: int, email: string, name: string, role: string, created_at: string}
     */
    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'role' => $user->role,
            'created_at' => $user->createdAt,
        ];
    }
}
