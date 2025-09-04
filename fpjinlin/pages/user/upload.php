<?php
/**
 * 文件上传页面
 * 复盘精灵系统
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/User.php';
require_once __DIR__ . '/../../includes/AnalysisOrder.php';

$userManager = new User();
$orderManager = new AnalysisOrder();

// 检查登录状态
$userManager->requireLogin();

$currentUser = $userManager->getCurrentUser();

// 获取订单ID
$orderId = intval($_GET['order_id'] ?? 0);
if (!$orderId) {
    header('Location: index.php');
    exit;
}

// 验证订单权限
$order = $orderManager->getUserOrder($currentUser['id'], $orderId);
if (!$order) {
    header('Location: index.php');
    exit;
}

// 获取已上传的截图
$screenshots = $orderManager->getOrderScreenshots($orderId);
$uploadedTypes = array_column($screenshots, 'image_type');

$error = '';
$success = '';

// 处理文件上传
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'upload_screenshots':
                    $uploadedFiles = $orderManager->uploadScreenshots($orderId, $_FILES);
                    $success = '截图上传成功！';
                    // 刷新页面显示最新状态
                    header("Location: upload.php?order_id={$orderId}");
                    exit;
                    break;
                    
                case 'upload_cover':
                    if (isset($_FILES['cover']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK) {
                        $coverPath = $orderManager->uploadCover($orderId, $_FILES['cover']);
                        $success = '封面上传成功！';
                        header("Location: upload.php?order_id={$orderId}");
                        exit;
                    } else {
                        throw new Exception('请选择封面图片');
                    }
                    break;
                    
                case 'submit_analysis':
                    $orderManager->submitForAnalysis($orderId);
                    $success = '订单已提交分析，请耐心等待结果！';
                    header("Location: orders.php");
                    exit;
                    break;
            }
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// 重新获取订单信息
$order = $orderManager->getUserOrder($currentUser['id'], $orderId);
$screenshots = $orderManager->getOrderScreenshots($orderId);
$uploadedTypes = array_column($screenshots, 'image_type');
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>上传文件 - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        body {
            background-color: #f8f9fa;
        }
        
        .navbar {
            background: var(--primary-gradient);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .main-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border: none;
        }
        
        .upload-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .upload-item {
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 1rem;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .upload-item:hover {
            border-color: #667eea;
            background-color: rgba(102, 126, 234, 0.05);
        }
        
        .upload-item.uploaded {
            border-color: #28a745;
            background-color: rgba(40, 167, 69, 0.1);
            border-style: solid;
        }
        
        .upload-preview {
            max-width: 100%;
            max-height: 200px;
            border-radius: 8px;
            margin-bottom: 0.5rem;
        }
        
        .progress-section {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }
        
        .btn-gradient {
            background: var(--primary-gradient);
            border: none;
            color: white;
            padding: 0.75rem 2rem;
            border-radius: 25px;
            font-weight: 500;
        }
        
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
        }
        
        .step {
            flex: 1;
            text-align: center;
            position: relative;
        }
        
        .step:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 20px;
            right: -50%;
            width: 100%;
            height: 2px;
            background-color: #dee2e6;
            z-index: 1;
        }
        
        .step.completed::after {
            background-color: #28a745;
        }
        
        .step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #dee2e6;
            color: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.5rem;
            position: relative;
            z-index: 2;
        }
        
        .step.completed .step-circle {
            background-color: #28a745;
            color: white;
        }
        
        .step.current .step-circle {
            background-color: #667eea;
            color: white;
        }
    </style>
</head>
<body>
    <!-- 导航栏 -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-magic"></i> <?php echo SITE_NAME; ?>
            </a>
        </div>
    </nav>
    
    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <!-- 返回按钮 -->
                <div class="mb-3">
                    <a href="orders.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> 返回订单列表
                    </a>
                </div>
                
                <div class="main-card">
                    <div class="card-header bg-white border-bottom">
                        <h4 class="mb-1">
                            <i class="bi bi-upload"></i> 上传分析文件
                        </h4>
                        <p class="text-muted mb-0">订单：<?php echo htmlspecialchars($order['title']); ?></p>
                    </div>
                    
                    <div class="card-body">
                        <!-- 步骤指示器 -->
                        <div class="step-indicator">
                            <div class="step completed">
                                <div class="step-circle">
                                    <i class="bi bi-check"></i>
                                </div>
                                <small>创建订单</small>
                            </div>
                            <div class="step current">
                                <div class="step-circle">2</div>
                                <small>上传文件</small>
                            </div>
                            <div class="step">
                                <div class="step-circle">3</div>
                                <small>AI分析</small>
                            </div>
                            <div class="step">
                                <div class="step-circle">4</div>
                                <small>查看报告</small>
                            </div>
                        </div>
                        
                        <!-- 进度显示 -->
                        <div class="progress-section text-center">
                            <h5 class="mb-3">
                                <i class="bi bi-info-circle"></i> 上传进度
                            </h5>
                            <div class="row">
                                <div class="col-md-4">
                                    <h6>数据截图</h6>
                                    <p class="mb-0"><?php echo count($screenshots); ?>/5 已上传</p>
                                </div>
                                <div class="col-md-4">
                                    <h6>封面图片</h6>
                                    <p class="mb-0"><?php echo $order['cover_image'] ? '已上传' : '未上传'; ?></p>
                                </div>
                                <div class="col-md-4">
                                    <h6>话术内容</h6>
                                    <p class="mb-0">已填写</p>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- 截图上传区域 -->
                        <div class="upload-section">
                            <h5 class="mb-3">
                                <i class="bi bi-images"></i> 直播数据截图 
                                <span class="text-danger">*</span>
                                <small class="text-muted">（需要5张截图）</small>
                            </h5>
                            
                            <form method="POST" enctype="multipart/form-data" id="screenshotsForm">
                                <input type="hidden" name="action" value="upload_screenshots">
                                
                                <div class="row">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <?php 
                                        $dataType = "data{$i}";
                                        $isUploaded = in_array($dataType, $uploadedTypes);
                                        $screenshot = null;
                                        if ($isUploaded) {
                                            foreach ($screenshots as $s) {
                                                if ($s['image_type'] === $dataType) {
                                                    $screenshot = $s;
                                                    break;
                                                }
                                            }
                                        }
                                        ?>
                                        <div class="col-md-6 col-lg-4 mb-3">
                                            <div class="upload-item <?php echo $isUploaded ? 'uploaded' : ''; ?>" 
                                                 onclick="<?php echo $isUploaded ? '' : "document.getElementById('file{$i}').click()"; ?>">
                                                
                                                <?php if ($isUploaded && $screenshot): ?>
                                                    <img src="<?php echo UPLOAD_URL . $screenshot['image_path']; ?>" 
                                                         class="upload-preview" alt="数据截图<?php echo $i; ?>">
                                                    <p class="mb-1 text-success">
                                                        <i class="bi bi-check-circle"></i> 数据截图<?php echo $i; ?>
                                                    </p>
                                                    <small class="text-muted">
                                                        <?php echo formatFileSize($screenshot['file_size']); ?>
                                                    </small>
                                                <?php else: ?>
                                                    <i class="bi bi-cloud-upload display-4 text-muted"></i>
                                                    <h6 class="mt-2">数据截图<?php echo $i; ?></h6>
                                                    <p class="text-muted small mb-0">点击上传图片</p>
                                                    <small class="text-muted">支持JPG、PNG格式，最大10MB</small>
                                                <?php endif; ?>
                                                
                                                <input type="file" id="file<?php echo $i; ?>" name="<?php echo $dataType; ?>" 
                                                       accept="image/*" style="display: none;" 
                                                       onchange="previewImage(this, <?php echo $i; ?>)">
                                            </div>
                                        </div>
                                    <?php endfor; ?>
                                </div>
                                
                                <div class="text-center mt-3">
                                    <button type="submit" class="btn btn-primary" id="uploadScreenshotsBtn">
                                        <i class="bi bi-upload"></i> 上传截图
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- 封面上传区域 -->
                        <div class="upload-section">
                            <h5 class="mb-3">
                                <i class="bi bi-image"></i> 直播封面图片
                                <small class="text-muted">（可选）</small>
                            </h5>
                            
                            <form method="POST" enctype="multipart/form-data" id="coverForm">
                                <input type="hidden" name="action" value="upload_cover">
                                
                                <div class="row justify-content-center">
                                    <div class="col-md-6">
                                        <div class="upload-item <?php echo $order['cover_image'] ? 'uploaded' : ''; ?>" 
                                             onclick="<?php echo $order['cover_image'] ? '' : "document.getElementById('coverFile').click()"; ?>">
                                            
                                            <?php if ($order['cover_image']): ?>
                                                <img src="<?php echo UPLOAD_URL . $order['cover_image']; ?>" 
                                                     class="upload-preview" alt="封面图片">
                                                <p class="mb-1 text-success">
                                                    <i class="bi bi-check-circle"></i> 封面图片
                                                </p>
                                                <small class="text-muted">已上传</small>
                                            <?php else: ?>
                                                <i class="bi bi-image display-4 text-muted"></i>
                                                <h6 class="mt-2">直播封面</h6>
                                                <p class="text-muted small mb-0">点击上传封面图片</p>
                                                <small class="text-muted">支持JPG、PNG格式，最大10MB</small>
                                            <?php endif; ?>
                                            
                                            <input type="file" id="coverFile" name="cover" 
                                                   accept="image/*" style="display: none;" 
                                                   onchange="previewCover(this)">
                                        </div>
                                        
                                        <?php if (!$order['cover_image']): ?>
                                            <div class="text-center mt-3">
                                                <button type="submit" class="btn btn-outline-primary" id="uploadCoverBtn">
                                                    <i class="bi bi-upload"></i> 上传封面
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </form>
                        </div>
                        
                        <!-- 提交分析 -->
                        <div class="text-center">
                            <?php 
                            $canSubmit = count($screenshots) >= 5 && !empty($order['own_script']);
                            $allUploaded = count($screenshots) >= 5;
                            ?>
                            
                            <?php if (!$allUploaded): ?>
                                <div class="alert alert-warning">
                                    <i class="bi bi-exclamation-triangle"></i> 
                                    请先上传完整的5张数据截图才能提交分析
                                </div>
                            <?php endif; ?>
                            
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="submit_analysis">
                                <button type="submit" class="btn btn-gradient btn-lg" 
                                        <?php echo $canSubmit ? '' : 'disabled'; ?>
                                        onclick="return confirm('确认提交订单进行AI分析？分析过程需要1-3分钟。')">
                                    <i class="bi bi-send"></i> 提交AI分析
                                </button>
                            </form>
                            
                            <?php if ($canSubmit): ?>
                                <p class="text-muted mt-2">
                                    <i class="bi bi-info-circle"></i> 
                                    提交后将开始AI分析，完成后会短信通知您
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 截图预览
        function previewImage(input, index) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                
                // 验证文件大小
                if (file.size > <?php echo UPLOAD_MAX_SIZE; ?>) {
                    alert('文件大小不能超过<?php echo formatFileSize(UPLOAD_MAX_SIZE); ?>');
                    input.value = '';
                    return;
                }
                
                // 验证文件类型
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                if (!allowedTypes.includes(file.type)) {
                    alert('只支持JPG、PNG、GIF、WebP格式的图片');
                    input.value = '';
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    const uploadItem = input.closest('.upload-item');
                    uploadItem.innerHTML = `
                        <img src="${e.target.result}" class="upload-preview" alt="数据截图${index}">
                        <p class="mb-1 text-primary">
                            <i class="bi bi-image"></i> 数据截图${index}
                        </p>
                        <small class="text-muted">${(file.size / 1024 / 1024).toFixed(2)} MB</small>
                        <input type="file" id="file${index}" name="data${index}" accept="image/*" style="display: none;" onchange="previewImage(this, ${index})">
                    `;
                    uploadItem.classList.add('uploaded');
                };
                reader.readAsDataURL(file);
                
                // 启用上传按钮
                document.getElementById('uploadScreenshotsBtn').disabled = false;
            }
        }
        
        // 封面预览
        function previewCover(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                
                // 验证文件大小
                if (file.size > <?php echo UPLOAD_MAX_SIZE; ?>) {
                    alert('文件大小不能超过<?php echo formatFileSize(UPLOAD_MAX_SIZE); ?>');
                    input.value = '';
                    return;
                }
                
                // 验证文件类型
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                if (!allowedTypes.includes(file.type)) {
                    alert('只支持JPG、PNG、GIF、WebP格式的图片');
                    input.value = '';
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    const uploadItem = input.closest('.upload-item');
                    uploadItem.innerHTML = `
                        <img src="${e.target.result}" class="upload-preview" alt="封面图片">
                        <p class="mb-1 text-primary">
                            <i class="bi bi-image"></i> 封面图片
                        </p>
                        <small class="text-muted">${(file.size / 1024 / 1024).toFixed(2)} MB</small>
                        <input type="file" id="coverFile" name="cover" accept="image/*" style="display: none;" onchange="previewCover(this)">
                    `;
                    uploadItem.classList.add('uploaded');
                };
                reader.readAsDataURL(file);
                
                // 启用上传按钮
                document.getElementById('uploadCoverBtn').disabled = false;
            }
        }
        
        // 拖拽上传
        document.querySelectorAll('.upload-item').forEach(item => {
            item.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.style.borderColor = '#667eea';
                this.style.backgroundColor = 'rgba(102, 126, 234, 0.1)';
            });
            
            item.addEventListener('dragleave', function(e) {
                e.preventDefault();
                this.style.borderColor = '#dee2e6';
                this.style.backgroundColor = '';
            });
            
            item.addEventListener('drop', function(e) {
                e.preventDefault();
                this.style.borderColor = '#dee2e6';
                this.style.backgroundColor = '';
                
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    const input = this.querySelector('input[type="file"]');
                    if (input) {
                        input.files = files;
                        input.dispatchEvent(new Event('change'));
                    }
                }
            });
        });
        
        // 表单提交验证
        document.getElementById('screenshotsForm').addEventListener('submit', function(e) {
            const fileInputs = this.querySelectorAll('input[type="file"]');
            let hasFiles = false;
            
            fileInputs.forEach(input => {
                if (input.files && input.files.length > 0) {
                    hasFiles = true;
                }
            });
            
            if (!hasFiles) {
                e.preventDefault();
                alert('请至少选择一张图片上传');
                return false;
            }
        });
        
        document.getElementById('coverForm').addEventListener('submit', function(e) {
            const coverInput = document.getElementById('coverFile');
            if (!coverInput.files || coverInput.files.length === 0) {
                e.preventDefault();
                alert('请选择封面图片');
                return false;
            }
        });
    </script>
</body>
</html>