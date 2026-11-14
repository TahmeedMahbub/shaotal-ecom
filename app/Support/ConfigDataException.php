<?php

namespace App\Support;

use RuntimeException;
use Throwable;

/** A required config_data row is missing or invalid, or the table cannot be read (see StoreConfig). */
final class ConfigDataException extends RuntimeException
{
    public function __construct(string $message, ?Throwable $previous = null, public readonly bool $tableUnreadable = false)
    {
        parent::__construct($message, 0, $previous);
    }
}
