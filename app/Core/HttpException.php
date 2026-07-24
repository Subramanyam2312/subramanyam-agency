<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Any exception that should become a specific HTTP status rather than a 500.
 * Thrown by the router (404/405) and by middleware (401/403/419/429).
 */
class HttpException extends RuntimeException
{
    /** @var array<string,string> */
    private array $headers;

    /**
     * @param array<string,string> $headers
     */
    public function __construct(private int $status, string $message = '', array $headers = [])
    {
        $this->headers = $headers;

        parent::__construct($message !== '' ? $message : self::defaultMessage($status));
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * @return array<string,string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    private static function defaultMessage(int $status): string
    {
        return match ($status) {
            400     => 'Bad request.',
            401     => 'Authentication required.',
            403     => 'You do not have permission to do that.',
            404     => 'Page not found.',
            405     => 'Method not allowed.',
            419     => 'Your session expired. Please try again.',
            422     => 'The submitted data was not valid.',
            429     => 'Too many requests. Please slow down.',
            default => 'Something went wrong.',
        };
    }
}
