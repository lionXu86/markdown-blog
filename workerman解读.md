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
