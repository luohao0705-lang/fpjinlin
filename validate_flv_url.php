<?php
/**
 * 验证FLV地址有效性
 */

echo "🔍 验证FLV地址有效性\n";
echo "==================\n\n";

// 测试地址列表
$testUrls = [
    'http://pull-l3.douyincdn.com/third/stream-406142351343616',
    'http://pull-l3.douyincdn.com/third/stream-4061423513436164',
    'http://pull-flv-l26.douyincdn.com/stage/stream-117942867085230219_or4.flv?arch_hrchy=w1&exp_hrchy=w1&expire=68ca7511&major_anchor_level=common&sign=8dedf99c273092e6389e3dbbad9ed1b2&t_id=037-20250910164505061DD0AF4B1E4DCD2B27-8zG4Wv&unique_id=stream-117942867085230219_139_flv_or4'
];

foreach ($testUrls as $index => $url) {
    echo "测试地址 " . ($index + 1) . ": $url\n";
    
    // 1. 检查URL格式
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        echo "❌ URL格式无效\n\n";
        continue;
    }
    
    // 2. 检查HTTP状态
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Referer: https://live.douyin.com/'
    ]);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    
    echo "HTTP状态码: $httpCode\n";
    echo "内容类型: $contentType\n";
    
    if ($httpCode === 200) {
        echo "✅ 地址可访问\n";
        
        // 3. 测试FFmpeg是否能处理
        echo "测试FFmpeg处理...\n";
        $output = [];
        $returnCode = 0;
        exec("ffmpeg -i " . escapeshellarg($url) . " -t 5 -f null - 2>&1", $output, $returnCode);
        
        if ($returnCode === 0) {
            echo "✅ FFmpeg可以处理此地址\n";
        } else {
            echo "❌ FFmpeg处理失败 (返回码: $returnCode)\n";
            echo "错误信息: " . implode("\n", array_slice($output, -3)) . "\n";
        }
    } else {
        echo "❌ 地址不可访问\n";
    }
    
    echo "----------------------------------------\n\n";
}

echo "🎯 建议使用可访问的FLV地址进行录制\n";
?>
