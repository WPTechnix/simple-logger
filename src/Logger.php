<?php

declare(strict_types=1);

namespace WPTechnix\SimpleLogger;

use Stringable;
use WPTechnix\SimpleLogger\Exception\InvalidArgumentException;
use WPTechnix\SimpleLogger\Handler\HandlerInterface;
use DateTimeZone;
use DateTimeImmutable;
use Throwable;
use Psr\Log\AbstractLogger;

/**
 * Class Logger
 *
 * The main logger class that channels log entries to appropriate handlers.
 *
 * It implements the PSR-3 LoggerInterface, allowing it to be used as a
 * drop-in replacement for other standard loggers.
 */
class Logger extends AbstractLogger
{
    /**
     * A user-defined callback to handle exceptions that occur within handlers.
     *
     * @var callable|null
     */
    private $exceptionHandler = null;

    /**
     * The stack of handlers that will process log entries.
     *
     * @var array<HandlerInterface>
     */
    private array $handlers;

    /**
     * The timezone for all log entries created by this logger instance.
     *
     * @var DateTimeZone
     */
    private DateTimeZone $timeZone;

    /**
     * Class Constructor.
     *
     * @param string $channelName The name of the channel this logger represents.
     * @param HandlerInterface|array<HandlerInterface> $handlers A single handler or an array of
     *                                                           handlers to process logs.
     *
     * @throws InvalidArgumentException If no handlers are provided or if an invalid handler is given.
     */
    public function __construct(
        private string $channelName,
        array|HandlerInterface $handlers
    ) {
        if (! is_array($handlers)) {
            $handlers = [$handlers];
        }

        if (0 === count($handlers)) {
            throw new InvalidArgumentException('At least one handler must be provided for the logger channel.');
        }

        foreach ($handlers as $handler) {
            if (! ($handler instanceof HandlerInterface)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Invalid Handler provided. All handlers must implement %s.',
                        HandlerInterface::class
                    )
                );
            }
        }
        $this->handlers = $handlers;

        // Default to UTC for consistency. Can be overridden with setTimeZone().
        $this->timeZone = new DateTimeZone('UTC');
    }

    /**
     * {@inheritDoc}
     */
    public function log($level, Stringable|string $message, array $context = []): void
    {
        if (! LogLevel::isValidLevel($level)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid log level provided: "%s".',
                    is_string($level) ? $level : gettype($level)
                )
            );
        }

        /** @noinspection PhpUnhandledExceptionInspection */
        $date   = new DateTimeImmutable('now', $this->timeZone);
        $record = new LogEntry($level, (string)$message, $context, $date);

        $record = $record->withChannelName($this->channelName);

        // Pass the record to all handlers.
        foreach ($this->handlers as $handler) {
            try {
                $handler->handle($record);
            } catch (Throwable $e) {
                $this->handleException($e, $handler);
            }
        }
    }

    /**
     * Sets a custom handler for exceptions occurring within log handlers.
     *
     * @param callable|null $handler A callable that receives the exception and the handler instance.
     *                               If null, the exception will be thrown AS IS.
     *
     * @phpstan-param (callable(Throwable, HandlerInterface): void )|null $handler
     */
    public function setExceptionHandler(?callable $handler): void
    {
        $this->exceptionHandler = $handler;
    }

    /**
     * Sets the timezone for future log entries created by this logger.
     *
     * @param DateTimeZone|string $timezone A DateTimeZone object or a valid timezone string.
     *
     * @throws Throwable If the timezone string is invalid.
     */
    public function setTimeZone(DateTimeZone|string $timezone): void
    {
        $this->timeZone = is_string($timezone) ? new DateTimeZone($timezone) : $timezone;
    }

    /**
     * Invokes the custom exception handler if it is set.
     *
     * @param Throwable $e The exception that was caught.
     * @param HandlerInterface $handler The handler that threw the exception.
     */
    private function handleException(Throwable $e, HandlerInterface $handler): void
    {
        if (isset($this->exceptionHandler)) {
            ($this->exceptionHandler)($e, $handler);
        }
    }
}
