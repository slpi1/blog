---
title: 队列部分源码阅读
---

# 队列所涉及到的服务与提供者
## Illuminate\Queue\QueueServiceProvider
队列服务由服务提供者QueueServiceProvider注册。
 - registerManager() 注册队列管理器，同时添加 Null/Sync/Database/Redis/Beanstalkd/Sqs 连接驱动
    - Null：不启动队列，生产者产生的任务被丢弃
    - Sync：同步队列，生产者产生的任务直接执行
    - Database：数据库队列驱动，生产者产生的任务放入数据库
    - Redis：Redis队列驱动，生产者产生的任务放入Redis
    - Beanstalkd：略过
    - Sqs：略过
 - registerConnection() 注册队列连接获取闭包，当需要用到队列驱动连接时，实例化连接
 - registerWorker() 注册队列消费者
 - registerListener() Listen模式注册队列消费者
 - registerFailedJobServices() 注册失败任务服务

|注册方法|对象|别名|
|----|----|---|
| QueueServiceProvider::registerManager()  | \Illuminate\Queue\QueueManager::class |queue|
| QueueServiceProvider::registerConnection()  | \Illuminate\Queue\Queue::class |queue.connection|
| QueueServiceProvider::registerWorker()  | \Illuminate\Queue\Worker::class |queue.worker|
| QueueServiceProvider::registerListener()  | \Illuminate\Queue\Listener::class |queue.listener|
| QueueServiceProvider::registerFailedJobServices()  | \Illuminate\Queue\Failed\FailedJobProviderInterface::class |queue.failer|

## Illuminate\Bus\BusServiceProvider
这个服务提供者注册了Dispatcher这个服务，可以将具体的任务派发到队列。

# 任务机制

一个可放入队列的任务类：
```php
<?php

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class Job implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
}
```
任务在队列中需要经过两个过程：一个是任务入队，就是将要执行的任务，放到队列中去的过程，所有会发生这一过程的业务、对象、调用等等，可以统称为生产者；与之对应的，将任务从队列中取出，并执行的过程，叫做任务出队，所有会发生这一过程的业务、对象、调用等等，可以统称为消费者。

## 任务入队过程
可以将一个任务通过如下方式放入队列:
```php
// 这个任务将被分发到默认队列...
Job::dispatch();

// 这个任务将被发送到「emails」队列...
Job::dispatch()->onQueue('emails');
```

### Illuminate\Foundation\Bus\Dispatchable

如果任务可以被派发，需要use该trait。这个trait给Job添加了两个静态方法`dispatch/withChain`。
1. `dispatch`

dispatch方法触发任务指派动作。当执行 `Job::dispatch()`时，会实例化一个`Illuminate\Foundation\Bus\PendingDispatch`对象`PendingDispatch`，并且将任务调用类实例化后的对象`job`当作构造函数的参数：
```php
// Illuminate\Foundation\Bus\Dispatchable::trait
public static function dispatch()
{
    // 这里的static转发到实际执行dispath的类 Job::dispatch，也就是Job类
    return new PendingDispatch(new static(...func_get_args()));
}
```

`PendingDispatch`对象接下来可以通过链式调用来指定队列相关信息 `onConnection/onQueue/allOnConnection/allOnQueue/delay/chain`，然后在析构函数中，做实际的派发动作：
```php
// Illuminate\Foundation\Bus\PendingDispatch::class
public function __destruct()
{
    // Illuminate\Contracts\Bus\Dispatcher
    // 这个服务由Illuminate\Bus\BusServiceProvider注册
    app(Dispatcher::class)->dispatch($this->job);
}
```
`PendingDispath`这个中间指派者的作用，就是引出这里从容器中解析出来的服务`Dispatcher::class`，真正的任务指派者`Dispatcher`。

2. `withChain`

withChain用于指定应该按顺序运行的队列列表。它只是一个语法糖，实际上的效果等同于：
```php
// 1. withChain的用法
Job::withChain([
    new OptimizePodcast,
    new ReleasePodcast
])->dispatch();

// 2. 等同于dispatch的用法
Job::dispatch()->chain([
    new OptimizePodcast,
    new ReleasePodcast
])
```

### Illuminate\Contracts\Queue\ShouldQueue

