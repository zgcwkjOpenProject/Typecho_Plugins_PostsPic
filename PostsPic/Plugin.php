<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
set_time_limit(0); // 设置脚本执行时间
ini_set('memory_limit', '256M'); // 设置内存限制

/**
 * 处理文章的图片
 *
 * @package PostsPic
 * @author zgcwkj
 * @version 1.0.0
 * @link http://zgcwkj.cn
 */
class PostsPic_Plugin implements Typecho_Plugin_Interface
{
    /**
     * 缓存目录
     */
    public static $cacheDir = '/usr/img';
    
    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     *
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function activate()
    {
        // 检查环境
        if (!function_exists('gd_info')) throw new Typecho_Plugin_Exception(_t('对不起, 您的主机没有PHP中开启GD库支持'));
        // 缓存目录
        $dirCache = __TYPECHO_ROOT_DIR__ . self::$cacheDir;
        if (!is_dir($dirCache)) @mkdir($dirCache, 0777);
        if (!is_writable($dirCache)) throw new Typecho_Plugin_Exception(_t('对不起, usr目录权限不足，无法使用使用'));
        // 创建路由
        Helper::addRoute('_PostsPicMark', '/piMark', 'PostsPic_Action', 'mark');
        Helper::addRoute('_PostsPicClear', '/piClear', 'PostsPic_Action', 'clear');
        // 监听事件
        Typecho_Plugin::factory('Widget_Abstract_Contents')->contentEx = array('PostsPic_Plugin', 'parse');
        // 提示用户
        return _t('插件已经激活，请正确设置插件！');
    }

    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     * 
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function deactivate()
    {
        // 删除路由
        Helper::removeRoute("_PostsPicMark");
        Helper::removeRoute("_PostsPicClear");
    }

    /**
     * 获取插件配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form 配置面板
     * @return void
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        $options = Helper::options();
        echo '<h4>作者：<a href="http://zgcwkj.cn" target="_blank">zgcwkj</a> 2025年4月21日</h4>';
        echo '<h4>源码：<a href="http://github.com/zgcwkjOpenProject/Typecho_Plugins_PostsPic" target="_blank">PostsPic</a></h4>';
        echo '<h4>缓存：<a href="' . $options->index . '/piClear" target="_blank">清理缓存文件</a></h4>';
        echo '<hr>';
        // 图片质量
        $quality = new Typecho_Widget_Helper_Form_Element_Radio(
            'quality',
            array('20' => _t('20'), '50' => _t('50'), '80' => _t('80'), '100' => _t('100')),
            '80',
            '图片质量',
            '设置转换后的图片格式质量（最佳建议 80）'
        );
        $form->addInput($quality);
        // 图片格式
        $picExt = new Typecho_Widget_Helper_Form_Element_Radio(
            'picExt',
            array('.jpeg' => _t('jpeg'), '.webp' => _t('webp')),
            '.jpeg',
            '图片格式',
            '设置转换后的图片格式');
        $form->addInput($picExt);
        // 水印图片
        $vmPic = new Typecho_Widget_Helper_Form_Element_Text(
            'vmPic',
            NULL,
            'wm.png',
            '水印图片',
            '插件目录下的图片文件名（必须是 Png 格式）');
        $form->addInput($vmPic);
        // 水印位置
        $vmPicPos = new Typecho_Widget_Helper_Form_Element_Radio(
            'vmPicPos',
            array('top-left' => _t('左上'), 'top-right' => _t('右上'), 'bottom-left' => _t('左下'), 'bottom-right' => _t('右下'), '' => _t('居中')),
            'bottom-right',
            '水印位置',
            '水印相对于原图的位置');
        $form->addInput($vmPicPos);
        // // 水印透明度
        // $vmPicOpacity = new Typecho_Widget_Helper_Form_Element_Radio(
        //     'vmPicOpacity',
        //     array('20' => _t('20%'), '50' => _t('50%'), '80' => _t('80%'), '100' => _t('不透明')),
        //     '100',
        //     '水印透明度',
        //     '水印相对于原图的透明度');
        // $form->addInput($vmPicOpacity);
    }

    /**
    * 个人用户的配置面板
    *
    * @access public
    * @param Typecho_Widget_Helper_Form $form
    * @return void
    */
    public static function personalConfig(Typecho_Widget_Helper_Form $form) {}

    /**
     * 解析内容
     * 
     * @param Typecho_Widget_Helper_Form $content
     * @param Typecho_Widget_Helper_Form $widget
     * @param Typecho_Widget_Helper_Form $lastResult
     * @return void
     */
    public static function parse($content, $widget, $lastResult)
    {
        $options = Helper::options();
        $cfg = $options->plugin('PostsPic');
        // 匹配图片
        $value = $content;
        $regex = "/<img.*?src=\"(.*?)\".*?[\/]?>/";
        preg_match_all($regex, $value, $matches);
        // 替换标签地址
        $count = count($matches[0]);
        for ($i = 0; $i < $count; $i++) {
            $url = $matches[1][$i];
            $m = parse_url($url);
            $ext = strtolower(pathinfo($m['path'], PATHINFO_EXTENSION));
            // 避开动态图片
            if ($ext == 'gif' && self::isGif(self::lujin(__TYPECHO_ROOT_DIR__ . $m['path']))) {
                continue;
            }
            // 生成新地址
            $cacheFile = self::$cacheDir . '/' . md5($m['path']) . $cfg->picExt;
            if (file_exists(__TYPECHO_ROOT_DIR__ . $cacheFile)) {
                $mUrl = self::lujin($options->siteUrl . $cacheFile);
            } else {
                $mUrl = $options->index . '/piMark?' . base64_encode($m['path']);
            }
            $url = str_replace($url, $mUrl, $matches[0][$i]);
            $value = str_replace($matches[0][$i], $url, $value);
        }
        $content = $value;
        return $content;
    }

