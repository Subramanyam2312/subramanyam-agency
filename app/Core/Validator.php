<?php

declare(strict_types=1);

namespace App\Core;

use InvalidArgumentException;

/**
 * Rule-string validator.
 *
 *     $validator = Validator::make($request->all(), [
 *         'title' => 'required|max:180',
 *         'email' => 'required|email|unique:users,email',
 *     ]);
 *
 * Stops at the first failing rule per field, so a blank field reports "is required"
 * rather than also complaining that it is not a valid email address.
 */
final class Validator
{
    /** @var array<string,string> */
    private array $errors = [];

    /** @var array<string,mixed> */
    private array $validated = [];

    /**
     * @param array<string,mixed>  $data
     * @param array<string,string> $rules
     * @param array<string,string> $labels Field name overrides for messages.
     */
    private function __construct(
        private array $data,
        private array $rules,
        private array $labels = []
    ) {
        $this->run();
    }

    /**
     * @param array<string,mixed>  $data
     * @param array<string,string> $rules
     * @param array<string,string> $labels
     */
    public static function make(array $data, array $rules, array $labels = []): self
    {
        return new self($data, $rules, $labels);
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function passes(): bool
    {
        return $this->errors === [];
    }

    /**
     * @return array<string,string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): ?string
    {
        return $this->errors === [] ? null : reset($this->errors);
    }

    /**
     * Only the fields that had rules — so a stray extra POST field can never reach
     * an INSERT statement.
     *
     * @return array<string,mixed>
     */
    public function validated(): array
    {
        return $this->validated;
    }

    private function run(): void
    {
        foreach ($this->rules as $field => $ruleString) {
            $value    = $this->data[$field] ?? null;
            $rules    = explode('|', $ruleString);
            $nullable = in_array('nullable', $rules, true);

            $isEmpty = $value === null || $value === '' || (is_array($value) && $value === []);

            if ($isEmpty && !in_array('required', $rules, true)) {
                // Optional and absent: record null and skip the remaining rules.
                $this->validated[$field] = $nullable ? null : $value;
                continue;
            }

            foreach ($rules as $rule) {
                if ($rule === 'nullable') {
                    continue;
                }

                [$name, $parameter] = array_pad(explode(':', $rule, 2), 2, null);

                $message = $this->check($name, $field, $value, $parameter);

                if ($message !== null) {
                    $this->errors[$field] = $message;
                    continue 2;
                }
            }

            $this->validated[$field] = $value;
        }
    }

    /**
     * Returns an error message, or null when the value satisfies the rule.
     */
    private function check(string $rule, string $field, mixed $value, ?string $parameter): ?string
    {
        $label = $this->label($field);

        return match ($rule) {
            'required' => ($value === null || $value === '' || (is_array($value) && $value === []))
                ? "{$label} is required."
                : null,

            'string' => !is_string($value) ? "{$label} must be text." : null,

            'email' => filter_var((string) $value, FILTER_VALIDATE_EMAIL) === false
                ? "{$label} must be a valid email address."
                : null,

            'url' => filter_var((string) $value, FILTER_VALIDATE_URL) === false
                ? "{$label} must be a valid URL."
                : null,

            'numeric' => !is_numeric($value) ? "{$label} must be a number." : null,

            'integer' => filter_var($value, FILTER_VALIDATE_INT) === false
                ? "{$label} must be a whole number."
                : null,

            'boolean' => !in_array($value, [true, false, 0, 1, '0', '1', 'on', 'yes'], true)
                ? "{$label} must be true or false."
                : null,

            'array' => !is_array($value) ? "{$label} must be a list." : null,

            'min' => $this->checkSize($value, (float) $parameter, '<')
                ? (is_numeric($value)
                    ? "{$label} must be at least {$parameter}."
                    : "{$label} must be at least {$parameter} characters.")
                : null,

            'max' => $this->checkSize($value, (float) $parameter, '>')
                ? (is_numeric($value)
                    ? "{$label} may not be greater than {$parameter}."
                    : "{$label} may not be longer than {$parameter} characters.")
                : null,

            'in' => !in_array((string) $value, explode(',', (string) $parameter), true)
                ? "{$label} is not a valid choice."
                : null,

            'date' => strtotime((string) $value) === false
                ? "{$label} must be a valid date."
                : null,

            'slug' => preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', (string) $value) !== 1
                ? "{$label} may only contain lowercase letters, numbers and hyphens."
                : null,

            'phone' => preg_match('/^[0-9+()\s-]{6,20}$/', (string) $value) !== 1
                ? "{$label} must be a valid phone number."
                : null,

            'confirmed' => ($this->data[$field . '_confirmation'] ?? null) !== $value
                ? "{$label} confirmation does not match."
                : null,

            'same' => ($this->data[$parameter] ?? null) !== $value
                ? "{$label} must match {$this->label((string) $parameter)}."
                : null,

            'regex' => preg_match((string) $parameter, (string) $value) !== 1
                ? "{$label} is not in the expected format."
                : null,

            'unique' => $this->checkUnique($value, (string) $parameter)
                ? null
                : "That {$this->lowerLabel($field)} is already taken.",

            'exists' => $this->checkExists($value, (string) $parameter)
                ? null
                : "The selected {$this->lowerLabel($field)} does not exist.",

            default => throw new InvalidArgumentException("Unknown validation rule: {$rule}"),
        };
    }

    private function checkSize(mixed $value, float $limit, string $comparison): bool
    {
        $size = is_numeric($value)
            ? (float) $value
            : (is_array($value) ? count($value) : mb_strlen((string) $value));

        return $comparison === '<' ? $size < $limit : $size > $limit;
    }

    /**
     * 'unique:users,email' or 'unique:posts,slug,17' where 17 is an id to ignore
     * (so saving a record without changing its slug does not fail against itself).
     */
    private function checkUnique(mixed $value, string $parameter): bool
    {
        [$table, $column, $ignoreId] = array_pad(explode(',', $parameter), 3, null);

        $sql = sprintf(
            'SELECT COUNT(*) FROM `%s` WHERE `%s` = :value',
            Database::identifier((string) $table),
            Database::identifier((string) $column)
        );

        $params = [':value' => $value];

        if ($ignoreId !== null && $ignoreId !== '') {
            $sql .= ' AND `id` != :ignore';
            $params[':ignore'] = (int) $ignoreId;
        }

        // Soft-deleted rows still occupy a unique index, so they must count.
        return (int) Database::scalar($sql, $params) === 0;
    }

    private function checkExists(mixed $value, string $parameter): bool
    {
        [$table, $column] = array_pad(explode(',', $parameter), 2, 'id');

        return (int) Database::scalar(
            sprintf(
                'SELECT COUNT(*) FROM `%s` WHERE `%s` = :value',
                Database::identifier((string) $table),
                Database::identifier((string) $column)
            ),
            [':value' => $value]
        ) > 0;
    }

    private function label(string $field): string
    {
        if (isset($this->labels[$field])) {
            return $this->labels[$field];
        }

        return ucfirst(str_replace('_', ' ', $field));
    }

    private function lowerLabel(string $field): string
    {
        return strtolower($this->label($field));
    }
}
