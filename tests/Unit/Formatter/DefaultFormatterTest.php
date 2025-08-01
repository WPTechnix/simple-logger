<?php

declare(strict_types=1);

namespace WPTechnix\SimpleLogger\Tests\Unit\Formatter;

use DateTimeImmutable;
use JsonSerializable;
use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Stringable;
use Throwable;
use WPTechnix\SimpleLogger\Formatter\DefaultFormatter;
use WPTechnix\SimpleLogger\LogEntry;
use Psr\Log\LogLevel as PsrLogLevel;

/**
 * @covers \WPTechnix\SimpleLogger\Formatter\AbstractFormatter
 * @covers \WPTechnix\SimpleLogger\Formatter\DefaultFormatter
 * @uses   \WPTechnix\SimpleLogger\LogEntry
 * @uses   \WPTechnix\SimpleLogger\LogLevel
 */
class DefaultFormatterTest extends TestCase
{
    /**
     * Default Formatter.
     *
     * @var DefaultFormatter
     */
    private DefaultFormatter $formatter;

    public static function dataTypeProvider(): array
    {
        $exception = new LogicException('Test Error');

        return [
            // Test Name      => [value,                expectedString,                    expectedNormalized]
            'null'       => [null, 'null', null],
            'boolean'    => [true, 'true', true],
            'integer'    => [123, '123', 123],
            'string'     => ['hello', 'hello', 'hello'],
            'array'      => [[1], '[array:1]', [1]],
            'object'     => [(object)['a' => 1], '[object:stdClass]', ['a' => 1]],
            'DateTime'   => [
                new DateTimeImmutable('2025-01-01T12:00:00Z'),
                '2025-01-01T12:00:00+00:00',
                '2025-01-01T12:00:00+00:00'
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
            'Throwable'  => [$exception, 'LogicException: Test Error in', ['class' => LogicException::class]],
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

    // ===================================================================
    // == JsonSerializable Override Tests
    // ===================================================================

    /** @test */
    public function testJsonSerializableInContextIsNormalizedToArray(): void
    {
        // This test specifically covers the override of normalizeJsonSerializable.
        $userObject     = new class implements JsonSerializable {
            public function jsonSerialize(): array
            {
                return ['id' => 123, 'name' => 'test'];
            }
        };
        $entry          = new LogEntry(PsrLogLevel::INFO, 'A plain message', ['user' => $userObject]);
        $formattedEntry = $this->formatter->format($entry);
        $expectedArray  = ['id' => 123, 'name' => 'test'];
        self::assertSame($expectedArray, $formattedEntry->getContext()['user']);
    }

    /** @test */
    public function testJsonSerializableInMessageIsStringifiedToJson(): void
    {
        // This test specifically covers the override of stringifyJsonSerializable.
        $userObject     = new class implements JsonSerializable {
            public function jsonSerialize(): array
            {
                return ['id' => 123, 'name' => 'test'];
            }
        };
        $entry          = new LogEntry(PsrLogLevel::INFO, 'User data: {user}', ['user' => $userObject]);
        $formattedEntry = $this->formatter->format($entry);
        $expectedJson   = '{"id":123,"name":"test"}';
        self::assertSame('User data: ' . $expectedJson, $formattedEntry->getMessage());
        self::assertArrayNotHasKey('user', $formattedEntry->getContext());
    }


    // ===================================================================
    // == General Data Type Handling (via AbstractFormatter)
    // ===================================================================

    /**
     * @test
     * @dataProvider dataTypeProvider
     */
    public function testFormatHandlesAllOtherDataTypes(
        mixed $value,
        string $expectedString,
        mixed $expectedNormalized
    ): void {
        // Test stringification when value is used in the message
        $entryForString  = new LogEntry(PsrLogLevel::INFO, 'Value is {data}', ['data' => $value]);
        $formattedString = $this->formatter->format($entryForString);
        self::assertStringContainsString($expectedString, $formattedString->getMessage());
        self::assertArrayNotHasKey('data', $formattedString->getContext());

        // Test normalization when value is left in the context
        $entryForContext  = new LogEntry(PsrLogLevel::INFO, 'A plain message', ['data' => $value]);
        $formattedContext = $this->formatter->format($entryForContext);
        if ($value instanceof Throwable) {
            self::assertSame($expectedNormalized['class'], $formattedContext->getContext()['data']['class']);
        } else {
            self::assertEquals($expectedNormalized, $formattedContext->getContext()['data']);
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
        $this->formatter->setIncludeStackTraceInContext(false); // Default is true
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
        $entry         = new LogEntry(PsrLogLevel::DEBUG, 'A plain message', $recursiveData);
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
    public function testNormalizeExceptionHandlesPreviousException(): void
    {
        $previous  = new LogicException('The root cause');
        $exception = new RuntimeException('Wrapper exception', 0, $previous);
        $entry     = new LogEntry(PsrLogLevel::ALERT, 'A plain message', ['exception' => $exception]);
        $context   = $this->formatter->format($entry)->getContext();
        self::assertArrayHasKey('previous', $context['exception']);
        self::assertSame('LogicException', $context['exception']['previous']['class']);
    }

    protected function setUp(): void
    {
        $this->formatter = new DefaultFormatter();
    }
}
