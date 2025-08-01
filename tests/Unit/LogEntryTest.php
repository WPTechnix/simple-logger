<?php

declare(strict_types=1);

namespace WPTechnix\SimpleLogger\Tests\Unit;

use DateTimeImmutable;
use Psr\Log\LogLevel;
use WPTechnix\SimpleLogger\LogEntry;

/**
 * Log Entry Test
 *
 * @covers \WPTechnix\SimpleLogger\LogEntry
 * @uses   \WPTechnix\SimpleLogger\LogLevel
 */
final class LogEntryTest extends UnitTest
{
    /**
     * Tests that the constructor correctly assigns all properties and getters return them.
     *
     * @test
     */
    public function testConstructorAndGetters(): void
    {
        // Arrange
        $date    = new DateTimeImmutable();
        $context = ['user_id' => 123];

        // Act
        $entry = new LogEntry(
            LogLevel::INFO,
            'Test message',
            $context,
            $date,
            'test-channel'
        );

        // Assert
        self::assertSame(LogLevel::INFO, $entry->getLevel()->getName());
        self::assertSame('Test message', $entry->getMessage());
        self::assertSame($context, $entry->getContext());
        self::assertSame($date, $entry->getDate());
        self::assertSame('test-channel', $entry->getChannelName());
    }

    /**
     * Tests that a date is automatically created if none is provided.
     *
     * @test
     */
    public function testDefaultDateIsCreated(): void
    {
        // Act
        $entry = new LogEntry(LogLevel::DEBUG, 'Message');

        // Assert
        self::assertEquals(gmdate('Y-m-d'), $entry->getDate()->format('Y-m-d'));
    }

    /**
     * Tests that the "wither" methods return a new instance with the modified
     * data, ensuring the original object remains immutable.
     *
     * @test
     */
    public function testImmutabilityWithWitherMethods(): void
    {
        // Arrange
        $originalEntry = new LogEntry(LogLevel::INFO, 'Original message');

        // Act & Assert for withMessage
        $newMessageEntry = $originalEntry->withMessage('New message');
        self::assertNotSame($originalEntry, $newMessageEntry);
        self::assertSame('Original message', $originalEntry->getMessage());
        self::assertSame('New message', $newMessageEntry->getMessage());

        // Act & Assert for withContext
        $newContext      = ['key' => 'value'];
        $newContextEntry = $originalEntry->withContext($newContext);
        self::assertNotSame($originalEntry, $newContextEntry);
        self::assertSame([], $originalEntry->getContext());
        self::assertSame($newContext, $newContextEntry->getContext());

        // Act & Assert for withChannelName
        $newChannelEntry = $originalEntry->withChannelName('new-channel');
        self::assertNotSame($originalEntry, $newChannelEntry);
        self::assertSame('default', $originalEntry->getChannelName());
        self::assertSame('new-channel', $newChannelEntry->getChannelName());

        // Act & Assert for withMessageAndContext
        $newContext                = ['key' => 'value2'];
        $newMessageAndContextEntry = $originalEntry->withMessageAndContext('New message2', $newContext);
        self::assertNotSame($originalEntry, $newMessageAndContextEntry);
        self::assertSame('New message2', $newMessageAndContextEntry->getMessage());
        self::assertSame($newContext, $newMessageAndContextEntry->getContext());
    }
}
