# word操作与pdf转码

# Index
 - [word文档中动态插入内容](#word文档中动态插入内容)
 - [检查预定义标记是否存在](#检查预定义标记是否存在)
     - [一般情况](#一般情况)
     - [审阅模式](#审阅模式)
 - [word转pdf](#word转pdf)
 - [其他问题](#其他问题)
     - [mac上编辑过后，标记检查失败](#mac上编辑过后，标记检查失败)
     - [pdf转码时中文出现乱码](#pdf转码时中文出现乱码)

最近在开发供应商入库系统时，涉及到部分操作 `office` 文档的过程，在这里简单记录一下。主要有下面这几个过程

 - 在 `word` 文档指定位置插入内容
 - 检查 `word` 文档中预定内容是否存在
 - `word` 文档转 `pdf`

# word文档中动态插入内容
在项目中会按供应商商生成许多合同文档，附件等，需要以供应商公司名称等预定内容填充至文档中。这里可以使用 `phpoffice/phpword` 扩展直接来完成：

```php
use PhpOffice\PhpWord\TemplateProcessor;

$template = '/template.docx';
$option = [
    'company' => '游族',
    'user' => '雷行'
];
$target = '/target.docx';

$templateProcessor = new TemplateProcessor($template);
$templateProcessor->setValue(array_keys($option), array_values($option));
$templateProcessor->saveAs($target);

```

其中 `$template` 表示模板文档路径，`$option` 是待填充内容。 `$target` 是生成的文件保存路径。待填充部分的内容，以规定的格式将键值填入文档，即可完成替换。如模板文档中字符 `${company}` 会被替换为 `游族`, `${user}` 会被替换为 `雷行`。

# 检查预定义标记是否存在
由于业务流程问题，在入库过程中，需要在文档中保留一部分的标记，供后续流程进行替换。在此之前，供应商会下载文档，进行编辑，然后重新上传，在上传时，服务端需要检查文档中标记是否被编辑过，是则提示供应商错误信息，以免后续流程执行错误。

## 一般情况
通过 `TemplateProcessor::getVariableCount()` 方法可以获取文档中存在的标记和数量，返回数据格式如下：

```php
$target = '/target.docx';

$templateProcessor = new TemplateProcessor($target);
$result = $templateProcessor->getVariableCount();

// $result
// [
//    'company' => 1,
//    'user' => 1
// ]
```

因此只需要检查返回结果中是否存在相应的键值即可。

## 审阅模式
在审阅模式下，直接通过上述方法，无法获得正确的结果，其原因在于：审阅模式下，编辑 `word` 文档，即使删除了标记，但在 `word` 文档的数据源中，仍然存在标记，只不过通过删除线，在文档中变成了不可见内容。所以即使用户编辑了标记，上述方法也无法检测到标记的丢失。

将 `word` 文档重命名为 `zip` 的文档后解压，得到大致如下结构的目录：

```
/
|---_rels/
    |---.rels
|---docProps/
    |---app.xml
    |---core.xml
    |---custom.xml
|---word/
    |---_rels/
    |---media/
    |---theme/
    |---document.xml
    |---endnotes.xml
    |---fontTable.xml
    |---footer1.xml
    |---footnotes.xml
    |---header1.xml
    |---numbering.xml
    |---people.xml
    |---settings.xml
    |---styles.xml
    |---webSettings.xml
|---[Content_Types].xml
```

操作 `word` 文档，实际就是对压缩文件内的子文件的操作。文档的内容基本都在 `/word/document.xml` 这个文件当中。我们查看审阅模式下的 `document.xml` 找到被编辑过的标记：

```xml
<!--- 这是审阅模式下被编辑过的标记的数据源 -->
<w:ins w:id="29" w:author="宋正平(雷行)" w:date="2019-10-08T13:58:00Z">
    <w:del w:id="30" w:author="宋正平(雷行) [2]" w:date="2019-12-24T10:51:00Z">
        <w:r w:rsidR="001737A4" w:rsidRPr="001737A4" w:rsidDel="00212F81">
            <w:rPr>
                <w:rFonts w:ascii="微软雅黑" w:eastAsia="微软雅黑" w:hAnsi="微软雅黑"/>
                <w:szCs w:val="21"/>
                <w:u w:val="single"/>
            </w:rPr>
            <w:delText>${sY}</w:delText>
        </w:r>
    </w:del>
</w:ins>


<!--- 这是审阅模式下没有编辑过的标记的数据源 -->
<w:ins w:id="32" w:author="宋正平(雷行)" w:date="2019-10-08T13:58:00Z">
    <w:r w:rsidR="001737A4" w:rsidRPr="001737A4">
        <w:rPr>
            <w:rFonts w:ascii="微软雅黑" w:eastAsia="微软雅黑" w:hAnsi="微软雅黑"/>
            <w:szCs w:val="21"/>
            <w:u w:val="single"/>
        </w:rPr>
        <w:t>${sM}</w:t>
    </w:r>
</w:ins>
```

通过对比发现，审阅模式下被编辑过的内容，依然存在文档数据源中，不过被添加了一对 `<w:delText></w:delText>` 的标记。所以我们只需要在执行 `getVariableCount()` 方法时，过滤掉这部分的标记即可。

为了方便操作，我们新建一个类，继承自 `PhpOffice\PhpWord\TemplateProcessor` 类，然后添加相应操作：

```php
<?php

namespace App\Services;

use App\Exceptions\ErrorLogicException;
use PhpOffice\PhpWord\TemplateProcessor;

class WordTagDeleteCheck extends TemplateProcessor
{
    /**
     * 过滤审阅模式下已删除的标记
     *
     * @method  getVariableCountWithoutDel
     * @author  雷行  songzhp@yoozoo.com  2019-10-30T12:01:16+0800
     * @return  array
     */
    public function getVariableCountWithoutDel()
    {

        // 通过原方法获取标记列表
        $vars = $this->getVariableCount();

        // 构建替换数组，将带有删除线的标记替换为空字符
        $option = [];
        foreach ($vars as $key => $value) {
            $option['<w:delText>${' . $key . '}</w:delText>'] = '';
        }

        $search                     = array_keys($option);
        $replace                    = array_values($option);

        // 对文档页头执行替换
        $this->tempDocumentHeaders  = $this->setValueForPart($search, $replace, $this->tempDocumentHeaders, self::MAXIMUM_REPLACEMENTS_DEFAULT);

        // 对文档内容执行替换
        $this->tempDocumentMainPart = $this->setValueForPart($search, $replace, $this->tempDocumentMainPart, self::MAXIMUM_REPLACEMENTS_DEFAULT);

        // 对文档页脚执行替换
        $this->tempDocumentFooters  = $this->setValueForPart($search, $replace, $this->tempDocumentFooters, self::MAXIMUM_REPLACEMENTS_DEFAULT);

        // 重新获取文档标记
        return $this->getVariableCount();
    }

    /**
     * 检查合同时间标记是否缺少
     *
     * @method  checkDateTagDelete
     * @author  雷行  songzhp@yoozoo.com  2019-10-30T12:01:40+0800
     * @return  boolean
     */
    public function checkDateTagDelete()
    {

        $vars = $this->getVariableCountWithoutDel();
        if (!isset($vars['sY']) ||
            !isset($vars['sM']) ||
            !isset($vars['sD']) ||
            !isset($vars['eY']) ||
            !isset($vars['eM']) ||
            !isset($vars['eD'])
        ) {
            throw new ErrorLogicException('file.doc.contract');
        }
        return true;
    }
}

```

# word转pdf
要完成word转pdf，需要现在服务器上安装软件 `libreoffice`， 然后就可以通过命令来完成：

```bash
export HOME=/output && soffice  --headless --convert-to pdf:writer_pdf_Export  --outdir /output /target.docx
```

其中，`target.docx` 表示文档路径，`output` 表示转码后 `pdf` 文档存放的目录。

一般来说，执行 `docx` 文档的生成、`pdf` 的转码，都需要放到队列中异步执行，然而 `libreoffice` 提供的命令不支持并发操作。所以在启动队列时，执行 `pdf` 文件转码的队列只允许有一个，否则出现并发，会导致进程卡死，`PHP` 执行 `exec` 的进程挂起，队列中的任务会无限超时，`pdf` 生成失败，千万要注意。

# 其他问题

## mac上编辑过后，标记检查失败
mac上编辑 `word` 文档时，可能因 `mac` 上不具备原文档所需要的字体，自动转化为其他字体，此时会改变标记的 `xml` 数据，导致标记检查失效。解决方法是在保存模板时嵌入字体

## pdf转码时中文出现乱码
 - 检查服务器上是否有安装中文字体，如果没有可能会导致中文全部乱码
 - 原 `word` 文档是否有嵌入字体，如果服务端已安装中文字体，`word` 文档嵌入字体，可能会导致部分中文乱码

