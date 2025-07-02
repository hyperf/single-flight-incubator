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

    private const DELIMITER = 'B@#_!';

    public array $annotations = [
        Barrier::class,
    ];

    /**
     * @throws AnnotationException|BarrierException|Exception|\Exception
     */
    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        $annotation = AnnotationCollector::getClassMethodAnnotation($proceedingJoinPoint->className, $proceedingJoinPoint->methodName)[Barrier::class] ?? null;
        if (is_null($annotation)) {
            throw new AnnotationException("Annotation Barrier couldn't be collected successfully.");
        }

        $args = $proceedingJoinPoint->arguments['keys'];
        if (($key = $args[self::ARG_KEY] ?? null) && ! is_string($key)) {
            throw new RuntimeException('Barrier argument ' . self::ARG_KEY . ' must be a valid string');
        }
        if (($parties = $args[self::ARG_PARTIES] ?? null) && ! is_int($parties)) {
            throw new RuntimeException('Barrier argument ' . self::ARG_PARTIES . ' must be a valid integer');
        }
        if (($timeout = $args[self::ARG_TIMEOUT] ?? null) && ! is_float($timeout)) {
            throw new RuntimeException('Barrier argument ' . self::ARG_TIMEOUT . ' must be a valid float');
        }

        $barrierKey = $this->barrierKey($annotation->value, $args, Context::key());
        $parties = $this->parties($annotation->parties, $proceedingJoinPoint->arguments['keys'][self::ARG_PARTIES] ?? 0, Context::parties());
        $timeout = $this->timeout($annotation->timeout, $proceedingJoinPoint->arguments['keys'][self::ARG_TIMEOUT] ?? -1, Context::timeout());
        $key = $barrierKey . self::DELIMITER . $parties;

        return BarrierManager::counterCall($key, $parties, $proceedingJoinPoint->process(...), $timeout);
    }

    /**
     * @throws RuntimeException
     */
    private function parties(int $annoParties, int $argParties, int $contextParties): int
    {
        if ($annoParties > 0) {
            return $annoParties;
        }
        if ($argParties > 0) {
            return $argParties;
        }
        if ($contextParties > 0) {
            return $contextParties;
        }

        throw new RuntimeException('No valid Barrier annotation parties property resolved');
    }

    private function timeout(float $annoTimeout, float $argTimeout, float $contextTimeout): float
    {
        if ($annoTimeout > 0) {
            return $annoTimeout;
        }
        if ($argTimeout > 0) {
            return $argTimeout;
        }
        if ($contextTimeout > 0) {
            return $contextTimeout;
        }

        return -1;
    }

    private function barrierKey(string $annoValue, array $args, string $contextValue): string
    {
        if ($value = $annoValue) {
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
        if (($value = $args[self::ARG_KEY] ?? null) && is_string($value)) {
            return $value;
        }
        if ($contextValue) {
            return $contextValue;
        }

        throw new RuntimeException('No valid Barrier annotation value property resolved');
    }
}
