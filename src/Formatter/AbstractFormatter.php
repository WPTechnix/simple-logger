<?php

declare(strict_types=1);

namespace WPTechnix\SimpleLogger\Formatter;

use DateTimeInterface;
use JsonException;
use Stringable;
use Throwable;
use Closure;
use WPTechnix\SimpleLogger\LogEntry;
use JsonSerializable;

/**
 * Abstract base formatter providing common functionality for log formatters.
 *
 * Concrete handlers must implement the `format` method.
 *
 * @template TFormatted = mixed
 *
 * @implements FormatterInterface<TFormatted>
 */
abstract class AbstractFormatter implements FormatterInterface
{
    /**
     * Include stack trace in message.
     *
     * @var bool
     */
    protected bool $includeStackTrace = false;

    /**
     * Include stack trace in context.
     *
     * @var bool
     */
    protected bool $includeStackTraceInContext = true;

    /**
     * Base Path to strip in exceptions.
     *
     * @var string
     */
    protected string $basePath = '';

    /**
     * Max Recursion Depth.
     *
     * @var int
     * @phpstan-var int<0,max>
     */
    protected int $maxRecursionDepth = 10;

    /**
     * Maximum characters to accept as string when normalizing. <=0 means no limit.
     *
     * @var int
     * @phpstan-var int<0,max>
     */
    protected int $maxStringLength = 10000;

    /**
     * Configure stack trace inclusion in messages.
     *
     * @param bool $includeStackTrace Whether to include stack trace in formatted messages.
     *
     * @return static
     */
    public function setIncludeStackTrace(bool $includeStackTrace): static
    {
        $this->includeStackTrace = $includeStackTrace;

        return $this;
    }

    /**
     * Configure stack trace inclusion in context.
     *
     * @param bool $includeStackTraceInContext Whether to include stack trace in context data.
     *
     * @return static
     */
    public function setIncludeStackTraceInContext(bool $includeStackTraceInContext): static
    {
        $this->includeStackTraceInContext = $includeStackTraceInContext;

        return $this;
    }

    /**
     * Set the base path to strip from file paths in exceptions.
     *
     * Useful for concise and clear exception information in logs.
     *
     * @param string $basePath Base path.
     *
     * @return static
     */
    public function setBasePath(string $basePath): static
    {
        $this->basePath = rtrim($basePath, DIRECTORY_SEPARATOR);

        return $this;
    }

    /**
     * Set maximum recursion depth for data normalization.
     *
     * When <= 0, no recursion limit will be applied.
     *
     * @param int $depth Maximum recursion depth.
     *
     * @return static
     */
    public function setMaxRecursionDepth(int $depth): static
    {
        $this->maxRecursionDepth = max(0, $depth);

        return $this;
    }

    /**
     * Set maximum string length.
     *
     * When <= 0, no string length limit will be applied.
     *
     * @param int $length Maximum string length.
     *
     * @return static
     */
    public function setMaxStringLength(int $length): static
    {
        $this->maxStringLength = max(0, $length);

        return $this;
    }

    /**
     * Format a log entry.
     *
     * @param LogEntry $entry The log entry to format.
     *
     * @phpstan-return TFormatted Formatted log entry.
     */
    abstract public function format(LogEntry $entry);

    /**
     * Interpolate context values into message placeholders.
     *
     * Replaces {key} placeholders in the message with values from context.
     * Used context keys are removed from the context array.
     *
     * @param string $message Message with potential placeholders
     * @param array<string, mixed> $context Context data (modified by reference)
     *
     * @return string Interpolated message
     */
    protected function interpolate(string $message, array &$context): string
    {
        if (0 === count($context) || ! str_contains($message, '{')) {
            return $message;
        }

        $replacements = [];
        foreach ($context as $key => $value) {
            if (! is_string($key)) {
                continue; // @codeCoverageIgnore
            }

            $placeholder = '{' . $key . '}';
            if (str_contains($message, $placeholder)) {
                $replacements[$placeholder] = $this->stringifyValue($value);
                unset($context[$key]);
            }
        }

        return 0 === count($replacements) ? $message : strtr($message, $replacements);
    }

    /**
     * Convert any value to a string representation.
     *
     * @param mixed $value Value to convert
     *
     * @return string String representation of the value
     */
    protected function stringifyValue(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            $string = (string)$value;

            return 0 < $this->maxStringLength && strlen($string) > $this->maxStringLength
                ? substr($string, 0, $this->maxStringLength) . '...'
                : $string;
        }

        if (is_resource($value)) {
            return sprintf('[resource:%s]', get_resource_type($value));
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        if ($value instanceof Throwable) {
            return $this->stringifyException($value);
        }

        if ($value instanceof JsonSerializable) {
            return $this->stringifyJsonSerializable($value);
        }

        // As of PHP 8.0, any class that defines a __toString() method implicitly
        // implements the \Stringable interface. However, it is recommended to explicitly
        // declare implementation of \Stringable for clarity and static analysis tools.
        if ($value instanceof Stringable) {
            return (string)$value;
        }

        if ($value instanceof Closure) {
            return '[closure]';
        }

        if (is_object($value)) {
            return sprintf('[object:%s]', $value::class);
        }

        if (is_array($value)) {
            $count = count($value);

            return sprintf('[array:%d]', $count);
        }

        return '[unknown]'; // @codeCoverageIgnore
    }

