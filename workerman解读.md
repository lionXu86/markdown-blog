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













