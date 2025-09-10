<?php
/**
 * PHP配置优化脚本
 */

echo "⚙️ PHP配置优化\n";
echo "=============\n\n";

// 当前配置
echo "当前PHP配置:\n";
echo "内存限制: " . ini_get('memory_limit') . "\n";
echo "最大执行时间: " . ini_get('max_execution_time') . "\n";
echo "最大输入时间: " . ini_get('max_input_time') . "\n";
echo "POST最大大小: " . ini_get('post_max_size') . "\n";
echo "上传最大文件大小: " . ini_get('upload_max_filesize') . "\n";

// 建议的配置
$recommendedConfig = [
    'memory_limit' => '512M',
    'max_execution_time' => '300',
    'max_input_time' => '300',
    'post_max_size' => '100M',
    'upload_max_filesize' => '100M',
    'max_file_uploads' => '20',
    'default_socket_timeout' => '60'
];

echo "\n建议的配置:\n";
foreach ($recommendedConfig as $key => $value) {
    echo "$key = $value\n";
}

// 生成php.ini配置
echo "\n生成php.ini配置片段:\n";
echo "========================\n";
foreach ($recommendedConfig as $key => $value) {
    echo "$key = $value\n";
}

echo "\n使用方法:\n";
echo "1. 找到php.ini文件: php --ini\n";
echo "2. 编辑php.ini文件，添加上述配置\n";
echo "3. 重启Web服务器\n";

// 检查当前配置是否满足要求
echo "\n配置检查:\n";
$issues = [];

if (ini_get('memory_limit') < 512) {
    $issues[] = "内存限制过低，建议设置为512M+";
}

if (ini_get('max_execution_time') < 300) {
    $issues[] = "最大执行时间过短，建议设置为300秒+";
}

if (ini_get('max_input_time') < 300) {
    $issues[] = "最大输入时间过短，建议设置为300秒+";
}

if (empty($issues)) {
    echo "✅ PHP配置符合要求\n";
} else {
    echo "⚠️ 发现以下问题:\n";
    foreach ($issues as $issue) {
        echo "- $issue\n";
    }
}

echo "\nWeb服务器重启命令:\n";
echo "Apache: systemctl restart httpd 或 service apache2 restart\n";
echo "Nginx: systemctl restart nginx 或 service nginx restart\n";
?>
