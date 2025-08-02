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
use RuntimeException;

/**
 * @covers \WPTechnix\SimpleLogger\Logger
 * @uses   \WPTechnix\SimpleLogger\LogEntry
 * @uses   \WPTechnix\SimpleLogger\LogLevel
 */
final class LoggerTest extends UnitTest
{
    // ===================================================================
    // == Constructor and Setup Tests
    // ===================================================================

    /** @phpstan-return array<array<LogLevel::*>> */
    public static function psrLogLevelsProvider(): array
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

    /** @test */
    public function itCanBeInstantiatedWithASingleHandler(): void
    {
        $this->expectNotToPerformAssertions();
        try {
            new Logger('test', $this->createMock(HandlerInterface::class));
        } catch (Throwable) {
            self::fail('Logger instantiation failed.');
        }
    }

    /** @test */
    public function itCanBeInstantiatedWithAnArrayOfHandlers(): void
    {
        $this->expectNotToPerformAssertions();
        $handlers = [$this->createMock(HandlerInterface::class)];
        try {
            new Logger('test', $handlers);
        } catch (Throwable) {
            self::fail('Logger instantiation failed.');
        }
    }

    /** @test */
    public function itThrowsExceptionIfNoHandlersAreProvided(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one handler must be provided');
        new Logger('test', []);
    }

    /** @test */
    public function itThrowsExceptionIfAnInvalidHandlerIsProvided(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid Handler provided');
        // @phpstan-ignore-next-line
        new Logger('test', [new \stdClass()]);
    }

    /** @test */
    public function itThrowsExceptionForInvalidInjectorInConstructor(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid Injector provided. Injector must be a callable.');
        // @phpstan-ignore-next-line
        new Logger('test', $this->createMock(HandlerInterface::class), ['not-a-callable']);
    }

    // ===================================================================
    // == Core Logging Flow
    // ===================================================================

    /** @test */
    public function itThrowsExceptionForInvalidLogLevel(): void
    {
        $logger = new Logger('test', $this->createMock(HandlerInterface::class));
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid log level provided: "invalid-level"');
        $logger->log('invalid-level', 'This will fail.');
    }

    /** @test */
    public function itPassesCorrectLogEntryToHandler(): void
    {
        $handler = $this->createMock(HandlerInterface::class);
        $logger  = new Logger('app', $handler);

        // Configure the mock
        $handler->method('getInjectors')->willReturn([]);
        $handler->method('shouldHandle')->willReturn(true);
        $handler->expects(self::once())
                ->method('handle')
                ->with(
                    self::callback(function (LogEntry $entry) {
                        self::assertSame('info', $entry->getLevel()->getName());
                        self::assertSame('Test message', $entry->getMessage());
                        self::assertSame(['user_id' => 123], $entry->getContext());
                        self::assertSame('app', $entry->getChannelName());

                        return true;
                    })
                );

        $logger->info('Test message', ['user_id' => 123]);
    }

    /** @test */
    public function itDoesNotCallHandlerWhenShouldHandleIsFalse(): void
    {
        $handler = $this->createMock(HandlerInterface::class);
        $logger  = new Logger('test', $handler);

        // Configure the mock
        $handler->method('getInjectors')->willReturn([]);
        $handler->method('shouldHandle')->willReturn(false); // The key condition
        $handler->expects(self::never())->method('handle'); // Assert handle() is never called

        $logger->info('This message should be skipped by the handler.');
    }

    // ===================================================================
    // == Injector Tests
    // ===================================================================

    /** @test */
    public function itCallsAllHandlersInSequence(): void
    {
        $handler1 = $this->createMock(HandlerInterface::class);
        $handler1->method('getInjectors')->willReturn([]);
        $handler1->method('shouldHandle')->willReturn(true);
        $handler1->expects(self::once())->method('handle');

        $handler2 = $this->createMock(HandlerInterface::class);
        $handler2->method('getInjectors')->willReturn([]);
        $handler2->method('shouldHandle')->willReturn(true);
        $handler2->expects(self::once())->method('handle');

        $logger = new Logger('multichannel', [$handler1, $handler2]);

        $logger->warning('This goes to both.');
    }

    /** @test */
    public function itAppliesLoggerInjectorsBeforePassingToHandlers(): void
    {
        $handler = $this->createMock(HandlerInterface::class);
        $handler->method('getInjectors')->willReturn([]);
        $handler->method('shouldHandle')->willReturn(true);

        $injector = function (LogEntry $entry): LogEntry {
            return $entry->withExtra(['logger_injected' => true]);
        };

        $logger = new Logger('test', $handler, $injector);

        $handler->expects(self::once())
                ->method('handle')
                ->with(
                    self::callback(function (LogEntry $entry) {
                        self::assertSame(['logger_injected' => true], $entry->getExtra());

                        return true;
                    })
                );

        $logger->info('message');
    }

    // ===================================================================
    // == Exception Handling Tests
    // ===================================================================

