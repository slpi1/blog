---
title: 再谈Filesystem及Storage驱动的扩展
date: 2020-11-21 11:23
tags:
- PHP
- Laravel
---

之前写过一篇文章来讲 `laravel` 中的文件系统，提到框架中使用到两套文件系统，一套是 `NativeFilesystem`，主要用于框架内部一些用到文件管理的场景，比如：生成缓存文件、生成模板文件、操作扩展包的文件等。另外一套是 `Flysystem`基于 `league/flysystem` 扩展，提供对本地文件系统、`FTP`、云盘等具备文件存储功能的系统的操作。也就是使用文档中的 `Storage` 。

`Storage` 面向更广泛、更普遍、更严格的文件管理的场景，所以逻辑也更为严谨与复杂。框架对 `Storage` 的封装有四个层次，每层解决不同的问题，各层都起到承上启下的作用，他们分别是：

 - `Storage`: 最上层的对用户友好的操作接口，语义化程度较高。
 - `FilesystemAdapter`: 对文件系统的高级抽象，包含一定的使用场景原语，是对文件系统的运用范围的一定程度的扩展。
 - `Filesystem`: 统一文件管理的公共接口，而不需要关心文件最终会存储到什么地方，这一层已经回归到对文件的管理，这一单一职责上。
 - `Adapter`: 承接具体的文件管理职责，这里明确文件的存储方式，能对外提供的接口。

在了解到框架 `Storage` 的具体实现逻辑后，再来谈谈为什么我们要了解这些。在我负责的美术相关业务系统中，有大量的文件存储的需求，原本是单独部署的一个存储服务器，挂载到 `web` 服务器，统一保存这些文件资源。 后来 `IT` 部门对外采购了云存储服务，用来统一存储公司各个业务部门的业务文件，刚好我们系统也可以接入。由于前期的架构，代码中大量使用 `Storage` 的 `local` 驱动管理文件，而在接入云盘的过程中，需要实现新的驱动，来取代原始的 `local` 驱动。由此，在了解到框架对 `Storage` 的封装，以及云供应商提供的接口文档后，开始着手实现云存储的驱动。


在了解框架实现之后，很容易做出判断，在原有应用场景不变的情况下，可以跳过对 `Storage` 前三层的修改，直接在第四层 `Adapter` 进行封装，而这一层的主要职责，是对接统一的文件管理接口与云供应商提供的接口。具体需要实现的接口类是 `League\Flysystem\AdapterInterface`:

```php
<?php

namespace League\Flysystem;

interface AdapterInterface extends ReadInterface
{
    const VISIBILITY_PUBLIC = 'public';
    const VISIBILITY_PRIVATE = 'private';

    public function write($path, $contents, Config $config);
    public function writeStream($path, $resource, Config $config);
    public function update($path, $contents, Config $config);
    public function updateStream($path, $resource, Config $config);
    public function rename($path, $newpath);
    public function copy($path, $newpath);
    public function delete($path);
    public function deleteDir($dirname);
    public function createDir($dirname, Config $config);
    public function setVisibility($path, $visibility);
}
```


考虑到后期可能对文件管理行为添加权限等场景，决定在 `Adapter` 下层再添加一层对云供应商接口的封装，其主要职责是对接 `AdapterInterface` 以及预留后期可能需要实现的功能接口。到此，就可以规划各层级的职责，建立基础的代码框架。

```php
/**
 * 一、云供应商接口封装类
 * 负责处理云服务的登录，会话保存，接口调用
 */
class CloudDriverHandler
{

}


/**
 * 二、云存储驱动
 * 通过调用CloudDriverHandler的接口实现AdapterInterface的方法，
 */
class CloudAdapter extends AbstractAdapter /*implements AdapterInterface*/
{
    protected $handler;
    public function __construct(CloudDriverHandler $handler)
    {
        $this->handler = $handler;
    }

}

/**
 * 三、 通过 Provider 注入实现，扩展Stroage驱动
 */
class CloudServiceProvider extends ServiceProvider
{
    public function boot()
    {
        Storage::extend('cloud', function ($app, $config) {

            // 当 Storage 扩展驱动被解析时，为 CloudDriverHandler 实例导入配置
            // 并绑定实例到容器，后续容器解析该对象时，返回的就是已配置过的实例
            $handler = new CloudDriverHandler($app['cache']);
            $handler->init($config);
            $app->instance('cloud', $handler);

            $adaper = new CloudAdapter($handler);
            if (isset($config['root'])) {
                $adaper->setPathPrefix($config['root']);
            }

            return new FilesystemAdapter(new Filesystem($adaper, $config));
        });
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(CloudDriverHandler::class, function ($app) {
            // 触发 Storage::extend('cloud') 的解析，达到注入配置的目的
            // 需要配置 filesystems.cloud
            Storage::cloud();
            if ($app->resolved('cloud')) {
                return $app['cloud'];
            }
            throw new PanException('请配置默认 cloud 驱动');
        });

        $this->app->alias(CloudDriverHandler::class, 'cloud');
    }

    public function provides()
    {
        return [
            'cloud' => CloudDriverHandler::class,
        ];
    }
}
```

代码开发完毕后，配置好云存储的参数，就可以开始使用，使用方面也可以区分两个层级。第一层级是以 `Storage` 完成对文件管理的操作，第二层级是以服务注入的方式，调用云供应商提供的原生接口，以次来实现存储功能外的需求。


```php
// 1. 作为 Storage 的云盘驱动使用
Storage::disk('cloud')->has('test.txt');
Storage::disk('cloud')->copy('test.txt', '/folder/test.txt');
Storage::disk('cloud')->rename('test.txt', 'newName.txt');
Storage::disk('cloud')->delete('test.txt');

// 2. 调用云盘服务的 filesystem 以外的接口
$pan = app('cloud');
$pan->getHost();
$pan->getUserInfoByAccount('songzhp');
```

`CloudDriverHandler::class/CloudAdapter::class` 的具体实现如下：

