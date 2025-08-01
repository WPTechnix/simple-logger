<?php

declare(strict_types=1);

namespace WPTechnix\SimpleLogger\Formatter;

use JsonSerializable;
use WPTechnix\SimpleLogger\LogEntry;

/**
 * Default formatter.
 *
 * Calls the `interpolate` method to interpolate context values into message placeholders.
 * Used context keys are removed from the context array.
 *
 * @extends AbstractFormatter<LogEntry>
 */
class DefaultFormatter extends AbstractFormatter
{
    /**
     * Indicates if context keys used in message interpolation
     * should be removed from the context array.
     *
     * @var bool
     */
    protected bool $removeContextKeysOnceMapped = true;

    /**
     * Set whether to remove context keys used in message interpolation.
     *
     * @param bool $removeContextKeysOnceMapped Whether to trim context keys used in message interpolation.
     *
     * @return static
     */
    public function setRemoveContextKeysOnceMapped(bool $removeContextKeysOnceMapped): static
    {
        $this->removeContextKeysOnceMapped = $removeContextKeysOnceMapped;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function format(LogEntry $entry): LogEntry
    {
        $context = $entry->getContext();
        $message = $this->interpolate($entry->getMessage(), $context);

        $contextToUse = $this->removeContextKeysOnceMapped ? $context : $entry->getContext();

        $updatedContext = $this->normalizeData($contextToUse);

        return $entry->withMessageAndContext($message, $updatedContext);
    }

    /**
     * {@inheritDoc}
     *
     * OVERRIDE: Stringifies a JsonSerializable object by converting it to a JSON string,
     * providing the most informative representation for direct inclusion in a log message.
     */
    protected function stringifyJsonSerializable(JsonSerializable $data): string
    {
        $normalizedData = $this->normalizeJsonSerializable($data);

        return $this->safeJsonEncode($normalizedData);
    }
}
