## redis的setnx实现分布式锁，乐观锁机制

基于hyperf+redis实现了分布式锁的机制，也是乐观锁机制，和使用redis的watch命令不同，这次使用的是setnx命令。

```php
<?php
namespace App\Process;

use Hyperf\Process\AbstractProcess;
use Hyperf\Redis\Redis;
use Hyperf\Utils\Coroutine;
use Hyperf\Utils\Exception\ParallelExecutionException;
use Hyperf\Utils\Parallel;

class MyProcess extends AbstractProcess
{
    public function handle(): void
    {
        $parallel = new Parallel(3);

        $container = $this->container;

        for ($i = 2; $i--;) {
            $parallel->add(function() use ($container){
                $cid = Coroutine::id();

                $redis = $container->get(Redis::class);

                $num = 0;
                $lock = 0;
                $lockout_time = time();

                while ($num < 3) {
                    sleep(1);

                    $lockout_time = time() + 5;
                    $lock = $redis->setnx("lock.foo", $lockout_time);

                    if ($lock == 1) {
                        if ($num >= 2) {
                            echo "\n[$cid] 获得了锁,但是次数已经大于2，模拟异常，不释放锁，直接退出\n\n";

                            break;
                        }

                        $num++;
                    } else {
                        $expire_time = $redis->get("lock.foo");

                        if ($expire_time < time() && $redis->getset("lock.foo", $lockout_time) < time() ) {
                            $lock = 1;

                            $num++;
                        } else {
                            echo "[$cid] 没有获得了锁，重新尝试去获得锁\n";
                            $lock = 0;

                            continue;
                        }
                    }
                    
                    echo "\n[$cid] 第{$num}次获得了锁,开始进行耗时2秒的任务\n\n";
                    sleep(2);
                    echo "[$cid] 任务完成，释放锁，重新参与竞争锁\n";

                    if ($lockout_time  >= time()) {
                        $redis->del("lock.foo");

                        $lock = 0;
                    }
                }
            });
        }
        
        try {
            $parallel->wait();
            echo "协程全部退出\n";
        } catch (ParallelExecutionException  $e) {
            $e->getResults();
        }
      
        while(true){}
    }
}
```

运行结果：
```
[4] 没有获得了锁，重新尝试去获得锁

[3] 第1次获得了锁,开始进行耗时2秒的任务

[4] 没有获得了锁，重新尝试去获得锁
[3] 任务完成，释放锁，重新参与竞争锁

[4] 第1次获得了锁,开始进行耗时2秒的任务

[3] 没有获得了锁，重新尝试去获得锁
[4] 任务完成，释放锁，重新参与竞争锁

[3] 第2次获得了锁,开始进行耗时2秒的任务

[4] 没有获得了锁，重新尝试去获得锁
[3] 任务完成，释放锁，重新参与竞争锁

[4] 第2次获得了锁,开始进行耗时2秒的任务

[3] 没有获得了锁，重新尝试去获得锁
[3] 没有获得了锁，重新尝试去获得锁
[4] 任务完成，释放锁，重新参与竞争锁

[3] 获得了锁,但是次数已经大于2，模拟异常，不释放锁，直接退出

[4] 没有获得了锁，重新尝试去获得锁
[4] 没有获得了锁，重新尝试去获得锁
[4] 没有获得了锁，重新尝试去获得锁
[4] 没有获得了锁，重新尝试去获得锁
[4] 没有获得了锁，重新尝试去获得锁
[4] 没有获得了锁，重新尝试去获得锁

[4] 第3次获得了锁,开始进行耗时2秒的任务

[4] 任务完成，释放锁，重新参与竞争锁

[4] 获得了锁,但是次数已经大于2，模拟异常，不释放锁，直接退出

协程全部退出

```

## 总结

乐观锁的缺点就是要重复试错，不过相比悲观锁的阻塞等待导致的超时异常已经算不少优化了。

精简代码：

```
$redis = $container->get(Redis::class);

$lock = 0;
$lockout_time = time() + 5;

while (true) {
    $lockout_time = time() + 5;
    $lock = $redis->setnx("lock.foo", $lockout_time);

    if ($lock == 0) {
        $expire_time = $redis->get("lock.foo");

        if ($expire_time < time() && $redis->getset("lock.foo", $lockout_time) < time() ) {
            $lock = 1;
        } else {
            $lock = 0;
            continue;
        }
    }

    //业务逻辑操作
    do();

    if ($lockout_time  >= time()) {
        $redis->del("lock.foo");

        $lock = 0;
    }
}
```
