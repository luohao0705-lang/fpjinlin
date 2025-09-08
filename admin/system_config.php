<?php
/**
 * 管理员 - 系统配置页面
 */
require_once '../config/config.php';
require_once '../config/database.php';

// 检查管理员登录
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$db = new Database();
$admin = $db->fetchOne("SELECT * FROM admins WHERE id = ?", [$_SESSION['admin_id']]);

if (!$admin || $admin['status'] != 1) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$message = '';
$error = '';

// 获取当前配置
$configs = $db->fetchAll("SELECT * FROM system_configs ORDER BY config_key");
$configMap = [];
foreach ($configs as $config) {
    $configMap[$config['config_key']] = $config['config_value'];
}

// 处理配置更新
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'update_configs') {
        $updates = [];
        $configKeys = [
            'deepseek_api_key' => 'DeepSeek API密钥',
            'deepseek_api_url' => 'DeepSeek API地址',
            'qwen_omni_api_key' => 'Qwen-Omni API密钥',
            'qwen_omni_api_url' => 'Qwen-Omni API地址',
            'whisper_model_path' => 'Whisper模型路径',
            'oss_bucket' => 'OSS存储桶名称',
            'oss_endpoint' => 'OSS端点',
            'oss_access_key' => 'OSS访问密钥',
            'oss_secret_key' => 'OSS秘密密钥',
            'video_analysis_cost_coins' => '视频分析消费精灵币数量',
            'max_video_duration' => '最大视频时长(秒)',
            'video_segment_duration' => '视频切片时长(秒)',
            'video_resolution' => '视频转码分辨率',
            'video_bitrate' => '视频转码码率',
            'audio_bitrate' => '音频转码码率',
            'max_concurrent_processing' => '最大并发处理数量',
            'video_retention_days' => '视频文件保留天数',
            'sms_access_key' => '短信AccessKey',
            'sms_access_secret' => '短信AccessSecret',
            'sms_sign_name' => '短信签名',
            'sms_template_register' => '注册短信模板',
            'sms_template_login' => '登录短信模板',
            'sms_template_analysis' => '分析完成短信模板',
            'analysis_cost_coins' => '文本分析消费精灵币数量',
            'max_upload_size' => '最大上传文件大小(MB)',
            'site_title' => '网站标题',
            'site_description' => '网站描述',
            'contact_phone' => '客服电话',
            'contact_qq' => '客服QQ'
        ];
        
        $db->beginTransaction();
        try {
            foreach ($configKeys as $key => $description) {
                $value = trim($_POST[$key] ?? '');
                
                // 验证必填项
                if (in_array($key, ['deepseek_api_key', 'analysis_cost_coins', 'video_analysis_cost_coins']) && empty($value)) {
                    throw new Exception($description . '不能为空');
                }
                
                // 验证数字类型
                if (in_array($key, ['analysis_cost_coins', 'video_analysis_cost_coins', 'max_upload_size', 'max_video_duration', 'video_segment_duration', 'max_concurrent_processing', 'video_retention_days']) && !is_numeric($value)) {
                    throw new Exception($description . '必须为数字');
                }
                
                // 更新或插入配置
                $existing = $db->fetchOne("SELECT id FROM system_configs WHERE config_key = ?", [$key]);
                if ($existing) {
                    $db->query(
                        "UPDATE system_configs SET config_value = ?, updated_at = NOW() WHERE config_key = ?",
                        [$value, $key]
                    );
                } else {
                    $db->insert(
                        "INSERT INTO system_configs (config_key, config_value, description) VALUES (?, ?, ?)",
                        [$key, $value, $description]
                    );
                }
                
                $updates[] = $key;
            }
            
            $db->commit();
            $message = '系统配置已更新';
            
            // 记录操作日志
            $operationLog = new OperationLog();
            $operationLog->log($_SESSION['admin_id'], 'system_config', '更新系统配置：' . implode(', ', $updates));
            
            // 重新加载配置
            $configs = $db->fetchAll("SELECT * FROM system_configs ORDER BY config_key");
            $configMap = [];
            foreach ($configs as $config) {
                $configMap[$config['config_key']] = $config['config_value'];
            }
            
        } catch (Exception $e) {
            $db->rollback();
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统配置 - <?php echo APP_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- 自定义样式 -->
    <link href="../assets/css/style.css" rel="stylesheet">
    
    <style>
        .sidebar {
            min-height: calc(100vh - 56px);
            background-color: #343a40;
        }
        .sidebar .nav-link {
            color: #adb5bd;
            padding: 0.75rem 1rem;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: #fff;
            background-color: #495057;
        }
        .main-content {
            background-color: #f8f9fa;
            min-height: calc(100vh - 56px);
        }
        .config-section {
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
    <!-- 顶部导航 -->
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-cog me-2"></i><?php echo APP_NAME; ?> 管理后台
            </a>
            
            <div class="navbar-nav flex-row">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle text-light" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-shield me-1"></i><?php echo htmlspecialchars($admin['real_name'] ?: $admin['username']); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="../index.php" target="_blank"><i class="fas fa-external-link-alt me-2"></i>前台首页</a></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>退出登录</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- 侧边栏 -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="index.php">
                                <i class="fas fa-tachometer-alt me-2"></i>仪表盘
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="users.php">
                                <i class="fas fa-users me-2"></i>用户管理
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="orders.php">
                                <i class="fas fa-list-alt me-2"></i>订单管理
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="exchange_codes.php">
                                <i class="fas fa-ticket-alt me-2"></i>兑换码管理
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="system_config.php">
                                <i class="fas fa-cogs me-2"></i>系统配置
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="statistics.php">
                                <i class="fas fa-chart-bar me-2"></i>数据统计
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="logs.php">
                                <i class="fas fa-history me-2"></i>操作日志
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- 主要内容区域 -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">系统配置</h1>
                </div>

                <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="action" value="update_configs">
                    
                    <!-- AI服务配置 -->
                    <div class="config-section">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-robot me-2"></i>AI服务配置
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="deepseek_api_key" class="form-label">
                                                DeepSeek API密钥 <span class="text-danger">*</span>
                                            </label>
                                            <input type="password" class="form-control" id="deepseek_api_key" 
                                                   name="deepseek_api_key" 
                                                   value="<?php echo htmlspecialchars($configMap['deepseek_api_key'] ?? ''); ?>" 
                                                   required>
                                            <div class="form-text">用于文本分析服务</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="deepseek_api_url" class="form-label">DeepSeek API地址</label>
                                            <input type="url" class="form-control" id="deepseek_api_url" 
                                                   name="deepseek_api_url" 
                                                   value="<?php echo htmlspecialchars($configMap['deepseek_api_url'] ?? 'https://api.deepseek.com/v1/chat/completions'); ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="qwen_omni_api_key" class="form-label">Qwen-Omni API密钥</label>
                                            <input type="password" class="form-control" id="qwen_omni_api_key" 
                                                   name="qwen_omni_api_key" 
                                                   value="<?php echo htmlspecialchars($configMap['qwen_omni_api_key'] ?? ''); ?>">
                                            <div class="form-text">用于视频内容理解分析</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="qwen_omni_api_url" class="form-label">Qwen-Omni API地址</label>
                                            <input type="url" class="form-control" id="qwen_omni_api_url" 
                                                   name="qwen_omni_api_url" 
                                                   value="<?php echo htmlspecialchars($configMap['qwen_omni_api_url'] ?? 'https://dashscope.aliyuncs.com/api/v1/services/aigc/video-understanding/generation'); ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="mb-3">
                                            <label for="whisper_model_path" class="form-label">Whisper模型路径</label>
                                            <input type="text" class="form-control" id="whisper_model_path" 
                                                   name="whisper_model_path" 
                                                   value="<?php echo htmlspecialchars($configMap['whisper_model_path'] ?? '/opt/whisper/models'); ?>">
                                            <div class="form-text">Whisper语音识别模型文件路径</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 视频分析配置 -->
                    <div class="config-section">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-video me-2"></i>视频分析配置
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="video_analysis_cost_coins" class="form-label">
                                                视频分析消费精灵币数量 <span class="text-danger">*</span>
                                            </label>
                                            <input type="number" class="form-control" id="video_analysis_cost_coins" 
                                                   name="video_analysis_cost_coins" min="1" 
                                                   value="<?php echo htmlspecialchars($configMap['video_analysis_cost_coins'] ?? '50'); ?>" 
                                                   required>
                                            <div class="form-text">每次视频分析消费的精灵币数量</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="max_video_duration" class="form-label">最大视频时长(秒)</label>
                                            <input type="number" class="form-control" id="max_video_duration" 
                                                   name="max_video_duration" min="60" max="7200"
                                                   value="<?php echo htmlspecialchars($configMap['max_video_duration'] ?? '3600'); ?>">
                                            <div class="form-text">支持的最大视频时长，默认60分钟</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="video_segment_duration" class="form-label">视频切片时长(秒)</label>
                                            <input type="number" class="form-control" id="video_segment_duration" 
                                                   name="video_segment_duration" min="30" max="300"
                                                   value="<?php echo htmlspecialchars($configMap['video_segment_duration'] ?? '120'); ?>">
                                            <div class="form-text">视频切片处理时长，默认2分钟</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="video_resolution" class="form-label">视频转码分辨率</label>
                                            <select class="form-control" id="video_resolution" name="video_resolution">
                                                <option value="480p" <?php echo ($configMap['video_resolution'] ?? '720p') == '480p' ? 'selected' : ''; ?>>480p</option>
                                                <option value="720p" <?php echo ($configMap['video_resolution'] ?? '720p') == '720p' ? 'selected' : ''; ?>>720p</option>
                                                <option value="1080p" <?php echo ($configMap['video_resolution'] ?? '720p') == '1080p' ? 'selected' : ''; ?>>1080p</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="video_bitrate" class="form-label">视频转码码率</label>
                                            <input type="text" class="form-control" id="video_bitrate" 
                                                   name="video_bitrate" 
                                                   value="<?php echo htmlspecialchars($configMap['video_bitrate'] ?? '1500k'); ?>">
                                            <div class="form-text">如：1500k, 2000k</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="audio_bitrate" class="form-label">音频转码码率</label>
                                            <input type="text" class="form-control" id="audio_bitrate" 
                                                   name="audio_bitrate" 
                                                   value="<?php echo htmlspecialchars($configMap['audio_bitrate'] ?? '64k'); ?>">
                                            <div class="form-text">如：64k, 128k</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="max_concurrent_processing" class="form-label">最大并发处理数量</label>
                                            <input type="number" class="form-control" id="max_concurrent_processing" 
                                                   name="max_concurrent_processing" min="1" max="10"
                                                   value="<?php echo htmlspecialchars($configMap['max_concurrent_processing'] ?? '3'); ?>">
                                            <div class="form-text">同时处理的视频分析任务数量</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="video_retention_days" class="form-label">视频文件保留天数</label>
                                            <input type="number" class="form-control" id="video_retention_days" 
                                                   name="video_retention_days" min="1" max="365"
                                                   value="<?php echo htmlspecialchars($configMap['video_retention_days'] ?? '30'); ?>">
                                            <div class="form-text">视频文件在服务器上的保留时间</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- OSS存储配置 -->
                    <div class="config-section">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-cloud me-2"></i>OSS存储配置
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="oss_bucket" class="form-label">OSS存储桶名称</label>
                                            <input type="text" class="form-control" id="oss_bucket" 
                                                   name="oss_bucket" 
                                                   value="<?php echo htmlspecialchars($configMap['oss_bucket'] ?? ''); ?>">
                                            <div class="form-text">阿里云OSS存储桶名称</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="oss_endpoint" class="form-label">OSS端点</label>
                                            <input type="text" class="form-control" id="oss_endpoint" 
                                                   name="oss_endpoint" 
                                                   value="<?php echo htmlspecialchars($configMap['oss_endpoint'] ?? ''); ?>">
                                            <div class="form-text">如：oss-cn-hangzhou.aliyuncs.com</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="oss_access_key" class="form-label">OSS访问密钥</label>
                                            <input type="password" class="form-control" id="oss_access_key" 
                                                   name="oss_access_key" 
                                                   value="<?php echo htmlspecialchars($configMap['oss_access_key'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="oss_secret_key" class="form-label">OSS秘密密钥</label>
                                            <input type="password" class="form-control" id="oss_secret_key" 
                                                   name="oss_secret_key" 
                                                   value="<?php echo htmlspecialchars($configMap['oss_secret_key'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 短信服务配置 -->
                    <div class="config-section">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-sms me-2"></i>短信服务配置
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="sms_access_key" class="form-label">AccessKey</label>
                                            <input type="text" class="form-control" id="sms_access_key" 
                                                   name="sms_access_key" 
                                                   value="<?php echo htmlspecialchars($configMap['sms_access_key'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="sms_access_secret" class="form-label">AccessSecret</label>
                                            <input type="password" class="form-control" id="sms_access_secret" 
                                                   name="sms_access_secret" 
                                                   value="<?php echo htmlspecialchars($configMap['sms_access_secret'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="sms_sign_name" class="form-label">短信签名</label>
                                            <input type="text" class="form-control" id="sms_sign_name" 
                                                   name="sms_sign_name" 
                                                   value="<?php echo htmlspecialchars($configMap['sms_sign_name'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="sms_template_register" class="form-label">注册短信模板</label>
                                            <input type="text" class="form-control" id="sms_template_register" 
                                                   name="sms_template_register" 
                                                   value="<?php echo htmlspecialchars($configMap['sms_template_register'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="sms_template_login" class="form-label">登录短信模板</label>
                                            <input type="text" class="form-control" id="sms_template_login" 
                                                   name="sms_template_login" 
                                                   value="<?php echo htmlspecialchars($configMap['sms_template_login'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="sms_template_analysis" class="form-label">分析完成短信模板</label>
                                            <input type="text" class="form-control" id="sms_template_analysis" 
                                                   name="sms_template_analysis" 
                                                   value="<?php echo htmlspecialchars($configMap['sms_template_analysis'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 业务配置 -->
                    <div class="config-section">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-business-time me-2"></i>业务配置
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="analysis_cost_coins" class="form-label">
                                                分析消费精灵币数量 <span class="text-danger">*</span>
                                            </label>
                                            <input type="number" class="form-control" id="analysis_cost_coins" 
                                                   name="analysis_cost_coins" min="1" 
                                                   value="<?php echo htmlspecialchars($configMap['analysis_cost_coins'] ?? '10'); ?>" 
                                                   required>
                                            <div class="form-text">每次分析消费的精灵币数量</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="max_upload_size" class="form-label">最大上传文件大小(MB)</label>
                                            <input type="number" class="form-control" id="max_upload_size" 
                                                   name="max_upload_size" min="1" max="100"
                                                   value="<?php echo htmlspecialchars($configMap['max_upload_size'] ?? '10'); ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 网站信息配置 -->
                    <div class="config-section">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-globe me-2"></i>网站信息配置
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="site_title" class="form-label">网站标题</label>
                                            <input type="text" class="form-control" id="site_title" 
                                                   name="site_title" 
                                                   value="<?php echo htmlspecialchars($configMap['site_title'] ?? '复盘精灵'); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="site_description" class="form-label">网站描述</label>
                                            <input type="text" class="form-control" id="site_description" 
                                                   name="site_description" 
                                                   value="<?php echo htmlspecialchars($configMap['site_description'] ?? '专业的视频号直播复盘分析平台'); ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="contact_phone" class="form-label">客服电话</label>
                                            <input type="tel" class="form-control" id="contact_phone" 
                                                   name="contact_phone" 
                                                   value="<?php echo htmlspecialchars($configMap['contact_phone'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="contact_qq" class="form-label">客服QQ</label>
                                            <input type="text" class="form-control" id="contact_qq" 
                                                   name="contact_qq" 
                                                   value="<?php echo htmlspecialchars($configMap['contact_qq'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 保存按钮 -->
                    <div class="text-center mb-4">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save me-2"></i>保存配置
                        </button>
                        <a href="index.php" class="btn btn-outline-secondary btn-lg ms-3">
                            <i class="fas fa-arrow-left me-2"></i>返回仪表盘
                        </a>
                    </div>
                </form>

                <!-- 配置说明 -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-info-circle me-2"></i>配置说明
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <h6>AI服务配置</h6>
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-check text-success me-2"></i>DeepSeek：用于文本分析</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Qwen-Omni：用于视频理解</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Whisper：用于语音识别</li>
                                </ul>
                            </div>
                            <div class="col-md-4">
                                <h6>视频分析配置</h6>
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-check text-success me-2"></i>消费精灵币：视频分析比文本分析消耗更多</li>
                                    <li><i class="fas fa-check text-success me-2"></i>视频处理：自动下载、转码、切片</li>
                                    <li><i class="fas fa-check text-success me-2"></i>并发限制：控制服务器负载</li>
                                </ul>
                            </div>
                            <div class="col-md-4">
                                <h6>OSS存储配置</h6>
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-check text-success me-2"></i>存储视频文件和处理结果</li>
                                    <li><i class="fas fa-check text-success me-2"></i>需要阿里云OSS服务</li>
                                    <li><i class="fas fa-check text-success me-2"></i>支持自动清理过期文件</li>
                                </ul>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <h6>短信服务配置</h6>
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-check text-success me-2"></i>使用阿里云短信服务</li>
                                    <li><i class="fas fa-check text-success me-2"></i>模板变量：{code} 为验证码</li>
                                    <li><i class="fas fa-check text-success me-2"></i>签名需要在阿里云控制台申请</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6>业务配置</h6>
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-check text-success me-2"></i>精灵币消费：每次分析的消费数量</li>
                                    <li><i class="fas fa-check text-success me-2"></i>上传限制：单个文件最大大小</li>
                                </ul>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <h6>网站信息</h6>
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-check text-success me-2"></i>用于页面标题和SEO</li>
                                    <li><i class="fas fa-check text-success me-2"></i>客服信息显示在前台页面</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 显示/隐藏密码
        document.querySelectorAll('input[type="password"]').forEach(input => {
            const container = input.parentElement;
            const toggleBtn = document.createElement('button');
            toggleBtn.type = 'button';
            toggleBtn.className = 'btn btn-outline-secondary position-absolute';
            toggleBtn.style.cssText = 'right: 10px; top: 50%; transform: translateY(-50%); border: none; z-index: 10;';
            toggleBtn.innerHTML = '<i class="fas fa-eye"></i>';
            
            container.style.position = 'relative';
            input.style.paddingRight = '45px';
            container.appendChild(toggleBtn);
            
            toggleBtn.addEventListener('click', function() {
                const type = input.type === 'password' ? 'text' : 'password';
                input.type = type;
                this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
            });
        });

        // 表单验证
        (function() {
            'use strict';
            window.addEventListener('load', function() {
                const form = document.querySelector('form');
                form.addEventListener('submit', function(event) {
                    if (form.checkValidity() === false) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            }, false);
        })();
    </script>
</body>
</html>