```php
<?php

namespace Youzu\Pan;

use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\Config;
use League\Flysystem\Handler;
use League\Flysystem\Util;

class CloudAdapter extends AbstractAdapter
{
    protected $handler;
    public function __construct(CloudDriverHandler $handler)
    {
        $this->handler = $handler;
    }

    /**
     * Write a new file.
     *
     * @param string $path
     * @param string $contents
     * @param Config $config   Config object
     *
     * @return array|false false on failure file meta data on success
     */
    public function write($path, $contents, Config $config)
    {
        $path = $this->applyPathPrefix($path);

        $info       = pathinfo($path);
        $folderPath = $info['dirname'];
        $filename   = $info['basename'];

        $folderInfo = $this->mkdir($folderPath);
        $folderId   = $folderInfo['folderId'];

        $fileId = $this->handler->upload($folderId, $filename, $contents);

        $type   = 'file';
        $size   = strlen($contents);
        $result = compact('contents', 'type', 'size', 'path', 'fileId', 'folderId');
        return $result;
    }

    /**
     * Write a new file using a stream.
     *
     * @param string   $path
     * @param resource $resource
     * @param Config   $config   Config object
     *
     * @return array|false false on failure file meta data on success
     */
    public function writeStream($path, $resource, Config $config)
    {
        $path = $this->applyPathPrefix($path);

        $info       = pathinfo($path);
        $folderPath = $info['dirname'];
        $filename   = $info['basename'];

        $folderInfo = $this->mkdir($folderPath);
        $folderId   = $folderInfo['folderId'];

        $fileId = $this->handler->uploadStream($folderId, $filename, $resource);

        $type   = 'file';
        $result = compact('type', 'path', 'fileId', 'folderId');
        return $result;
    }

    /**
     * Update a file.
     *
     * @param string $path
     * @param string $contents
     * @param Config $config   Config object
     *
     * @return array|false false on failure file meta data on success
     */
    public function update($path, $contents, Config $config)
    {
        $path = $this->applyPathPrefix($path);

        $info       = pathinfo($path);
        $folderPath = $info['dirname'];
        $filename   = $info['basename'];

        $folderId = $this->handler->getFolderId($folderPath);

        $fileId = $this->handler->upload($folderId, $filename, $contents, true);
        $type   = 'file';
        $size   = strlen($contents);
        $result = compact('type', 'path', 'size', 'contents', 'fileId', 'folderId');
        return $result;
    }

    /**
     * Update a file using a stream.
     *
     * @param string   $path
     * @param resource $resource
     * @param Config   $config   Config object
     *
     * @return array|false false on failure file meta data on success
     */
    public function updateStream($path, $resource, Config $config)
    {
        $path = $this->applyPathPrefix($path);

        $info       = pathinfo($path);
        $folderPath = $info['dirname'];
        $filename   = $info['basename'];
        $folderId   = $this->handler->getFolderId($folderPath);

        $fileId = $this->handler->uploadStream($folderId, $filename, $resource, true);
        $type   = 'file';
        $result = compact('type', 'path', 'fileId', 'folderId');
        return $result;
    }

    /**
     * Rename a file.
     *
     * @param string $path
     * @param string $newpath
     *
     * @return bool
     */
    public function rename($path, $newpath)
    {
        $path    = $this->applyPathPrefix($path);
        $newpath = $this->applyPathPrefix($newpath);

        $originName = pathinfo($path, PATHINFO_BASENAME);
        $newName    = pathinfo($newpath, PATHINFO_BASENAME);

        $parentFolder     = pathinfo($newpath, PATHINFO_DIRNAME);
        $parentFolderInfo = $this->mkdir($parentFolder);
        $parentFolderId   = $parentFolderInfo['folderId'];

        if ($fileId = $this->handler->getFileId($path)) {
            // 如果目标文件夹中有同名的文件
            if ($this->handler->fileExists($originName, $parentFolderId)) {
                // 1. 重命名为临时文件
                $templateName = $this->templateName();
                $this->handler->renameFile($fileId, $templateName);
            }

            // 移动文件
            $this->handler->moveFile($fileId, $parentFolderId);
            // 重命名为新的文件名
            return $this->handler->renameFile($fileId, $newName);
        } elseif ($folderId = $this->handler->getFolderId($path)) {

            // 如果目标文件夹中有同名的文件
            if ($this->handler->folderExists($originName, $parentFolderId)) {
                // 1. 重命名为临时文件
                $templateName = $this->templateName();
                $this->handler->renameFolder($folderId, $templateName);
            }

            // 移动文件
            $this->handler->moveFolder($folderId, $parentFolderId);
            // 重命名为新的文件名
            return $this->handler->renameFolder($folderId, $newName);
        }
    }

    /**
     * 生成临时文档名
     *
     * @method  templateName
     * @author  雷行  songzhp@yoozoo.com  2020-10-22T17:06:18+0800
     * @return  string
     */
    protected function templateName()
    {
        return uniqid() . '_' . rand(1000, 9999);
    }

    /**
     * Copy a file.
     *
     * @param string $path
     * @param string $newpath
     *
     * @return bool
     */
    public function copy($path, $newpath)
    {
        $oldOriginPath = $path;
        $oldNewPath    = $newpath;

        $originName = pathinfo($path, PATHINFO_BASENAME);
        $newName    = pathinfo($newpath, PATHINFO_BASENAME);

        $path    = $this->applyPathPrefix($path);
        $newpath = $this->applyPathPrefix($newpath);

        if ($folderId = $this->handler->getFolderId($path)) {
            return false;
        }
        $fileInfo       = $this->handler->getFileInfo($path);
        $fileId         = $fileInfo['FileId'];
        $parentFolderId = $fileInfo['ParentFolderId'];

        $newParentFolder     = pathinfo($newpath, PATHINFO_DIRNAME);
        $newParentFolderInfo = $this->mkdir($newParentFolder);

        // 1. 复制到其他文件夹，直接调接口
        if ($parentFolderId != $newParentFolderInfo['folderId']) {
            // copy 到其他目录后，文件名不变，要更新一次名称
            if ($this->handler->copyFile($fileId, $newParentFolderInfo['folderId'])) {
                $copyPath  = $newParentFolder . '/' . $originName;
                $newFileId = $this->handler->getFileId($copyPath);
                return $this->handler->renameFile($newFileId, $newName);
            }
            return false;
        }

        // 2. 复制到原文件夹，需要重命名
        return (bool) $this->write($oldNewPath, $this->read($oldOriginPath)['contents'], new Config);

    }

    /**
     * Delete a file.
     *
     * @param string $path
     *
     * @return bool
     */
    public function delete($path)
    {
        $path = $this->applyPathPrefix($path);

        if ($fileId = $this->handler->getFileId($path)) {
            return $this->handler->deleteFileById($fileId);
        } elseif ($folderId = $this->handler->getFolderId($path)) {
            return $this->handler->deleteFolderById($folderId);
        }
    }

    /**
     * Delete a directory.
     *
     * @param string $path
     *
     * @return bool
     */
    public function deleteDir($path)
    {
        return $this->delete($path);
    }

    /**
     * Create a directory.
     *
     * @param string $path directory name
     * @param Config $config
     *
     * @return array|false
     */
    public function createDir($path, Config $config)
    {
        $path = $this->applyPathPrefix($path);
        return $this->mkdir($path);
    }

    protected function mkdir($path)
    {
        if ($folderId = $this->handler->getFolderId($path)) {
            return ['path' => $path, 'type' => 'dir', 'folderId' => $folderId];
        }
        $folderId = $this->handler->createFolder($path);

        return ['path' => $path, 'type' => 'dir', 'folderId' => $folderId];
    }

    /**
     * Set the visibility for a file.
     *
     * @param string $path
     * @param string $visibility
     *
     * @return array|false file meta data
     */
    public function setVisibility($path, $visibility)
    {
        $visibility = false;
        return compact('path', 'visibility');
    }

    /**
     * Get the visibility of a file.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function getVisibility($path)
    {
        $visibility = false;
        return compact('path', 'visibility');
    }
    /**
     * 返回文件连接
     *
     * @method  getUrl
     * @author  雷行  songzhp@yoozoo.com  2020-10-27T16:24:38+0800
     * @param   string  $path  文件路径
     * @return  string
     */
    public function getUrl($path)
    {
        $path = $this->applyPathPrefix($path);

        $host = $this->handler->getHost();
        if ($fileId = $this->handler->getFileId($path)) {
            return $this->handler->publishFile($fileId);
        } elseif ($fileId = $this->handler->getFolderId($path)) {
            return $this->handler->publishFolder($fileId);
        }
        return false;
    }

    /**
     * Check whether a file exists.
     *
     * @param string $path
     *
     * @return array|bool|null
     */
    public function has($path)
    {

        $path   = $this->applyPathPrefix($path);
        $rootId = $this->handler->getTopId();
        $info   = explode('/', $path);
        $last   = array_pop($info);

        // 以目录分割符结尾，目录存在
        if ($last == '') {
            return (bool) $this->handler->getFolderId($path);
        }

        // 目录或文件存在
        return $this->handler->getFolderId($path) || $this->handler->getFileId($path);
    }

    /**
     * Read a file.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function read($path)
    {

        $path = $this->applyPathPrefix($path);

        if ($fileId = $this->handler->getFileId($path)) {
            $body = $this->handler->download($fileId);

            $content = '';
            while (!$body->eof()) {
                $content .= $body->read(1024);
            }

            return ['type' => 'file', 'path' => $path, 'contents' => $content];
        }
        return false;
    }

    /**
     * Read a file as a stream.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function readStream($path)
    {
        $path = $this->applyPathPrefix($path);

        $temp = tmpfile();
        if ($fileId = $this->handler->getFileId($path)) {
            $body = $this->handler->download($fileId);

            while (!$body->eof()) {
                fwrite($temp, $body->read(1024));
            }
            fseek($temp, 0);

            return ['type' => 'file', 'path' => $path, 'stream' => $temp];
        }
        return false;
    }

    /**
     * List contents of a directory.
     *
     * @param string $directory
     * @param bool   $recursive
     *
     * @return array
     */
    public function listContents($directory = '', $recursive = false)
    {
        $directory = $this->applyPathPrefix($directory);

        return $this->listDirectory($directory);
    }

    /**
     * 列出文件夹内容
     *
     * @method  listDirectory
     * @author  雷行  songzhp@yoozoo.com  2020-11-05T11:39:53+0800
     * @param   string         $directory
     * @param   boolean        $recursive
     * @return  array
     */
    protected function listDirectory($directory = '', $recursive = false)
    {

        $data = [];
        if ($folderId = $this->handler->getFolderId($directory)) {
            $list = $this->handler->listFolder($folderId);

            foreach ($list as $item) {
                if (isset($item['FolderId'])) {
                    $path   = ($directory ? $directory . '/' : '') . $item['FolderName'];
                    $data[] = $this->formatFolderInfo($item, $path);

                    if ($recursive) {
                        $data = array_merge($data, $this->listDirectory($path, $recursive));
                    }
                } else {

                    $path   = ($directory ? $directory . '/' : '') . $item['FileName'];
                    $data[] = $this->formatFileInfo($item, $path);
                }
            }
        }

        return $data;
    }

    /**
     * Get all the meta data of a file or directory.
     *
     * @param string $path
     *
     * @return false|array
     */
    public function getMetadata($path)
    {
        $path = $this->applyPathPrefix($path);
        if ($fileInfo = $this->handler->getFileInfo($path)) {
            return $this->formatFileInfo($fileInfo, $path);
        } elseif ($folderInfo = $this->handler->getFolderInfo($path)) {
            return $this->formatFolderInfo($folderInfo, $path);
        }
    }

    protected function formatFileInfo($fileInfo, $path)
    {
        return [
            //TODO:: link?
            'fileId'         => $fileInfo['FileId'],
            'parentFolderId' => $fileInfo['ParentFolderId'],
            'type'           => 'file',
            'path'           => $path,
            'timestamp'      => strtotime($fileInfo['FileModifyTime']),
            'mimetype'       => Util\MimeType::detectByFilename($fileInfo['FileName']),
            'size'           => isset($fileInfo['FileSize']) ? $fileInfo['FileSize'] : $fileInfo['FileLastSize'],
        ];
    }

    protected function formatFolderInfo($folderInfo, $path)
    {
        return [
            'folderId'       => $folderInfo['FolderId'],
            'parentFolderId' => $fileInfo['ParentFolderId'],
            'type'           => 'dir',
            'path'           => $path,
            'timestamp'      => isset($folderInfo['ModifyTime'])
            ? strtotime($folderInfo['ModifyTime'])
            : strtotime($folderInfo['FolderModifyTime']),
        ];
    }

    /**
     * Get all the meta data of a file or directory.
     *
     * @param string $path
     *
     * @return false|array
     */
    public function getSize($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * Get the mimetype of a file.
     *
     * @param string $path
     *
     * @return false|array
     */
    public function getMimetype($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * Get the timestamp of a file.
     *
     * @param string $path
     *
     * @return false|array
     */
    public function getTimestamp($path)
    {
        return $this->getMetadata($path);
    }
}

```


