<?php
/**
 * 创建分析页面
 */
require_once 'config/config.php';
require_once 'config/database.php';

// 检查用户登录
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user = new User();
$userInfo = $user->getUserById($_SESSION['user_id']);
$analysisCost = DEFAULT_ANALYSIS_COST;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>创建分析 - <?php echo APP_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- 自定义样式 -->
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- 导航栏 -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-magic me-2"></i><?php echo APP_NAME; ?>
            </a>
            
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    <i class="fas fa-coins text-warning me-1"></i>
                    余额：<strong><?php echo $userInfo['jingling_coins']; ?></strong> 精灵币
                </span>
                <a class="nav-link" href="index.php">
                    <i class="fas fa-home me-1"></i>返回首页
                </a>
            </div>
        </div>
    </nav>

    <div class="container my-5">
        <!-- 页面标题 -->
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-plus-circle text-primary me-2"></i>创建分析订单</h2>
                    <div class="text-end">
                        <small class="text-muted">分析费用：<span class="text-primary fw-bold"><?php echo $analysisCost; ?></span> 精灵币</small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 余额检查 -->
        <?php if ($userInfo['jingling_coins'] < $analysisCost): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle me-2"></i>
            您的精灵币余额不足，无法创建分析订单。
            <a href="recharge.php" class="btn btn-sm btn-primary ms-2">
                <i class="fas fa-plus me-1"></i>立即充值
            </a>
        </div>
        <?php else: ?>
        
        <!-- 创建表单 -->
        <form id="analysis-form">
            <!-- 基本信息 -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>基本信息</h5>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label for="analysis-title" class="form-label">分析标题 <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="analysis-title" name="title" 
                               placeholder="请输入本次分析的标题，如：双11直播复盘分析" required>
                        <div class="form-text">给这次分析起个有意义的标题，方便后续查找</div>
                    </div>
                </div>
            </div>
            
            <!-- 直播截图上传 -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-images me-2"></i>直播截图 
                        <span class="text-danger">*</span>
                        <small class="text-muted">（需要5张）</small>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="upload-area" data-type="screenshots" onclick="$('#screenshot-upload').click()">
                        <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                        <h6>点击或拖拽上传直播截图</h6>
                        <p class="text-muted mb-2">支持 JPG、PNG、GIF 格式，单个文件不超过10MB</p>
                        <p class="text-muted small">
                            已上传：<span id="screenshot-count" class="text-primary fw-bold">0</span>/5 张
                        </p>
                        <input type="file" id="screenshot-upload" multiple accept="image/*" style="display: none;">
                    </div>
                    <div id="screenshots-preview" class="mt-3"></div>
                </div>
            </div>
            
            <!-- 封面图上传 -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-image me-2"></i>封面图 
                        <span class="text-danger">*</span>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="upload-area" data-type="cover" onclick="$('#cover-upload').click()">
                        <i class="fas fa-image fa-3x text-muted mb-3"></i>
                        <h6>点击或拖拽上传封面图</h6>
                        <p class="text-muted">支持 JPG、PNG、GIF 格式，不超过10MB</p>
                        <input type="file" id="cover-upload" accept="image/*" style="display: none;">
                    </div>
                    <div id="cover-preview" class="mt-3"></div>
                </div>
            </div>
            
            <!-- 话术内容 -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-comments me-2"></i>话术内容</h5>
                </div>
                <div class="card-body">
                    <!-- 本方话术 -->
                    <div class="form-group mb-4">
                        <label for="self-script" class="form-label">本方话术 <span class="text-danger">*</span></label>
                        <textarea class="form-control script-input" id="self-script" name="self_script" 
                                  placeholder="请输入您的直播话术内容..." required></textarea>
                        <div class="form-text">请尽量完整地输入您的直播话术，包括开场、介绍、互动等环节</div>
                    </div>
                    
                    <!-- 同行话术 -->
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="competitor-script-1" class="form-label">同行话术1 <span class="text-danger">*</span></label>
                            <textarea class="form-control script-input" id="competitor-script-1" name="competitor_script_1" 
                                      placeholder="请输入同行1的话术..." required></textarea>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="competitor-script-2" class="form-label">同行话术2 <span class="text-danger">*</span></label>
                            <textarea class="form-control script-input" id="competitor-script-2" name="competitor_script_2" 
                                      placeholder="请输入同行2的话术..." required></textarea>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="competitor-script-3" class="form-label">同行话术3 <span class="text-danger">*</span></label>
                            <textarea class="form-control script-input" id="competitor-script-3" name="competitor_script_3" 
                                      placeholder="请输入同行3的话术..." required></textarea>
                        </div>
                    </div>
                    <div class="form-text">
                        <i class="fas fa-info-circle me-1"></i>
                        同行话术用于对比分析，帮助您了解竞争对手的优势和不足
                    </div>
                </div>
            </div>
            
            <!-- 费用说明 -->
            <div class="card mb-4">
                <div class="card-body bg-light">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h6 class="mb-2"><i class="fas fa-calculator text-primary me-2"></i>费用说明</h6>
                            <p class="mb-0 text-muted">
                                本次分析将消耗 <strong class="text-primary"><?php echo $analysisCost; ?></strong> 个精灵币，
                                您当前余额：<strong class="text-success"><?php echo $userInfo['jingling_coins']; ?></strong> 个精灵币
                            </p>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg" id="submit-analysis" disabled>
                                    <i class="fas fa-magic me-2"></i>开始AI分析
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
        
        <?php endif; ?>
        
        <!-- 使用说明 -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-question-circle me-2"></i>使用说明</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>直播截图要求：</h6>
                        <ul class="text-muted small">
                            <li>需要上传5张关键截图</li>
                            <li>建议包含：开场、产品介绍、互动、促销、结尾等环节</li>
                            <li>图片清晰，能看清直播间内容</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6>话术要求：</h6>
                        <ul class="text-muted small">
                            <li>本方话术要完整，包含各个环节</li>
                            <li>同行话术可以是片段，但要有代表性</li>
                            <li>文字清晰，避免错别字</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- 自定义JS -->
    <script src="assets/js/app.js"></script>
    
    <script>
        // 表单提交处理
        $('#analysis-form').on('submit', function(e) {
            e.preventDefault();
            submitAnalysis();
        });
        
        // 监听输入变化，更新提交按钮状态
        $('#analysis-form input, #analysis-form textarea').on('input', function() {
            updateUploadStatus();
        });
        
        // 文件上传处理
        $('#screenshot-upload').on('change', function(e) {
            handleFileUpload(e.target.files, 'screenshots', 5);
        });
        
        $('#cover-upload').on('change', function(e) {
            handleFileUpload(e.target.files, 'cover', 1);
        });
    </script>
</body>
</html>