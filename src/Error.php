<?php

declare(strict_types=1);

namespace Devhammed\Byteship;

use Exception;

class Error extends Exception
{
    /** @param array<string, mixed>|null $details */
    public function __construct(
        protected string $error,
        string $message,
        protected ?array $details = null,
        protected ?int $status = null,
    ) {
        parent::__construct($message);
    }

    public static function fromResponse(?array $details, int $status): self
    {
        $error = 'api_request_failed';
        $message = 'Byteship API request failed with status '.$status;

        if ($details !== null) {
            $errorCode = $details['error'] ?? null;
            $detailMessage = $details['detail'] ?? null;
            if (is_string($errorCode) && $errorCode !== '') {
                $error = $errorCode;
            }
            if (is_string($detailMessage) && $detailMessage !== '') {
                $message = $detailMessage;
            }
        }

        return new Error($error, $message, $details, $status);
    }

    public function getError(): string
    {
        return $this->error;
    }

    public function getStatus(): ?int
    {
        return $this->status;
    }

    public function getDetails(): ?array
    {
        return $this->details;
    }

    public function __toString(): string
    {
        if ($this->error !== '') {
            return $this->error.': '.$this->message;
        }

        return $this->message;
    }
}
