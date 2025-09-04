<?php
/**
 * 服务器环境检测脚本
 * 用于快速诊断PHP环境和系统配置
 */

// 设置错误报告级别，只显示严重错误
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 1);

// 输出HTML头部
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>复盘精灵 - 服务器环境检测</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; background-color: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #28a745; background-color: #d4edda; padding: 8px; border-radius: 4px; margin: 4px 0; }
        .error { color: #dc3545; background-color: #f8d7da; padding: 8px; border-radius: 4px; margin: 4px 0; }
        .warning { color: #856404; background-color: #fff3cd; padding: 8px; border-radius: 4px; margin: 4px 0; }
        .info { color: #0c5460; background-color: #d1ecf1; padding: 8px; border-radius: 4px; margin: 4px 0; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 5px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f8f9fa; }
        h1, h2 { color: #333; }
        .section { margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>复盘精灵 - 服务器环境检测</h1>
        
        <?php
        echo '<div class="section">';
        echo '<h2>1. PHP基本信息</h2>';
        echo '<div class="info">PHP版本: ' . PHP_VERSION . '</div>';
        echo '<div class="info">操作系统: ' . PHP_OS . '</div>';
        echo '<div class="info">服务器软件: ' . ($_SERVER['SERVER_SOFTWARE'] ?? '未知') . '</div>';
        echo '<div class="info">文档根目录: ' . ($_SERVER['DOCUMENT_ROOT'] ?? '未知') . '</div>';
        echo '</div>';
        
        echo '<div class="section">';
        echo '<h2>2. 关键函数检测</h2>';
        $functions = [
            'putenv' => 'putenv() - 环境变量设置',
            'exec' => 'exec() - 命令执行',
            'system' => 'system() - 系统命令',
            'shell_exec' => 'shell_exec() - Shell执行',
            'file_get_contents' => 'file_get_contents() - 文件读取',
            'file_put_contents' => 'file_put_contents() - 文件写入',
            'curl_init' => 'curl_init() - HTTP请求',
            'json_encode' => 'json_encode() - JSON编码',
            'json_decode' => 'json_decode() - JSON解码',
            'password_hash' => 'password_hash() - 密码哈希',
            'password_verify' => 'password_verify() - 密码验证'
        ];
        
        foreach ($functions as $func => $desc) {
            if (function_exists($func)) {
                echo '<div class="success">✓ ' . $desc . ' - 可用</div>';
            } else {
                echo '<div class="error">✗ ' . $desc . ' - 不可用</div>';
            }
        }
        echo '</div>';
        
        echo '<div class="section">';
        echo '<h2>3. 禁用函数检测</h2>';
        $disabled = ini_get('disable_functions');
        if (empty($disabled)) {
            echo '<div class="success">✓ 没有禁用的函数</div>';
        } else {
            echo '<div class="warning">⚠ 被禁用的函数:</div>';
            echo '<pre>' . htmlspecialchars($disabled) . '</pre>';
        }
        echo '</div>';
        
        echo '<div class="section">';
        echo '<h2>4. PHP扩展检测</h2>';
        $extensions = [
            'pdo' => 'PDO - 数据库抽象层',
            'pdo_mysql' => 'PDO MySQL - MySQL数据库支持',
            'json' => 'JSON - JSON处理',
            'curl' => 'cURL - HTTP客户端',
            'mbstring' => 'mbstring - 多字节字符串',
            'openssl' => 'OpenSSL - 加密支持',
            'zip' => 'ZIP - 压缩文件支持',
            'gd' => 'GD - 图像处理',
            'fileinfo' => 'FileInfo - 文件信息'
        ];
        
        foreach ($extensions as $ext => $desc) {
            if (extension_loaded($ext)) {
                echo '<div class="success">✓ ' . $desc . ' - 已加载</div>';
            } else {
                echo '<div class="error">✗ ' . $desc . ' - 未加载</div>';
            }
        }
        echo '</div>';
        
        echo '<div class="section">';
        echo '<h2>5. 文件权限检测</h2>';
        $directories = [
            __DIR__ . '/logs' => 'logs目录',
            __DIR__ . '/assets' => 'assets目录',
            __DIR__ . '/assets/uploads' => 'uploads目录',
            __DIR__ . '/config' => 'config目录'
        ];
        
        foreach ($directories as $path => $name) {
            if (is_dir($path)) {
                if (is_writable($path)) {
                    echo '<div class="success">✓ ' . $name . ' - 存在且可写</div>';
                } else {
                    echo '<div class="warning">⚠ ' . $name . ' - 存在但不可写</div>';
                }
            } else {
                echo '<div class="error">✗ ' . $name . ' - 不存在</div>';
            }
        }
        echo '</div>';
        
        echo '<div class="section">';
        echo '<h2>6. 配置建议</h2>';
        $memory_limit = ini_get('memory_limit');
        $upload_max = ini_get('upload_max_filesize');
        $post_max = ini_get('post_max_size');
        $max_execution = ini_get('max_execution_time');
        
        echo '<table>';
        echo '<tr><th>配置项</th><th>当前值</th><th>建议值</th><th>状态</th></tr>';
        
        $configs = [
            ['memory_limit', $memory_limit, '128M', intval($memory_limit) >= 128],
            ['upload_max_filesize', $upload_max, '10M', intval($upload_max) >= 10],
            ['post_max_size', $post_max, '12M', intval($post_max) >= 12],
            ['max_execution_time', $max_execution, '60', intval($max_execution) >= 60]
        ];
        
        foreach ($configs as $config) {
            $status = $config[3] ? '<span class="success">✓</span>' : '<span class="warning">⚠</span>';
            echo '<tr>';
            echo '<td>' . $config[0] . '</td>';
            echo '<td>' . $config[1] . '</td>';
            echo '<td>' . $config[2] . '</td>';
            echo '<td>' . $status . '</td>';
            echo '</tr>';
        }
        echo '</table>';
        echo '</div>';
        
        echo '<div class="section">';
        echo '<h2>7. 数据库连接测试</h2>';
        try {
            // 尝试包含配置文件
            if (file_exists(__DIR__ . '/config/config.php')) {
                require_once __DIR__ . '/config/config.php';
                echo '<div class="success">✓ 配置文件加载成功</div>';
                
                if (class_exists('Database')) {
                    $db = new Database();
                    $conn = $db->getConnection();
                    echo '<div class="success">✓ 数据库连接成功</div>';
                } else {
                    echo '<div class="error">✗ Database类不存在</div>';
                }
            } else {
                echo '<div class="warning">⚠ 配置文件不存在，跳过数据库测试</div>';
            }
        } catch (Exception $e) {
            echo '<div class="error">✗ 数据库连接失败: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
        echo '</div>';
        
        echo '<div class="section">';
        echo '<h2>检测完成</h2>';
        echo '<div class="info">检测时间: ' . date('Y-m-d H:i:s') . '</div>';
        echo '<p><a href="test_admin_login.php">→ 进行管理员登录测试</a></p>';
        echo '<p><a href="javascript:history.back()">← 返回上一页</a></p>';
        echo '</div>';
        ?>
    </div>
</body>
</html>