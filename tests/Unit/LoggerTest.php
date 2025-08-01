<?php

declare(strict_types=1);

namespace WPTechnix\SimpleLogger\Tests\Unit;

use DateTimeZone;
use Psr\Log\LogLevel;
use Throwable;
use WPTechnix\SimpleLogger\Exception\InvalidArgumentException;
use WPTechnix\SimpleLogger\Handler\HandlerInterface;
use WPTechnix\SimpleLogger\LogEntry;
use WPTechnix\SimpleLogger\Logger;

/**
 * @covers \WPTechnix\SimpleLogger\Logger
 * @uses   \WPTechnix\SimpleLogger\LogEntry
 * @uses   \WPTechnix\SimpleLogger\LogLevel
 */
final class LoggerTest extends UnitTest
{
    /**
     * @test
     */
    public function itCanBeInstantiatedWithASingleHandler(): void
    {
        $handler = $this->createMock(HandlerInterface::class);

        $this->expectNotToPerformAssertions();

        try {
            new Logger('test-channel', $handler);
        } catch (Throwable) {
            self::fail('Logger instantiation failed.');
        }
    }

    /**
     * @test
     */
    public function itCanBeInstantiatedWithAnArrayOfHandlers(): void
    {
        $this->expectNotToPerformAssertions();

        $handlers = [
            $this->createMock(HandlerInterface::class),
            $this->createMock(HandlerInterface::class),
        ];

        try {
            new Logger('test-channel', $handlers);
        } catch (Throwable) {
            self::fail('Logger instantiation failed.');
        }
    }

    /**
     * @test
     */
    public function itThrowsExceptionIfNoHandlersAreProvided(): void
    {
        // Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one handler must be provided for the logger channel.');

        // Act
        new Logger('test-channel', []);
    }

    /**
     * @test
     */
    public function itThrowsExceptionIfAnInvalidHandlerIsProvided(): void
    {
        // Arrange
        $handlers = [
            new \stdClass(), // Invalid handler
        ];

        // Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid Handler provided. All handlers must implement');

        // Act
        // @phpstan-ignore-next-line
        new Logger('test-channel', $handlers);
    }

    /**
     * @test
     */
    public function itCorrectlyPassesLogEntryToHandler(): void
    {
        // Arrange
        $handler = $this->createMock(HandlerInterface::class);
        $logger  = new Logger('app', $handler);

        // Assert that the handler's `handle` method is called exactly once.
        $handler->expects(self::once())
                ->method('handle')
                ->with(
                    self::callback(function (LogEntry $entry) {
                        // Assert that the LogEntry passed to the handler is correct.
                        self::assertSame(LogLevel::INFO, $entry->getLevel()->getName());
                        self::assertSame('Test message', $entry->getMessage());
                        self::assertSame(['user_id' => 123], $entry->getContext());
                        self::assertSame('app', $entry->getChannelName()); // Critical check for channel propagation

                        return true;
                    })
                );

        // Act
        $logger->info('Test message', ['user_id' => 123]);
    }

    /**
     * @test
     */
    public function itCallsAllProvidedHandlers(): void
    {
        // Arrange
        $handler1 = $this->createMock(HandlerInterface::class);
        $handler2 = $this->createMock(HandlerInterface::class);
        $logger   = new Logger('multichannel', [$handler1, $handler2]);

        // Assert
        $handler1->expects(self::once())->method('handle');
        $handler2->expects(self::once())->method('handle');

        // Act
        $logger->warning('This message goes to both handlers.');
    }

    /**
     * @test
     */
    public function itThrowsExceptionForInvalidLogLevel(): void
    {
        // Arrange
        $handler = $this->createMock(HandlerInterface::class);
        $logger  = new Logger('test', $handler);

        // Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid log level provided: "invalid-level".');

        // Act
        $logger->log('invalid-level', 'This will fail.');
    }

