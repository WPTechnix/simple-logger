<?php

declare(strict_types=1);

namespace WPTechnix\SimpleLogger;

use DateTimeImmutable;
use DateTimeZone;
use Psr\Log\LogLevel as PsrLogLevel;

/**
 * Class LogEntry
 *
 * An immutable data object representing a single log entry.
 *
 * This class holds all information about a log event and is passed through
 * formatters and handlers. Its immutability ensures that handlers cannot
 * interfere with each other by modifying the record.
 */
final class LogEntry
{
    /**
     * Log entry level.
     *
     * @var LogLevel
     */
    private LogLevel $level;

    /**
     * Log entry date.
     *
     * @var DateTimeImmutable
     */
    private DateTimeImmutable $date;

    /**
     * Class Constructor.
     *
     * @phpstan-param PsrLogLevel::*|LogLevel $level The PSR-3 log level.
     *
     * @param string $message The log message.
     * @param array<string, mixed> $context The context data for the log.
     * @param DateTimeImmutable|null $date The date and time of the log event. Defaults to now (UTC).
     * @param string $channelName The channel from which the log originated.
     *
     * @noinspection PhpDocMissingThrowsInspection
     */
    public function __construct(
        string|LogLevel $level,
        private string $message,
        private array $context = [],
        ?DateTimeImmutable $date = null,
        private string $channelName = 'default'
    ) {
        $this->level = is_string($level) ? new LogLevel($level) : $level;

        if ($date === null) {
            /** @noinspection PhpUnhandledExceptionInspection */
            $this->date = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        } else {
            $this->date = $date;
        }
    }

    /**
     * Get Level.
     *
     * @return LogLevel
     */
    public function getLevel(): LogLevel
    {
        return $this->level;
    }

    /**
     * Get Message.
     *
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Get Date.
     *
     * @return DateTimeImmutable
     */
    public function getDate(): DateTimeImmutable
    {
        return $this->date;
    }

    /**
     * Get Channel Name.
     *
     * @return string
     */
    public function getChannelName(): string
    {
        return $this->channelName;
    }

    /**
     * Returns a new instance with a modified message.
     *
     * @param string $message The new message.
     *
     * @return self A new LogEntry instance with the updated message.
     */
    public function withMessage(string $message): self
    {
        $clone          = clone $this;
        $clone->message = $message;

        return $clone;
    }

    /**
     * Returns a new instance with a modified context.
     *
     * @param array<string, mixed> $context The new context data.
     *
     * @return self A new LogEntry instance with the updated context.
     */
    public function withContext(array $context): self
    {
        $clone          = clone $this;
        $clone->context = $context;

        return $clone;
    }

    /**
     * Returns a new instance with a modified channel name.
     *
     * @param string $channelName The new channel name.
     *
     * @return self A new LogEntry instance with the updated channel name.
     */
    public function withChannelName(string $channelName): self
    {
        $clone              = clone $this;
        $clone->channelName = $channelName;

        return $clone;
    }

    /**
     * Returns a new instance with modified message and context.
     *
     * @param string $message The new message.
     * @param array<string, mixed> $context The new context data.
     *
     * @return self A new LogEntry instance with the updated message and context.
     */
    public function withMessageAndContext(string $message, array $context): self
    {
        $clone          = clone $this;
        $clone->message = $message;
        $clone->context = $context;

        return $clone;
    }
}
