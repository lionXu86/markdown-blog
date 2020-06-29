# 构建docker开发环境镜像
* 操作系统 windows10 
* 虚拟机 vmware
* 容器 docker
* 基础镜像 centos7，以后会将alpine作为基础镜像

## php环境安装
> 以下操作都是在docker容器的centos实例下进行

### 启用EPEL和REMI软件源
执行以下指令，分别安装EPEL和Remi软件源：
```
# yum install https://dl.fedoraproject.org/pub/epel/epel-release-latest-7.noarch.rpm
# yum install http://rpms.remirepo.net/enterprise/remi-release-7.rpm
```

### 安装yum包管理工具
默认安装的yum软件包管理工具，自身的管理工具yum-utils（主要是提供了yum-config-manager，用于启用/禁用yum软件库）没有安装。
```
# yum install yum-utils
```

### 启用PHP7软件库
启用php各个版本的指令：
```
# yum-config-manager --enable remi-php71  
# yum-config-manager --enable remi-php72  
# yum-config-manager --enable remi-php73 
```

### 安装php7
先更新软件库索引：
```
# yum update
```

执行以下指令安装php：
```
# yum install php
# php -v
```
如果需要安装其他扩展，直接通过yum安装即可：
```
# yum install php php-mcrypt php-cli php-gd php-curl php-mysql php-ldap php-zip php-fileinfo
```







