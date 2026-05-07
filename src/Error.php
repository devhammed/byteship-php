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
