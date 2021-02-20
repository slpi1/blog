---
title: php基础 - 文档操作
date: 2015-09-24 14:30
description: php对文档及目录的操作。
keywords: php基础 - 文档操作
tags:
- PHP
---

## 目录操作 ##
### chdir  ###
改变目录。将 PHP 的当前目录改为 directory。  
`bool chdir ( string $directory )`

### dir ###
directory 类。这是个仿冒面向对象的机制来读取一个目录。给定的 directory 被打开。一旦目录被打开，有两个属性可用。handle 属性可以用在其它目录函数例如 readdir()，rewinddir() 和 closedir() 中。path 属性被设为被打开的目录路径。有三个方法可用：read，rewind 和 close。 
```
<?php
    $d = dir("/etc/php5");
    echo "Handle: " . $d->handle . "\n";
    echo "Path: " . $d->path . "\n";
    while (false !== ($entry = $d->read())) {
       echo $entry."\n";
    }
    $d->close();
?>
```
### opendir ###
打开目录句柄，可由 `closedir()`，`readdir()` 和 `rewinddir()` 使用。同dir对象的handle属性  
`resource opendir ( string $path [, resource $context ] )`

### readdir ###
从目录句柄中读取条目，同dir对象的read方法  
`string readdir ( resource $dir_handle )`
```
<?php
    // 注意在 4.0.0-RC2 之前不存在 !== 运算符

    if ($handle = opendir('/path/to/files')) {
        echo "Directory handle: $handle\n";
        echo "Files:\n";

        /* 这是正确地遍历目录方法 */
        while (false !== ($file = readdir($handle))) {
            echo "$file\n";
        }

        /* 这是错误地遍历目录的方法 */
        while ($file = readdir($handle)) {
            echo "$file\n";
        }

        closedir($handle);
    }
?>
```
请留意上述例子中检查 readdir()/$d->read() 返回值的风格。这里明确地测试返回值是否全等于（值和类型都相同——更多信息参见比较运算符）FALSE，否则任何目录项的名称求值为 FALSE 的都会导致循环停止（例如一个目录名为“0”）

### closedir ###
关闭目录句柄,同dir对象的close方法  
`void closedir ( resource $dir_handle )`

### rewinddir ###
倒回目录句柄,同dir对象的rewind方法  
`void rewinddir ( resource $dir_handle )`

### scandir ###
列出指定路径中的文件和目录  
`array scandir ( string $directory [, int $sorting_order [, resource $context ]] )`

## 文件操作 ##
### pathinfo ###
返回文件路径的信息  
`mixed pathinfo ( string $path [, int $options = PATHINFO_DIRNAME | PATHINFO_BASENAME | PATHINFO_EXTENSION | PATHINFO_FILENAME ] )`  
pathinfo() 返回一个关联数组包含有 path 的信息。包括以下的数组单元：dirname，filename，basename 和 extension。  
> note:
文件包含多个extension时，只返回最后一个，文件没有extension是，不返回对应值
5.2以后的版本中，加入filename参数

### basename ###
返回路径中的文件名部分，第二个参数为后缀，存在时，这部分会被去掉  
`string basename ( string $path [, string $suffix ] )`

### dirname ###
Given a string containing the path of a file or directory, this function will return the parent directory's path that islevels up from the current directory.  
文件或目录所在的路径。  
`string dirname ( string $path )`

### copy ###
拷贝文件  
`bool copy ( string $source , string $dest )`

**disk_free_space('C:') 函数返回目录中的可用空间 以字节为单位。**

**disk_total_space('C:') 函数返回指定目录的磁盘总大小 以字节为单位。**

