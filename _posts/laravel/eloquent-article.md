---
title: laravel ORM源码分析
date: 2019-08-02 12:50
tag: laravel
categories:
- PHP
- Laravel
---

在web应用中，与数据库的交互可以说是最常用且最重要的操作。作为当前最流行的php框架之一，laravel对数据库操作的封装，可以说是非常优秀了。在官方文档当中，数据库的使用说明文档占据了两个大章节，分别是【数据库】与【Eloquent ORM】，为什么针对同一功能，官方要出两个文档呢？是因为它重要？复杂？对此我无从猜测，不过可以从源码中窥知一二。


# 一、 Eloquent的生命周期
在laravel应用的生命周期里，数据库部分出现在第二阶段，容器启动阶段。更精确的说，是容器启动阶段的服务提供者注册/启动阶段。数据库服务的入口，是数据库的服务提供者，即`Illuminate\Database\DatabaseServiceProvider`。


DatabaseServiceProvider的注册方法如代码所示：

```
public function register()
{
    Model::clearBootedModels();

    $this->registerConnectionServices();

    $this->registerEloquentFactory();

    $this->registerQueueableEntityResolver();
}
```

其中，`registerConnectionServices()`方法注册了三个别名服务，分别是`db.factor/db/db.connection`。db用于管理数据库连接；db.factor用于创建数据库连接；而db.connection绑定了一个可用的连接对象。值得一提的是，db.connection是通过bind方法绑定闭包到容器当中，所以在注册阶段并未实例化，而是在真正 需要进行数据连接时实例化连接对象，然后替换原来的闭包。

`registerEloquentFactory()`方法注册了数据填充功能中的数据工厂，用于生成模拟数据。`registerQueueableEntityResolver()`方法注册了队列的数据库实现。

接着，在DatabaseServiceProvider的启动方法中：

```
public function boot()
{
    Model::setConnectionResolver($this->app['db']);

    Model::setEventDispatcher($this->app['events']);
}
```
分别调用了Model的两个静态方法`setConnectionResolver()/setEventDispatcher()`，加上注册方法中的`clearBootedModels()`，完成了Eloquent ORM的Model类的全局设置。

```
Model::clearBootedModels();
Model::setConnectionResolver($this->app['db']);
Model::setEventDispatcher($this->app['events']);
```


# 二、 楔子 - Eloquent ORM的使用

我们先回顾一下官方文档中，关于ORM的用法：

```
// 1. 静态调用
User::all();
User::find(1);
User::where();

// 2. 对象调用
$flight = App\Flight::find(1);
$flight->name = 'New Flight Name';
$flight->save();
$filght->delete();
```

Eloquent ORM既可以通过静态调用执行方法，也可以先获取到模型对象，然后执行方法。但他们实质是一样的。在Model中定义的静态方法如下：

```
protected static function boot()
protected static function bootTraits()
public static function clearBootedModels()
public static function on($connection = null)
public static function onWriteConnection()
public static function all($columns = ['*'])
public static function with($relations)
public static function destroy($ids)
public static function query()
public static function resolveConnection($connection = null)
public static function getConnectionResolver()
public static function setConnectionResolver(Resolver $resolver)
public static function unsetConnectionResolver()
public static function __callStatic($method, $parameters)
```

可以看到，形如`User::find(1)/User::where()`的静态调用方法，本身不在类中有定义，而是转发到__callStatic魔术方法：

```
public static function __callStatic($method, $parameters)
{
    return (new static)->$method(...$parameters);
}
```

也就是先实例化自身，然后在对象上执行调用。所以，在使用Eloquent的过程中，模型基本上都会有实例化的过程，然后再对象的基础上进行方法的调用。那么我们看看Model的构造方法中，都做了哪些动作：

```
public function __construct(array $attributes = [])
{
    $this->bootIfNotBooted();

    $this->syncOriginal();

    $this->fill($attributes);
}
```
`bootIfNotBooted()`是模型的启动方法，标记模型被启动，并且触发模型启动的前置与后置事件。在启动过程中，会查询模型使用的trait中是否包含`boot{Name}`形式的方法，有的话就执行，这个步骤可以为模型扩展一些功能，比如文档中的软删除：

> 要在模型上启动软删除，则必须在模型上使用 Illuminate\Database\Eloquent\SoftDeletes trait 并添加 deleted_at 字段到你的 $dates 属性上。

