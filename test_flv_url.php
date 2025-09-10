<?php
/**
 * 测试FLV地址有效性
 */

echo "🔍 测试FLV地址有效性\n";
echo "==================\n\n";

// 真实的抖音FLV地址
$flvUrl = 'http://pull-flv-l26.douyincdn.com/stage/stream-117942867085230219_or4.flv?arch_hrchy=w1&exp_hrchy=w1&expire=68ca7511&major_anchor_level=common&sign=8dedf99c273092e6389e3dbbad9ed1b2&t_id=037-20250910164505061DD0AF4B1E4DCD2B27-8zG4Wv&unique_id=stream-117942867085230219_139_flv_or4';

echo "FLV地址: $flvUrl\n";
echo "==================\n\n";

// 1. 测试网络连接
echo "1. 测试网络连接...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $flvUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_NOBODY, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$contentLength = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "❌ 网络错误: $error\n";
} else {
    echo "✅ 网络连接正常\n";
    echo "HTTP状态码: $httpCode\n";
    echo "内容类型: $contentType\n";
    echo "内容长度: $contentLength 字节\n";
}
echo "\n";

// 2. 测试FFmpeg探测
echo "2. 测试FFmpeg探测...\n";
$probeCommand = "ffprobe -v quiet -print_format json -show_format -show_streams " . escapeshellarg($flvUrl);
$output = [];
$returnCode = 0;
exec($probeCommand, $output, $returnCode);

if ($returnCode === 0) {
    echo "✅ FFmpeg探测成功\n";
    $json = implode('', $output);
    $data = json_decode($json, true);
    
    if ($data && isset($data['format'])) {
        echo "格式信息:\n";
        echo "  格式名称: " . ($data['format']['format_name'] ?? 'unknown') . "\n";
        echo "  时长: " . ($data['format']['duration'] ?? 'unknown') . " 秒\n";
        echo "  文件大小: " . ($data['format']['size'] ?? 'unknown') . " 字节\n";
        echo "  码率: " . ($data['format']['bit_rate'] ?? 'unknown') . " bps\n";
    }
    
    if ($data && isset($data['streams'][0])) {
        $stream = $data['streams'][0];
        echo "视频流信息:\n";
        echo "  编码: " . ($stream['codec_name'] ?? 'unknown') . "\n";
        echo "  分辨率: " . ($stream['width'] ?? 'unknown') . "x" . ($stream['height'] ?? 'unknown') . "\n";
        echo "  帧率: " . ($stream['r_frame_rate'] ?? 'unknown') . "\n";
    }
} else {
    echo "❌ FFmpeg探测失败 (返回码: $returnCode)\n";
    echo "错误信息: " . implode("\n", $output) . "\n";
}
echo "\n";

// 3. 测试下载一小段
echo "3. 测试下载一小段...\n";
$testFile = '/tmp/test_flv_sample.flv';
$downloadCommand = "wget --user-agent='Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36' --timeout=10 --tries=1 --output-document=" . escapeshellarg($testFile) . " " . escapeshellarg($flvUrl) . " 2>&1";
$output = [];
$returnCode = 0;
exec($downloadCommand, $output, $returnCode);

if ($returnCode === 0 && file_exists($testFile)) {
    $fileSize = filesize($testFile);
    echo "✅ 下载成功\n";
    echo "文件大小: " . number_format($fileSize) . " 字节\n";
    
    // 检查文件类型
    $fileType = shell_exec("file " . escapeshellarg($testFile));
    echo "文件类型: " . trim($fileType) . "\n";
    
    // 清理测试文件
    unlink($testFile);
} else {
    echo "❌ 下载失败 (返回码: $returnCode)\n";
    echo "错误信息: " . implode("\n", $output) . "\n";
}
echo "\n";

// 4. 测试FFmpeg录制
echo "4. 测试FFmpeg录制...\n";
$outputFile = '/tmp/test_recording.mp4';
$recordCommand = "ffmpeg -i " . escapeshellarg($flvUrl) . " -t 5 -c copy " . escapeshellarg($outputFile) . " -y 2>&1";
$output = [];
$returnCode = 0;
exec($recordCommand, $output, $returnCode);

if ($returnCode === 0 && file_exists($outputFile)) {
    $fileSize = filesize($outputFile);
    echo "✅ 录制成功\n";
    echo "输出文件大小: " . number_format($fileSize) . " 字节\n";
    
    // 清理测试文件
    unlink($outputFile);
} else {
    echo "❌ 录制失败 (返回码: $returnCode)\n";
    echo "错误信息: " . implode("\n", $output) . "\n";
}

echo "\n🔍 测试完成！\n";
?>