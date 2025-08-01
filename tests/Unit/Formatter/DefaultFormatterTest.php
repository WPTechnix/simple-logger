<?php

declare(strict_types=1);

namespace WPTechnix\SimpleLogger\Tests\Unit\Formatter;

use PHPUnit\Framework\TestCase;
use WPTechnix\SimpleLogger\Formatter\DefaultFormatter;
use WPTechnix\SimpleLogger\LogEntry;
use Psr\Log\LogLevel as PsrLogLevel;
use LogicException;
use RuntimeException;
use Throwable;
use Stringable;
use DateTimeImmutable;
use JsonSerializable;

/**
 * @covers \WPTechnix\SimpleLogger\Formatter\AbstractFormatter
 * @covers \WPTechnix\SimpleLogger\Formatter\DefaultFormatter
 * @uses   \WPTechnix\SimpleLogger\LogEntry
 * @uses   \WPTechnix\SimpleLogger\LogLevel
 */
class DefaultFormatterTest extends TestCase
{
    private DefaultFormatter $formatter;

    public static function dataTypeProvider(): array
    {
        $exception = new LogicException('Test Error');

        return [
            // Test Name           => [value,                expectedString,                    expectedNormalized]
            'null'       => [null, 'null', null],
            'boolean'    => [true, 'true', true],
            'integer'    => [123, '123', 123],
            'string'     => ['hello', 'hello', 'hello'],
            'array'      => [[1], '[array:1]', [1]],
            'object'     => [(object)['a' => 1], '[object:stdClass]', ['a' => 1]],
            'DateTime'   => [
                new DateTimeImmutable('2025-01-01T00:00:00Z'),
                '2025-01-01T00:00:00+00:00',
                '2025-01-01T00:00:00+00:00'
            ],
            'Stringable' => [
                new class implements Stringable {
                    public function __toString(): string
                    {
                        return 'stringified';
                    }
                },
                'stringified',
                'stringified'
            ],
            'Closure'    => [fn() => 'test', '[closure]', '[closure]'],
            'Resource'   => [fopen('php://memory', 'r'), '[resource:stream]', '[resource:stream]'],
            'Throwable'  => [$exception, 'LogicException: Test Error', ['class' => LogicException::class]],
        ];
    }

    // ===================================================================
    // == Core DefaultFormatter Behavior
    // ===================================================================

    /** @test */
    public function testFormatInterpolatesAndRemovesContextByDefault(): void
    {
        $entry     = new LogEntry(
            PsrLogLevel::INFO,
            'User {username} logged in from IP {ip_address}.',
            ['username' => 'testuser', 'ip_address' => '127.0.0.1', 'extra' => true]
        );
        $formatted = $this->formatter->format($entry);
        self::assertSame('User testuser logged in from IP 127.0.0.1.', $formatted->getMessage());
        self::assertSame(['extra' => true], $formatted->getContext());
    }

    /** @test */
    public function testFormatKeepsContextWhenConfigured(): void
    {
        $this->formatter->setRemoveContextKeysOnceMapped(false);
        $originalContext = ['username' => 'testuser'];
        $entry           = new LogEntry(PsrLogLevel::INFO, 'User: {username}', $originalContext);
        $formatted       = $this->formatter->format($entry);
        self::assertSame('User: testuser', $formatted->getMessage());
        self::assertSame($originalContext, $formatted->getContext());
    }

    /** @test */
    public function testFormatDoesNothingWhenNoPlaceholdersExist(): void
    {
        $originalMessage = 'A simple log message.';
        $originalContext = ['user_id' => 5];
        $entry           = new LogEntry(PsrLogLevel::INFO, $originalMessage, $originalContext);
        $formatted       = $this->formatter->format($entry);
        self::assertSame($originalMessage, $formatted->getMessage());
        self::assertSame($originalContext, $formatted->getContext());
    }

    // ===================================================================
    // == Data Type Handling (Comprehensive Provider)
    // ===================================================================

