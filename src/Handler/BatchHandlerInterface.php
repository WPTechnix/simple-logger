<?php

declare(strict_types=1);

namespace WPTechnix\SimpleLogger\Handler;

use WPTechnix\SimpleLogger\LogEntry;

/**
 * Interface BatchHandlerInterface
 *
 * Represents a log handler capable of processing multiple log entries in a single operation.
 * This is typically used to improve performance when handling high-volume or batch-based logging.
 *
 * Implementations should ensure that all log entries in the batch are handled consistently.
 */
interface BatchHandlerInterface extends HandlerInterface
{
    /**
     * Processes a batch of log entries.
     *
     * @param LogEntry[] $entries An array of LogEntry objects to be processed.
     *
     * @return void
     */
    public function handleBatch(array $entries): void;
}
