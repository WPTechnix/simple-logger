<?php

namespace WPTechnix\SimpleLogger\Formatter;

use WPTechnix\SimpleLogger\LogEntry;

/**
 * Formatter Interface.
 *
 * @template TFormatted = mixed
 */
interface FormatterInterface
{
    /**
     * Format a log entry.
     *
     * @param LogEntry $entry The log entry to format.
     *
     * @phpstan-return TFormatted Formatted log entry.
     */
    public function format(LogEntry $entry);
}
