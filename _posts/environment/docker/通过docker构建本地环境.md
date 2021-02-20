---
title: 通过docker构建本地环境
date: 2019-11-20 15:01
categories:
- docker
---

# 文件及目录说明

## 文件
- `docker-composer.yml`: docker容器编排文件，也是搭建本地环境的入口文件。
- `Dockerfile.php`: php环境容器
- `Dockerfile.queue`: supervisord环境容器，容器基于php容器，开启了cron，用于实现队列及定时任务。该文件构建的容器用于模拟linux的shell环境，其他项目中所需要的软件可以在此容器中安装。

## 目录
- `data`: mysql与redis数据目录
- `font`: 字体库
- `supervisor`: supervisor配置文件目录
- `vhost`： nginx站点配置目录

# 其他变量
- `docker网络名`： localhost
- `工作目录`：D:\song\www 请根据自身情况修改，用户存放项目代码
- `容器工作目录`： /app 容器中的该目录会映射到本地工作目录
- `nginx容器站点配置目录`： /etc/nginx/conf.d nginx容器中的该目录会映射到本地nginx站点配置目录
- `mysql容器数据目录`：/var/lib/mysql mysql容器中的该目录映射到本地mysql的数据目录
- `redis容器数据目录`：/data redis容器中的该目录映射到本地redis数据目录

请检查上述变量在 `docker-composer.yml` 配置文件中的映射是否正确，检查各容器的 `volumes`项。

# 使用
在上述配置正确，docker引擎正常启动的前提下，在本目录执行 `docker-compose up -d` 即可启动docker环境。