如果一个任务实现了该接口，就可以被放入队列。上文所提到的真正的任务指派者`Dispatcher`，它在`PendingDispath`销毁时所执行的`dispatch`方法代码如下：
```php
// Illuminate\Bus\Dispatcher::class
public function dispatch($command)
{
    // 检查任务指派者是否注入了队列服务
    // 并且当前任务需要方法队列
    if ($this->queueResolver && $this->commandShouldBeQueued($command)) {
        return $this->dispatchToQueue($command);
    }

    return $this->dispatchNow($command);
}
```
`commandShouldBeQueued`方法就是检查任务对象是否实现了ShouldQueue接口，如果是，就是执行dispatchToQueue方法，将任务放入队列之中；否则执行dispatchNow，放入队列执行栈（pipeline），进行同步执行。

需要注意的是，此时放入队列的，并不是文章中我们一直提到的任务，而是任务的一个封装Payload：
```php
// Illuminate\Queue\Queue

protected function createPayloadArray($job, $data = '')
{
    // 如果我们文中所提到的“任务”，是一个对象的话，会调用createObjectPayload方法
    // 进行一次封装，将封装后的数据Payload存入队列，如果不是一个对象的话，调用
    // createStringPayload进行一次封装，然后存入队列
    return is_object($job)
                ? $this->createObjectPayload($job)
                : $this->createStringPayload($job, $data);
}

protected function createObjectPayload($job)
{
    return [
        'displayName' => $this->getDisplayName($job),
        'job' => 'Illuminate\Queue\CallQueuedHandler@call',
        'maxTries' => $job->tries ?? null,
        'timeout' => $job->timeout ?? null,
        'timeoutAt' => $this->getJobExpiration($job),
        'data' => [
            'commandName' => get_class($job),
            'command' => serialize(clone $job),
        ],
    ];
}

protected function createStringPayload($job, $data)
{
    return [
        'displayName' => is_string($job) ? explode('@', $job)[0] : null,
        'job' => $job, 'maxTries' => null,
        'timeout' => null, 'data' => $data,
    ];
}
```

之所以要进行一次封装，是为了在消费端取出任务信息时，可以借此解析出一个QueueJob对象。然后将执行过程，交给这个QueueJob对象来完成。

### Illuminate\Bus\Queueable

这个trait定义了任务队列化相关操作。上文提到的`PendingDispath`，可以指定队列信息的方法，都是转发到任务对应的方法进行调用，Queueable就是实现了这部分的功能。这部分包括以下接口：

|方法名|描述|
|---|---|
|onConnection|指定连接名  |
|onQueue| 指定队列名 |
|allOnConnection| 指定工作链的连接名 |
|allOnQueue| 指定工作链的队列名 |
|delay| 设置延迟执行时间 |
|chain| 指定工作链 |

以及最后一个方法`dispatchNextJobInChain`。上述方法都是在任务执行前调用，设置任务相关参数。`dispatchNextJobInChain`是在任务执行期间，如果检查到任务定义了工作链，就会派发工作链上面的任务到队列中。

### Illuminate\Queue\SerializesModels

这个trait的作用是字符串化任务信息，方便将队列信息保存到数据库或Redis等存储器中，然后在队列的消费端取出任务信息，并据此重新实例化为任务对象，便于执行任务。

### Illuminate\Queue\InteractsWithQueue

这个trait赋予了任务与队列交互的功能。上文提到，存入队列中的信息，是对任务封装后的数据Payload。在消费端取出任务时，由Payload可以实例化一个【队列任务】，这个队列任务会通过InteractsWithQueue的setJob方法，注入到任务中去，**任务通过【队列任务】完成与队列交互的功能**。

## 任务出队
任务出队是建立在消费者开始工作的基础之上的。在laravel的应用中，一类消费者就是Worker，队列处理器。通过命令行`php artisan queue:work`来启动一个Worker。Worker在daemon模式下，会不断的尝试从队列中取出任务并执行，这一过程有以下执行环节：

 - 第一步：检查是否要暂停队列，是则暂停一段时间，否则经行下一步
 - 第二步：取出当前要执行的任务，并给任务设置一个超时进程，
 - 第三步：执行任务，如果当前没有任务，暂停一段时间
 - 第四步：检查是否要停止队列

遇到三种情况会停止队列：
 - Worker进程收到SIGTERM信号
 - 使用内存超过限制
 - 收到重启命令