    /**
     * 判断是否是动态图片
     *
     * @param string $filename
     * @return boolean
     */
    public static function isGif($filename)
    {
        $fp = fopen($filename, 'rb');
        $size = filesize($filename) > 1024 ? 1024 : filesize($filename);
        $filecontent = fread($fp, $size);
        fclose($fp);
        return strpos($filecontent, chr(0x21) . chr(0xff) . chr(0x0b) . 'NETSCAPE2.0') === FALSE ? 0 : 1;
    }

    /**
     * 合并重复路径
     *
     * @param string $uri
     * @return string
     */
    public static function lujin($uri)
    {
        $uri = str_replace("\\", "/", $uri);
        $a = explode('/', $uri);
        $b = array_unique($a);
        return implode('/', $b);
    }

    /**
     * 使用 GD 库转换为对应格式
     *
     * @param string $sourceFile 源文件路径
     * @param string $outputFile 输出文件路径
     * @return bool 转换是否成功
     */
    public static function ToWebPWithGD($sourceFile, $outputFile)
    {
        $options = Helper::options();
        $cfg = $options->plugin('PostsPic');
        // 获取源图像的宽度、高度和类型
        list($width, $height, $type) = getimagesize($sourceFile);
        // 宽高过大的直接原始文件
        if ($width > 10240 || $height > 10240) {
            copy($sourceFile, $outputFile);
            return true;
        }
        // 计算等比缩放后的宽度和高度，确保不会超出最大宽高
        $maxWidth = $width;
        $maxHeight = $height;
        $ratio = min($maxWidth / $width, $maxHeight / $height, 1);
        $newWidth = $width * $ratio;
        $newHeight = $height * $ratio;
        // 根据图像类型选择相应的创建函数
        $image = null;
        switch ($type) {
            case IMAGETYPE_JPEG:
                $image = imagecreatefromjpeg($sourceFile);
                break;
            case IMAGETYPE_PNG:
                $image = imagecreatefrompng($sourceFile);
                break;
            case IMAGETYPE_GIF:
                $image = imagecreatefromgif($sourceFile);
                break;
            default:
                return false; // 不支持的图像类型
        }
        // 检查图像是否成功创建
        if (!$image)
            return false;
        // 使用 GD 库对图像进行等比缩放
        $resizedImage = imagescale($image, $newWidth, $newHeight);
        // 如果有水印图片，添加水印
        $watermarkFile = __TYPECHO_ROOT_DIR__ . __TYPECHO_PLUGIN_DIR__ . '/PostsPic/' . $cfg->vmPic;
        if ($watermarkFile && file_exists($watermarkFile)) {
            // 获取水印图片信息
            list($w_width, $w_height, $w_type) = getimagesize($watermarkFile);
            // 创建水印图片
            $watermark = imagecreatefrompng($watermarkFile);
            // 检查水印图片是否成功创建
            if ($watermark) {
                // 保留透明度
                imagesavealpha($watermark, false);
                // 计算水印缩放比例，使水印更契合原图
                $maxWatermarkWidth = $newWidth / 8;
                $watermarkRatio = $maxWatermarkWidth / $w_width;
                $new_w_width = $w_width * $watermarkRatio;
                $new_w_height = $w_height * $watermarkRatio;
                // 缩放水印图片
                $resizedWatermark = imagescale($watermark, $new_w_width, $new_w_height);
                imagedestroy($watermark);
                $watermark = $resizedWatermark;
                $w_width = $new_w_width;
                $w_height = $new_w_height;
                // 计算水印位置
                switch ($cfg->vmPicPos) {
                    case 'top-left':// 左上角
                        $x = 0;
                        $y = 0;
                        break;
                    case 'top-right':// 右上角
                        $x = $newWidth - $w_width;
                        $y = 0;
                        break;
                    case 'bottom-left':// 左下角
                        $x = 0;
                        $y = $newHeight - $w_height;
                        break;
                    case 'bottom-right':// 右下角
                        $x = $newWidth - $w_width;
                        $y = $newHeight - $w_height;
                        break;
                    default: // 居中
                        $x = ($newWidth - $w_width) / 2;
                        $y = ($newHeight - $w_height) / 2;
                }
                // 合并图片
                // $opacity = (int)$cfg->vmPicOpacity;
                imagecopy($resizedImage, $watermark, $x, $y, 0, 0, $w_width, $w_height);
                imagedestroy($watermark);
            }
        }
        // 生成新文件
        if ($cfg->picExt == '.webp') {
            $result = imagewebp($resizedImage, $outputFile, $cfg->quality);
        } else {
            $result = imagejpeg($resizedImage, $outputFile, $cfg->quality);
        }
        // 销毁图像资源，释放内存
        imagedestroy($image);
        imagedestroy($resizedImage);
        // 返回结果
        return $result;
    }
}
