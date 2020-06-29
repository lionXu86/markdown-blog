
# 构建docker开发环境镜像
* 操作系统 windows10 
* 虚拟机 vmware
* 容器 docker
* 基础镜像 alpine

## 虚拟机安装
这个根据机器的操作系统来安装

## php运行环境安装
> 以下操作都是在docker容器的alpine实例下进行

默认的alpine软件源比较慢，更换成国内阿里云的
```
# 配置地址文件路径 /etc/apk/repositories，修改成以下地址即可
https://mirrors.aliyun.com/alpine/v3.6/main/
https://mirrors.aliyun.com/alpine/v3.6/community/
```

### 安装php以及常用扩展
```
# apk add php php-fpm php_session php_pdo ...
# php-fpm //启动php cgi服务
```

### 安装nginx
```
# apk add nginx
```

新建应用根目录
```
# mkdir /www
```

配置nginx,支持php
```
server {
    listen      80;
    server_name localhost;
    root        /www;

    index index.php index.html;

    location / {
        if (!-e $request_filename) {
            rewrite ^(.*)$ /index.php?s=/$1 last;
        }
    }

    location ~\.php(.*)$ {
        fastcgi_pass    127.0.0.1:9000;
        fastcgi_index   index.php;
        include         fastcgi.conf;

    }
}
```

启动nginx
```
# nginx -c /etc/nginx/nginx.conf
```

### docker启动实例
```
# docker run -it -d -v /mnt/hgfs/workspace:/www -p 80:80 --name dev my-alpine:v2 sh
```


