<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 图片处理路由
 *
 * @author zgcwkj
 * @copyright Copyright (c) 2025 zgcwkj (http://zgcwkj.cn)
 * @copyright Copyright (c) 2013 DEFE (http://defe.me)
 */
class PostsPic_Action extends Typecho_Widget
{
    /**
     * 生成图片
     */
    public function mark()
    {
        $options = Helper::options();
        $cfg = $options->plugin('PostsPic');
        // 缓存目录
        $dirCache = __TYPECHO_ROOT_DIR__ . PostsPic_Plugin::$cacheDir;
        if (!is_dir($dirCache)) @mkdir($dirCache, 0777);
        // 取出参数
        $requestUri = $_SERVER['REQUEST_URI'];
        $parts = explode('?', $requestUri);
        $imgParam = $parts[count($parts) - 1];
        // 文件信息
        $imgTemp = base64_decode($imgParam);
        $saveFile = $dirCache . '/' . md5($imgTemp) . $cfg->picExt; 
        $imgPath = __TYPECHO_ROOT_DIR__ . $imgTemp;
        // 生成文件
        if (file_exists($imgPath)) {
            $result = true;
            // 验证来源
            if ($cfg->picLink) {
                // 来源验证
                $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
                if (empty($referer)) {
                    header('HTTP/1.1 403 Forbidden');
                    die('Access Denied');
                }
                // 来源域名
                $host = parse_url($referer, PHP_URL_HOST);
                $allowedHost = parse_url($options->siteUrl, PHP_URL_HOST);
                if ($host !== $allowedHost) {
                    header('HTTP/1.1 403 Forbidden');
                    die('Access Denied');
                }
            }
            // 检查是否存在转换图片
            if (!is_file($saveFile)) {
                $result = PostsPic_Plugin::ToWebPWithGD($imgPath, $saveFile);
            }
            // 输出图片
            if ($result) {
                // 设置缓存控制头
                $expires = 60 * 60 * 24 * 7; // 7天
                header('Cache-Control: public, max-age=' . $expires);
                header('Pragma: cache');
                header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $expires) . ' GMT');
                header('Content-Type: image/jpeg');
                readfile($saveFile);
                return;
            }
        }
        // 404页面
        $this->widget('Widget_Archive@404', 'type=404')->render();
    }

    /**
     * 清除水印图片缓存
     * @return boolean
     */
    public function clear()
    {
        // 判断用户
        $user = Typecho_Widget::widget('Widget_User');
        if (!$user->hasLogin() || !$user->pass('administrator', true)) {
            throw new Typecho_Widget_Exception('对不起，您没有清除缓存的权限');
        }
        // 缓存目录
        $dirCache = __TYPECHO_ROOT_DIR__ . PostsPic_Plugin::$cacheDir;
        if (!is_dir($dirCache))
            @mkdir($dirCache, 0777);
        // 开始删除
        if (is_writable($dirCache)) {
            chdir($dirCache);
            $dh = opendir('.');
            $num = 0;
            while (false !== ($et = readdir($dh))) {
                if (is_file($et)) {
                    if (!@unlink($et)) {
                        return false;
                        echo "缓存文件 {$et} 未能删除，请检查目录权限";
                        break;
                    }
                    echo "清除文件：{$et} <br>";
                    $num++;
                }
            }
            closedir($dh);
            echo "共清除 {$num} 个缓存文件<br>";
            chdir('..');
            if (@rmdir('img'))
                echo '缓存文件目录已删除';
        }
    }
}
