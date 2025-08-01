<?php

declare(strict_types=1);

namespace WPTechnix\SimpleLogger\Exception;

use Psr\Log\InvalidArgumentException as BaseInvalidArgumentException;

/**
 * Class InvalidArgumentException
 */
class InvalidArgumentException extends BaseInvalidArgumentException implements LoggerExceptionInterface
{
}