    /**
     * Format an exception as a string.
     *
     * @param Throwable $exception Exception to format
     *
     * @return string Formatted exception string
     */
    protected function stringifyException(Throwable $exception): string
    {
        $class   = $exception::class;
        $code    = $exception->getCode();
        $message = $exception->getMessage();
        $file    = $this->normalizePath($exception->getFile());
        $line    = $exception->getLine();

        $formatted = sprintf(
            '%s%s: %s in %s:%d',
            $class,
            $code !== 0 ? "($code)" : '',
            $message,
            $file,
            $line
        );

        if ($this->includeStackTrace) {
            $trace = $this->stringifyStackTrace($exception);
            if ($trace !== '') {
                $formatted .= PHP_EOL . 'Stack trace:' . PHP_EOL . $trace;
            }
        }

        return $formatted;
    }

    /**
     * Normalize data for safe logging.
     *
     * @param mixed $data Data to normalize
     * @param int $depth Current recursion depth
     *
     * @return mixed Normalized data
     */
    protected function normalizeData(mixed $data, int $depth = 0): mixed
    {
        if (0 < $this->maxRecursionDepth && $depth > $this->maxRecursionDepth) {
            return '[...max depth reached...]';
        }

        if ($data === null || is_scalar($data)) {
            return $data;
        }

        if (is_resource($data)) {
            return sprintf('[resource:%s]', get_resource_type($data));
        }

        if ($data instanceof DateTimeInterface) {
            return $data->format(DateTimeInterface::ATOM);
        }

        if ($data instanceof Throwable) {
            return $this->normalizeException($data);
        }

        if ($data instanceof Closure) {
            return '[closure]';
        }

        if ($data instanceof JsonSerializable) {
            return $this->normalizeJsonSerializable($data, $depth + 1);
        }

        if ($data instanceof Stringable) {
            return (string)$data;
        }

        if (is_iterable($data) || is_object($data)) {
            return $this->normalizeIterable($data, $depth + 1);
        }

        return '[unserializable]'; // @codeCoverageIgnore
    }

    /**
     * Normalize an exception to an array.
     *
     * @param Throwable $exception Exception to normalize
     *
     * @return array<string, mixed> Normalized exception data
     */
    protected function normalizeException(Throwable $exception): array
    {
        $data = [
            'class'   => $exception::class,
            'message' => $exception->getMessage(),
            'code'    => $exception->getCode(),
            'file'    => $this->normalizePath($exception->getFile()),
            'line'    => $exception->getLine(),
        ];

        if ($this->includeStackTraceInContext) {
            $data['trace'] = $this->stringifyStackTrace($exception);
        }

        if ($exception->getPrevious() !== null) {
            $data['previous'] = $this->normalizeException($exception->getPrevious());
        }

        return $data;
    }

    /**
     * Normalize iterable data or objects.
     *
     * @phpstan-param iterable<int|string, mixed>|object $data Data to normalize.
     *
     * @param int $depth Current recursion depth.
     *
     * @return array<string|int, mixed> Normalized data.
     */
    protected function normalizeIterable(iterable|object $data, int $depth = 0): array
    {
        $normalized = [];
        $items      = is_object($data) ? get_object_vars($data) : $data;

        foreach ($items as $key => $value) {
            $normalized[$key] = $this->normalizeData($value, $depth);
        }

        return $normalized;
    }

    /**
     * Format exception stack trace as string.
     *
     * @param Throwable $exception Exception to get trace from
     *
     * @return string Formatted stack trace
     */
    protected function stringifyStackTrace(Throwable $exception): string
    {
        $trace = $exception->getTraceAsString();

        return '' !== $this->basePath ? str_replace($this->basePath, '', $trace) : $trace;
    }

    /**
     * Stringify JsonSerializable objects.
     *
     * By default, we treat JsonSerializable objects as regular objects when
     * converting them to string. Child classes may override this method
     * to provide a different behavior.
     *
     * @param JsonSerializable $data Data to stringify.
     *
     * @return string Stringified data.
     *
     * @codeCoverageIgnore
     */
    protected function stringifyJsonSerializable(JsonSerializable $data): string
    {
        return '[object:' . get_class($data) . ']';
    }

    /**
     * Normalize file path by removing base path.
     *
     * @param string $path File path to normalize
     *
     * @return string Normalized path
     */
    protected function normalizePath(string $path): string
    {
        if ('' === $this->basePath || ! str_starts_with($path, $this->basePath)) {
            return $path;
        }

        $normalized = substr($path, strlen($this->basePath));

        return ltrim($normalized, DIRECTORY_SEPARATOR);
    }

    /**
     * Normalize JsonSerializable objects.
     *
     * @param JsonSerializable $data Data to normalize.
     * @param int $depth Current recursion depth.
     *
     * @return mixed Normalized data.
     */
    protected function normalizeJsonSerializable(JsonSerializable $data, int $depth = 0): mixed
    {
        return $this->normalizeData($data->jsonSerialize(), $depth);
    }

    /**
     * Safely encode data as JSON.
     *
     * @param mixed $data Data to encode
     *
     * @return string JSON string or error message
     */
    protected function safeJsonEncode(mixed $data): string
    {
        try {
            $json = json_encode(
                $data,
                JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_THROW_ON_ERROR
            );

            return $json !== false ? $json : '{}';
            // @codeCoverageIgnoreStart
        } catch (JsonException $e) {
            return (string)json_encode(['jsonError' => $e->getMessage()]);
        }
        // @codeCoverageIgnoreEnd
    }
}
