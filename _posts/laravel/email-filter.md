# 邮件发送过滤

# Index
 - [实现思路](#实现思路)
 - [监听事件](#监听事件)
 - [过滤](#过滤)

## 实现思路
开发中无法避免有要发送邮件的情况，在开发或测试环节，一般都需要对邮件发送操作进行拦截，仅对指定的邮箱发送邮件，避免用户收到测试邮件。在 `Laravel` 中有一个非常简单的办法实现这一功能，其原理如下：


无论是通过 `Mail` 或 `Notification` 的方式来发送邮件，最后都会执行到 `Illuminate\Mail\Mailer::class` 类的 `send()` 方法:

```php
public function send($view, array $data = [], $callback = null)
{
    if ($view instanceof MailableContract) {
        return $this->sendMailable($view);
    }

    // First we need to parse the view, which could either be a string or an array
    // containing both an HTML and plain text versions of the view which should
    // be used when sending an e-mail. We will extract both of them out here.
    list($view, $plain, $raw) = $this->parseView($view);

    $data['message'] = $message = $this->createMessage();

    // Once we have retrieved the view content for the e-mail we will set the body
    // of this message using the HTML type, which will provide a simple wrapper
    // to creating view based emails that are able to receive arrays of data.
    $this->addContent($message, $view, $plain, $raw, $data);

    call_user_func($callback, $message);

    // If a global "to" address has been set, we will set that address on the mail
    // message. This is primarily useful during local development in which each
    // message should be delivered into a single mail address for inspection.
    if (isset($this->to['address'])) {
        $this->setGlobalTo($message);
    }

    // Next we will determine if the message should be sent. We give the developer
    // one final chance to stop this message and then we will send it to all of
    // its recipients. We will then fire the sent event for the sent message.
    $swiftMessage = $message->getSwiftMessage();

    if ($this->shouldSendMessage($swiftMessage)) {
        $this->sendSwiftMessage($swiftMessage);

        $this->dispatchSentEvent($message);
    }
}
```

在通过一系列的操作之后，得到了一个 `$swiftMessage` 对象，通过 `shouldSendMessage()` 方法来检查该对象是否应该发送邮件。此处会触发一个 `MessageSending` 事件，通过监听这个事件，并做出相应的返回，达到控制邮件是否发送的目的。

## 监听事件

在 `App\Providers\EventServiceProvider::class` 类中定义事件监听：

```php

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        'Illuminate\Mail\Events\MessageSending' => [
            'App\Listeners\SendMailFilter',
        ],
    ];
}
```

然后通过 `php artisan event:generate` 命令生成监听类 `App\Listeners\SendMailFilter::class`

## 过滤
在事件监听器中写入邮箱过滤的逻辑，比如通过添加白名单的方式:

```php
class SendMailFilter
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    // 定义放行的收件邮箱列表
    protected $whiteList = [
        'aaa@youzu.com',
        'bbb@youzu.com',
        'ccc@youzu.com',
    ];

    /**
     * Handle the event.
     *
     * @param  MessageSending  $event
     * @return void
     */
    public function handle(MessageSending $event)
    {
        // 如果是正式环境的非调试模式，不启用过滤
        if (App::environment('production') && !config('app.debug')) {
            return true;
        }

        // 此处的 $event->message 是Swift_Mime_SimpleMessage 类的一个实例
        // 获取收件邮箱列表
        $to = $event->message->getTo();

        // $cc = $event->message->getCc();
        // $bcc = $event->message->getBcc();

        foreach ($to as $mail => $name) {
            if (!in_array($mail, $this->whiteList)) {
                // throw new Exception
                return false;
            }
        }
        return true;
    }
}
```

需要注意的是，在 `return false` 处可以直接抛出异常，也可达到过滤效果，区别在于，通过 `return false` 的方式不会打断程序原始的执行流程。