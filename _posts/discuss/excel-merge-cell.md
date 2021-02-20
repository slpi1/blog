---
title: Excel单元格自动合并的实现方案
date: 2018-09-25 10:16
tags:
- PHP
---

# Index
 - [背景](#背景)
 - [思路](#思路)
    - [找出合并的隐含条件](#找出合并的隐含条件)
    - [可合并区域的寻找](#可合并区域的寻找)
    - [可合并区域的影响](#可合并区域的影响)
    - [结束条件](#结束条件)
    - [其他问题](#其他问题)
        - [三角形问题](#三角形问题)
        - [起始点的合并顺序](#起始点的合并顺序)
        - [我们如何引入自定义条件](#我们如何引入自定义条件)
 - [点睛之笔-自定义条件](#点睛之笔-自定义条件)
    - [停止行与停止列](#停止行与停止列)
    - [继承](#继承)
    - [合并优先序](#合并优先序)
 - [实现](#实现)
    - [基本对象](#基本对象)
        - [单元格对象](#单元格对象)
        - [区域对象](#区域对象)
        - [数据源对象](#数据源对象)
        - [搜索执行对象](#搜索执行对象)
        - [停止规则对象](#停止规则对象)
    - [执行过程](#执行过程)
        - [合并的初始化与进行](#合并的初始化与进行)
        - [停止行规则](#停止行规则)

# 背景
以前在开发有格数据驾驶舱的时候，由于需要展示比较多的表格，而且表格有合并的情况，每个表格的合并规则还不一致。当时需要同时支持导出 `Excel` 文件的合并，以及返回到接口的数据，供前端展示时合并，这两种情况。通过分析之后，计划通过两种方案来实现：
 - `Excel` 模板。模板包含要合并的情况，导出时仅填充数据。
 - 动态合并规则。按要求，自动对数据项进行合并。

通过模板的方式来解决这个问题，需要面以下下困难：
 - 如果合并的情况比较复杂，比如前十行与后十行的合并情况不一致，更进一步，如果这个“十”是变化的，那么模板就无能为力了。
 - 返回给前端接口的表格数据，无法共模板这一方案，需要单独想办法解决

由于上述两个原因，决定采用方案二，动态合并规则来实现。总的概括一下我们要解决的问题：

```
任意给定一组二维数据，根据自定义的一些规则，来对这组数据进行合并，并列出所有合并区域的起点与终点。
```

# 思路

如何找出需要合并的区域呢？先假设一个无规则的情况；如果有一个表格，你可以自由的对表格数据进行合并，要如何实现？

## 找出合并的隐含条件
在无规则情况下，其实默认单元格满足下列两个条件，就可以进行合并：

 - 两个单元格的值相等
 - 两个单元格的位置相邻

## 可合并区域的寻找
那么，合并区域的寻找，可以通过以下过程来展开：

- 确定起点：确定数据的起点，假设为 `O(0,0)`，标记点 `O` 为合并的起点
- 横向检查：检查 `O` 右侧的点 `N(0,1)`，如果点 `O` 与点 `N` 的值相等，那么表示可以合并，继续检查 `N` 右侧的点 `N1`，直到 `Nx` 的值不等于 `O` 的值，至此，横向检查完毕
- 纵向检查：检查 `O` 下方的点 `H(1,0)`, 如果点 `O` 与点 `H` 的值相等，那么从 `H` 点开始，启动第二轮横向检查。如果不想等，那么合并结束，合并区域为 `O~Nx-1` 。 `Nx-1` 表示最后一轮横向检查的终点。

这个过程是一次合并区域检查的过程，每执行一个这样的过程，就会得到一个合并区域，记作 `Range[O,Nx-1]`，如果点 `O` 与点 `Nx-1` 相等，说明该合并区域就是一个点，可以舍弃掉。

## 可合并区域的影响
每寻找到一个合并区域 `Range[O,Nx-1]` ,我们就消除了一个起点，同时，得到了两个新的起点。由于合并区域是一个矩形，矩形有四个点，其中一个是我们选择的起始点，还剩下三个点，由于对角线上的点比较特殊，我们先抛开不谈，还剩下起始点相邻的两个点，这两个点，就是下一次合并的起始点。
假设合并区域的终点为 `Nx-1(m,n)`，那么，由点 `O` 分裂出两个新的起始点

```
O(0,0) -> O1(0,n), O2(m,0)
```

> 由于合并起始点需要进行一个单位的偏移，对角点会偏移成三个点。但这三个点都有可能被相邻两个点的区域所包含，也有可能不会包含。将对角点考虑进来讨论的话，会大大的增加问题的复杂性，但并不会对结果有积极的意义，弊大于利，所以讨论与编码时，都将这个点忽略

接下来，我们继续以 `O1/O2` 为起始点，分别进行可合并区域的寻找，就能又找到两个可合并区域，以及，分裂成四个新的起始点。

## 结束条件
根据上述分析，我们会发现，随着可合并区域不断被找出，起始点不断被分裂成更多的起始点。那么这个循环会一直持续下去吗？并不是，当分裂到数据的边界的时候，一个起始点就只会分裂成一个起始点，这时，起始点的规模就会开始收缩。那么，“数据的边界”，包含哪些情况呢？

 - 数据的范围达到给定范围的极限，就是，数据右边或下边没有更多数据了。
 - 数据的范围，触及到某个已找到的可合并区域的边界。显然，由此分裂出的开始点，已经被“寻找过”，并不会产生新的可合并区域。

当所有的起始点，都触碰到数据的边界的时候，查找，就结束了，已找到的可合并区域，就是给定数据中，所有的可合并区域。

## 其他问题
我们按照上述思路，已经归纳出了方案的基本雏形，只不过在实际使用中，可能并不会达到我们想要的结果，因为这当中忽略了几个比较重要的问题。

### 三角形问题
如果数据中有一个三角形区域 `O(0,0) -> A(0,10) -> B(10, 0)` 其中所有的单元格的值都相等，按照上述思路，最终寻找的可合并区域是 `Range[O(0,0), B(10,0)]`，因为我们在可合并区域的寻找过程中，遵循的是“横向合并优先”，如果我们遵循“纵向合并优先”的话，最终需要的可合并区域是 `Range[O(0,0), A(0, 10)]`，然而实际当中，也许这两种情况，都不是我们希望的结果，比如，若我希望合并区域有最大的面积，那么，最终的可合并区域应该是 `Range[O(0,0), M(5,5)]`。究竟应该如何取舍，实际上取决于“我们的要求”。

### 起始点的合并顺序
由于起始点是会逐渐分裂增加的，那么，依据什么来决定，哪个起始点优先进入合并队列呢？考虑一种极端情况，第一个可合并区域将整个数据分成了两个部分：可合并区域的数据都是1，剩下部分的数据，都是2。如果称可合并区域为第二象限，那么以右侧的点开始，得到的合并区域是第一象限与第四象限的组合；如果以下侧的点，开始，得到的合并区域是第三象限与第四象限的组合。所以，起始点的选取顺序，也会导致合并区域结果的差异。

### 我们如何引入自定义条件
到此为止，我们上述的讨论，都不涉及到自定义条件的问题，而这本身就是需求之一。


# 点睛之笔-自定义条件

如果讨论至于上述的思路，那么这一方案实际上并无太大用处，因为由于最后三个问题的存在，导致最终合并的结果，很有可能并不是我们想要的。其中，第三个问题，它既是一个问题，又是一个需求，那么，考虑在解决该问题的同时，附带解决其余两个问题。我们将上文的思路，归纳成两个过程：

- 寻找合并区域
- 循环起始点

从顺序上看，`寻找合并区域` 先于 `循环起始点` ，从因果关系上看，`寻找合并区域` 会导致 `循环起始点` 的主体 `起始点` 起始点的变化。所以我们先考虑从 `寻找合并区域` 这一过程中，引入自定义条件。 `寻找合并区域` 分为两个主要过程，`横向检查` 与 `纵向检查`，我们引入的条件，应该是能影响这两个过程的结束位置。

对于上述 `三角形问题` ,如果我们要求可合并区域的面积最大，可以转化为这样的一组条件：
 - 横向合并到 `5` 为止
 - 纵向合并到 `5` 为止

这里的两个条件，就是我们的自定义条件，我们把这类条件，归纳为 `停止行/停止列`

## 停止行与停止列
停止行与停止列，是自定义条件的核心。他规定合并区域的寻找，在遇到哪些行与列的时候，就停止。因此，我们只需要在寻找合并区域的逻辑当中，引入对停止行与停止列的检查即可。需要留意的是，停止行与停止列的位置可能是变化的，我们在编码时可能需要考虑到这一点。

## 继承
停止行与停止列可能需要被继承，他所代表的含义是：表格前面部分的停止规则，极有可能对后面的数据生效，但是根据后面的数据以及规则，可能无法计算出合适的停止规则，这时，直接将前面的停止规则继承过来即可。

## 合并优先序
我们在讨论 `三角形问题` 时，提到过 `横向合并优先/纵向合并优先` 的概念，这是指在寻找合并区域时遇到的顺序问题。在循环起始点时，也存在这么一个问题：即应该以左侧的点开始新一轮的合并区域寻找，还是应该以下侧的点，开始新一轮的合并区域寻找。这是两个合并优先序的问题。

先来看一看起始点的分裂情况，假设有以下分裂过程：

```
O(0,0) -> [N(2, 0),H(0,2)]

N(2, 0) -> [N1(5, 0), H1(2,3)]; H(0, 2) -> [N2(1, 2), H2(0, 4)]
```
起始点出现的顺序是

```
N - H - N1 - H1 - N2 - H2
```

分布大概如下表所示

|0 O(0,0)|1|2 N(2, 0)|3|4|5 N1(5,0)|
|---|---|---|---|---|---|
|1|---|---|---|---|---|
|2 H(0,2)|N2(1,2)|---|---|---|---|
|3|---|H1(2,3)|---|---|---|
|4 H2(0,4)|---|---|---|---|---|
|5|---|---|---|---|---|

如果以开始点出现的顺序进行合并，通过相对位置可知，点 `H1` 可能会被包含在 `N2` 开始的可合并区域中。但是按点的顺序来看，`H1` 的合并先发生，由于已合并区域会形成边界，这时 `N2` 进行合并的话，不可能再次包含点 `H1`。

通过以上示例可以看出，起始点的合并开始顺序，确实会影响最终的合并结果。因此，在每次合并完成后，都需要对已存在的点和新生成的两个点，进行一次排序，用以决定究竟哪个点该进入下一次的合并。

每个点都有一个横坐标与纵坐标，很容易想到的方法是，仅比较每个点的横坐标，越小的点，越先开始合并；或者仅比较每个点的纵坐标，越小的点，越先开始合并。但如果有两个点的某个坐标相同，又恰巧以该坐标决定合并顺序呢，很容易想到，比较应该同时结合横坐标与纵坐标，但以某一项为主；就像如果以横坐标为主，那么横坐标的值充当十位，纵坐标的值充当个位，用以区分其权重。

显然，如果横坐标的排序权重高，我们称为“横向合并优先”，那么每次分裂之后，横向的点都会进入下一个合并队列，纵向的点，进入等待队列，直到横向的数据达到数据范围的极限，此时，等待队列中的开始点，类似下列情况：

```
Nn - H1 - H2 - H3 - - - Hn
```

横向数据范围越大，`Hn` 中 `n` 的值越大，等待合并的点越多。如果纵坐标的排序权重高，我们称为“横向合并优先”，情况依然类似。

其实，只要合并不是无序的，无论是横向优先，还是纵向优先，对合并结果的影响并不是很大，尤其是在停止行规则充分时，基本可以达到相同的合并结果。不过因为横向与纵向数据范围的差异，可能导致待合并队列中点的个数差异，进一步影响点排序的效率，因此，此处可以有一个优化，来使排序的效率增加，就是以数据范围小的坐标作为合并优先序。

# 实现

基于上述思想。我们简单理一下，该如何编码来实现本功能。

## 基本对象

### 单元格对象
该对象用来表示点，他需要有一个横坐标属性，纵坐标属性，还需要能计算出左侧的点，右侧的点，以及两个点是否是同一个点。

```php
class Cell
{

    protected $x;
    protected $y;

    public function __construct($x = 0, $y = 0)
    {
        $this->x = $x;
        $this->y = $y;
    }

    public function getX()
    {
        return $this->x;
    }

    public function getY()
    {
        return $this->y;
    }

    public function nextHorizonCell()
    {
        return new Cell($this->x + 1, $this->y);
    }

    public function nextVerticalCell()
    {
        return new Cell($this->x, $this->y + 1);
    }

    public function equal(Cell $cell)
    {
        return $this->x == $cell->getX() && $this->y == $cell->getY();
    }
}
```

这个实现并没有任何对点的值的表示，为何要这样处理，我们放在后面来说。

### 区域对象
区域表示的是一个范围，他有一个开始的点，与结束的点。同时他还需要有获取分裂后的点的能力，需要有判断点是否落在区域中的能力。

```php

use Cell;

class Range
{
    public $start;
    public $end;

    public $active;

    public $maxX = 0;

    public function __construct(Cell $start, Cell $end)
    {
        $this->start = $start;
        $this->end   = $this->active   = $end;
    }

    public function haveCell(Cell $cell)
    {
        $cellX = $cell->getX();
        $cellY = $cell->getY();

        $startX = $this->start->getX();
        $startY = $this->start->getY();

        $endX = $this->end->getX();
        $endY = $this->end->getY();
        if ($cellX >= $startX && $cellX <= $endX && $cellY >= $startY && $cellY <= $endY) {
            return true;
        }
        return false;
    }

    public function isCell()
    {
        if ($this->start->getX() == $this->end->getX() && $this->start->getY() == $this->end->getY()) {
            return true;
        }
        return false;
    }

    public function horizonTouchAllow(Cell $cell)
    {
        if ($this->maxX != 0 && $cell->getX() > $this->maxX) {
            return false;
        }
        return true;
    }

    public function markMaxHorizonTouch()
    {
        $this->maxX = $this->maxX == 0 ? $this->active->getX() : min($this->maxX, $this->active->getX());
    }

    public function getValue()
    {
        return [
            [$this->start->getX(), $this->start->getY()],
            [$this->end->getX(), $this->end->getY()],
        ];
    }

    public function nextRowFirst()
    {
        $x = $this->start->getX();
        $y = $this->end->getY() + 1;
        return new Cell($x, $y);
    }

    public function nextColumnFirst()
    {
        $x = $this->end->getX() + 1;
        $y = $this->start->getY();
        return new Cell($x, $y);
    }

    public function nextMergeStartCells()
    {
        $nextRowFirst    = $this->nextRowFirst();
        $nextColumnFirst = $this->nextColumnFirst();
        return [
            $nextColumnFirst,
            $nextRowFirst,
        ];
    }

}
```

### 数据源对象
数据源对象，就是给定的初始二维数据。只不过我们在编码时，不应该把它具体化，只需要知道，这个对象需要提供哪些功能。因此，数据源对象是什么。我们并不关心，但他需要实现这个接口：

```php
use Cell;

interface RepositoryInterface
{
    // 获取点的值
    public function getValue(Cell $cell);

    // 获取数据横向范围
    public function getWidth();

    // 获取数据纵向范围
    public function getHeight();
}
```

这里来解释一下，为何单元格对象并不能获取自身的值？因为源数据有可能需要一个很大的存储空间，如果单元格能获取到值，必然需要在某处引用这一数据源，此时，`Cell` 类会对外部产生一个依赖，并且需要在实例化时主动传入该数据对象。而在执行过程中，会出现大量的点对象，由此可能导致内存占用增加，所以，将单元格的取值过程转嫁给数据源对象。

### 搜索执行对象

对给定数据源执行搜索，其中包含代码主要的逻辑部分。

### 停止规则对象

停止规则对象依赖给定数据源，用以计算出动态的停止规则。


## 执行过程

### 合并的初始化与进行

```php

public function start()
{
    // 将点 O(0,0) 放入横向合并队列
    $this->horizonMergeQueue[] = new Cell;

    // 开始检查合并
    $this->catchMergeRange();

    // 返回合并区域列表
    return $this->mergedRanges;
}

public function catchMergeRange()
{
    // 判断下一个要合并的起始点
    $startCell = $this->getNextMergeStart();

    // 如果不存在，表示合并结束
    if ($startCell) {

        // 如果点不存在于已合并的区域中
        if (!$this->merged($startCell)) {

            // 初始化待合并区域
            $range = new Range($startCell, $startCell);

            // 可合并区域的寻找 - 区域终点搜索
            $range = $this->touchRange($range);

            // 判断区域是否是一个点，如果不是，则加入已合并区域列表
            if (!$range->isCell()) {
                $this->mergedRanges[$this->getRangeId($range)] = $range;
            }

            // 起始点分裂
            list($cellHorizon, $cellVertical) = $range->nextMergeStartCells();

            // 将点$cellHorizon 加入横向合并队列
            if ($this->isExistsCell($cellHorizon)) {
                $this->horizonMergeQueue[$this->getCellHorizonIndex($cellHorizon)] = $cellHorizon;
                $this->makeExtendStopRule($cellHorizon);
            }


            // 将点$cellVertical 加入纵向合并队列
            if ($this->isExistsCell($cellVertical)) {
                $this->verticalMergeQueue[$this->getCellVerticalIndex($cellVertical)] = $cellVertical;
                $this->makeExtendStopRule($cellVertical);
            }
        }

        // 启动下一个起始点的合并
        $this->catchMergeRange();
    }
}


public function touchRange(Range $range)
{
    // 区域内 - 横向检查
    $range = $this->touchCellHorizon($range);

    // 区域内 - 纵向检查
    $range = $this->touchCellVertical($range);
    return $range;
}

/**
 * 纵向检查
 */
public function touchCellHorizon(Range $range)
{
    $nextHorizonCell = $range->active->nextHorizonCell();

    // 达到纵向数据范围的极限，检查停止
    if (!$this->isExistsCell($nextHorizonCell)) {
        $range->end = $range->active;
        return $range;
    }

    // 纵向检查通过，启动横向检查，在这里检查停止行规则
    if ($range->horizonTouchAllow($nextHorizonCell) &&
        !$this->atStopColumn($nextHorizonCell) &&
        $this->getValue($range->active) === $this->getValue($nextHorizonCell)) {
        $range->active = $nextHorizonCell;
        return $this->touchCellHorizon($range);
    } else {
        // 否则检查停止
        $range->end = $range->active;

        $range->markMaxHorizonTouch();
    }

    return $range;
}

/**
 * 横向检查
 */
public function touchCellVertical(Range $range)
{
    $nextRowFirst = $range->nextRowFirst();
    if (!$this->isExistsCell($nextRowFirst)) {
        return $range;
    }

    // 横向检查通过，继续下一轮检查，在这里检查停止行规则
    if (!$this->atStopRow($nextRowFirst) && $this->getValue($range->start) === $this->getValue($nextRowFirst)) {
        $range->active = $nextRowFirst;
        return $this->touchRange($range);
    }
    return $range;
}

/**
 * 获取下一个起始点
 */
public function getNextMergeStart()
{
    if (empty($this->horizonMergeQueue) && empty($this->verticalMergeQueue)) {
        return false;
    }

    if (($this->mergeDirect == self::HORIZON_DIRECT && !empty($this->horizonMergeQueue)) || empty($this->verticalMergeQueue)) {
        return $this->getNextMergeStartOfQueue($this->horizonMergeQueue);
    } else {
        return $this->getNextMergeStartOfQueue($this->verticalMergeQueue);
    }
}

/**
 * 去除起始点时，先进行一次排序
 */
public function getNextMergeStartOfQueue(&$queue)
{
    krsort($queue);
    return array_pop($queue);
}


// 是否是停止列
public function atStopColumn(Cell $cell)
{
    return $this->stopRule->atStopColumn($cell);
}

// 是否是停止行
public function atStopRow(Cell $cell)
{
    return $this->stopRule->atStopRow($cell);
}
```

这里只列出了主要的检查逻辑。

### 停止行规则

停止规则对象

```php
class StopRule
{
    use ExcelBaseOperate;

    private $exporter;
    protected $stopColumn = [];
    protected $stopRow    = [];

    public $extendStopRows = false;

    public $extendStopColumns = false;

    public function __construct($exporter)
    {
        $this->exporter = $exporter;

        if (isset($exporter->extendStopRows) && $exporter->extendStopRows) {
            $this->extendStopRows = true;
        }

        if (isset($exporter->extendStopColumns) && $exporter->extendStopColumns) {
            $this->extendStopColumns = true;
        }
    }

    // 传入一个点对象，用来检查是否处于停止行
    public function atStopColumn(Cell $cell)
    {
        // 如果数据源对象实现了规则，则获取数据源的规则，并解析停止结果
        if ($this->exporter instanceof WithStopRule) {
            $rule       = $this->exporter->stopColumns();
            $shouldStop = $this->parserStopColumnRule($rule, $cell);

            if ($shouldStop) {
                return true;
            }
        }

        if (in_array($cell->getX(), $this->stopColumn)) {
            return true;
        }
        return false;
    }

    // 传入一个点对象，用来检查是否处于停止列
    public function atStopRow(Cell $cell)
    {
        // 如果数据源对象实现了规则，则获取数据源的规则，并解析停止结果
        if ($this->exporter instanceof WithStopRule) {
            $rule       = $this->exporter->stopRows();
            $shouldStop = $this->parserStopRowRule($rule, $cell);

            if ($shouldStop) {
                return true;
            }
        }

        if (in_array($cell->getY(), $this->stopRow)) {
            return true;
        }
        return false;
    }

    public function addStopColumn($index)
    {
        $this->stopColumn[$index] = $index;
    }

    public function addStopRow($index)
    {
        $this->stopRow[$index] = $index;
    }

    // 按数据源规则解析停止结果
    public function parserStopColumnRule($rule, Cell $cell)
    {
        // 规则为布尔值，表示始终停止或不停止
        if (is_bool($rule)) {
            return $rule;
        }

        // 规则为数组，表示指定的停止行号或ID
        if (is_array($rule)) {
            foreach ($rule as $item) {
                if (is_numeric($item) && $cell->getX() == $item) {
                    return true;
                }

                if ($this->isColumnName($item) && $this->columnNameToIndex($item) == $cell->getX()) {
                    return true;
                }
            }
        }

        // 规则为一个可执行结构，由数据源自身计算是否需要停止
        if (is_callable($rule)) {
            return call_user_func_array($rule, [$cell->getX(), $cell->getY()]);
        }

        return false;
    }

    public function parserStopRowRule($rule, Cell $cell)
    {
        if (is_bool($rule)) {
            return $rule;
        }

        if (is_array($rule)) {
            foreach ($rule as $item) {
                if (is_numeric($item) && $cell->getY() == $item) {
                    return true;
                }
            }
        }

        if (is_callable($rule)) {
            return call_user_func_array($rule, [$cell->getY(), $cell->getX()]);
        }

        return false;
    }
}
```

在进行停止行的判定时，有这样的一部分代码：

```php
if ($this->exporter instanceof WithStopRule) {
    $rule       = $this->exporter->stopRows();
    $shouldStop = $this->parserStopRowRule($rule, $cell);

    if ($shouldStop) {
        return true;
    }
}
```

其中 `exporter` 就是一开始给定的原始数据对象，如果该对象实现了 `WithStopRule` 接口的话，表名该对象定义了自己的停止规则，通过 `stopRows/stopColumns` 方法获取到原始数据定义的停止规则 `$rule`，然后将 `$rule`和当前的点 `$cell` 传给 `parserStopRowRule` 方法，计算出在当前点 `$cell` 是否需要停止。

最终代码结构如下：

```
/
|----/Concerns/
|--------/ExtendStopColumns.php·············停止列继承
|--------/ExtendStopRows.php················停止行继承
|--------/RepositoryInterface.php···········源数据对象接口
|--------/WithStopRule.php··················停止规则定义接口
|----/Merge/
|--------/Discover.php······················搜索过程定义
|--------/StopRule.php······················停止规则解析
|----/Repositories/
|--------/ArrayRepository.php···············二维数组源数据包装对象
|--------/WorksheetRepository.php···········Excel工作表源数据包装对象
|----/Table/
|--------/Cell.php··························基础点对象
|--------/Range.php·························基础区域对象
|----/Table/
|--------/ExcelBaseOperate.php··············行与列操作方法
```


完整代码请转[gitlab.uuzu.com/songzhp/laravel-excel-merge](https://gitlab.uuzu.com/songzhp/laravel-excel-merge)