    /**
     * @test
     * @dataProvider dataTypeProvider
     */
    public function testFormatHandlesAllDataTypes(mixed $value, string $expectedString, mixed $expectedNormalized): void
    {
        // Test stringification for interpolation
        $entry1     = new LogEntry(PsrLogLevel::INFO, '{data}', ['data' => $value]);
        $formatted1 = $this->formatter->format($entry1);
        self::assertStringContainsString($expectedString, $formatted1->getMessage());

        // Test normalization for context
        $entry2     = new LogEntry(PsrLogLevel::INFO, 'msg', ['data' => $value]);
        $formatted2 = $this->formatter->format($entry2);
        if ($value instanceof Throwable) {
            self::assertSame($expectedNormalized['class'], $formatted2->getContext()['data']['class']);
        } else {
            self::assertEquals($expectedNormalized, $formatted2->getContext()['data']);
        }

        if (is_resource($value)) {
            fclose($value);
        }
    }

    /** @test */
    public function testSetIncludeStackTraceInMessage(): void
    {
        $this->formatter->setIncludeStackTrace(true);
        $entry = new LogEntry(PsrLogLevel::CRITICAL, '{e}', ['e' => new LogicException('Test')]);
        self::assertStringContainsString('Stack trace:', $this->formatter->format($entry)->getMessage());
    }

    // ===================================================================
    // == Setter Effects and Edge Case Coverage
    // ===================================================================

    /** @test */
    public function testSetIncludeStackTraceInContext(): void
    {
        $this->formatter->setIncludeStackTraceInContext(false); // Default is true, so we test the change
        $entry = new LogEntry(PsrLogLevel::ERROR, 'e', ['exception' => new RuntimeException('Test')]);
        self::assertArrayNotHasKey('trace', $this->formatter->format($entry)->getContext()['exception']);
    }

    /** @test */
    public function testSetBasePathStripsPathWhenItMatches(): void
    {
        $basePath = dirname(__DIR__);
        $this->formatter->setBasePath($basePath);
        $entry = new LogEntry(PsrLogLevel::ERROR, 'e', ['exception' => new RuntimeException()]);
        self::assertStringNotContainsString(
            $basePath,
            $this->formatter->format($entry)->getContext()['exception']['file']
        );
    }

    /** @test */
    public function testNormalizePathReturnsUnmodifiedPathWhenNotMatching(): void
    {
        $this->formatter->setBasePath('/non/existent/path');
        $exception = new RuntimeException();
        $entry     = new LogEntry(PsrLogLevel::ERROR, 'e', ['exception' => $exception]);
        self::assertSame($exception->getFile(), $this->formatter->format($entry)->getContext()['exception']['file']);
    }

    /** @test */
    public function testSetMaxRecursionDepthIsHit(): void
    {
        $this->formatter->setMaxRecursionDepth(1);
        $recursiveData = ['level1' => ['level2' => 'hidden']];
        $entry         = new LogEntry(PsrLogLevel::DEBUG, 'data', $recursiveData);
        self::assertSame(
            '[...max depth reached...]',
            $this->formatter->format($entry)->getContext()['level1']['level2']
        );
    }

    /** @test */
    public function testSetMaxStringLengthIsHit(): void
    {
        $this->formatter->setMaxStringLength(5);
        $entry = new LogEntry(PsrLogLevel::INFO, '{data}', ['data' => 'this-is-a-long-string']);
        self::assertSame('this-...', $this->formatter->format($entry)->getMessage());
    }

    /** @test */
    public function testSetSkipJsonSerializables(): void
    {
        $jsonSerializable = new class implements JsonSerializable {
            public function jsonSerialize(): array
            {
                return ['json' => 'ok'];
            }
        };
        $entry            = new LogEntry(PsrLogLevel::DEBUG, 'data', ['data' => $jsonSerializable]);

        // Default behavior (skip=true) passes the object through.
        $this->formatter->setSkipJsonSerializables(true);
        self::assertSame($jsonSerializable, $this->formatter->format($entry)->getContext()['data']);

        // Set skip=false to trigger normalization.
        $this->formatter->setSkipJsonSerializables(false);
        self::assertEquals([], $this->formatter->format($entry)->getContext()['data']);
    }

    /** @test */
    public function testNormalizeExceptionHandlesPreviousException(): void
    {
        $previous  = new LogicException('The root cause');
        $exception = new RuntimeException('Wrapper exception', 0, $previous);
        $entry     = new LogEntry(PsrLogLevel::ALERT, 'error', ['exception' => $exception]);
        $context   = $this->formatter->format($entry)->getContext();
        self::assertArrayHasKey('previous', $context['exception']);
        self::assertSame('LogicException', $context['exception']['previous']['class']);
    }

    /**
     * This method is called before each test.
     */
    protected function setUp(): void
    {
        $this->formatter = new DefaultFormatter();
    }
}
