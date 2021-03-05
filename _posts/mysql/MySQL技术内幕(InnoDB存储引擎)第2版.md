---
title: MySQL技术内幕(InnoDB存储引擎)第2版
date: 202103-05 10:00
---

# InnoDB体系架构

## 后台线程
### master thread
核心线程，主要负责将缓冲池中的页数据刷新到磁盘，保证数据的一致性。
- 脏页的刷新
- 合并插入缓冲/insert buffer 
- undo 页的回收

### IO thread
使用AIO（Async IO）来处理IO，提高数据库性能
- write
- read
- insert buffer
- log IO thread

### purge thread
事务提交完毕后，已使用的undo 页可能不再需要，应该被回收。减轻master thread 的负担。
### page cleanner thread
负责脏页的刷新，减轻master thread 的负担。

## 内存
### 缓冲池
在读数据过程中，先将数据页读到缓冲池；对数据的修改操作，先将缓冲池中的数据修改，再通过 checkpoint 机制来统一刷新到磁盘。提高数据库的整体新能。
缓冲池中缓冲的数据页包含：数据页，索引页，undo 页，insert buffer 页，自适应哈希索引页，锁信息，数据字典信息。
缓冲池页大小默认为16K

### lru list,free list,flush list
LRU 算法：最近最少使用算法。
midpoint 改良，新数据从midpoint 处插入，停留时间窗口机制，放入LRU头位置。

- 预读失效
- 缓冲池污染

缓冲池中并非所有的数据都通过LRU管理。

free list 空闲页列表，当LRU list 需要申请新的页空间，会查询 free list,如果有，则从free list 删除加入 LRU list；
flush list 脏页列表，脏页列表的数据页同时也存在于 LRU list

### redo 日志缓冲
重做日志缓冲在缓冲池外，会按一定的频率刷新到磁盘。
- master thread 循环每秒刷新重做日志缓冲
- 每个事务提交时刷新重做日志缓冲
- 当重做日志剩余空间不足一半

### 额外的内存池

# checkpoint
事务提交时，先写redo log，在写页。
checkpoint的目的
 - 缩短数据库的恢复时间
 - 缓冲池不够时，将脏页刷新到磁盘
 - 重做日志不可用时，刷新脏页

当 checkpoint 的进度达到 71/76/78/81% 时分别对应不同的事件，导致不同的新能下降。用户线程帮助写checkpoint/用户线程等待写checkpoint

# InnoDB 关键特性
 - 插入缓冲 insert buffer
 - 两次写
 - 自适应哈希索引
 - 异步IO
 - 刷新邻接页

## Insert buffer
写数据时，聚集索引按顺序写，有比较好的写入性能，辅助索引不一定按顺序写，会有随机读的问题，导致性不高。

写入辅助索引页数据时，先判断数据页是否在缓冲池，如果是，则直接写入缓冲池；若不在，则先放入一个 insert buffer 中，然后以一定的频率来合并 insert buffer 与辅助索引页子节点，可以将多次插入合并为一个操作，提高非聚集索引的插入性能。

条件：
 - 索引是辅助索引
 - 索引不是唯一索引

唯一索引在写入时需要判断唯一性，触发随机读，insert buffer 失去意义。

### change buffer
insert buffer/delete buffer /purge buffer

### insert buffer 的实现
insert buffer 结构是一颗B+树，存放在共享表空间中。

### merge insert buffer
insert buffer 在何时被合并到辅助索引页？
 - 辅助索引页被读到缓冲池
 - insert buffer bitmap 追踪到该辅助索引页无空间可用
 - Master thread


## 两次写
保证数据的可靠性。doublewrite 有两部分构成，一个是内存中double write buffer ，大小为2M。另一个是共享表空间中的double write,大小也是2M。

在刷脏时，先将脏页复制到 double write buffer,在通过 double write buffer 分两次，每次1M顺序写入表空间的物理磁盘，然后马上调用fsync，同步缓冲磁盘数据，在写完 double write 之后，再将double write buffer 写入表空间，此时的写入是离散的。

共享表空间的double write 是对最新写表的页数据的一个备份，可以再宕机时恢复表空间的数据。

## 自适应哈希索引
引擎会自动的对热点数据建立哈希索引，加快访问速度。只对精确查询有效。

## 异步IO

## 刷新邻接页

# 表

## 索引组织表
## 逻辑存储结构
表中所有数据都被逻辑的存放在表空间中。
 - 段
 - 区 1M (= 64 * 16K)
 - 页/块 16K

表空间存放的是数据页，索引页，插入缓冲bitmap页。
回滚，插入缓冲索引，系统事务，二次写缓冲都放在共享表空间

### 段
 - 数据段： leaf node segment
 - 索引段：Non-leaf node segment
 - 回滚段：rollback segment

### 区
任何情况下，区的大小都是1M

### 页
默认大小16K，可修改，不能再次修改，除非重建表数据。
 - 数据页：B-tree node
 - undo页：
 - 系统页：
 - 事务页：
 - 插入缓冲位图：insert buffer bitmap
 - 插入缓冲空闲页列表：insert buffer free list
 - 未压缩的二进制大对象
 - 压缩的二进制大对象

### 行
数据是按行存放的。每个页存放的行数有最大值7992

## InnoDB 行记录格式
 - compact
 - redundant

### Compact

```
变长字段长度列表（最大2字节） + Null 标志位 + 记录头信息 + {列数据} + [事务ID列 + 回滚指针列]
```

### redundant

### 行溢出数据
一般情况下，行数据存放在 B-tree node,发生行溢出时，数据页存放在未压缩的二进制大对象页中。
为了保证一个页中至少存放两条数据，每行数据有最大限制，单字段 `varchar（8092）`

### compressed/dynamic
对blob格式的数据完全溢出

### char 的行结构存储
char(N) varchar(N)中的N都是指的字符数。

## InnoDB 数据页结构

## 约束
## 视图
## 分区
 - range分区
 - list分区
 - hash分区
 - key分区

# 索引与算法

## InnoDB 存储引擎概述
- B+树索引
- 哈希索引
- 全文索引

## 数据结构与算法

## 二分查找法，二叉查找树与平衡二叉树

## B+树
### 数据插入
拆分插入，视叶节点与索引节点的空闲情况来拆分：
- 叶节点未满：插入叶节点
- 叶节点已满，父节点未满：拆分叶节点，取中间值放入父节点，叶节点从中间拆分为两个叶节点
- 叶节点已满，父节点已满：页节点一分为二，父节点一分为二，父节点的中间值继续放入上层节点

旋转
叶节点已满，兄弟节点未满：记录移到兄弟节点

### 数据删除
填充因子50%

## B+树索引
### 聚集索引
### 辅助索引