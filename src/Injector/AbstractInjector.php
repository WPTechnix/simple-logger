<?php

declare(strict_types=1);

namespace WPTechnix\SimpleLogger\Injector;

use WPTechnix\SimpleLogger\LogEntry;

/**
 * Class AbstractInjector
 *
 * Provides a base implementation for injectors.
 */
abstract class AbstractInjector implements InjectorInterface
{
    /**
     * The key under which the injected data will be added.
     *
     * @var string
     */
    protected string $key;

    /**
     * @inheritDoc
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * @inheritDoc
     */
    public function __invoke(LogEntry $entry): LogEntry
    {
        return $entry->withExtra([$this->key => $this->getData($entry)]);
    }

    /**
     * Returns the data to be injected into the log entry.
     *
     * @param LogEntry $entry The log entry to inject data into.
     *
     * @return mixed The data to inject.
     */
    abstract protected function getData(LogEntry $entry): mixed;
}
