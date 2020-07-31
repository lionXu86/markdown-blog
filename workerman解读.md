# workerman解读

### 1. 如何制作守护进程

代码如下
```php
    /**
     * Run as deamon mode.
     *
     * @throws Exception
     */
    protected static function daemonize()
    {
        if (!static::$daemonize || static::$_OS !== \OS_TYPE_LINUX) {
            return;
        }
        \umask(0);
        $pid = \pcntl_fork();
        if (-1 === $pid) {
            throw new Exception('Fork fail');
        } elseif ($pid > 0) {
            exit(0);
        }
        if (-1 === \posix_setsid()) {
            throw new Exception("Setsid fail");
        }
        // Fork again avoid SVR4 system regain the control of terminal.
        $pid = \pcntl_fork();
        if (-1 === $pid) {
            throw new Exception("Fork fail");
        } elseif (0 !== $pid) {
            exit(0);
        }
    }
```
workerman 使用了一个经典的制作守护进程方法,fork两次，保留孙子进程，父进程全部退出，这样会使孙子进程孤立，被操作系统的init进程接管，除非操作系统停止运行，这个孙子一直由操作系统管理和清理，避免了成为僵尸进程的可能性。

### 2.初始化操作

代码如下

```php
    /**
     * Run all worker instances.
     *
     * @return void
     */
    public static function runAll()
    {
        static::checkSapiEnv();
        static::init();
        static::lock();
        static::parseCommand();
        static::daemonize();
        static::initWorkers();
        static::installSignal();
        static::saveMasterPid();
        static::unlock();
        static::displayUI();
        static::forkWorkers();
        static::resetStd();
        static::monitorWorkers();
    }
```
> checkSapiEnv

确定进程是以php cli模式启动，判断操作系统类型，windows和类unix系统，后面fork进程时会有所不同。

> init

* 错误处理，设置set_error_handler回调，错误处理，回调函数里只是打印了错误信息到标准输出
* 确定启动文件，通过debug_backtrace函数，查找调用栈，确定进程启动文件位置，并保存到_startFile变量
* 主进程pid文件，生成主进程pid保存文件路径，并保存到pidFile
* 日志文件，生成日志保存文件路径，并保存到logFile
* 统计文件,设置全局统计开始时间，和统计文件保存路径
* 设置进程标题
* 预设置子进程集合，根据count参数，设置主进程下子进程的id集合，保存到变量_idMap
* 计时器，为主进程专用计时器，通过注册主进程对系统SIGALRM信号的处理函数，循环发送和处理，实现计时器功能，区别于事件循环处理

> lock

加个文件锁呗

> parseCommand

解析命令参数，发送系统信号，管理进程之类的操作,具体看installSignal方法中注册信号处理的回调

> daemonize

之前专门讲的守护进程生成方式，见上文

> initWorkers

基本上通过posix系统函数，获取操作系统相关的信息，设置子进程的对应信息

> installSignal

注册信号处理函数，不多说

> saveMasterPid

保存主进程的pid信息到pidFile保存的路径

> unlock

文件读写完成，解锁呗

> displayUI

格式化启动时输出的信息

>> forkWorkers

关键操作，fork子进程，后续操作和主进程断开

> resetStd

重置标准输入输出文件，将输出写到指定文件

> monitorWorkers

主进程起循环，监控子进程


### 3.更细化的解读

#### 进程管理

主进程管理子进程主要通过3个全局变量来管理，$_pidMap,$_idMap,$_workers。

在主进程中，$_pidMap和$_idMap都保存着子进程的pid,$_workers保存每个子进程详细的相关信息，但是不保存socket的fd信息，也没必要保存，因为在workerman里主进程只做管理子进程的工作，不负责关于连接的处理，所以在初始化的时候也不会去做生成socket文件的操作。每个子进程对应的worker信息在fork之后的信息如下
```
array(1) {
  ["000000002554dd9a00000000127e228e"]=>
  object(Workerman\Worker)#3 (29) {
    ["id"]=>
    int(0)
    ["name"]=>
    string(4) "none"
    ["count"]=>
    int(4)
    ["user"]=>
    string(3) "666"
    ["group"]=>
    string(0) ""
    ["reloadable"]=>
    bool(true)
    ["reusePort"]=>
    bool(true)
    ["onWorkerStart"]=>
    NULL
    ["onConnect"]=>
    NULL
    ["onMessage"]=>
    object(Closure)#2 (1) {
      ["parameter"]=>
      array(2) {
        ["$connection"]=>
        string(10) "<required>"
        ["$data"]=>
        string(10) "<required>"
      }
    }
    ["onClose"]=>
    NULL
    ["onError"]=>
    NULL
    ["onBufferFull"]=>
    NULL
    ["onBufferDrain"]=>
    NULL
    ["onWorkerStop"]=>
    NULL
    ["onWorkerReload"]=>
    NULL
    ["transport"]=>
    string(3) "tcp"
    ["connections"]=>
    array(0) {
    }
    ["protocol"]=>
    NULL
    ["_autoloadRootPath":protected]=>
    string(25) "/mnt/hgfs/workspace/666"
    ["_pauseAccept":protected]=>
    bool(true)
    ["stopping"]=>
    bool(false)
    ["_mainSocket":protected]=>
    NULL
    ["_socketName":protected]=>
    string(19) "http://0.0.0.0:2345"
    ["_localSocket":protected]=>
    NULL
    ["_context":protected]=>
    resource(31) of type (stream-context)
    ["workerId"]=>
    string(32) "000000002554dd9a00000000127e228e"
    ["socket"]=>
    string(19) "http://0.0.0.0:2345"
    ["status"]=>
    string(13) "<g> [OK] </g>"
  }
}
```
其中的_mainSocket字段为空。在主进程fork为每个子进程fork之后，由于每个进程的内存空间独立，所以在主进程和子进程中的这三个主要变量都会有所不同，主进程保存着全部信息，而子进程在fork之后则会删除其他进程的信息，关闭与自己不相干的socket监听，只保留自己监听的fd信息，循环自己的事件处理。


#### socket管理

由于主进程不做socket的管理，所以socket相关的全部工作都是子进程自己管理。监听处理方式是事件模式，有各种实现的事件处理器，生成之后会保存子进程自己的全局变量globalEvent中。

#### 总结
使用workman的好处很多，好用方便，稳定。主要说下可以再优化的地方。由于是子进程自己管理，每个子进程都是抢占式的，由操作系统来调度，所以每个子进程在进行一些阻塞式的io操作和系统调用时，比如sleep，文件读取，网络连接，容易阻塞整个进程，会让进程被操作系统调度挂起，进程内的其他连接也就无法处理，并且子进程间挂起和恢复时的上下文切换的开销也会比线程和协程的高，这也是为什么协程出现的原因。说下协程实现的好处，用非阻塞的方式调用，避免进程被挂起，充分利用操作系统分配的时间片。进程内实现协程调度算法，显式和隐式切换协程的执行权，提高效率。编码也更加方便，避免事件回调的缺点，用同步的方式写异步代码。



















