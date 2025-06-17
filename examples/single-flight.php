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
require_once __DIR__ . '/../vendor/autoload.php';

use Hyperf\Incubator\SingleFlight\SingleFlight;
use Swoole\Coroutine;

use function Swoole\Coroutine\go;
use function Swoole\Coroutine\run;

$ret = [];
$barrierKey = uniqid();

run(static function () use (&$ret, $barrierKey) {
    for ($i = 0; $i < 10; ++$i) {
        go(static function () use (&$ret, $barrierKey, $i) {
            $ret[] = SingleFlight::do($barrierKey, static function () use ($i) {
                // ensure that other coroutines can be scheduled at the same time
                usleep(1000);
                return [Coroutine::getCid() => $i];
            });
        });
    }
});

if (count(array_unique($ret)) === 1) {
    $ret = var_export($ret, true);
    printf("%s\n只有一个协程会执行闭包逻辑，其他协程等待其结果进行复用\n", $ret);
}
