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
异步的实现方式就是将阻塞用更小的粒度去调度，放到线程或者协程里操作，避免阻塞当前的线程或协程（栈）
