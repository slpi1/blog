---
title: 数组与对象
---

## 从 `json_decode` 开始
在 `PHP` 中做 `json` 相关操作的时候，经常会用到 `json_encode/json_decode` 操作，其中 `json_decode` 函数可以传入第二个参数来决定，返回类型是一个数组还是一个对象。两者有何区别呢？除了在后续调用方式上有不同之外，其实并没有太大区别。

通过上述情形可以得到一个结论：数组与对象，可以承载相同的信息。那么我们再编程的过程中，是既可以通过数组来承载信息，也是可以通过对象来承载信息的，两者可以达到相同的效果。

那么，两者之间的区别呢？这就是今天要说的主要内容了。

## 对象与数组的区别
抛开 `json_decode` 的情形，我么来说说其他情形中的数组与对象。同样的，作为一个切入点，我们以 `ThinkPHP 3.x` 与 `ThinkPHP 5.x` 中的数据层来进行说明。为行文方便，分别以 `M` 和 `Model` 进行指代。假设存在一个 `User` 用户表，有 `uid/username/password/mobile` 字段。`M` 作为数据层与数据库交互过程中，查询出的信息，是以数组来表现的：
```
[array]:
$user = [
    'uid' => 1,
    'username' => 'admin',
    'passwrod' => '...',
    'mobile' => 13888888888
];
```
而 `Model` 查询出的信息，是以对象来表现的:
```
[User]:
$user = {
    public uid = 1;
    public username = 'admin';
    public password = '...';
    public mobile = 13888888888;
}
```
然而，在实际运用中，`User` 对象不会只有简单的几个属性，还会有其他的属性以及方法等，但并不会影响用户信息的表达。所以，对象比数组要包涵更多的信息。

### 引入一个场景
我们引入一个简单的场景，叫做点名：当我们获取到一个‘用户’后，要求用户返回他的用户名。对于数组及对象的简单实现如下
```
[array]
return $user['username'];

[User]
return $user->username;
```
在这个简单的场景中，两者同样简单。现在引入一个新的场景：展现受保护的手机号，将手机号以 `138****8888` 的格式对外展示。明显这一需求需要借助函数来实现：
```
[array]
$user = [
    'uid' => 1,
    'username' => 'admin',
    'passwrod' => '...',
    'mobile' => 13888888888
];
function protectMobile($mobile)
{
    return substr($mobile,0,3) . '****' . substr($mobile, 7, 4);
}
$mobile = protectMobile($user['mobile']);

[User]
User 
{
    public uid = 1;
    public username = 'admin';
    public password = '...';
    public mobile = 13888888888;
    
    public function protectMobile()
    {
        return substr($this->mobile,0,3) . '****' . substr($this->mobile, 7, 4);
    }
}

$mobile = $user->protectMobile();
```
说一下两个的区别。在用户信息用数组来表示的情况下，需要先定义一个 `protecteMobile($mobile)` 函数来实现这一需求。在用户信息用对象表示的情况下，需要给 `User` 类定义一个 `protectMobile()` 方法来实现。用对象的形式有下列优点：
- 方法无需传参，可以直接通过对象的属性拿到。
- 通过箭头语法，有很强的逻辑联系续性。
缺点：
- 依托一个 `User` 对象，无法对独立的手机号使用。

做上述场景假设说明的目的，是为了强调：在通过对象的方式承载信息的时候，由于对象本身含有其他方法，导致信息的载体会有一定的自主性，在封闭的对象内部，即可实现**相关**的功能。当然，这一层含义也不能过度的延伸，只有在逻辑上有比较强的相关性时，才能增强代码的可读性。

### 通过对象进行传参
同样引入一个场景：需要将用户提升为`VIP`。在用户到达某一条件后可以将用户提升为`VIP`，为了实现这一需求，已经在数据表中添加了`is_vip`字段。
现在对这一需求做出如下实现：
```
case 1:
function vip($uid)
{
    $user = User::get($uid);
    $user->is_vip = true;
    return false !== $user->save();
}
return vip($uid);


case 2:
function vip(User $user)
{
    $user->is_vip = true;
    return false !== $user->save();
}

$user = User::get($uid);
return vip($user);
```
在该场景中，需要获取一个用户，给定的条件是一个 `uid`。通过直接传递 `uid` 给`vip()` 函数的话。会在函数内部做一次数据库查询。而用 `$user` 做参数的话，有下列优点：
- 数据库查询外置，可以与多个方法，共享查询结果，减少重复的数据库查询次数
- 可以对函数参数做类型限定，对不合法参数做出`error_report`，参数限定为 `User` 类型，本身带有一定的语意，增强代码的可读性。

## 总结
数组的含义更抽象，对象的含义更具体。由于对象本身比数组包含更多的信息，可以形成更多的逻辑流，在带入对象的概念之后，遇到需求可以划分更清晰、更合理的问题域，即封装的边界问题，在编写代码的时候，提升代码可读性。所以讲了这些，最终回到了 `OOP` 的概念上来，即：我们应该是面向对象编程，不应该面向数组编程