<?php
/**
 * 存储状态检查页面
 * 复盘精灵系统 - 后台管理
 */
require_once '../config/config.php';
require_once '../config/database.php';

// 启动session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 检查管理员登录
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require_once '../includes/classes/StorageManager.php';

$storageManager = new StorageManager();
$storageInfo = [];

// 检查存储状态
try {
    $storageInfo['oss_configured'] = !empty(getSystemConfig('oss_bucket', '')) && 
                                   !empty(getSystemConfig('oss_endpoint', '')) && 
                                   !empty(getSystemConfig('oss_access_key', '')) && 
                                   !empty(getSystemConfig('oss_secret_key', ''));
    
    $storageInfo['local_path'] = '/www/wwwroot/hsh6.com/storage';
    $storageInfo['local_writable'] = is_writable($storageInfo['local_path']);
    
    // 检查存储目录
    $storageInfo['directories'] = [
        'videos' => is_dir($storageInfo['local_path'] . '/videos'),
        'segments' => is_dir($storageInfo['local_path'] . '/segments'),
        'reports' => is_dir($storageInfo['local_path'] . '/reports'),
        'temp' => is_dir($storageInfo['local_path'] . '/temp'),
    ];
    
    // 统计文件数量
    $db = new Database();
    $storageInfo['file_counts'] = [
        'video_files' => $db->fetchOne("SELECT COUNT(*) as count FROM video_files")['count'],
        'video_segments' => $db->fetchOne("SELECT COUNT(*) as count FROM video_segments")['count'],
        'analysis_results' => $db->fetchOne("SELECT COUNT(*) as count FROM video_analysis_results")['count'],
    ];
    
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>存储状态 - 复盘精灵管理后台</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- 侧边栏 -->
            <div class="col-md-2 bg-dark min-vh-100">
                <div class="p-3">
                    <h5 class="text-white mb-4">复盘精灵 管理后台</h5>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link text-light" href="index.php">
                                <i class="fas fa-tachometer-alt me-2"></i>仪表盘
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-light" href="users.php">
                                <i class="fas fa-users me-2"></i>用户管理
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-light" href="orders.php">
                                <i class="fas fa-file-alt me-2"></i>文本分析订单
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-light" href="video_orders.php">
                                <i class="fas fa-video me-2"></i>视频分析订单
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-light" href="exchange_codes.php">
                                <i class="fas fa-gift me-2"></i>兑换码管理
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-light" href="system_config.php">
                                <i class="fas fa-cog me-2"></i>系统配置
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-warning active" href="storage_status.php">
                                <i class="fas fa-hdd me-2"></i>存储状态
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            
            <!-- 主内容区 -->
            <div class="col-md-10">
                <div class="p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2><i class="fas fa-hdd me-2"></i>存储状态</h2>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>返回仪表盘
                        </a>
                    </div>
                    
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>错误: <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- OSS配置状态 -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-cloud me-2"></i>阿里云OSS配置</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p><strong>配置状态:</strong> 
                                                <?php if ($storageInfo['oss_configured']): ?>
                                                    <span class="badge bg-success">已配置</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">未配置</span>
                                                <?php endif; ?>
                                            </p>
                                            <p><strong>存储桶:</strong> <?php echo htmlspecialchars(getSystemConfig('oss_bucket', '未设置')); ?></p>
                                            <p><strong>访问域名:</strong> <?php echo htmlspecialchars(getSystemConfig('oss_endpoint', '未设置')); ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <p><strong>AccessKey:</strong> 
                                                <?php echo getSystemConfig('oss_access_key', '') ? '已设置' : '未设置'; ?>
                                            </p>
                                            <p><strong>SecretKey:</strong> 
                                                <?php echo getSystemConfig('oss_secret_key', '') ? '已设置' : '未设置'; ?>
                                            </p>
                                        </div>
                                    </div>
                                    <?php if (!$storageInfo['oss_configured']): ?>
                                        <div class="alert alert-info mt-3">
                                            <i class="fas fa-info-circle me-2"></i>
                                            OSS未配置，系统将使用本地存储。如需使用OSS，请在<a href="system_config.php">系统配置</a>中设置相关参数。
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 本地存储状态 -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-server me-2"></i>本地存储状态</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p><strong>存储路径:</strong> <?php echo htmlspecialchars($storageInfo['local_path']); ?></p>
                                            <p><strong>写入权限:</strong> 
                                                <?php if ($storageInfo['local_writable']): ?>
                                                    <span class="badge bg-success">可写</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">不可写</span>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                        <div class="col-md-6">
                                            <h6>目录状态:</h6>
                                            <ul class="list-unstyled">
                                                <?php foreach ($storageInfo['directories'] as $dir => $exists): ?>
                                                    <li>
                                                        <i class="fas fa-folder<?php echo $exists ? '-check' : '-times'; ?> me-2 text-<?php echo $exists ? 'success' : 'danger'; ?>"></i>
                                                        <?php echo ucfirst($dir); ?>: 
                                                        <?php if ($exists): ?>
                                                            <span class="badge bg-success">存在</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger">不存在</span>
                                                        <?php endif; ?>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 文件统计 -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>文件统计</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <?php foreach ($storageInfo['file_counts'] as $type => $count): ?>
                                            <div class="col-md-4">
                                                <div class="text-center">
                                                    <h3 class="text-primary"><?php echo $count; ?></h3>
                                                    <p class="text-muted"><?php echo ucfirst(str_replace('_', ' ', $type)); ?></p>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 存储测试 -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-vial me-2"></i>存储测试</h5>
                                </div>
                                <div class="card-body">
                                    <button class="btn btn-primary" onclick="testStorage()">
                                        <i class="fas fa-play me-2"></i>测试存储功能
                                    </button>
                                    <div id="testResult" class="mt-3"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function testStorage() {
            const btn = document.querySelector('button[onclick="testStorage()"]');
            const resultDiv = document.getElementById('testResult');
            
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>测试中...';
            
            fetch('api/test_storage.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    resultDiv.innerHTML = `
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>存储测试成功！
                            <br><small>${data.message}</small>
                        </div>
                    `;
                } else {
                    resultDiv.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-times-circle me-2"></i>存储测试失败！
                            <br><small>${data.message}</small>
                        </div>
                    `;
                }
            })
            .catch(error => {
                resultDiv.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-times-circle me-2"></i>测试请求失败！
                        <br><small>${error.message}</small>
                    </div>
                `;
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-play me-2"></i>测试存储功能';
            });
        }
    </script>
</body>
</html>
