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

namespace Hyperf\Incubator\WorkerPool\Aspect;

use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\Di\Aop\AbstractAspect;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\Di\Exception\AnnotationException;
use Hyperf\Incubator\WorkerPool\Annotation\WorkerPool;
use Hyperf\Incubator\WorkerPool\Context;
use Hyperf\Incubator\WorkerPool\Exception\RuntimeException;
use Hyperf\Incubator\WorkerPool\Exception\WorkerPoolException;
use Hyperf\Incubator\WorkerPool\Task;
use Hyperf\Incubator\WorkerPool\WorkerPoolManager;
use Hyperf\Stringable\Str;

use function Hyperf\Collection\data_get;

class WorkerPoolAspect extends AbstractAspect
{
    public const ARG_NAME = 'workerPoolName';

    public const ARG_TIMEOUT = 'workerPoolTimeout';

    public const ARG_SYNC = 'workerPoolSync';

    public array $annotations = [
        WorkerPool::class,
    ];

    /**
     * @throws AnnotationException|WorkerPoolException
     */
    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        /** @var WorkerPool $annotation */
        $annotation = AnnotationCollector::getClassMethodAnnotation($proceedingJoinPoint->className, $proceedingJoinPoint->methodName)[WorkerPool::class] ?? null;
        if (is_null($annotation)) {
            throw new AnnotationException("Annotation WorkerPool couldn't be collected successfully.");
        }

        $args = $proceedingJoinPoint->arguments['keys'];
        $name = $this->name($annotation->name, $args, Context::name());
        $timeout = $this->timeout($annotation->timeout, (float) ($args[self::ARG_TIMEOUT] ?? -1), Context::timeout());
        $sync = $this->sync($annotation->sync, (bool) ($args[self::ARG_SYNC] ?? true), Context::sync());

        $pool = WorkerPoolManager::getPool($name);
        $task = new Task($proceedingJoinPoint->process(...), $sync);
        $ret = $pool->submitTask($task, $timeout);
        if ($sync) {
            return $ret;
        }
        return $task->waitResult();
    }

    private function name(string $annoName, array $args, string $contextName): string
    {
        if ($value = $annoName) {
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
        if (($name = $args[self::ARG_NAME] ?? null) && is_string($name)) {
            return $name;
        }
        if ($contextName) {
            return $contextName;
        }

        throw new RuntimeException('No valid WorkerPool annotation name property resolved');
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

    private function sync(bool $annoSync, bool $argSync, bool $contextSync): bool
    {
        if (! $annoSync || ! $argSync || ! $contextSync) {
            return false;
        }
        return true;
    }
}
