```
// 开启或关闭
Co::set(["hook_flags" => SWOOLE_HOOK_SLEEP]);

Co\run(function(){
    for ($i = 10; $i--;) {
        go(function() use($i) {
            sleep(1);
            echo $i . PHP_EOL;
        });
    }
});
```
异步的实现思想是将阻塞用更小的粒度去调度，方法是放到协程里操作，避免阻塞当前的线程，用同步的方式写异步代码，在需要的时候去触发调度机制。
