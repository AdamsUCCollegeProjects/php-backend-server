<?php

declare(strict_types=1);

namespace App\Core;

final class Validator
{
    private const RULE_REQUIRED = 'required';
    private const RULE_EMAIL = 'email';
    private const RULE_MIN_LENGTH = 'minLength';
    private const RULE_MAX_LENGTH = 'maxLength';

    /**
     * @param array<string, mixed> $data
     * @param array<string, list<string>> $rules
     * @return array<string, string>
     */
    public function validate(array $data, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;
            $fieldError = $this->validateField($field, $value, $fieldRules);

            if ($fieldError !== null) {
                $errors[$field] = $fieldError;
            }
        }

        return $errors;
    }

    /**
     * @param list<string> $rules
     */
    private function validateField(string $field, mixed $value, array $rules): ?string
    {
        foreach ($rules as $rule) {
            $error = $this->applyRule($field, $value, $rule);

            if ($error !== null) {
                return $error;
            }
        }

        return null;
    }

    private function applyRule(string $field, mixed $value, string $rule): ?string
    {
        if ($rule === self::RULE_REQUIRED) {
            return $this->validateRequired($field, $value);
        }

        if ($this->isEmpty($value)) {
            return null;
        }

        return match (true) {
            $rule === self::RULE_EMAIL => $this->validateEmail($value),
            str_starts_with($rule, self::RULE_MIN_LENGTH . ':') => $this->validateMinLength($value, $rule),
            str_starts_with($rule, self::RULE_MAX_LENGTH . ':') => $this->validateMaxLength($value, $rule),
            default => null,
        };
    }

    private function validateRequired(string $field, mixed $value): ?string
    {
        if ($this->isEmpty($value)) {
            return sprintf('%s is required', $field);
        }

        return null;
    }

    private function validateEmail(mixed $value): ?string
    {
        if (! is_string($value) || ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return 'Invalid email';
        }

        return null;
    }

    private function validateMinLength(mixed $value, string $rule): ?string
    {
        $minLength = (int) substr($rule, strlen(self::RULE_MIN_LENGTH . ':'));

        if (! is_string($value) || strlen($value) < $minLength) {
            return sprintf('Must be at least %d characters', $minLength);
        }

        return null;
    }

    private function validateMaxLength(mixed $value, string $rule): ?string
    {
        $maxLength = (int) substr($rule, strlen(self::RULE_MAX_LENGTH . ':'));

        if (! is_string($value) || strlen($value) > $maxLength) {
            return sprintf('Must be at most %d characters', $maxLength);
        }

        return null;
    }

    private function isEmpty(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value) && trim($value) === '') {
            return true;
        }

        return false;
    }
}
