<?php

declare(strict_types=1);

namespace WPTechnix\SimpleLogger;

use Psr\Log\LogLevel as PsrLogLevel;
use WPTechnix\SimpleLogger\Exception\InvalidArgumentException;
use Stringable;

/**
 * Class LogLevel
 *
 * Represents a PSR-3 compatible log level with associated priority and comparison capabilities.
 */
final class LogLevel implements Stringable
{
    /**
     * Log levels in descending order of priority.
     *
     * @phpstan-var array<PsrLogLevel::*, int>
     */
    public const PRIORITIES = [
        PsrLogLevel::EMERGENCY => 100,
        PsrLogLevel::ALERT     => 70,
        PsrLogLevel::CRITICAL  => 60,
        PsrLogLevel::ERROR     => 50,
        PsrLogLevel::WARNING   => 40,
        PsrLogLevel::NOTICE    => 35,
        PsrLogLevel::INFO      => 30,
        PsrLogLevel::DEBUG     => 10,
    ];

    /**
     * The current log level's priority.
     *
     * @var int
     */
    protected int $priority;

    /**
     * The current log level label.
     *
     * @var string
     * @phpstan-var PsrLogLevel::*
     */
    private string $level;

    /**
     * Constructor.
     *
     * @param string $level Log level string.
     *
     * @throws InvalidArgumentException If level is not valid.
     */
    public function __construct(string $level)
    {
        if (! self::isValidLevel($level)) {
            throw new InvalidArgumentException(sprintf('Invalid log level "%s".', $level));
        }

        $this->level    = $level;
        $this->priority = self::PRIORITIES[$level];
    }

    /**
     * Validate a log level.
     *
     * @param mixed $level Log level.
     *
     * @return bool True if valid, false otherwise.
     *
     * @phpstan-assert-if-true ($level is self ? self : PsrLogLevel::*) $level
     */
    public static function isValidLevel(mixed $level): bool
    {
        return $level instanceof self ||
               (is_string($level) && isset(self::PRIORITIES[$level]));
    }

    /**
     * Create a new instance from a string or an existing instance.
     *
     * @param mixed $level Log level.
     *
     * @return self
     */
    public static function from(mixed $level): self
    {
        return is_string($level) ? new self($level) : clone $level;
    }

    /**
     * Get the log level name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->level;
    }

    /**
     * Get the log level label.
     *
     * @return string
     */
    public function getLabel(): string
    {
        return strtoupper($this->level);
    }

    /**
     * Get the log level name.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->level;
    }

    /**
     * Get the log level priority.
     *
     * @return int
     */
    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * Check if this level is lower than the given one.
     *
     * @param LogLevel|string $other Log level to compare with.
     *
     * @return bool
     */
    public function isLowerThan(LogLevel|string $other): bool
    {
        $other = self::from($other);

        return $this->getPriority() < $other->getPriority();
    }

    /**
     * Check if this level is higher than the given one.
     *
     * @param LogLevel|string $other Log level to compare with.
     *
     * @return bool
     */
    public function isHigherThan(LogLevel|string $other): bool
    {
        $other = self::from($other);

        return $this->getPriority() > $other->getPriority();
    }

    /**
     * Check if this level is at least the given one.
     *
     * @param LogLevel|string $other Log level to compare with.
     *
     * @return bool
     */
    public function isAtLeast(LogLevel|string $other): bool
    {
        $other = self::from($other);

        return $this->getPriority() >= $other->getPriority();
    }

    /**
     * Check if this level is at most the given one.
     *
     * @param LogLevel|string $other Log level to compare with.
     *
     * @return bool
     */
    public function isAtMost(LogLevel|string $other): bool
    {
        $other = self::from($other);

        return $this->getPriority() <= $other->getPriority();
    }
}