第二步就是任务出队的过程。
```php

// 该方法表示，从连接$connection中，名称为$queue的队列中取出下一个任务。
protected function getNextJob($connection, $queue)
{
    try {
        foreach (explode(',', $queue) as $queue) {
            if (! is_null($job = $connection->pop($queue))) {
                return $job;
            }
        }
    } catch (Exception $e) {
        $this->exceptions->report($e);

        $this->stopWorkerIfLostConnection($e);
    } catch (Throwable $e) {
        $this->exceptions->report($e = new FatalThrowableError($e));

        $this->stopWorkerIfLostConnection($e);
    }
}
```
如果队列驱动使用的时Database，那么$connection指的就是Illuminate\Queue\DatabaseQueue的实例，如果队列驱动使用的时Redis，那么$connection指的就是Illuminate\Queue\RedisQueue的实例，不同实例取出的任务对象类型也不一样。

# 事件机制
在充分理解任务机制的前提下，事件机制就很好理解了。事件监听器的原理是，通过Illuminate\Events\CallQueuedListener 来作为一个特殊的“任务”，将事件绑定与监听信息保存到这个“任务”中，当事件被触发时，通过事件解析出与之对应的“任务”，然后对这个“任务”进行派发，执行这个“任务”时，再去执行事件监听器。所以，这个环节的重点其实是，事件、监听器、CallQueuedListener三者之间是如何进行关联的，也就是事件监听机制，这里不做展开。

```php
// 1. 触发一个事件
event(new Event);

// 2. 从触发事件到进入队列的过程形容如下
$evnetJob = new \Illuminate\Events\CallQueuedListener(new Event);

new PendingDispatch($evnetJob);
```

# 消息机制 Notification
消息机制的实现与事件机制类似。通过Illuminate\Notifications\SendQueuedNotifications 来作为一个特殊的“任务”，与消息相关信息进行关联，通过SendQueuedNotifications对象来完成入队与出队相关过程，然后在执行“任务”SendQueuedNotifications的时候解析出关联的notifiables和notification，然后据此执行消息相关逻辑。


# 手动入队
任务系统与事件监听，都是系统提供的两种任务与队列机制，除此之外，你还可以手动推送任务到队列，即通过Queue Facade来指派任务。
```php
// MyTask
class MyTask implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle($job, $args)
    {
        echo "MyTask";
        return true;
    }
}

// 推送至队列
Queue::push('MyTask@handle', $args, $queueName);

// MyAnotherTask
class MyAnotherTask implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle($args)
    {
        echo "MyTask";
        return true;
    }
}

// 推送至队列
Queue::push(new MyAnotherTask, $args, $queueName);
```

push的第一个参数如果是一个对象，则需要有一个handle方法作为执行方法；如果是一个字符串，则要以`class@method`的方式，指定对象要执行的方法；在5.3及以后的版本中，不可以用匿名函数作为参数。

以对象的形式push进队列和以字符串的形式push进队列，handle方法所需的参数是不一样的：通过对象的方式push时，createPayload的过程中会利用CallQueuedHandler进行包装，后面执行时借用CallQueuedHandler的call方法执行，这时对象的handle方法不需要$job参数。

通过字符串的方式，将任务推送到队列中，后面会直接执行对象的指定方法，但并没有结束，如果仅仅通过上述代码来执行的话，这个任务可能会一直执行下去，原因是缺少对执行任务后的处理：如果任务执行成功，因该从队列中删除掉；如果执行失败，也要有对应的处理措施。

我们不妨来看看通过CallQueuedHandler的机制，是如何来处理这个问题的。