    /** @test */
    public function itAppliesHandlerInjectorsBeforeHandling(): void
    {
        $handler = $this->createMock(HandlerInterface::class);
        $handler->method('shouldHandle')->willReturn(true);

        $loggerInjector = function (LogEntry $entry): LogEntry {
            return $entry->withExtra(['logger' => 1]);
        };

        $handlerInjector = function (LogEntry $entry): LogEntry {
            $extra            = $entry->getExtra();
            $extra['handler'] = 2;

            return $entry->withExtra($extra);
        };

        // Configure the mock to return the handler-specific injector
        $handler->method('getInjectors')->willReturn([$handlerInjector]);

        $logger = new Logger('test', $handler, $loggerInjector);

        $handler->expects(self::once())
                ->method('handle')
                ->with(
                    self::callback(function (LogEntry $entry) {
                        // Assert that both injectors have been applied in order
                        $expectedExtra = ['logger' => 1, 'handler' => 2];
                        self::assertSame($expectedExtra, $entry->getExtra());

                        return true;
                    })
                );

        $logger->info('message');
    }

    /** @test */
    public function itCallsCustomExceptionHandlerWhenHandlerThrows(): void
    {
        $testException  = new RuntimeException('Handler failed!');
        $failingHandler = $this->createMock(HandlerInterface::class);
        $failingHandler->method('getInjectors')->willReturn([]);
        $failingHandler->method('shouldHandle')->willReturn(true);
        $failingHandler->method('handle')->will(self::throwException($testException));

        $logger          = new Logger('exception-test', $failingHandler);
        $exceptionCaught = false;

        $logger->setExceptionHandler(
            function (
                Throwable $e,
                ?HandlerInterface $handler
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
        self::assertTrue($exceptionCaught, 'The custom exception handler was not called.');
    }

    /** @test */
    public function itCallsCustomExceptionHandlerWhenLoggerInjectorThrows(): void
    {
        $testException   = new RuntimeException('Injector failed!');
        $failingInjector = function (LogEntry $entry) use ($testException): LogEntry {
            throw $testException;
        };

        $logger          = new Logger('test', $this->createMock(HandlerInterface::class), $failingInjector);
        $exceptionCaught = false;

        $logger->setExceptionHandler(
            function (Throwable $e, ?HandlerInterface $handler) use ($testException, &$exceptionCaught) {
                self::assertSame($testException, $e);
                self::assertNull($handler, 'Handler should be null for a logger-level injector exception.');
                $exceptionCaught = true;
            }
        );

        $logger->error('This triggers the injector exception.');
        self::assertTrue($exceptionCaught, 'The custom exception handler was not called for the logger injector.');
    }


    // ===================================================================
    // == Misc Tests
    // ===================================================================

    /** @test */
    public function itCallsCustomExceptionHandlerWhenHandlerInjectorThrows(): void
    {
        $thrownException = new RuntimeException('Handler Injector failed!');
        $failingInjector = fn() => throw $thrownException;
        $mockHandler     = $this->createMock(HandlerInterface::class);
        $mockHandler->method('shouldHandle')->willReturn(true);
        $mockHandler->method('getInjectors')->willReturn([$failingInjector]);

        $logger          = new Logger('test', $mockHandler);
        $exceptionCaught = false;

        $logger->setExceptionHandler(
            function (Throwable $e, ?HandlerInterface $handler) use ($thrownException, &$exceptionCaught) {
                self::assertSame($e, $thrownException);
                self::assertNull($handler, 'Handler should be null when exception thrown in injector.');
                $exceptionCaught = true;
            }
        );

        $logger->error('This triggers the handler injector exception.');
        self::assertTrue($exceptionCaught, 'The custom exception handler was not called for the handler injector.');
    }

    /** @test */
    public function itSetsAndUsesCustomTimezone(): void
    {
        $handler = $this->createMock(HandlerInterface::class);
        $handler->method('getInjectors')->willReturn([]);
        $handler->method('shouldHandle')->willReturn(true);
        $logger   = new Logger('timezone-test', $handler);
        $timezone = new DateTimeZone('America/New_York');

        $handler->expects(self::once())
                ->method('handle')
                ->with(
                    self::callback(function (LogEntry $entry) use ($timezone) {
                        self::assertSame($timezone->getName(), $entry->getDate()->getTimezone()->getName());

                        return true;
                    })
                );

        $logger->setTimeZone($timezone);
        $logger->debug('Testing timezone setting.');
    }

    /**
     * @test
     * @dataProvider psrLogLevelsProvider
     */
    public function convenienceMethodsCallLogWithCorrectLevel(string $level): void
    {
        $logger = $this->getMockBuilder(Logger::class)
                       ->setConstructorArgs(['test', $this->createMock(HandlerInterface::class)])
                       ->onlyMethods(['log'])
                       ->getMock();

        $logger->expects(self::once())
               ->method('log')
               ->with(self::equalTo($level), self::equalTo('test message'));

        // Call the convenience method (e.g., $logger->info(...))
        $method = [$logger, $level];
        if (is_callable($method)) {
            call_user_func_array($method, ['test message']);
        }
    }
}