就是在启动`SoftDeletes`traits的时候，给模型添加了一组查询作用域，来新增`Restore()/WithTrashed()/WithoutTrashed()/OnlyTrashed()`四个方法，同时改写delete方法的逻辑，从而定义了软删除的相关行为。

`syncOriginal()`方法的作用在于保存原始对象数据，当更新对象的属性时，可以进行脏检查。

`fill($attributes)`就是初始化模型的属性。

在实际运用中可能会注意到，我们很少会用new的方法、通过构造函数来实例化模型对象，但在后续我们要说道的查询方法中，会有一个`装载对象`的过程，有这样的用法。为什么我们很少会new一个Model，其实原因两个方面：首先从逻辑上说，是先有一条数据库记录，然后才有基于该记录的数据模型，所以在new之前必然要有查询数据库的动作；其次是因为直接new出来的Model，它的状态有可能并不正确，需要手动进行设置，可以查阅Model的`newInstance()/newFromBuilder()`两个方法来理解“状态不正确”的含义。

# 三、 深入 - Eloquent ORM的查询过程

我们以`User::all()`的查询过程来作为本小节的开始，Model的all()方法代码如下：

```
public static function all($columns = ['*'])
{
    return (new static)->newQuery()->get(
        is_array($columns) ? $columns : func_get_args()
    );
}
```

这个查询过程，可以分成三个步骤来执行:

- `new static`: 模型实例化，得到模型对象。
- `$model->newQuery()`: 根据模型对象，获取查询构造器$query。
- `$query->get($columns)`: 根据查询构造器，取得模型数据集合。

Eloquent ORM的查询过程，就可以归纳成这三个过程:

```
[模型对象]=>[查询构造器]=>[数据集合]
```
数据集合也是模型对象的集合，即使是做`first()`查询，也是先获取到只有一个对象的数据集合，然后取出第一个对象。但数据集合中的模型对象，与第一步中的模型对象不是同一个对象，作用也不一样。第一步实例化得到的模型对象，是一个`空对象`，其作用是为了获取第二步的查询构造器，第三步中的模型对象，是经过数据库查询，获取到数据后，对数据进行封装后的对象，是一个有数据的对象，从查询数据到模型对象的过程，我称之为`装载对象`，装载对象，正是使用的上文提及的`newFromBuilder()`方法。

`newQuery()`的调用过程很长，概括如下：

```
newQuery()
-->newQueryWithoutScopes()  // 添加查询作用域
-->newModelQuery() // 添加对关系模型的加载
-->newEloquentBuilder(newBaseQueryBuilder()) // 获取查询构造器
--> return new Builder(new QueryBuilder()) // 查询构造器的再封装
```

它引出了Eloquent ORM中的一个重要概念，叫做$query，查询构造器，虽然官方文档中，有大篇幅关于Model的使用说明，但其实很多方法都会转发给$query去执行。从最后的一次调用可以看出，有两个查询构造器，分别是：

- 数据库查询构造器：Illuminate\Database\Query\Builder
- Eloquent ORM查询构造器：Illuminate\Database\Eloquent\Builder

> 备注：
> 1. 由于两个类名一致，我们约定当提到Builder时，我们指的是Illuminate\Database\Query\Builder;当提到EloquentBuilder时，我们指的是Illuminate\Database\Eloquent\Builder。
> 2. 在代码中，Builder或EloquentBuilder的实例一般用变量$query来表示

这两个查询构造器的存在，解释了本文开头提到的问题：为什么关于数据库的文档说明，会分为两个章节？因为一章是对`Illuminate\Database\Query\Builder`的说明，另一章是对`Illuminate\Database\Eloquent\Builder`的说明(直观的理解为对Model的说明)。

数据库查询构造器Builder定义了一组通用的，人性化的操作接口，来描述将要执行的SQL语句（见官方文档【数据库 - 查询构造器】一章。）在这一层提供的接口更接近SQL原生的使用方法，比如：`where/join/select/insert/delete/update`等，都很容易在数据库的体系内找到相应的操作或指令；EloquentBuilder是对Builder的再封装，EloquentBuilder在Builder的基础之上，定义了一些更复杂，但更便捷的描述接口（见官方文档【Eloquent ORM - 快速入门】一章。），比如：`first/firstOrCreate/paginator`等。

