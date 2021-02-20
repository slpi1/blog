---
title: 文件系统
date: 2019-12-12 15:56
tag: laravel
categories:
- PHP
- Laravel
---

通过阅读 `laravel` 的 `Filesystem` 部分的代码可知，框架提供两套文件系统的操作接口：

```php
// Illuminate\Filesystem\FilesystemServiceProvider
public function register()
{
    $this->registerNativeFilesystem();

    $this->registerFlysystem();
}
```
他们之间有些差异，以及在使用的过程中，也会有稍许不同。

# NativeFilesystem

`NativeFilesystem` 是由 `registerNativeFilesystem()` 方法注册的文件系统操作接口，其对应的类是 `\Illuminate\Filesystem\Filesystem`，对应的 `Facade` 门面名称是 `File`，在容器内的别名是 `files`。

他是框架提供的一组对本地文件系统常用的操作接口，特点是简单易理解，基本就是对 `PHP` 的一些原生函数的封装。但是在官方入门文档中并不包含对这一套接口的说明，因为上述原因，这部分的接口并没做太多的逻辑业务处理，并不适合在业务流程中使用，但是框架本身有对这部分的接口使用，主要集中在命令行操作的过程中，比如：生成缓存文件、生成模板文件、操作扩展包的文件等。

# Flysystem

`Flysystem` 是框架提供的另一套文件系统操作接口，基于 `league/flysystem` 扩展，提供对本地文件系统、`FTP`、云盘等具备文件存储功能的系统的操作。也就是使用文档中的 `Storage` 。

`Storage` 经过多次封装

 - `Storage` 的操作接口由类 `Illuminate\Filesystem\FilesystemAdapter::class` 提供；
 - `Illuminate\Filesystem\FilesystemAdapter::class` 类负责与 `league/flysystem` 的统一接口 `League\Flysystem\Filesystem::class` 进行对接;
 - `League\Flysystem\Filesystem::class` 负责与存储驱动交互，完成最后的文件操作。

`league/flysystem` 默认支持 `Ftp/Local/NullAdapter` 三种存储驱动，其他的存储驱动需要通过 `composer` 安装额外的扩展，如： `sftp/aws/Azure/Memory/aliyun-oss` 等。

`laravel` 框架 `5.5` 版本默认支持 `league/flysystem` 的 `Local/Ftp/S3/Rackspace/` 三种驱动，如果需要通过其他储存，可以通过 `Storage::extend()` 方法，扩展驱动实例。

# 区别
如果在使用 `Storage` 时，通过 `Local` 本地文件驱动来操作文件，与直接通过 `File` 来操作文件，有哪些区别呢？

## 路径字符串的规范化

`File` 操作文件时，如果参数是相对路径，则是相对当前脚本执行路径。并不会对路径参数做额外的处理。
`Storage` 操作文件时，必须先配置存储的根路径，所有的参数路径都是基于根路径参数。同时，在操作文件之前，会对参数路径进行规范化。规范化包括:
 - 如果根路劲是符号连接，转化为真实路劲
 - 处理 `./..` 目录标识符
 - 转化 `window` 格式的目录分隔符
 - 处理路径参数中的空白字符：

```php
protected static function removeFunkyWhiteSpace($path) {
    // We do this check in a loop, since removing invalid unicode characters
    // can lead to new characters being created.
    while (preg_match('#\p{C}+|^\./#u', $path)) {
        $path = preg_replace('#\p{C}+|^\./#u', '', $path);
    }

    return $path;
}
```
解释一下上面出现的正则：
 - `#` 是分隔符
 - `u` 是模式修饰符，此修正符打开一个与 `perl` 不兼容的附加功能 [模式修饰符](https://www.php.net/manual/zh/reference.pcre.pattern.modifiers.php)
 - `\p{C}+` 是匹配 `Unicode` 字符中的其他字符。[Unicode字符属性](https://www.php.net/manual/zh/regexp.reference.unicode.php)

所以上述正则的含义是，将路径中的 **起头的./字符或一些不规范的unicode字符** 替换为空。

## 写入文件是否加锁
`File` 在写入文件时，默认采用不加锁的策略，`Storage` 在写入文件时，始终会先获取独占锁，然后进行文件的写入。


# 其他
`File` 与 `Storage` 在进行目录迭代时，都使用到了 `PHP标准库` 中的文件对象和目录迭代器。

 - [`DirectoryIterator`](https://www.php.net/manual/zh/class.directoryiterator.php)
 - [`SplFileInfo `](https://www.php.net/manual/zh/class.splfileinfo.php)