无论是系统的任务机制，或是事件机制，消费端从队列中取出任务信息后，还原出一个Job对象（RedisJob/DatabaseJob），然后执行这个Job的fire方法时，都会借助CallQueuedHandler这个对象来执行任务的具体内容：
```php
// worker->process()  消费者执行一个任务
public function process($connectionName, $job, WorkerOptions $options)
{
    try {
        $this->raiseBeforeJobEvent($connectionName, $job);

        $this->markJobAsFailedIfAlreadyExceedsMaxAttempts(
            $connectionName, $job, (int) $options->maxTries
        );

        $job->fire();

        $this->raiseAfterJobEvent($connectionName, $job);
    } catch (Exception $e) {
        $this->handleJobException($connectionName, $job, $options, $e);
    } catch (Throwable $e) {
        $this->handleJobException(
            $connectionName, $job, $options, new FatalThrowableError($e)
        );
    }
}

// Job->fire()
public function fire()
{
    $payload = $this->payload();

    // 解析队列任务信息，查看上文中的createPayload方法：  $payload = ['job' => 'Illuminate\Queue\CallQueuedHandler@call']
    // 所以这里$class = Illuminate\Queue\CallQueuedHandler, $method = call
    list($class, $method) = JobName::parse($payload['job']);

    // 如果payload是通过CallQueuedHandler进行包装的，那么此时instance就是CallQueuedHandler的实例，method就是call方法
    // 如果payload是通过字符串进行包装的，那么此时的instance就是制定的任务对象，method就是制定的调用方法
    ($this->instance = $this->resolve($class))->{$method}($this, $payload['data']);
}


// CallQueuedHandler->call()
public function call(Job $job, array $data)
{
    // 预处理任务信息
    try {
        $command = $this->setJobInstanceIfNecessary(
            $job, unserialize($data['command'])
        );
    } catch (ModelNotFoundException $e) {
        return $this->handleModelNotFound($job, $e);
    }

    // 通过dispatcher同步执行任务
    $this->dispatcher->dispatchNow(
        $command, $this->resolveHandler($job, $command)
    );

    // 如果任务未失败，且未释放，确保工作链上的任务都已派发
    if (!$job->hasFailed() && !$job->isReleased()) {
        $this->ensureNextJobInChainIsDispatched($command);
    }

    // 如果任务未删除或未释放，删除任务
    if (!$job->isDeletedOrReleased()) {
        $job->delete();
    }
}
```

也就是说，如果通过字符串的方式，手动派发任务到队列，需要自己手动进行像CallQueuedHandler::call()方法中那样的收尾工作，使任务执行完毕后，清除任务存储在队列中的信息，避免任务被重新执行。这个时候，就需要将任务信息传递到被执行方法中，也就是$job参数。

# Job的存储
当任务被添加到队列，又从队列中取出时，为了保证数据的正常，会用到一些技巧。常用的数据存储驱动是Database与Redis，两者的处理上有所不同，我们逐一来讲。

## Redis
假设我们现在设置有一个名叫queue的队列，那么，在队列执行的过程中，会有下列几个key被redis用到：

- queue 任务信息默认存储的key
- queue:reserved 任务执行过程中，临时存储的key
- queue:delayed 任务执行失败，被重新发布到的key，或者延迟执行的任务被发布到的key

### 入队
任务信息被推入队列时，调用RedisQueue的push方法，将任务信息的载体payload，rpush到键名为queue的lists中：

```php
    /**
     * Push a new job onto the queue.
     *
     * @param  object|string  $job
     * @param  mixed   $data
     * @param  string  $queue
     * @return mixed
     */
    public function push($job, $data = '', $queue = null)
    {
        return $this->pushRaw($this->createPayload($job, $data), $queue);
    }

    /**
     * Push a raw payload onto the queue.
     *
     * @param  string  $payload
     * @param  string  $queue
     * @param  array   $options
     * @return mixed
     */
    public function pushRaw($payload, $queue = null, array $options = [])
    {
        $this->getConnection()->rpush($this->getQueue($queue), $payload);

        return json_decode($payload, true)['id'] ?? null;
    }
```

如果是将一个任务推入队列中延迟执行，调用的是RedisQueue的later方法，zadd到键名为queue:delayed的zset中，延迟时长作为排序的依据：

```php

    /**
     * Push a new job onto the queue after a delay.
     *
     * @param  \DateTimeInterface|\DateInterval|int  $delay
     * @param  object|string  $job
     * @param  mixed   $data
     * @param  string  $queue
     * @return mixed
     */
    public function later($delay, $job, $data = '', $queue = null)
    {
        return $this->laterRaw($delay, $this->createPayload($job, $data), $queue);
    }

    /**
     * Push a raw job onto the queue after a delay.
     *
     * @param  \DateTimeInterface|\DateInterval|int  $delay
     * @param  string  $payload
     * @param  string  $queue
     * @return mixed
     */
    protected function laterRaw($delay, $payload, $queue = null)
    {
        $this->getConnection()->zadd(
            $this->getQueue($queue).':delayed', $this->availableAt($delay), $payload
        );

        return json_decode($payload, true)['id'] ?? null;
    }
```

### 出队
出队的方法只有一个，就是RedisQueue的pop方法。

