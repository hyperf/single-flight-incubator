<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace Hyperf\Incubator\Semaphore;

use Hyperf\Context\Context as HyperfContext;

class Context
{
    public const SEMAPHORE_KEY = 'semaphore_key';

    public const SEMAPHORE_TOKENS = 'semaphore_tokens';

    public const SEMAPHORE_ACQUIRE = 'semaphore_acquire';

    public const SEMAPHORE_TIMEOUT = 'semaphore_timeout';

    public static function withKey(string $key): string
    {
        return HyperfContext::set(self::SEMAPHORE_KEY, $key);
    }

    public static function key(): string
    {
        return HyperfContext::get(self::SEMAPHORE_KEY, '');
    }

    public static function withTokens(int $tokens): int
    {
        return HyperfContext::set(self::SEMAPHORE_TOKENS, $tokens);
    }

    public static function tokens(): int
    {
        return HyperfContext::get(self::SEMAPHORE_TOKENS, 1);
    }

    public static function withAcquire(int $acquire): int
    {
        return HyperfContext::set(self::SEMAPHORE_ACQUIRE, $acquire);
    }

    public static function acquire(): int
    {
        return HyperfContext::get(self::SEMAPHORE_ACQUIRE, 1);
    }

    public static function withTimeout(float $timeout): float
    {
        return HyperfContext::set(self::SEMAPHORE_TIMEOUT, $timeout);
    }

    public static function timeout(): float
    {
        return HyperfContext::get(self::SEMAPHORE_TIMEOUT, -1);
    }

    public static function clear(string $key): void
    {
        HyperfContext::destroy($key);
    }

    public static function clearAll(): void
    {
        $ctx = HyperfContext::getContainer();
        unset($ctx[self::SEMAPHORE_KEY], $ctx[self::SEMAPHORE_TOKENS], $ctx[self::SEMAPHORE_ACQUIRE], $ctx[self::SEMAPHORE_TIMEOUT]);
    }
}
