---
title: 信息管理部PHP小组知识分享
date: 2019-10-16 18:18
---

# Index
- [编码规范](#编码规范)
    - [php编码规范](#php编码规范)
    - [编码习惯](#编码习惯)
- [环境搭建](#环境搭建)
    - [本地集成环境](#本地集成环境)
    - [虚拟机环境](#虚拟机环境)
    - [docker环境](#docker环境)
- [php框架](#php框架)
    - [laravel](#laravel)
        - [版本要求](#版本要求)
        - [源码分析系列](#源码分析系列)
        - [扩展包](#扩展包)
    - [YII](#YII)
- [最佳实践](#最佳实践)


# 编码规范

## php编码规范

目前主流的php编码规范是[PSR编码规范](https://github.com/PizzaLiu/PHP-FIG)。 个人意见是不需要严格去抠规范的细节，但是需要理解规范的意义：
>项目的目的在于：通过框架作者或者框架的代表之间讨论，以最低程度的限制，制定一个协作标准，各个框架遵循统一的编码规范，避免各家自行发展的风格阻碍了 PHP 的发展，解决这个程序设计师由来已久的困扰。

对我们的意义在于：规范让我们能编写可维护性更高的代码。

[规范概览](/standard.md)

通过给IDE编辑器安装相关插件，可以达到自动规范化代码的目的，具体教程方法可以在网上搜索一下。
 - [phpfpm](https://packagecontrol.io/packages/phpfmt)
 - [sublime安装phpfpm及配置](https://www.eertime.com/archives/86.html)

只不过，插件可以解决代码形式上的规范问题，能做的毕竟有限，何况在代码可维护性上还会遇到编码规范所不能解决的问题，我将这类问题归纳到编码习惯当中。

## 编码习惯
编码习惯是经验的总结，是一个不断补充的列表，具体内容见下面的链接。如果你有比较好的经验总结，也可提出来供大家参考。

- [编码习惯](/custom/) 持续更新中...

# 环境搭建

## 环境要求

 - `php7.2`


工欲善其事，必先利其器。为了提升开发效率，达到良好的编码体验，需要配置好一套自己熟悉的开发环境。这里推荐三种开发环境的搭建方式，分别是本地集成环境、虚拟机环境、docker环境，并就本人的使用经验来阐述各自的优缺点。


## 本地集成环境
目前可以免费使用的本地集成环境有很多，本人只用过 `Wampserver`，可以根据自己的喜好自己做选择。 **请注意尽量在官方网站下载集成环境软件，其他来源的软件可能携带病毒或者后门**。

**优点**
 - 环境搭建难度低
 - 系统资源占用低

**缺点**
 - windows版本的PHP不支持部分扩展
 - 周边服务使用不便或者干脆没有，imagick、redis、nginx等

 点评：
本地集成环境基本可以满足日常开发的需要，也是开发者本地必备的环境。但随着项目经验的累积，会遇到本地开发环境难以解决的问题。

## 虚拟机环境
先在本机上安装Vmware等虚拟机引擎，然后创建一个linux虚拟机，再在linux虚拟机上安装开发环境及周边服务。

**优点**
 - 熟悉linux系统的使用。
 - 很大程度上模拟正式环境。如果遇到正式环境上的bug无法在本地环境重现，可以再虚拟机环境上试试。
 - 周边服务安装简单。众所周知，很多软件在linux上安装就是一个命令的问题。
 - 可以装多个虚拟环境。

**缺点**
 - 环境搭建麻烦
 - 系统资源占用升高
 - 需要通过ssh工具来对环境进行管理
 - 不熟悉linux怎么使用？？ 流下的技术不足的泪水...

点评：
推荐使用。就我的使用经验来看，虚拟机更多的是对本地集成环境的补充，一般不会在开发的时候，将代码部署到虚拟机来运行，而是通过虚拟机来提供redis/es/nginx代理等服务。

## docker环境
通过docker来搭建开发环境也是一种方式。首先需要安装好docker引擎，然后需要进行镜像制作、镜像编排来完成开发环境的搭建。

**优点**
 - 比较贴合当前技术趋势
 - 拥有虚拟机环境的所有优点
 - 无需ssh工具就能体验linux的功能

**缺点**
 - 系统资源占用很高，可能比虚拟机环境的资源占用还高。
 - 前期工作量大且复杂
 - 需要学习docker相关知识。但是只需要会docker、docker-compose两个命令的使用就可以搭建

点评：
入坑吧，少年！[教程](/environment/docker/)

# php框架
我部门目前php的技术栈主要是laravel和YII，除部分遗留的系统外，新系统均使用laravel进行开发。
## laravel

### 版本要求
- `laravel 5.5`

### 源码分析系列
- [laravel应用执行流程](/laravel/progress-doc.md)
- [laravel ORM源码分析](/laravel/eloquent-article.md)
- [laravel 队列部分源码阅读](/laravel/queue.md)
- [laravel 路由模块源码分析](/laravel/route.md)
- [laravel 框架对__call魔术方法的使用](/laravel/__call.md)
- [laravel Auth源码分析](/laravel/auth.md)
- [laravel Pipeline源码分析](/laravel/pipeline.md)
- [邮件发送过滤](/laravel/email-filter.md)
- [laravel 中间件](/laravel/middleware.md)
- [laravel 文件系统](/laravel/filesystem.md)

### 扩展包
- [log](https://gitlab.uuzu.com/songzhp/log): laravel 日志记录扩展包
- [im-sdk](https://gitlab.uuzu.com/songzhp/im-sdk): 公司内部IM软件接口扩展包
- [emp-sdk](https://gitlab.uuzu.com/songzhp/emp-sdk): 公司人员中枢接口扩展包

## YII

# 最佳实践

- [PHP坑爹函数系列](/discuss/what_the_fu..function.md)
- [错误与异常的处理](/discuss/error.md)
- [如何进行代码结构的规划](/discuss/package.md)
- [Excel单元格自动合并的实现方案](/discuss/excel-merge-cell.md)
- [word操作与pdf转码](/discuss/office-to-pdf.md)

# TODO
- ~~PHP坑爹函数系列~~
- 可配置化
- ~~错误与异常的处理~~
- 接口返回定义
- 对象属性修改的注意事项
- input的作用域
- ~~如何进行代码结构的规划~~