    /**
     * @test
     */
    public function itThrowsExceptionForNonStringLogLevel(): void
    {
        // Arrange
        $handler = $this->createMock(HandlerInterface::class);
        $logger  = new Logger('test', $handler);

        // Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid log level provided: "integer".');

        // Act
        $logger->log(123, 'This will also fail.');
    }

    /**
     * @test
     */
    public function itSetsAndUsesCustomTimezone(): void
    {
        // Arrange
        $handler  = $this->createMock(HandlerInterface::class);
        $logger   = new Logger('timezone-test', $handler);
        $timezone = new DateTimeZone('America/New_York');

        // Assert
        $handler->expects(self::once())
                ->method('handle')
                ->with(
                    self::callback(function (LogEntry $entry) use ($timezone) {
                        self::assertSame($timezone->getName(), $entry->getDate()->getTimezone()->getName());

                        return true;
                    })
                );

        // Act
        $logger->setTimeZone($timezone);
        $logger->debug('Testing timezone setting.');
    }

    /**
     * @test
     */
    public function itCallsCustomExceptionHandlerWhenAHandlerFails(): void
    {
        // Arrange
        $failingHandler = $this->createMock(HandlerInterface::class);
        $testException  = new \RuntimeException('Handler failed!');
        $failingHandler->method('handle')->will(self::throwException($testException));

        $logger          = new Logger('exception-test', $failingHandler);
        $exceptionCaught = false;

        // Act
        $logger->setExceptionHandler(
            function (
                Throwable $e,
                HandlerInterface $handler
            ) use (
                $testException,
                $failingHandler,
                &$exceptionCaught
            ) {
                self::assertSame($testException, $e);
                self::assertSame($failingHandler, $handler);
                $exceptionCaught = true;
            }
        );

        $logger->error('This will trigger the exception.');

        // Assert
        self::assertTrue($exceptionCaught, 'The custom exception handler was not called.');
    }

    /**
     * @test
     */
    public function itDoesNotCrashIfAHandlerFailsWithoutACustomExceptionHandler(): void
    {
        // Arrange
        $failingHandler = $this->createMock(HandlerInterface::class);
        $failingHandler->method('handle')->will(self::throwException(new \RuntimeException()));

        $successfulHandler = $this->createMock(HandlerInterface::class);
        $successfulHandler->expects(self::once())->method('handle');

        $logger = new Logger('continue-on-fail', [$failingHandler, $successfulHandler]);

        // Act
        $logger->warning('A handler will fail, but the next should still run.');

        // No explicit assert needed. The test passes if it doesn't throw an unhandled exception
        // and if the second handler's `expects(once())` assertion is met.
    }

    /**
     * @test
     * @dataProvider psrLogLevelsProvider
     */
    public function convenienceMethodsCallLogWithCorrectLevel(string $level): void
    {
        // Arrange: Create a mock of the Logger that only mocks the `log` method.
        $logger = $this->getMockBuilder(Logger::class)
                       ->setConstructorArgs(['test', $this->createMock(HandlerInterface::class)])
                       ->onlyMethods(['log'])
                       ->getMock();

        // Assert
        $logger->expects(self::once())
               ->method('log')
               ->with(self::equalTo($level), self::equalTo('test message'));

        // Act: Call the convenience method (e.g., $logger->info(...))
        $method = [$logger, $level];
        if (is_callable($method)) {
            call_user_func_array($method, ['test message']);
        } else {
            self::fail('Invalid log level provided.');
        }
    }

    /**
     * Provides all PSR-3 log levels for testing convenience methods.
     *
     * @phpstan-return array<array<LogLevel::*>>
     */
    public function psrLogLevelsProvider(): array
    {
        return [
            [LogLevel::EMERGENCY],
            [LogLevel::ALERT],
            [LogLevel::CRITICAL],
            [LogLevel::ERROR],
            [LogLevel::WARNING],
            [LogLevel::NOTICE],
            [LogLevel::INFO],
            [LogLevel::DEBUG],
        ];
    }
}