```php
<?php

namespace Youzu\Pan;

use GuzzleHttp\Client;
use Log;
use Youzu\Pan\Apis;
use Youzu\Pan\Exceptions\PanException;
use Youzu\Pan\Exceptions\RequestException;

class CloudDriverHandler
{

    protected $host;
    protected $account;
    protected $password;

    protected $http;
    protected $token = null;

    protected $rootType     = 'person';
    protected $rootTypeList = ['person', 'public'];

    // 分片大小
    protected $uploadChunkSize = 1024 * 1200;

    protected $debug = false;

    public function __construct($cache)
    {
        $this->cache = $cache;
    }

    /**
     * 初始化配置
     *
     * @method  init
     * @author  雷行  songzhp@yoozoo.com  2020-11-20T12:22:14+0800
     * @param   array  $config
     * @return  void
     */
    public function init($config)
    {
        $this->host     = $config['host'];
        $this->account  = $config['account'];
        $this->password = $config['password'];

        $this->http = new Client([
            'base_uri' => $this->host,

            // 忽略证书过期的事实 ！-_-
            'verify'   => false,
            'headers'  => [
                //'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.75 Safari/537.36',
                'Accept' => 'application/json',
            ],
        ]);

        if (isset($config['type']) && in_array($config['type'], $this->rootTypeList)) {
            $this->setRootType($config['type']);
        }

        if (isset($config['debug']) && $config['debug']) {
            $this->debug = true;
        }

    }

    /**
     * 获取host
     *
     * @method  getHost
     * @author  雷行  songzhp@yoozoo.com  2020-11-04T17:58:32+0800
     * @return  string
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * 设置根目录类型
     *
     * @method  setRootType
     * @author  雷行  songzhp@yoozoo.com  2020-10-22T14:27:50+0800
     * @param   string       $type
     */
    protected function setRootType($type)
    {
        $this->rootType = $type;
        self::$topId    = null;
    }

    /**
     * 获取Token
     *
     * @method  getToken
     * @author  雷行  songzhp@yoozoo.com  2020-11-19T11:28:14+0800
     * @param   boolean $force 是否强制更新
     * @return  string
     */
    public function getToken($force = false)
    {
        $key = 'YOUZU:PAN:TOKEN';

        if (!$force) {
            if (isset($this->token)) {
                return $this->token;
            }

            $token = $this->cache->get($key);

            if ($token && $this->validateToken($token)) {
                $this->token = $token;
                return $this->token;
            }
        }

        $token = $this->login();

        // 设置token过期时间一小时
        $this->cache->put($key, $token, 60);
        $this->token = $token;
        return $this->token;
    }

    /**
     * 登录云盘
     *
     * @method  login
     * @author  雷行  songzhp@yoozoo.com  2020-10-22T10:35:36+0800
     * @return  boolean
     */
    public function login()
    {
        $source = new Apis\Org\UserLogin($this->account, $this->password);
        return $this->fetch($source);
    }

    /**
     * 验证token是否有效
     *
     * @method  validateToken
     * @author  雷行  songzhp@yoozoo.com  2020-10-22T10:35:06+0800
     * @param   string         $token  云盘Token
     * @return  Boolean
     */
    public function validateToken($token)
    {
        $source = new Apis\Org\CheckUserTokenValidity($token);
        return $this->fetch($source);
    }

    /**
     * 验证服务器时间
     *
     * @method  check
     * @author  雷行  songzhp@yoozoo.com  2020-10-22T11:01:07+0800
     * @return  string
     */
    public function check()
    {
        $source = new Apis\Doc\GetServerDateTime($this->getToken());
        return $this->fetch($source);
    }

    private static $topId = null;
    /**
     * 获取根目录ID
     *
     * @method  getTopId
     * @author  雷行  songzhp@yoozoo.com  2020-10-22T11:06:59+0800
     * @return  integer
     */
    public function getTopId()
    {
        if (!isset(self::$topId)) {
            $type        = $this->rootType == 'person' ? Apis\Doc\GetTopFolderId::PERSON_FOLDER : Apis\Doc\GetTopFolderId::PUBLIC_FOLDER;
            $source      = new Apis\Doc\GetTopFolderId($this->getToken(), $type);
            self::$topId = $this->fetch($source);
        }
        return self::$topId;
    }

    /**
     * 判断指定目录中是否存在文件夹名
     *
     * @method  folderExists
     * @author  雷行  songzhp@yoozoo.com  2020-10-22T11:16:12+0800
     * @param   string     $folderName  待判定文件夹名称
     * @param   interger     $parentId  父级文件夹ID
     * @return  boolean
     */
    public function folderExists($folderName, $parentId)
    {
        $source = new Apis\Folder\IsExistfolderInFolderByfolderName($this->getToken(), $folderName, $parentId);
        return $this->fetch($source);
    }

    /**
     * 判断指定目录中是否存在某文件
     *
     * @method  fileExists
     * @author  雷行  songzhp@yoozoo.com  2020-10-22T11:21:43+0800
     * @param   string      $fileName   待判定文件名
     * @param   integer      $parentId  父级文件夹ID
     * @return  boolean
     */
    public function fileExists($fileName, $parentId)
    {
        $source = new Apis\File\IsExistFileInFolderByFileName($this->getToken(), $fileName, $parentId);
        return $this->fetch($source);
    }

    /**
     * 列出文件目录
     *
     * @method  listFolder
     * @author  雷行  songzhp@yoozoo.com  2020-10-26T14:15:17+0800
     * @param   integer      $folderId  目录ID
     * @return  array
     */
    public function listFolder($folderId)
    {

        $page   = 1;
        $limit  = 20;
        $source = new Apis\Doc\GetFileAndFolderList($this->getToken(), $folderId, $page, $limit);
        $info   = $this->fetch($source);

        $page++;
        $total    = $info['Settings']['TotalCount'];
        $lastPage = ceil($total / $limit);

        $contents = array_merge($info['FilesInfo'], $info['FoldersInfo']);
        while ($page < $lastPage) {

            $source = new Apis\Doc\GetFileAndFolderList($this->getToken(), $folderId, $page, $limit);
            $info   = $this->fetch($source);

            $contents = array_merge($contents, $info['FilesInfo'], $info['FoldersInfo']);
        }
        return $contents;
    }

    /**
     * 根据目录路径，获取目录ID
     *
     * @method  getFolderId
     * @author  雷行  songzhp@yoozoo.com  2020-10-22T11:39:51+0800
     * @param   string       $path  string
     * @return  integer
     */
    public function getFolderId($path)
    {
        if ($info = $this->getFolderInfo($path)) {
            return $info['FolderId'];
        }
        return false;
    }

    /**
     * 根据目录路径，获取目录信息
     *
     * @method  getFolderInfo
     * @author  雷行  songzhp@yoozoo.com  2020-10-22T13:42:13+0800
     * @param   string         $path
     * @return  array
     */
    public function getFolderInfo($path)
    {

        $topId    = $this->getTopId();
        $realpath = $topId . '/' . $path;
        $source   = new Apis\Folder\GetFolderInfoByNamePath($this->getToken(), $realpath);
        $info     = $this->fetch($source);
        return $info;
    }

    /**
     * 根据文件ID，获取文件夹信息
     *
     * @method  getFolderInfoById
     * @author  雷行  songzhp@yoozoo.com  2020-10-23T10:12:04+0800
     * @param   integer           $folderId  文件ID
     * @return  array
     */
    public function getFolderInfoById($folderId)
    {
        $source = new Apis\Folder\GetFolderInfoById($this->getToken(), $folderId);
        if ($info = $this->fetch($source)) {
            return $info;
        }
        return false;
    }

    /**
     * 根据文件路劲，获取文件ID
     *
     * @method  getFileId
     * @author  雷行  songzhp@yoozoo.com  2020-10-22T12:06:19+0800
     * @param   string     $path  文件路径
     * @return  integer
     */
    public function getFileId($path)
    {
        if ($info = $this->getFileInfo($path)) {
            return $info['FileId'];
        }
        return false;
    }

    /**
     * 根据文件路径，获取文件信息
     *
     * @method  getFileInfo
     * @author  雷行  songzhp@yoozoo.com  2020-10-22T13:43:58+0800
     * @param   string       $path
     * @return  array
     */
    public function getFileInfo($path)
    {

        $topId    = $this->getTopId();
        $realpath = $topId . '/' . $path;
        $source   = new Apis\File\GetFileInfoByNamePath($this->getToken(), $realpath);
        $info     = $this->fetch($source);
        return $info;
    }

    /**
     * 根据文件ID，获取文件信息
     *
     * @method  getFileInfoById
     * @author  雷行  songzhp@yoozoo.com  2020-10-23T10:12:04+0800
     * @param   integer           $fileId  文件ID
     * @return  array
     */
    public function getFileInfoById($fileId)
    {
        $source = new Apis\File\GetFileInfoById($this->getToken(), $fileId);
        if ($info = $this->fetch($source)) {
            return $info;
        }
        return false;
    }

    /**
     * 将文本写入网盘文件
     *
     * @method  uploadStream
     * @author  雷行  songzhp@yoozoo.com  2020-10-23T17:12:53+0800
     * @param   integer        $folderId   父级文件夹ID
     * @param   string         $filename   文件名
     * @param   string         $content   资源
     * @param   boolean        $updateVersion   是否更新版本
     * @param   integer        $blockSize  分片块大小
     * @return  integer
     */
    public function upload($folderId, $filename, $content, $updateVersion = false, $blockSize = 0)
    {

        if (!$blockSize) {
            $blockSize = $this->uploadChunkSize;
        }

        $pos      = 0;
        $uploadId = uniqid() . time() . rand(100, 999);
        $size     = strlen($content);

        try {

            // 1. 启动传输任务
            $start    = new Apis\Uploads\StartUploadFile($this->getToken(), $uploadId, $filename, $folderId, $size, $updateVersion);
            $fileInfo = $this->fetch($start);
            if (!$fileInfo) {
                $this->stopUpload($folderId, $filename);
                return false;
            }
            $regionHash = $fileInfo['RegionHash'];

            // 3. 传输文件
            $chunks = str_split($content, $blockSize);
            $count  = count($chunks);
            foreach ($chunks as $key => $chunk) {
                if ($key < $count - 1) {
                    $len = $blockSize;
                } else {
                    $len = strlen($chunk);
                }
                $chunk = base64_encode($chunk);

                $uploader   = new Apis\Uploads\UploadFileBlock($this->getToken(), $regionHash, $uploadId, $blockSize, $chunk, $pos);
                $uploadInfo = $this->fetch($uploader);

                if (!$uploadInfo) {
                    $this->stopUpload($folderId, $filename);
                    return false;
                }
                $pos += $len;
            }

            // 3. 完成传输
            $finish     = new Apis\Uploads\EndUploadFile($this->getToken(), $regionHash, $uploadId);
            $finishInfo = $this->fetch($finish);
            if (!$finishInfo) {
                $this->stopUpload($folderId, $filename);
                return false;
            }
        } catch (\ErrorException $e) {
            $this->stopUpload($folderId, $filename);
            throw $e;
        }
        return $fileInfo['FileId'];
    }

    /**
     * 将资源写入网盘
     *
     * @method  uploadStream
     * @author  雷行  songzhp@yoozoo.com  2020-10-23T17:12:53+0800
     * @param   integer        $folderId   父级文件夹ID
     * @param   string         $filename   文件名
     * @param   resource       $resource   资源
     * @param   boolean        $updateVersion   是否更新版本
     * @param   integer        $blockSize  分片块大小
     * @return  integer
     */
    public function uploadStream($folderId, $filename, $resource, $updateVersion = false, $blockSize = 0)
    {
        if (!$blockSize) {
            $blockSize = $this->uploadChunkSize;
        }

        $pos      = 0;
        $uploadId = uniqid() . time() . rand(100, 999);
        $size     = $this->getStreamSize($resource);

        try {
            // 1. 启动传输任务
            $start    = new Apis\Uploads\StartUploadFile($this->getToken(), $uploadId, $filename, $folderId, $size, $updateVersion);
            $fileInfo = $this->fetch($start);
            if (!$fileInfo) {
                $this->stopUpload($folderId, $filename);
                return false;
            }
            $regionHash = $fileInfo['RegionHash'];

            rewind($resource);
            // 3. 传输文件
            while (!feof($resource)) {
                $data  = fread($resource, $blockSize);
                $len   = strlen($data);
                $chunk = base64_encode($data);

                $uploader   = new Apis\Uploads\UploadFileBlock($this->getToken(), $regionHash, $uploadId, $len, $chunk, $pos);
                $uploadInfo = $this->fetch($uploader);

                if (!$uploadInfo) {
                    $this->stopUpload($folderId, $filename);
                    return false;
                }
                $pos += $len;
            }

            $finish     = new Apis\Uploads\EndUploadFile($this->getToken(), $regionHash, $uploadId);
            $finishInfo = $this->fetch($finish);

            if (!$finishInfo) {
                $this->stopUpload($folderId, $filename);
                return false;
            }
        } catch (\ErrorException $e) {
            $this->stopUpload($folderId, $filename);
            throw $e;
        }
        return $fileInfo['FileId'];
    }

    /**
     * 获取资源大小
     *
     * @method  getStreamSize
     * @author  雷行  songzhp@yoozoo.com  2020-10-23T17:09:32+0800
     * @param   resource         $resource
     * @return  integer
     */
    protected function getStreamSize($resource)
    {
        $data  = null;
        $count = 0;
        while (!feof($resource)) {
            $data = fread($resource, $this->uploadChunkSize);
            $count++;
        }

        return ($count - 1) * $this->uploadChunkSize + strlen($data);
    }

    /**
     * 取消文件上传
     *
     * @method  stopUpload
     * @author  雷行  songzhp@yoozoo.com  2020-10-23T16:31:32+0800
     * @param   integer      $folderId  上传文件夹ID
     * @param   string       $filename  文件名
     * @return  void
     */
    public function stopUpload($folderId, $filename)
    {

        $finish     = new Apis\Uploads\UndoCreateFile($this->getToken(), $folderId, $filename);
        $finishInfo = $this->fetch($finish);
    }

    /**
     * 创建文件夹
     *
     * @method  createFolder
     * @author  雷行  songzhp@yoozoo.com  2020-10-22T16:06:36+0800
     * @param   string        $path
     * @return  integer
     */
    public function createFolder($path)
    {
        $topId     = $this->getTopId();
        $parts     = explode('/', $path);
        $suffix    = '';
        $parentId  = $topId;
        $currentId = null;
        while ($folderName = array_shift($parts)) {
            $suffix .= $folderName . '/';

            $currentId = $this->getFolderId($suffix);
            if (!$currentId) {
                $currentId = $this->createFolderUnder($parentId, $folderName);
            }
            $parentId = $currentId;
        }
        return $currentId;
    }

    /**
     * 在指定目录下新建文件夹
     *
     * @method  createFolderUnder
     * @author  雷行  songzhp@yoozoo.com  2020-10-22T15:02:59+0800
     * @param   integer             $parentId    父级目录ID
     * @param   string              $folderName  待创建文件名
     * @return  integer
     */
    public function createFolderUnder($parentId, $folderName)
    {
        $source = new Apis\Folder\CreateFolder($this->getToken(), $parentId, $folderName);
        if ($info = $this->fetch($source)) {
            return $info['FolderId'];
        }
        return false;
    }

    /**
     * 移动文件到指定目录
     *
     * @method  moveFile
     * @author  雷行  songzhp@yoozoo.com  2020-10-22T17:58:53+0800
     * @param   integer    $fileId              待移动文件ID
     * @param   integer    $targetFolderId      目标目录ID
     * @return  integer
     */
    public function moveFile($fileId, $targetFolderId)
    {
        $fileId = [$fileId];
        $source = new Apis\Doc\MoveFolderListAndFileList($this->getToken(), $targetFolderId, $fileId, []);
        return $this->fetch($source);
    }

    /**
     * 移动目录到指定目录
     *
     * @method  moveFolder
     * @author  雷行  songzhp@yoozoo.com  2020-10-22T17:59:44+0800
     * @param   integer      $folderId          待移动目录ID
     * @param   integer      $targetFolderId    目标目录ID
     * @return  integer
     */
    public function moveFolder($folderId, $targetFolderId)
    {
        $folderId = [$folderId];
        $source   = new Apis\Doc\MoveFolderListAndFileList($this->getToken(), $targetFolderId, [], $folderId);
        return $this->fetch($source);
    }

    /**
     * 重命名文件
     *
     * @method  renameFile
     * @author  雷行  songzhp@yoozoo.com  2020-10-22T18:00:36+0800
     * @param   integer      $fileId   待重命名文件ID
     * @param   string       $newName  新的文件名
     * @return  boolean
     */
    public function renameFile($fileId, $newName)
    {
        $source = new Apis\File\RenameFile($this->getToken(), $fileId, $newName);
        return $this->fetch($source);
    }

    /**
     * 重命名文件夹
     *
     * @method  renameFolder
     * @author  雷行  songzhp@yoozoo.com  2020-10-22T18:07:39+0800
     * @param   integer        $folderId  待重命名文件夹ID
     * @param   string        $newName   新的文件夹名称
     * @return  boolean
     */
    public function renameFolder($folderId, $newName)
    {
        $source = new Apis\Folder\RenameFolder($this->getToken(), $folderId, $newName);
        return $this->fetch($source);
    }

    /**
     * 复制文件
     *
     * @method  copyFile
     * @author  雷行  songzhp@yoozoo.com  2020-10-22T18:09:00+0800
     * @param   integer    $fileId            待移动文件ID
     * @param   integer    $targetFolderId    目标文件夹ID
     * @return  boolean
     */
    public function copyFile($fileId, $targetFolderId)
    {
        $fileId = [$fileId];
        $source = new Apis\Doc\CopyFolderListAndFileList($this->getToken(), $targetFolderId, $fileId, []);
        return $this->fetch($source);
    }

    /**
     * 复制文件夹
     *
     * @method  copyFolder
     * @author  雷行  songzhp@yoozoo.com  2020-10-22T18:10:30+0800
     * @param   integer      $folderId          待复制文件夹ID
     * @param   integer      $targetFolderId    目标文件夹ID
     * @return  boolean
     */
    public function copyFolder($folderId, $targetFolderId)
    {
        $folderId = [$folderId];
        $source   = new Apis\Doc\CopyFolderListAndFileList($this->getToken(), $targetFolderId, [], $folderId);
        return $this->fetch($source);
    }

    /**
     * 通过ID删除文件
     *
     * @method  deleteFileById
     * @author  雷行  songzhp@yoozoo.com  2020-10-22T18:11:07+0800
     * @param   integer          $fileId  待删除文件ID
     * @return  boolean
     */
    public function deleteFileById($fileId)
    {
        $fileIds = [$fileId];
        $source  = new Apis\Doc\RemoveFolderListAndFileList($this->getToken(), $fileIds, [], [], []);
        return $this->fetch($source);
    }

    /**
     * 通过ID删除文件夹
     *
     * @method  deleteFolderById
     * @author  雷行  songzhp@yoozoo.com  2020-10-22T18:11:30+0800
     * @param   integer            $folderId  待删除文件夹ID
     * @return  boolean
     */
    public function deleteFolderById($folderId)
    {
        $folderIds = [$folderId];
        $source    = new Apis\Doc\RemoveFolderListAndFileList($this->getToken(), [], $folderIds, [], []);
        return $this->fetch($source);
    }

    /**
     * 删除文件
     *
     * @method  deleteFile
     * @author  雷行  songzhp@yoozoo.com  2020-10-22T18:11:58+0800
     * @param   string      $path  待删除文件路径
     * @return  boolean
     */
    public function deleteFile($path)
    {
        $path   = [$path];
        $source = new Apis\Doc\RemoveFolderListAndFileList($this->getToken(), [], [], $path, []);
        return $this->fetch($source);
    }

    /**
     * 删除文件夹
     *
     * @method  deleteFolder
     * @author  雷行  songzhp@yoozoo.com  2020-10-22T18:12:20+0800
     * @param   string        $path  待删除文件夹路径
     * @return  boolean
     */
    public function deleteFolder($path)
    {
        $path   = [$path];
        $source = new Apis\Doc\RemoveFolderListAndFileList($this->getToken(), [], [], [], $path);
        return $this->fetch($source);
    }

    /**
     * 下载文件
     *
     * @method  download
     * @author  雷行  songzhp@yoozoo.com  2020-10-26T10:39:16+0800
     * @param   integer    $fileId    文件ID
     * @return  void
     */
    public function download($fileId)
    {

        $source    = new Apis\Download\DownLoadCheck($this->getToken(), $fileId);
        $checkInfo = $this->fetch($source);

        // 下载区域不在主区域的情况
        if ($checkInfo['RegionType'] != 1) {
            $download = new Apis\Download\HttpDownLoad($checkInfo['RegionUrl']);
        } else {
            $download = new Apis\Download\DownLoad($checkInfo['RegionHash']);
        }

        return $this->fetchStream($download);
    }

    /**
     * 文件外发
     *
     * @method  publishFile
     * @author  雷行  songzhp@yoozoo.com  2020-11-04T14:11:30+0800
     * @param   integer       $fileId  文件ID
     * @return  string
     */
    public function publishFile($fileId)
    {
        $endTime     = date('Y-m-d', strtotime('+1 day'));
        $source      = new Apis\DocPublish\CreateFilePublish($this->getToken(), $fileId, $endTime);
        $publishCode = $this->fetch($source);

        return [
            'fileId'      => $fileId,
            'publishCode' => $publishCode,
            'endTime'     => $endTime,
            'url'         => $this->host . '/preview.html?fileid=' . $fileId . '&ispublish=true&ifream=1&code=' . $publishCode,

        ];
    }

    /**
     * 文件夹外发
     *
     * @method  publishFolder
     * @author  雷行  songzhp@yoozoo.com  2020-11-04T14:12:22+0800
     * @param   integer         $folderId
     * @param   boolean         $uploadable
     * @return  string
     */
    public function publishFolder($folderId, $uploadable = false)
    {
        $endTime     = date('Y-m-d', strtotime('+1 day'));
        $source      = new Apis\DocPublish\CreateFolderPublish($this->getToken(), $folderId, $endTime, $uploadable);
        $publishCode = $this->fetch($source);

        return [
            'folderId'    => $folderId,
            'publishCode' => $publishCode,
            'endTime'     => $endTime,
            'uploadable'  => $uploadable,
        ];
    }

    /**
     * 按账号获取用户信息
     *
     * @method  getUserInfoByAccount
     * @author  雷行  songzhp@yoozoo.com  2020-11-19T18:17:28+0800
     * @param   string                $account
     * @return  array
     */
    public function getUserInfoByAccount($account)
    {
        $source = new Apis\OrgUser\GetUserInfoByAccount($this->getToken(), $account);
        return $this->fetch($source);
    }

    /**
     * 按账号获取ID
     *
     * @method  getUserIdByAccount
     * @author  雷行  songzhp@yoozoo.com  2020-11-20T09:41:48+0800
     * @param   string              $account
     * @return  string
     */
    public function getUserIdByAccount($account)
    {
        $info = $this->getUserInfoByAccount($account);
        return $info['ID'];
    }

    private static $operationUserId = null;

    /**
     * 获取当前操作人ID
     *
     * @method  getOperationUserId
     * @author  雷行  songzhp@yoozoo.com  2020-11-19T18:25:02+0800
     * @return  string
     */
    public function getOperationUserId()
    {
        if (!isset(self::$operationUserId)) {
            $info                  = $this->getUserInfoByAccount($this->account);
            self::$operationUserId = $info['ID'];
        }
        return self::$operationUserId;
    }

    /**
     * 注销账号
     *
     * @method  logOff
     * @author  雷行  songzhp@yoozoo.com  2020-11-20T09:43:03+0800
     * @param   string  $accounts
     * @return  boolean
     */
    public function logOff($accounts)
    {
        return $this->batchUserOperation($accounts, __FUNCTION__);
    }

    /**
     * 激活用户
     *
     * @method  activeUser
     * @author  雷行  songzhp@yoozoo.com  2020-11-20T09:53:39+0800
     * @param   string      $accounts
     * @return  boolean
     */
    public function activeUser($accounts)
    {
        return $this->batchUserOperation($accounts, __FUNCTION__);
    }

    /**
     * 锁定/解锁用户
     *
     * @method  toggleLockUser
     * @author  雷行  songzhp@yoozoo.com  2020-11-20T10:00:33+0800
     * @param   string          $accounts
     * @return  boolean
     */
    public function toggleLockUser($accounts)
    {
        return $this->batchUserOperation($accounts, __FUNCTION__);
    }

    /**
     * 用户批量操作
     *
     * @method  batchUserOperation
     * @author  雷行  songzhp@yoozoo.com  2020-11-20T10:13:38+0800
     * @param   string              $accounts
     * @param   string              $operation
     * @return  boolean
     */
    protected function batchUserOperation($accounts, $operation)
    {

        $operationUserId = $this->getOperationUserId();
        if (is_string($accounts)) {
            $accounts = [$accounts];
        }

        $userIds = [];
        foreach ($accounts as $account) {
            print_r($account);
            $userIds[] = $this->getUserIdByAccount($account);
        }

        $class = null;
        switch ($operation) {
            case 'logOff':
                $class = Apis\OrgUser\Logoff::class;
                break;
            case 'activeUser':
                $class = Apis\OrgUser\ActivateUser::class;
                break;
            case 'toggleLockUser':
                $class = Apis\OrgUser\ToggleLockUser::class;
                break;

            default:
                # code...
                break;
        }

        if (is_null($class)) {
            throw new PanException('用户操作类别不存在');
        }

        $source = new $class($this->getToken(), $operationUserId, $userIds);
        return $this->fetch($source);
    }

    public function fetchStream(Apis\SourceInterface $source)
    {
        if ($this->debug) {
            Log::info('                  ');
            Log::info('                  ');
            Log::info('                  ');
            Log::info($source->api);
            Log::info($this->cut($source->body()));
        }
        $response = $this->http->request(
            $source->method,
            $source->api,
            $source->body()
        );

        if ($response->getStatusCode() != 200) {
            throw new RequestException('请求错误：' . $response->getStatusCode());
        }

        return $response->getBody();
    }

    /**
     * 拉取资源
     *
     * @method  fetch
     * @author  雷行  songzhp@yoozoo.com  2020-11-19T11:42:46+0800
     * @param   Apis\SourceInterface  $source
     * @param   integer               $tries
     * @return  mix
     */
    public function fetch(Apis\SourceInterface $source, $tries = 0)
    {
        if ($tries > 3) {
            throw new RequestException('retry times too match!');
        }

        $body = $this->fetchStream($source);

        $data = json_decode((string) $body, true);
        if ($this->debug) {
            Log::info($data);
        }
        if (isset($data['errorCode'])) {
            if ($data['errorCode'] == 4) {
                // 出现token失效的时候，先强制重新登录刷新token，然后重试
                $source->updateToken($this->getToken(true));
                return $this->fetch($source, ++$tries);
            }
            throw new RequestException('errorCode:' . $data['errorCode'] . '，errorMsg:' . $data['errorMsg']);
        }
        return $source->parse($data);
    }

    /**
     * 截断太长的信息
     *
     * @method  cut
     * @author  雷行  songzhp@yoozoo.com  2020-11-06T17:13:19+0800
     * @param   array  $data
     * @return  array
     */
    protected function cut($data)
    {
        foreach ($data as $key => $item) {
            if (is_string($item) && strlen($item) > 1000) {
                $data[$key] = substr($item, 0, 100) . '...';
            }

            if (is_array($item)) {
                $data[$key] = $this->cut($item);
            }
        }
        return $data;
    }
}

```