<?php

declare(strict_types=1);

namespace Turkpin\InterviewTest;

use RuntimeException;

final class ApiException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly ?string $apiCode = null,
    ) {
        parent::__construct($message);
    }

    public function getApiCode(): ?string
    {
        return $this->apiCode;
    }
}
