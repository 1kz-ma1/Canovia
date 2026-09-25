<?php

namespace App\Exceptions;

use RuntimeException;

class NativeAiExecutionException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'native_ai_failed',
        public readonly ?int $runId = null,
    ) {
        parent::__construct($message);
    }
}
