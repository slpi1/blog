<?php

class ErrorDemo
{

    // 代码是否会继续运行
    protected $continue = false;

    // 捕获到错误
    protected $catchError = false;

    // 捕获到异常
    protected $catchException = false;

    public function __construct()
    {

        error_reporting(-1);
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleShutdown']);
    }

    public function run($method)
    {
        //try {

        $this->{$method}();
        // } catch (Error $e) {

        //     $this->catchError = true;
        // } catch (Exception $e) {

        //     $this->catchException = true;
        // }

        $this->continue = true;
    }

    public function handleError($level, $message, $file = '', $line = 0, $context = [])
    {
        $this->catchError = true;
        print_r("set_error_handler:\t\n");
        print_r([
            'level'   => $level,
            'message' => $message,
            'file'    => $file,
            'line'    => $line,
            'context' => $context,
        ]);
    }
    public function handleException($e)
    {
        $this->catchException = true;
        print_r("set_exception_handler:\r\n");
        print_r($e);
    }
    public function handleShutdown()
    {
        print_r("register_shutdown_function:\r\n");
        if (!is_null($error = error_get_last())) {
            print_r($error);
        }

        print_r("\r\n");
        print_r("\r\n代码是否继续运行:" . ($this->continue ? '是' : '否'));
        print_r("\r\n是否捕获到异常:" . ($this->catchException ? '是' : '否'));
        print_r("\r\n是否捕获到错误:" . ($this->catchError ? '是' : '否'));
    }

    public function caughtError()
    {
        // 1. TypeError
        $this->needAnArray(1);

        // 2. throw error
        // throw new Error('test');
    }

    /**
     * [fatalError description]
     *
     * @method  fatalError
     * @author  雷行  songzhp@yoozoo.com  2019-10-25T11:50:41+0800
     * @return  [type]      [description]
     */
    public function fatalError()
    {
        noFunction();
    }

    /**
     * 处理运行时警告
     * level=2
     *
     * @method  warning
     * @author  雷行  songzhp@yoozoo.com  2019-10-21T14:23:02+0800
     * @return  void
     */
    public function warning()
    {
        $array = [1];

        in_array($array);
    }

    /**
     * 处理运行时通知
     *
     * @method  notice
     * @author  雷行  songzhp@yoozoo.com  2019-10-21T14:24:34+0800
     * @return  void
     */
    public function notice()
    {
        return $a;
    }

    private function needAnArray(array $array)
    {

    }

}

return new ErrorDemo;