### fopen ###
打开文件或者 URL  
`resource fopen ( string $filename , string $mode [, bool $use_include_path [, resource $zcontext ]] )`  
mode 参数指定了所要求到该流的访问类型。可以是以下：fopen() 中 mode 的可能值列表
- 'r'   只读方式打开，将文件指针指向文件头。
- 'r+'  读写方式打开，将文件指针指向文件头。
- 'w'   写入方式打开，将文件指针指向文件头并将文件大小截为零。如果文件不存在则尝试创建之。
- 'w+'  读写方式打开，将文件指针指向文件头并将文件大小截为零。如果文件不存在则尝试创建之。
- 'a'   写入方式打开，将文件指针指向文件末尾。如果文件不存在则尝试创建之。
- 'a+'  读写方式打开，将文件指针指向文件末尾。如果文件不存在则尝试创建之。
- 'x'   创建并以写入方式打开，将文件指针指向文件头。如果文件已存在，则 fopen() 调用失败并返回 FALSE，并生成一条 E_WARNING 级别的错误信息。如果文件不存在则尝试创建之。这和给 底层的 open(2) 系统调用指定 O_EXCL|O_CREAT 标记是等价的。
- 'x+'  创建并以读写方式打开，其他的行为和 'x' 一样。
- 'c'   Open the file for writing only. If the file does not exist, it is created. If it exists, it is neither truncated (as opposed to 'w'), nor the call to this function fails (as is the case with 'x'). The file pointer is positioned on the beginning of the file. This may be useful if it's desired to get an advisory lock (see flock()) before attempting to modify the file, as using 'w' could truncate the file before the lock was obtained (if truncation is desired, ftruncate() can be used after the lock is requested).
- 'c+'  Open the file for reading and writing; otherwise it has the same behavior as 'c'.

>Note:为移植性考虑，强烈建议在用 fopen() 打开文件时总是使用 'b' 标记。 

### fclose ###
关闭一个已打开的文件指针  
`bool fclose ( resource $handle )`

## 文件输出 ##
### file_get_contents  ###
将整个文件读入一个字符串  
`string file_get_contents ( string $filename [, bool $use_include_path [, resource $context [, int $offset [, int $maxlen ]]]] )`  
参数 offset 指定开始的位置，参数maxlen 指定长度。如果失败，file_get_contents() 将返回 FALSE。 

### fgetc ###
从文件指针中读取字符
返回一个包含有一个字符的字符串，该字符从 handle 指向的文件中得到。碰到 EOF 则返回 FALSE。  
`string fgetc ( resource $handle )`
```
<?php
    $fp = fopen('somefile.txt', 'r');
    if (!$fp) {
        echo 'Could not open file somefile.txt';
    }
    while (false !== ($char = fgetc($fp))) {
        echo "$char\n";
    }
?>
```
### fgets ###
从文件指针中读取一行   
`string fgets ( int $handle [, int $length ] )`

>Note: length 参数从 PHP 4.2.0 起成为可选项，如果忽略，则行的长度被假定为 1024。从 PHP 4.3 开始，忽略掉 length 将继续从流中读取数据直到行结束。如果文件中的大多数行都大于 8KB，则在脚本中指定最大行的长度在利用资源上更为有效。

>Note: 从 PHP 4.3 开始本函数可以安全用于二进制文件。早期的版本则不行。

### fgetss ###
从文件指针中读取一行并过滤掉 HTML 标记，和 fgets() 相同，只除了 fgetss 尝试从读取的文本中去掉任何 HTML 和 PHP 标记。  
`string fgetss ( resource $handle [, int $length [, string $allowable_tags ]] )`

### file ###
把整个文件读入一个数组中  
`array file ( string $filename [, int $use_include_path [, resource $context ]] )`  
和 file_get_contents() 一样，只除了 file() 将文件作为一个数组返回。数组中的每个单元都是文件中相应的一行，包括换行符在内。如果失败 file() 返回 FALSE。  
如果也想在 include_path 中搜寻文件的话，可以将可选参数 use_include_path 设为 "1"。

### fread ###
读取文件（可安全用于二进制文件）  
`string fread ( int $handle , int $length )`  
fread() 从文件指针 handle 读取最多 length 个字节。该函数在读取完最多 length 个字节数，或到达 EOF 的时候，或（对于网络流）当一个包可用时，或（在打开用户空间流之后）已读取了 8192 个字节时就会停止读取文件，视乎先碰到哪种情况。
返回所读取的字符串，如果出错返回 FALSE。

### readfile ###
输出一个文件  
`int readfile ( string $filename [, bool $use_include_path [, resource $context ]] )`  
读入一个文件并写入到输出缓冲。可用于文件下载。
返回从文件中读入的字节数。如果出错返回 FALSE 并且除非是以 @readfile() 形式调用，否则会显示错误信息。