## 3.1 EloquentBuilder
EloquentBuilder是Eloquent ORM查询构造器，是比较高级的能与数据库交互的对象。一般在Model层面的与数据库交互的方法，都会转发到Model的EloquentBuilder对象上去执行，通过下列方法可以获取到一个Eloquent对象：

```
$query = User::query();
$query = User::select();
```
每个EloquentBuilder对象都会有一个Builder成员对象。

## 3.2 Builder

Builder是数据库查询构造器，在Builder层面已经可以与数据库进行交互了，如何获取到一个Builder对象呢？下面展示两种方法：

```
// 获取Builder对象
$query = DB::table('user');
$query = User::query()->getQuery();

// Builder对象与数据库交互
$query->select('name')->where('status', 1)->orderBy('id')->get();
```

Builder有三个成员对象:
 - ConnectionInterface
 - Grammar
 - Processor

**ConnectionInterface**

ConnectionInterface对象是执行SQL语句、对读写分离连接进行管理的对象，也就是数据库连接对象。是最初级的、能与数据交互的对象：
```
DB::select('select * from users where active = ?', [1]);
DB::insert('insert into users (id, name) values (?, ?)', [1, 'Dayle']);
```
> 虽然DB门面指向的是`Illuminate\Database\DatabaseManager`的实例，但是对数据库交互上的操作，都会转发到connection上去执行。

