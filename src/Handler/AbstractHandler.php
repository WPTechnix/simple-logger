<?php

declare(strict_types=1);

namespace WPTechnix\SimpleLogger\Handler;

use Psr\Log\LogLevel as PsrLogLevel;
use WPTechnix\SimpleLogger\LogLevel;
use WPTechnix\SimpleLogger\Exception\InvalidArgumentException;
use WPTechnix\SimpleLogger\Exception\LoggerException;
use WPTechnix\SimpleLogger\Formatter\FormatterInterface;
use WPTechnix\SimpleLogger\LogEntry;

/**
 * Class AbstractHandler
 *
 * Provides a base implementation for handlers.
 *
 * This class implements the log level filtering logic, ensuring that a handler
 * only processes entries that meet its configured minimum severity level.
 *
 * Concrete handlers must implement the `process` method.
 *
 * @phpstan-template FormattedType = LogEntry
 */
abstract class AbstractHandler implements HandlerInterface
{
    /**
     * The minimum log level that this handler will process.
     *
     * @phpstan-var LogLevel
     */
    protected LogLevel $minLogLevel;

    /**
     * The formatter to use for converting a log entry to a string.
     *
     * @phpstan-var FormatterInterface<FormattedType>
     */
    protected FormatterInterface $formatter;

    /**
     * Class Constructor.
     *
     * @phpstan-param PsrLogLevel::*|LogLevel $minLogLevel The minimum log level this handler will process.
     *
     * @throws InvalidArgumentException If an invalid log level is provided.
     */
    public function __construct(string|LogLevel $minLogLevel = PsrLogLevel::DEBUG)
    {
        $this->minLogLevel = LogLevel::from($minLogLevel);
    }

    /**
     * {@inheritDoc}
     */
    public function handle(LogEntry $entry): void
    {
        if ($this->shouldLog($entry)) {
            $this->process($entry);
        }
    }

    /**
     * Set formatter.
     *
     * @param FormatterInterface<FormattedType> $formatter The formatter to use.
     *
     * @phpstan-param FormatterInterface<FormattedType> $formatter
     *
     * @return static
     */
    public function setFormatter(FormatterInterface $formatter): static
    {
        $this->formatter = $formatter;

        return $this;
    }

    /**
     * Process the log entry to the storage medium.
     *
     * @param LogEntry $entry The log entry to write.
     *
     * @throws LoggerException If an error occurs during writing.
     */
    abstract protected function process(LogEntry $entry): void;

    /**
     * Checks if the given record should be processed by this handler.
     *
     * @param LogEntry $entry The log entry to check.
     *
     * @return bool True if the entry's level is high enough, false otherwise.
     */
    protected function shouldLog(LogEntry $entry): bool
    {
        return $entry->getLevel()->isAtLeast($this->minLogLevel->getName());
    }
}
