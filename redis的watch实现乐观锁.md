## 基于swoole原生+redis实现分布式锁，乐观锁

```
<?php

use Swoole\Coroutine\WaitGroup;

Co\run(function() {
    $wg = new WaitGroup();

    $wg->add(2);

    go(function() use($wg) {
        $cid = Co::getCid();

        $redis = new Redis();
        $redis->connect("192.168.1.190", 6379);

        $a = $redis->hget("test", "a");

        echo "[协程{$cid}]开始执行，读取数据为{$a}\n";

        // WATCH 命令可以被调用多次。 对键的监视从 WATCH 执行之后开始生效， 直到调用 EXEC 为止。
        $redis->watch("test");

        echo "[协程{$cid}]执行3秒耗时任务，调度器切换到别协程\n";

        Co::sleep(3);

        echo "[协程{$cid}]开启事务\n";

        $result = false;

        while($result == false) {
            $redis->multi();

            echo "[协程{$cid}]耗时任务执行完成，开始保存数据，a=100\n";
            $redis->hSet("test", "a", 100);
    
            $result = $redis->exec();
    
            if ($result) {
                echo "[协程{$cid}]乐观锁模式写入成功\n";

                $a = $redis->hget("test", "a");

                echo "[协程{$cid}]读取数据为{$a}\n";

                break;
            } else {
                echo "[协程{$cid}]乐观锁模式写入失败\n";

                $a = $redis->hget("test", "a");

                echo "[协程{$cid}]读取数据为{$a}\n";

                echo "[协程{$cid}]重新开启写入操作\n";
            }
        }

        $wg->done();
    });

    go(function() use($wg) {
        $cid = Co::getCid();

        $redis = new Redis();
        $redis->connect("192.168.1.190", 6379);

        $a = $redis->hget("test", "a");

        echo "[协程{$cid}]开始执行，读取数据为{$a}\n";

        echo "[协程{$cid}]执行1秒耗时任务，调度器切换到别协程\n";

        Co::sleep(1);

        // WATCH 命令可以被调用多次。 对键的监视从 WATCH 执行之后开始生效， 直到调用 EXEC 为止。
        $redis->watch("test");

        $result = false;

        while($result == false) {
            echo "[协程{$cid}]开启事务，没有耗时任务，保存数据a=666\n";

            $redis->multi();

            $redis->hSet("test", "a", 666);

            $result = $redis->exec();

            if ($result) {
                echo "[协程{$cid}]乐观锁模式写入成功\n";

                $a = $redis->hget("test", "a");

                echo "[协程{$cid}]读取数据为{$a}\n";
                
                break;
            } else {
                echo "[协程{$cid}]乐观锁模式写入失败\n";

                $a = $redis->hget("test", "a");

                echo "[协程{$cid}]读取数据为{$a}\n";

                echo "[协程{$cid}]重新开启写入操作\n";
            }
        }

        $wg->done();
    });

    $wg->wait();
});
```
