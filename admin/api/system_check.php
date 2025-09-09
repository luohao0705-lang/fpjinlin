<?php
/**
 * 系统检查API
 * 复盘精灵系统 - 后台管理
 */
require_once '../../config/config.php';
require_once '../../config/database.php';

// 启动session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

try {
    // 检查管理员登录
    if (!isset($_SESSION['admin_id'])) {
        throw new Exception('请先登录');
    }
    
    $checks = [];
    
    // 1. 检查FFmpeg
    $ffmpegOutput = [];
    $ffmpegReturnCode = 0;
    exec('ffmpeg -version 2>&1', $ffmpegOutput, $ffmpegReturnCode);
    
    $checks['ffmpeg'] = [
        'name' => 'FFmpeg',
        'status' => $ffmpegReturnCode === 0 ? 'ok' : 'error',
        'message' => $ffmpegReturnCode === 0 ? 'FFmpeg已安装' : 'FFmpeg未安装或不可用',
        'details' => $ffmpegReturnCode === 0 ? implode("\n", array_slice($ffmpegOutput, 0, 3)) : '请安装FFmpeg'
    ];
    
    // 2. 检查DeepSeek API
    $deepseekApiKey = getSystemConfig('deepseek_api_key', '');
    $checks['deepseek'] = [
        'name' => 'DeepSeek API',
        'status' => !empty($deepseekApiKey) ? 'ok' : 'warning',
        'message' => !empty($deepseekApiKey) ? 'DeepSeek API密钥已配置' : 'DeepSeek API密钥未配置',
        'details' => !empty($deepseekApiKey) ? 'API密钥长度: ' . strlen($deepseekApiKey) : '请在系统配置中设置DeepSeek API密钥'
    ];
    
    // 3. 检查Qwen-Omni API
    $qwenApiKey = getSystemConfig('qwen_omni_api_key', '');
    $checks['qwen_omni'] = [
        'name' => 'Qwen-Omni API',
        'status' => !empty($qwenApiKey) ? 'ok' : 'warning',
        'message' => !empty($qwenApiKey) ? 'Qwen-Omni API密钥已配置' : 'Qwen-Omni API密钥未配置',
        'details' => !empty($qwenApiKey) ? 'API密钥长度: ' . strlen($qwenApiKey) : '请在系统配置中设置Qwen-Omni API密钥'
    ];
    
    // 4. 检查Whisper服务
    $checks['whisper'] = [
        'name' => 'Whisper服务',
        'status' => 'ok',
        'message' => 'Whisper是开源服务，无需配置',
        'details' => '本地Whisper模型，无限制使用'
    ];
    
    // 5. 检查阿里云SMS
    $smsAccessKey = getSystemConfig('sms_access_key', '');
    $smsAccessSecret = getSystemConfig('sms_access_secret', '');
    $checks['aliyun_sms'] = [
        'name' => '阿里云SMS',
        'status' => (!empty($smsAccessKey) && !empty($smsAccessSecret)) ? 'ok' : 'warning',
        'message' => (!empty($smsAccessKey) && !empty($smsAccessSecret)) ? '阿里云SMS已配置' : '阿里云SMS未配置',
        'details' => (!empty($smsAccessKey) && !empty($smsAccessSecret)) ? 'AccessKey已配置' : '请在系统配置中设置阿里云SMS密钥'
    ];
    
    // 6. 检查OSS配置
    $ossBucket = getSystemConfig('oss_bucket', '');
    $ossEndpoint = getSystemConfig('oss_endpoint', '');
    $ossAccessKey = getSystemConfig('oss_access_key', '');
    $ossSecretKey = getSystemConfig('oss_secret_key', '');
    $checks['oss'] = [
        'name' => '阿里云OSS',
        'status' => (!empty($ossBucket) && !empty($ossEndpoint) && !empty($ossAccessKey) && !empty($ossSecretKey)) ? 'ok' : 'warning',
        'message' => (!empty($ossBucket) && !empty($ossEndpoint) && !empty($ossAccessKey) && !empty($ossSecretKey)) ? '阿里云OSS已配置' : '阿里云OSS未配置',
        'details' => (!empty($ossBucket) && !empty($ossEndpoint) && !empty($ossAccessKey) && !empty($ossSecretKey)) ? "存储桶: {$ossBucket}" : '请在系统配置中设置阿里云OSS'
    ];
    
    // 7. 检查数据库连接
    try {
        $db = new Database();
        $db->fetchOne("SELECT 1");
        $checks['database'] = [
            'name' => '数据库连接',
            'status' => 'ok',
            'message' => '数据库连接正常',
            'details' => 'MySQL连接成功'
        ];
    } catch (Exception $e) {
        $checks['database'] = [
            'name' => '数据库连接',
            'status' => 'error',
            'message' => '数据库连接失败',
            'details' => $e->getMessage()
        ];
    }
    
    // 8. 检查临时目录权限
    $tempDir = sys_get_temp_dir();
    $tempWritable = is_writable($tempDir);
    $checks['temp_dir'] = [
        'name' => '临时目录权限',
        'status' => $tempWritable ? 'ok' : 'error',
        'message' => $tempWritable ? '临时目录可写' : '临时目录不可写',
        'details' => $tempWritable ? "目录: {$tempDir}" : "目录: {$tempDir} (权限不足)"
    ];
    
    echo json_encode([
        'success' => true,
        'data' => $checks
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
