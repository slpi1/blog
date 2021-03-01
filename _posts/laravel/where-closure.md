---
title: laravel 中 where 闭包条件使用注意事项
date: 2021-03-01 14:50
tags:
- PHP
- Laravel
---

先看两个使用示例:

```php
use App\User;

// 示例1. where 闭包条件
$this->query = User::query();
$this->query->where('id', 1)->where('name', 'slpi1');
$this->query->where(function ($query) {
    $query->orWhere('created', '2021-02-01');
    $query->orWhere('updated', '2021-03-01');
});
$sql = $this->query->toSql();
dd($sql);
// select * from `users` where `id` = ? and `name` = ? and (`created` = ? or `updated` = ?)


// 示例2. where 闭包条件
$this->query = User::query();
$this->query->where('id', 1)->where('name', 'slpi1');
$this->query->where(function ($query) {
    $this->query->orWhere('created', '2021-02-01');
    $this->query->orWhere('updated', '2021-03-01');
});
$sql = $this->query->toSql();
dd($sql);
// select * from `users` where `id` = ? and `name` = ? or `created` = ? or `updated` = ?
```

通过上述两个示例，可以看出：
 - 在 `where` 闭包条件中，引用原查询来构造查询条件，也可以正常运行
 - 在 `where` 闭包条件中，引用原查询来构造查询条件，其结果并不满足闭包 `where` 的语义，属于使用错误

这一点在使用中应当特别注意，接下来再看看形成上述区别的原因是什么。

首先定位到框架关于 `where()` 方法的代码:

```php
// Illuminate\Database\Eloquent\Builder

/**
 * Add a basic where clause to the query.
 *
 * @param  string|array|\Closure  $column
 * @param  string  $operator
 * @param  mixed  $value
 * @param  string  $boolean
 * @return $this
 */
public function where($column, $operator = null, $value = null, $boolean = 'and')
{
    if ($column instanceof Closure) {
        $column($query = $this->model->newModelQuery());

        $this->query->addNestedWhereQuery($query->getQuery(), $boolean);
    } else {
        $this->query->where(...func_get_args());
    }

    return $this;
}
```

方法内容比较简单，仅有一个判断，当参数是一个闭包时，应该执行的流程，以及参数不是闭包时，应该执行的流程。其中方法中的 `$this->query` 指的是 `Illuminate\Database\Query\Builder` 的实例。为了方便描述，对两种 `$query` 做出描述上的区别:
 - `Illuminate\Database\Eloquent\Builder` 的实例对象 `$query`，称为模型查询对象
 - `Illuminate\Database\Query\Builder` 的实例对象 `$query`, 称为数据库查询对象
 - 每个模型查询对象内部，都有一个数据库查询对象:获取该对象的方法是 `$query->getQuery()`；在对象内部的表示是 `$this->query`

对 `模型查询对象A`，当 `where()` 方法参数是一个闭包时，先以当前模型初始化一个空的 `模型查询对象B`，然后传入闭包执行。所以，在 `where` 闭包查询中的局部变量 `$query` 也就是这个空的 `模型查询对象B`。 闭包执行完毕后， `模型查询对象A` 中的 `数据库查询对象a` 以 `模型查询对象B` 中的 `数据库查询对象b` 为参数，执行方法 `addNestedWhereQuery()`。在来看看 `addNestedWhereQuery()`  方法的实现，定位到相关代码: 

```php
// Illuminate\Database\Query\Builder

/**
 * Add another query builder as a nested where to the query builder.
 *
 * @param  \Illuminate\Database\Query\Builder|static $query
 * @param  string  $boolean
 * @return $this
 */
public function addNestedWhereQuery($query, $boolean = 'and')
{
    if (count($query->wheres)) {
        $type = 'Nested';

        $this->wheres[] = compact('type', 'query', 'boolean');

        $this->addBinding($query->getBindings(), 'where');
    }

    return $this;
}
```

这段代码作用是，将两个 `数据库查询对象` 以 `Nested` 的方式，合并为一个数据库查询对象，其中作为参数的 `数据库查询对象` 会以 `Nested where` 的形式，保存到调用对象的 `wheres` 条件数组中。在后面 `Grammar` 拼接 `SQL` 语句的过程中，针对 `wheres` 条件中 `Nested` 类型的查询条件，会以括号的新式，合并为一个条件组。

自此，就大概解释清楚 `where` 闭包条件中，最终查询语句中的 `()` 是如何形成的。

如果在 `where` 闭包条件中，通过引用原 `模型查询对象A` 来续写查询条件的话，那么即便在闭包执行期间，执行了上述过程，但由于 `模型查询对象B` 并未被调用，那么他的 `数据库查询对象b` 中的 `wheres` 条件数据就是空的，在调用 `addNestedWhereQuery()` 方法时，`if` 条件判断为假，不会执行相应逻辑。所以这种情况下，其运行实质是如下代码：

```php
// 示例3
$this->query = User::query();
$this->query->where('id', 1)->where('name', 'slpi1');
//$this->query->where(function ($query) {
$this->query->orWhere('created', '2021-02-01');
$this->query->orWhere('updated', '2021-03-01');
//});
$sql = $this->query->toSql();
dd($sql);
// select * from `users` where `id` = ? and `name` = ? or `created` = ? or `updated` = ?
```