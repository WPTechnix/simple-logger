<?php

declare(strict_types=1);

namespace WPTechnix\SimpleLogger\Handler\Decorator;

use WPTechnix\SimpleLogger\Handler\BatchHandlerInterface;
use WPTechnix\SimpleLogger\Handler\HandlerInterface;
use WPTechnix\SimpleLogger\LogEntry;

/**
 * Class BufferHandler
 *
 * A decorator that buffers log entries before delegating them to the underlying handler.
 * This is beneficial when the target handler involves costly operations.
 */
class BufferHandler implements HandlerInterface
{
    /**
     * Holds buffered log entries.
     *
     * @var LogEntry[]
     */
    private array $buffer = [];

    /**
     * Constructor.
     *
     * @param HandlerInterface $handler The underlying log handler instance.
     * @param int $bufferLimit Maximum number of log entries before an automatic flush.
     * @param bool $flushOnShutdown Whether to automatically flush buffered entries on PHP shutdown.
     */
    public function __construct(
        protected HandlerInterface $handler,
        protected int $bufferLimit = 0,
        protected bool $flushOnShutdown = true
    ) {
        if ($this->flushOnShutdown) {
            register_shutdown_function([$this, 'flush']);
        }
    }

    /**
     * Handles a log entry by adding it to the buffer.
     * Automatically flushes the buffer when the limit is reached.
     *
     * @param LogEntry $entry Log entry to buffer.
     */
    public function handle(LogEntry $entry): void
    {
        $this->buffer[] = $entry;

        if (count($this->buffer) >= $this->bufferLimit) {
            $this->flush();
        }
    }

    /**
     * Flushes all buffered log entries to the underlying handler.
     * Uses batch handling if supported; otherwise processes entries individually.
     */
    public function flush(): void
    {
        if (0 === count($this->buffer)) {
            return;
        }

        if ($this->handler instanceof BatchHandlerInterface) {
            $this->handler->handleBatch($this->buffer);
        } else {
            foreach ($this->buffer as $entry) {
                $this->handler->handle($entry);
            }
        }

        $this->buffer = [];
    }

    /**
     * Returns the current number of buffered log entries.
     *
     * @return int
     */
    public function getBufferSize(): int
    {
        return count($this->buffer);
    }

    /**
     * Determines whether the log entry should be processed
     * based on the underlying handler's logic.
     *
     * @param LogEntry $entry Log entry to evaluate.
     *
     * @return bool
     */
    public function shouldHandle(LogEntry $entry): bool
    {
        return $this->handler->shouldHandle($entry);
    }
}
