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

namespace Hyperf\Incubator\Barrier\Aspect;

use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\Di\Aop\AbstractAspect;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\Di\Exception\AnnotationException;
use Hyperf\Di\Exception\Exception;
use Hyperf\Incubator\Barrier\Annotation\Barrier;
use Hyperf\Incubator\Barrier\BarrierManager;
use Hyperf\Incubator\Barrier\Context;
use Hyperf\Incubator\Barrier\Exception\BarrierException;
use Hyperf\Incubator\Barrier\Exception\RuntimeException;
use Hyperf\Stringable\Str;

use function Hyperf\Collection\data_get;

class BarrierAspect extends AbstractAspect
{
    public const ARG_KEY = 'barrierKey';

    public const ARG_PARTIES = 'barrierParties';

    public const ARG_TIMEOUT = 'barrierTimeout';

    public array $annotations = [
        Barrier::class,
    ];

    /**
     * @throws AnnotationException|BarrierException|Exception|\Exception
     */
    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        $annotation = $this->barrierAnnotation($proceedingJoinPoint->className, $proceedingJoinPoint->methodName);
        if (is_null($annotation->value)) {
            return $proceedingJoinPoint->process();
        }

        $barrierKey = $this->barrierKey($annotation->value, $proceedingJoinPoint);
        $parties = $this->parties($annotation->parties, (int) ($proceedingJoinPoint->arguments['keys'][self::ARG_PARTIES] ?? 0));
        $timeout = $this->timeout($annotation->timeout, (float) ($proceedingJoinPoint->arguments['keys'][self::ARG_TIMEOUT] ?? -1));

        BarrierManager::awaitForCounter($barrierKey, $parties, $timeout);

        return $proceedingJoinPoint->process();
    }

    /**
     * @throws AnnotationException
     */
    private function barrierAnnotation(string $class, string $method): Barrier
    {
        $annotation = AnnotationCollector::getClassMethodAnnotation($class, $method)[Barrier::class] ?? null;
        if (is_null($annotation)) {
            throw new AnnotationException("Annotation Barrier couldn't be collected successfully.");
        }

        return $annotation;
    }

    /**
     * @throws RuntimeException
     */
    private function parties(int $annoParties, int $argParties): int
    {
        if ($annoParties > 0) {
            return $annoParties;
        }

        if ($argParties > 0) {
            return $argParties;
        }

        if (($parties = Context::parties()) && $parties > 0) {
            return $parties;
        }

        throw new RuntimeException('No valid annotation parties argument resolved');
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

    private function barrierKey(string $annoValue, ProceedingJoinPoint $proceedingJoinPoint): string
    {
        if ($annoValue) {
            $arguments = $proceedingJoinPoint->arguments['keys'];
            if ($value = $annoValue) {
                preg_match_all('/#\{[\w.]+}/', $value, $matches);
                $matches = $matches[0];
                if ($matches) {
                    foreach ($matches as $search) {
                        $k = str_replace(['#{', '}'], '', $search);
                        $value = Str::replaceFirst($search, (string) data_get($arguments, $k), $value);
                    }
                }
            }
            return $value;
        }

        if (($value = $proceedingJoinPoint->arguments['keys'][self::ARG_KEY] ?? null) && is_string($value)) {
            return $value;
        }

        if ($value = Context::key()) {
            return $value;
        }

        throw new RuntimeException('No valid annotation value argument resolved');
    }
}
