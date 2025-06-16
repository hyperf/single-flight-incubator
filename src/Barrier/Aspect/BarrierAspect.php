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
use Hyperf\Stringable\Str;

use function Hyperf\Collection\data_get;

class BarrierAspect extends AbstractAspect
{
    public array $annotations = [
        Barrier::class,
    ];

    /**
     * @throws Exception
     * @throws AnnotationException
     */
    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        $annotation = $this->barrierAnnotation($proceedingJoinPoint->className, $proceedingJoinPoint->methodName);
        if ($annotation === null) {
            throw new AnnotationException("Annotation Barrier couldn't be collected successfully.");
        }
        if (is_null($annotation->value)) {
            return $proceedingJoinPoint->process();
        }

        $barrierKey = $this->barrierKey($proceedingJoinPoint);
        BarrierManager::awaitForCounter($barrierKey, $annotation->parties, $annotation->timeout);

        return $proceedingJoinPoint->process();
    }

    private function barrierAnnotation(string $class, string $method): ?Barrier
    {
        return AnnotationCollector::getClassMethodAnnotation($class, $method)[Barrier::class] ?? null;
    }

    /**
     * Generate barrier key.
     * @throws AnnotationException
     */
    private function barrierKey(ProceedingJoinPoint $proceedingJoinPoint): string
    {
        $class = $proceedingJoinPoint->className;
        $method = $proceedingJoinPoint->methodName;
        $arguments = $proceedingJoinPoint->arguments['keys'];
        $annotation = $this->barrierAnnotation($class, $method);
        if ($annotation === null) {
            throw new AnnotationException("Annotation Barrier couldn't be collected successfully.");
        }

        if ($value = $annotation->value) {
            preg_match_all('/#\{[\w.]+}/', $value, $matches);
            $matches = $matches[0];
            if ($matches) {
                foreach ($matches as $search) {
                    $k = str_replace(['#{', '}'], '', $search);
                    $value = Str::replaceFirst($search, (string) data_get($arguments, $k), $value);
                }
            }
        } else {
            $value = implode(':', $arguments);
        }

        return $value;
    }
}