```php
    /**
     * Pop the next job off of the queue.
     *
     * @param  string  $queue
     * @return \Illuminate\Contracts\Queue\Job|null
     */
    public function pop($queue = null)
    {
        $this->migrate($prefixed = $this->getQueue($queue));

        list($job, $reserved) = $this->retrieveNextJob($prefixed);

        if ($reserved) {
            return new RedisJob(
                $this->container, $this, $job,
                $reserved, $this->connectionName, $queue ?: $this->default
            );
        }
    }

    /**
     * Migrate any delayed or expired jobs onto the primary queue.
     *
     * @param  string  $queue
     * @return void
     */
    protected function migrate($queue)
    {
        $this->migrateExpiredJobs($queue.':delayed', $queue);

        if (! is_null($this->retryAfter)) {
            $this->migrateExpiredJobs($queue.':reserved', $queue);
        }
    }

    /**
     * Migrate the delayed jobs that are ready to the regular queue.
     *
     * @param  string  $from
     * @param  string  $to
     * @return array
     */
    public function migrateExpiredJobs($from, $to)
    {
        return $this->getConnection()->eval(
            LuaScripts::migrateExpiredJobs(), 2, $from, $to, $this->currentTime()
        );
    }
```

在出队之前，先检查queue:delayed上是否有到期的任务，有的话，先将这部分任务的信息转移到queue上，如果设置有超时时间，还会检查queue:reserved上是否有到期的任务，将这部分的任务信息也转移到queue上。

接着通过retrieveNextJob方法获取下一个要执行的任务信息：从queue中取出第一个任务，将他的attempt值加一后放入到queue:reserved中。

```php
    /**
     * Retrieve the next job from the queue.
     *
     * @param  string  $queue
     * @return array
     */
    protected function retrieveNextJob($queue)
    {
        return $this->getConnection()->eval(
            LuaScripts::pop(), 2, $queue, $queue.':reserved',
            $this->availableAt($this->retryAfter)
        );
    }


// LuaScripts::pop
    /**
     * Get the Lua script for popping the next job off of the queue.
     *
     * KEYS[1] - The queue to pop jobs from, for example: queues:foo
     * KEYS[2] - The queue to place reserved jobs on, for example: queues:foo:reserved
     * ARGV[1] - The time at which the reserved job will expire
     *
     * @return string
     */
    public static function pop()
    {
        return <<<'LUA'
-- Pop the first job off of the queue...
local job = redis.call('lpop', KEYS[1])
local reserved = false

if(job ~= false) then
    -- Increment the attempt count and place job on the reserved queue...
    reserved = cjson.decode(job)
    reserved['attempts'] = reserved['attempts'] + 1
    reserved = cjson.encode(reserved)
    redis.call('zadd', KEYS[2], ARGV[1], reserved)
end

return {job, reserved}
LUA;
    }
```

在任务执行成功时，检查任务是否被删除或Release，如果没有的话，就从queue:reserved中删除任务信息；如果执行失败的话，检查是否超过最大执行次数，超过则删除任务信息，否则标记为已删除，从queue:reserved中删除任务信息，并重新发布任务到queue:delayed中。

```php

    /**
     * Delete a reserved job from the reserved queue and release it.
     *
     * @param  string  $queue
     * @param  \Illuminate\Queue\Jobs\RedisJob  $job
     * @param  int  $delay
     * @return void
     */
    public function deleteAndRelease($queue, $job, $delay)
    {
        $queue = $this->getQueue($queue);

        $this->getConnection()->eval(
            LuaScripts::release(), 2, $queue.':delayed', $queue.':reserved',
            $job->getReservedJob(), $this->availableAt($delay)
        );
    }


// LuaScripts::release()
    /**
     * Get the Lua script for releasing reserved jobs.
     *
     * KEYS[1] - The "delayed" queue we release jobs onto, for example: queues:foo:delayed
     * KEYS[2] - The queue the jobs are currently on, for example: queues:foo:reserved
     * ARGV[1] - The raw payload of the job to add to the "delayed" queue
     * ARGV[2] - The UNIX timestamp at which the job should become available
     *
     * @return string
     */
    public static function release()
    {
        return <<<'LUA'
-- Remove the job from the current queue...
redis.call('zrem', KEYS[2], ARGV[1])

-- Add the job onto the "delayed" queue...
redis.call('zadd', KEYS[1], ARGV[2], ARGV[1])

return true
LUA;
    }
```

## Database

如果队列驱动是数据库，那么这个过程就相对简单一些，只需要用一张数据表来保存任务信息，在取出任务及任务失败等复杂情况下，通过事务来保证任务执行的结果与数据的一致性。