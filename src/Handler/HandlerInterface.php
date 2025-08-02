<?php

declare(strict_types=1);

namespace WPTechnix\SimpleLogger\Handler;

use WPTechnix\SimpleLogger\Exception\LoggerException;
use WPTechnix\SimpleLogger\LogEntry;

/**
 * Interface HandlerInterface
 *
 * All handlers must implement this interface. It defines the core `handle`
 * method that takes a LogEntry and processes it.
 */
interface HandlerInterface
{
    /**
     * Handles a log entry.
     *
     * @param LogEntry $entry The log entry to handle.
     *
     * @throws LoggerException If the handler fails to handle the log entry.
     */
    public function handle(LogEntry $entry): void;

    /**
     * Determines if the handler should handle the given log entry.
     *
     * @param LogEntry $entry The log entry to check.
     *
     * @return bool True if the entry should be handled, false otherwise.
     */
    public function shouldHandle(LogEntry $entry): bool;

    /**
     * Returns a list of injectors that will be applied to each log
     * entry before it is handled.
     *
     * @return list<(callable(LogEntry): LogEntry)>
     */
    public function getInjectors(): array;

    /**
     * Adds an injector to the handler.
     *
     * @param (callable(LogEntry): LogEntry) $injector The injector to add.
     *
     * @return static
     */
    public function addInjector(callable $injector): static;
}
