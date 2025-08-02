<?php

declare(strict_types=1);

namespace WPTechnix\SimpleLogger\Injector;

use WPTechnix\SimpleLogger\LogEntry;

/**
 * Interface InjectorInterface
 *
 * Defines a contract for log entry injectors that provide additional contextual data.
 * Injectors can enrich a log entry by adding custom data to its `extra` field.
 */
interface InjectorInterface
{
    /**
     * Returns the key under which the injected data will be added.
     *
     * This key is used in the log entry's "extra" array to identify the injected data.
     *
     * @return string The unique key for the injected data.
     */
    public function getKey(): string;

    /**
     * Injects data into the provided log entry.
     *
     * The returned log entry should include the injected data under the key
     * returned by getKey(), typically within the "extra" array.
     *
     * @param LogEntry $entry The original log entry to enrich.
     *
     * @return LogEntry A new log entry instance with the injected data.
     */
    public function __invoke(LogEntry $entry): LogEntry;
}