回头看本文中 [Eloquent的生命周期](#Eloquent的生命周期) 关于DatabaseServiceProvider的启动方法的描述，DatabaseServiceProvider的启动方法中执行的代码 `Model::setConnectionResolver($this->app['db']);` ，这个步骤就是为了后续获取Builder的第一个成员对象`ConnectionInterface`，数据库连接对象。前文提到过，数据库的连接并不是在服务提供者启动时进行的，是在做出查询动作时才会连接数据库：

```
// Illuminate\Database\Eloquent\Model::class
protected function newBaseQueryBuilder()
{
    // 获取数据库连接
    $connection = $this->getConnection();

    // Builder实例化时传入的三个对象，ConnectionInterface、Grammar、Processor
    return new QueryBuilder(
        $connection, $connection->getQueryGrammar(), $connection->getPostProcessor()
    );
}

public function getConnection()
{
    return static::resolveConnection($this->getConnectionName());
}

public static function resolveConnection($connection = null)
{
    // 使用通过Model::setConnectionResolver($this->app['db'])注入的resolver进行数据库的连接
    return static::$resolver->connection($connection);
}
```

**Grammar**

Grammar对象是SQL语法解析对象，我们在Builder对象中调用的方法，会以Builder属性的形式将调用参数管理起来，然后在调用SQL执行方法时，先通过Grammar对象对这些数据进行解析，解析出将要执行的SQL语句，然后交给ConnectionInterface执行，获取到数据。

**Processor**

Processor对象的作用比较简单，将查询结果数据返回给Builder，包括查询的行数据，插入后的自增ID值。

## 3.3 SELECT语句的描述

在Builder对象中，关于数据库查询语句的描述，被分成12个部分:

- aggregate: 聚合查询列描述，该部分与columns互斥
- columns: 查询列描述
- from: 查询表描述
- joins: 聚合表描述
- wheres: 查询条件描述
- groups: 分组描述
- havings: 分组条件描述
- orders: 排序条件描述
- limit: 限制条数描述
- offset: 便宜了描述
- unions: 组合查询描述
- lock: 锁描述

其中，关于wherers的描述提供了相当丰富的操作接口，在实现这部分的接口时，在查询构造器Builder中将where操作分成了以下类型： `Basic/Column/In/NotIn/NotInSub/InSub/NotNull/Null/between/Nested/Sub/NotExists/Exists/Raw`。wheres条件的组装在 `Illuminate\Database\Query\Grammars\Grammar::compileWheres()` 方法中完成，每种类型都由两个部分组成：`逻辑符号 + 条件表达式`，逻辑符号包含`and/or`。多个where条件直接连接后，通过Grammar::removeLeadingBoolean去掉头部的逻辑符号，组装成最终的条件部分。如果有 `Nested` 的wheres描述，对Nested的部分单独执行`compileWheres`后，用括号包装起来形成一个复合的 `条件表达式`。

wheresTable:

|type|boolean|condition|
|---|---|---|
|Basic|<del>and</del> (Grammar::removeLeadingBoolean)| id = 1 |
|Column|and|table1.column1 = table2.column2|
|Nested|and|(wheresTable)|

最终组合成的Sql语句就是 ` id = 1 and table1.column1 = table2.column2 and (...)`。

where用法的一些注意事项：

- where的第一个参数是数组或闭包时，条件被描述为Nested类型，也就是参数分组。
- where的第二个参数，比较符号是等于号时，可以省略。
- where的第三个参数是闭包时，表示一个子查询

## 3.4 join语句的描述

每次对Builder执行join操作时，都会新建一个JoinClause对象，在文档中关于高级 Join 语法的说明中，有非常类似于where参数分组的用法，就是由闭包导入查询条件：

```
// join高级用法
DB::table('users')
    ->join('contacts', function ($join) {
        $join->on('users.id', '=', 'contacts.user_id')->orOn(...);
    })
    ->get();

// where参数分组
DB::table('users')
    ->where('name', '=', 'John')
    ->orWhere(function ($query) {
        $query->where('votes', '>', 100)
              ->where('title', '<>', 'Admin');
    })
    ->get();

```
实际上JoinClause继承自Builder，所以上述代码中的闭包参数$join，后面也是可以链式调用where系列函数的。与Builder对象的区别在于扩展了一个on方法，on方法类似于whereColumn，条件的两边是对表字段的描述。

Builder调用join方法时传入的条件，会以`Nested`的类型添加到JoinClause对象当中，然后将JoinClause对象加入到Builder的joins部分。join结构的组装与wheres类似，会单独对JoinClause对象进行一次compileWheres，然后组装到整体SQL语句中：`"{$join->type} join {$table} {$this->compileWheres($join)}"`。

# 四、 高级 - 读写分离的实现
读写分离的问题在connection的范畴。当模型实例化Builder的时候，会先去获取一个connection，如果有配置读写分离，先获取一个writeConnection，然后获取一个readConnection，并绑定到writeConnection上去。

```
Illuminate\Database\Connectors\ConnectionFactory
public function make(array $config, $name = null)
{
    $config = $this->parseConfig($config, $name);

    if (isset($config['read'])) {
        return $this->createReadWriteConnection($config);
    }

    return $this->createSingleConnection($config);
}

protected function createReadWriteConnection(array $config)
{
    $connection = $this->createSingleConnection($this->getWriteConfig($config));

    return $connection->setReadPdo($this->createReadPdo($config));
}
```

注意此时的writeConnection与readConnection并不会真正的连接数据库，而是一个闭包，保存了获取连接的方法，当第一次需要连接数据时，执行闭包获取到连接，并将该连接替换掉闭包，后续执行SQL语句时直接使用该连接即可。在实际使用过程中，可能读写连接的使用并不能简单的按照定义而来，有时需要主动设置要使用的连接。

## 4.1 读连接的使用判定
在配置读写分离后，默认查询会使用readConnection，以下情况会使用writeConnection：

 - 对select操作指定为write：

```
// connection 级别指定
Illuminate\Database\Connection::select($query, $bindings = [], $useReadPdo = true);

// Builder 级别指定
DB::table('user')->useWritePdo()->get();

// Model 级别指定
Model::onWriteConnection()->get()

```
 - 查询时启用锁
 - 启用事务
 - 启用sticky配置且前文有写操作
 - 在队列执行时，读取SerializesModels的模型数据时

关于其判定逻辑的代码如下：

```
// Illuminate\Database\Connection::getReadPdo():
public function getReadPdo()
{
    if ($this->transactions > 0) {
        return $this->getPdo();
    }

    if ($this->getConfig('sticky') && $this->recordsModified) {
        return $this->getPdo();
    }

    if ($this->readPdo instanceof Closure) {
        return $this->readPdo = call_user_func($this->readPdo);
    }

    return $this->readPdo ?: $this->getPdo();
}
```

# 五、 进阶 - 关系模型

关于关系模型的定义，其操作接口全部定义在Illuminate\Database\Eloquent\Concerns\HasRelationships::trait中。每个关系定义方法，都是对一个关系对象的定义。

## 5.1 关系对象
关系对象全部继承自 `Illuminate\Database\Eloquent\Relations\Relation::abstract` 虚拟类。关系对象由一个查询构造器组成，用来保存由关系定义所决定的关系查询条件，和加载关系时的额外条件。比如一对一（多）的关系定义中：

```
public function addConstraints()
{
    if (static::$constraints) {
        $this->query->where($this->foreignKey, '=', $this->getParentKey());

        $this->query->whereNotNull($this->foreignKey);
    }
}
```
每当需要获取关系数据时，都会实例化关系对象，实例化的过程中调用addConstraints方法。与此同时，在加载关系数据时，可以传入额外的查询条件：

```
$users = App\User::with(['posts' => function ($query) {
    $query->where('title', 'like', '%first%');
}])->get();
```
这些条件最终都会保存在关系对象的查询构造器中，在获取关系数据时，起到筛选作用。

在使用关系模型时，有两种模式：一种是即时加载模式，一种是预加载模式。

## 5.2 即时加载
即时加载关系对象，是基于当前模型对象来获取关系数据。当以`$user->post`的形式获取Model关系属性时，通过__get方法触发对关系模型的获取。

```
public function getAttribute($key)
{
    if (! $key) {
        return;
    }

    // 访问对象属性或存取器
    if (array_key_exists($key, $this->attributes) ||
        $this->hasGetMutator($key)) {
        return $this->getAttributeValue($key);
    }

    // 判断同名方法是否存在
    if (method_exists(self::class, $key)) {
        return;
    }

    // 获取关系对象
    return $this->getRelationValue($key);
}
```

获取关系模型并实例化，得到关系模型对象，执行关系模型对象的addConstraints方法，将模型对象，转化为关系模型对象的查询条件：

 - 已知模型对象
 - 关系定义绑定对象的模型名称
 - 关系定义外键，已知模型对象的主键，及主键的值
通过上述三个条件，可以生成关系查询，并获取到结果，这个过程是即时加载关系数据的。

即时加载在只有单个模型对象时比较适用，如果我们拥有的是一个模型集合，并且需要用到关系数据时，通过即时加载的模式，会有N+1的问题。针对每个模型去获取关系数据，都要进行一次数据库查询，这种情况下，就需要使用预加载的模式。

## 5.3 预加载
对于预加载关系的情况，Model::with('relation')标记关系为预加载，在Model::get()获取数据时，检查到对关系的预加载标记，会对关系进行实例化，这个实例化的过程，会通过Relation::noConstraints屏蔽对关系数据的直接加载，在后续过程中，由通过Model::get()获取的模型列表数据，得到模型的ID列表，关系利用这个ID列表，统一查询关系模型数据。查询完成之后匹配到对应的模型中去，其过程如下：

 - EloquentBuilder::get():
    - Builder::get() 获取到模型数据列表
    - EloquentBuilder::eagerLoadRelations(): 获取所有模型关系
        - foreach relations EloquentBuilder::eagerLoadRelation() 针对每个关系获取关系数据
    - Collection: 转化为集合

其中：eagerLoadRelation()的代码如下

```
Illuminate\Database\Eloquent\Builder::eagerLoadRelation()
protected function eagerLoadRelation(array $models, $name, Closure $constraints)
{
    // 获取关系对象，这里获取关系对象时会通过Relation::noConstraints屏蔽即时加载
    $relation = $this->getRelation($name);

    // 在这里将模型id列表注入到关系对象中，作为关系模型查询的条件
    $relation->addEagerConstraints($models);

    // 这里可以注入Model::with(['relation' => function($query){}])时定义的关系额外条件
    $constraints($relation);

    // 匹配每个关系对象数据到模型对象中去
    return $relation->match(
        $relation->initRelation($models, $name),
        $relation->getEager(), $name
    );
}
```

# 六、 总结

理解laravel的Eloquent ORM模型，可以先建立下列对象的概念：

- Model，模型对象，编码中比较容易接触与使用的对象，是框架开放给用户的最直观的操作接口;
- EloquentBuilder，Eloquent查询构造器;
- Builder，数据库查询构造器，是EloquentBuilder的组成部分;
- connection，数据库连接对象，与数据库进行交互，执行查询构造器描述的SQL语句;
- Grammar，语法解析器，将查询构造器的描述解释为规范的SQL语句;
- Processor，转发查询进程的结果数据;
- Relation，关系对象，描述两个模型之间的关系，关键是关系之间的查询条件;
- JoinClause，连接查询对象，多表join查询的实现;

上述对象的关系如图所示 ![relateion](/images/Laravel-Model.png)

当然，Eloquent ORM还有其他跟多的特性，比如数据迁移、数据填充、查询作用域、存取器等，可以留给读者自行去了解与熟悉。