## 文档写入 ##
### file_put_contents ###
将一个字符串写入文件，和依次调用 fopen()，fwrite() 以及 fclose() 功能一样。 参数 data 可以是数组（但不能为多维数组），这就相当于 `file_put_contents($filename, join('', $array))`  
`string file_get_contents ( string $filename [, bool $use_include_path [, resource $context [, int $offset [, int $maxlen ]]]] )`

### fwrite ###
写入文件（可安全用于二进制文件）  
`fwrite()` 把 string 的内容写入 文件指针 handle 处。 如果指定了 length，当写入了 length 个字节或者写完了 string 以后，写入就会停止，视乎先碰到哪种情况。  
fwrite() 返回写入的字符数，出现错误时则返回 FALSE 。  
`int fwrite ( resource $handle , string $string [, int $length ] )`

## 文件或目录删除 ##
### rmdir  ###
删除目录。尝试删除 dirname 所指定的目录。 该目录必须是空的，而且要有相应的权限。成功时返回 TRUE， 或者在失败时返回 FALSE.  
`bool rmdir ( string $dirname )`

### unlink ###
删除文件  
`bool unlink ( string $filename )`

## 判断函数 ##
### file_exists ###
检查文件或目录是否存在  
`bool file_exists ( string $filename )`

### is_dir ###
判断给定文件名是否是一个目录  
`bool is_dir ( string $filename )`  
如果文件名存在并且为目录则返回 TRUE。如果 filename 是一个相对路径，则按照当前工作目录检查其相对路径。  

### is_file ###
判断给定文件名是否为一个正常的文件  
`bool is_file ( string $filename )`
```
<?php
    var_dump(is_file('a_file.txt')) . "\n";  //true
    var_dump(is_file('/usr/bin/')) . "\n";   //false
?>
```
### is_readable  ###
判断给定文件名是否可读.如果由 filename 指定的文件或目录存在并且可读则返回 TRUE。  
`bool is_readable ( string $filename )`

### is_writable ###
判断给定的文件名是否可写  
`bool is_writable ( string $filename )`

### is_uploaded_file  ###
判断文件是否是通过 HTTP POST 上传的  
`bool is_uploaded_file ( string $filename )`  
如果 filename 所给出的文件是通过 HTTP POST 上传的则返回 TRUE。这可以用来确保恶意的用户无法欺骗脚本去访问本不能访问的文件，例如 /etc/passwd。  
这种检查显得格外重要，如果上传的文件有可能会造成对用户或本系统的其他用户显示其内容的话。  
为了能使 is_uploaded_file() 函数正常工作，必段指定类似于 `$_FILES['userfile']['tmp_name']` 的变量，而在从客户端上传的文件名` $_FILES['userfile']['name']` 不能正常运作。

### clearstatcache ###
清除文件状态缓存  
`void clearstatcache ( void )`  
当使用 stat()，lstat() 或者任何列在受影响函数表（见下面）中的函数时，PHP 将缓存这些函数的返回信息以提供更快的性能。然而在某些情况下，你可能想清除被缓存的信息。例如如果在一个脚本中多次检查同一个文件，而该文件在此脚本执行期间有被删除或修改的危险时，你需要清除文件状态缓存。这种情况下，可以用 clearstatcache() 函数来清除被 PHP 缓存的该文件信息。  
必须注意的是，对于不存在的文件，PHP 并不会缓存其信息。所以如果调用 file_exists() 来检查不存在的文件，在该文件没有被创建之前，它都会返回 FALSE。如果该文件被创建了，就算以后被删除，它都会返回 TRUE  
受影响的函数包括 stat()，lstat()，file_exists()，is_writable()，is_readable()，is_executable()，is_file()，is_dir()，is_link()，filectime()，fileatime()，filemtime()，fileinode()，filegroup()，fileowner()，filesize()，filetype() 和 fileperms()。

## 其他信息获取 ##
- fileatime — 取得文件的上次访问时间
- filectime — 取得文件的 inode 修改时间
- filegroup — 取得文件的组
- fileinode — 取得文件的 inode
- filemtime — 取得文件修改时间
- fileowner — 取得文件的所有者
- fileperms — 取得文件的权限
- filesize — 取得文件大小
