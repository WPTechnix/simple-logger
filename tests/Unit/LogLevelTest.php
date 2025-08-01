<?php

declare(strict_types=1);

namespace WPTechnix\SimpleLogger\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WPTechnix\SimpleLogger\LogLevel;
use WPTechnix\SimpleLogger\Exception\InvalidArgumentException;

/**
 * @covers \WPTechnix\SimpleLogger\LogLevel
 */
final class LogLevelTest extends TestCase
{
    /**
     * Valid Level Provider.
     * @return array<array<int, mixed>>
     */
    public static function validLevelProvider(): array
    {
        return [
            ['emergency', 100],
            ['alert', 70],
            ['critical', 60],
            ['error', 50],
            ['warning', 40],
            ['notice', 35],
            ['info', 30],
            ['debug', 10],
        ];
    }

    /**
     * Invalid Level Provider.
     *
     * @phpstan-return array<array<int, mixed>>
     */
    public static function invalidLevelProvider(): array
    {
        return [
            ['fatal'],
            ['log'],
            ['verbose'],
            [''],
        ];
    }

    /**
     * @dataProvider validLevelProvider
     */
    public function testCanCreateInstanceWithValidLevels(string $level, int $expectedPriority): void
    {
        $logLevel = new LogLevel($level);

        self::assertSame(strtoupper($level), $logLevel->getLabel());
        self::assertSame($level, $logLevel->getName());
        self::assertSame($level, (string)$logLevel);
        self::assertSame($expectedPriority, $logLevel->getPriority());
    }

    /**
     * @test
     * @dataProvider invalidLevelProvider
     */
    public function testThrowsExceptionOnInvalidLevel(mixed $level): void
    {
        $this->expectException(InvalidArgumentException::class);
        new LogLevel($level);
    }

    /**
     * @test
     */
    public function testComparisonMethods(): void
    {
        $info  = new LogLevel('info');
        $error = new LogLevel('error');

        self::assertTrue($info->isLowerThan($error));
        self::assertTrue($error->isHigherThan($info));

        self::assertTrue($error->isAtLeast('info'));
        self::assertTrue($info->isAtMost('error'));

        self::assertFalse($info->isAtLeast('emergency'));
        self::assertFalse($error->isLowerThan('debug'));
    }

    /**
     * @test
     */
    public function testComparisonAcceptsInstance(): void
    {
        $a = new LogLevel('notice');
        $b = new LogLevel('warning');

        self::assertTrue($a->isLowerThan($b));
        self::assertTrue($b->isHigherThan($a));
        self::assertTrue($b->isAtLeast($a));
        self::assertTrue($a->isAtMost($b));
    }
}
