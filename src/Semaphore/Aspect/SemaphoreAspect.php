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

namespace Hyperf\Incubator\Semaphore\Aspect;

use Exception;
use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\Di\Aop\AbstractAspect;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\Di\Exception\AnnotationException;
use Hyperf\Incubator\Semaphore\Annotation\Semaphore;
use Hyperf\Incubator\Semaphore\Context;
use Hyperf\Incubator\Semaphore\Exception\RuntimeException;
use Hyperf\Incubator\Semaphore\Exception\SemaphoreException;
use Hyperf\Incubator\Semaphore\Exception\TimeoutException;
use Hyperf\Incubator\Semaphore\SemaphoreManager;
use Hyperf\Stringable\Str;

use function Hyperf\Collection\data_get;

class SemaphoreAspect extends AbstractAspect
{
    public const ARG_KEY = 'semaphoreKey';

    public const ARG_TOKENS = 'semaphoreTokens';

    public const ARG_ACQUIRE = 'semaphoreAcquire';

    public const ARG_TIMEOUT = 'semaphoreTimeout';

    public array $annotations = [
        Semaphore::class,
    ];

    /**
     * @throws AnnotationException|Exception|TimeoutException
     */
    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        /** @var Semaphore $annotation */
        $annotation = AnnotationCollector::getClassMethodAnnotation($proceedingJoinPoint->className, $proceedingJoinPoint->methodName)[Semaphore::class] ?? null;
        if (is_null($annotation)) {
            throw new AnnotationException("Annotation Semaphore couldn't be collected successfully.");
        }

        $args = $proceedingJoinPoint->arguments['keys'];
        $key = $this->key($annotation->key, $args);
        $tokens = $this->tokens($annotation->tokens, (int) ($args[self::ARG_TOKENS] ?? 1));
        $acquire = $this->acquire($annotation->acquire, (int) ($args[self::ARG_ACQUIRE] ?? 1));
        $timeout = $this->timeout($annotation->timeout, (float) ($args[self::ARG_TIMEOUT] ?? -1));
        $key = $key . $tokens;

        $semaphore = SemaphoreManager::getSema($key, $tokens);
        $shouldRelease = true;
        try {
            $semaphore->acquire($acquire, $timeout);
            return $proceedingJoinPoint->process();
        } catch (SemaphoreException $e) {
            $shouldRelease = false;
            throw $e;
        } finally {
            if ($shouldRelease) {
                $semaphore->release($acquire);
            }
            unset($semaphore);
            SemaphoreManager::remove($key);
        }
    }

    private function key(string $key, array $args): string
    {
        if ($value = $key) {
            preg_match_all('/#\{[\w.]+}/', $value, $matches);
            $matches = $matches[0];
            if ($matches) {
                foreach ($matches as $search) {
                    $k = str_replace(['#{', '}'], '', $search);
                    $value = Str::replaceFirst($search, (string) data_get($args, $k), $value);
                }
            }
            return $value;
        }
        if (($key = $args[self::ARG_KEY] ?? null) && is_string($key)) {
            return $key;
        }
        if ($key = Context::key()) {
            return $key;
        }

        throw new RuntimeException('No valid annotation key argument resolved');
    }

    private function tokens(int $annoTokens, int $argTokens): int
    {
        if ($annoTokens > 1) {
            return $annoTokens;
        }
        if ($argTokens > 1) {
            return $argTokens;
        }
        if (($tokens = Context::tokens()) && $tokens > 1) {
            return $tokens;
        }

        return 1;
    }

    private function acquire(int $annoAcquire, int $argAcquire): int
    {
        if ($annoAcquire > 1) {
            return $annoAcquire;
        }
        if ($argAcquire > 1) {
            return $argAcquire;
        }
        if (($acquire = Context::acquire()) && $acquire > 1) {
            return $acquire;
        }

        return 1;
    }

    private function timeout(float $annoTimeout, float $argTimeout): float
    {
        if ($annoTimeout > 0) {
            return $annoTimeout;
        }
        if ($argTimeout > 0) {
            return $argTimeout;
        }
        if (($timeout = Context::timeout()) && $timeout > 0) {
            return $timeout;
        }

        return -1;
    }
}
