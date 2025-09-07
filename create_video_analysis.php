<?php
/**
 * 创建视频分析页面
 * 复盘精灵系统 - 视频驱动分析
 */
require_once 'config/config.php';
require_once 'config/database.php';

// 检查用户登录
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userObj = new User();
$user = $userObj->getUserById($_SESSION['user_id']);

if (!$user || $user['status'] != 1) {
    header('Location: login.php');
    exit;
}

// 获取系统配置
$videoAnalysisCost = getSystemConfig('video_analysis_cost_coins', 50);
$maxVideoDuration = getSystemConfig('max_video_duration', 3600);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>创建视频分析 - <?php echo APP_NAME; ?></title>
    
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
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">首页</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="create_video_analysis.php">视频分析</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="my_orders.php">我的订单</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="recharge.php">充值中心</a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($user['nickname']); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php">个人资料</a></li>
                            <li><a class="dropdown-item" href="coin_history.php">交易记录</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">退出登录</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- 主要内容 -->
    <div class="container mt-4">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <!-- 页面标题 -->
                <div class="text-center mb-4">
                    <h2><i class="fas fa-video me-2"></i>创建视频分析</h2>
                    <p class="text-muted">上传您的直播视频链接，AI将为您生成专业的复盘分析报告</p>
                </div>

                <!-- 费用说明 -->
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>分析费用：</strong>每次视频分析消耗 <span class="badge bg-primary"><?php echo $videoAnalysisCost; ?></span> 精灵币
                    <br>
                    <strong>当前余额：</strong><span class="badge bg-success"><?php echo $user['jingling_coins']; ?></span> 精灵币
                    <?php if ($user['jingling_coins'] < $videoAnalysisCost): ?>
                        <br><a href="recharge.php" class="btn btn-sm btn-warning mt-2">立即充值</a>
                    <?php endif; ?>
                </div>

                <!-- 创建表单 -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-upload me-2"></i>视频链接提交</h5>
                    </div>
                    <div class="card-body">
                        <form id="videoAnalysisForm">
                            <!-- 分析标题 -->
                            <div class="mb-3">
                                <label for="title" class="form-label">分析标题 <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="title" name="title" 
                                       placeholder="请输入分析标题，如：2024年双11直播复盘" required>
                            </div>

                            <!-- 本方视频链接 -->
                            <div class="mb-3">
                                <label for="self_video_link" class="form-label">本方直播视频链接 <span class="text-danger">*</span></label>
                                <input type="url" class="form-control" id="self_video_link" name="self_video_link" 
                                       placeholder="请粘贴您的直播视频分享链接（抖音/快手/小红书）" required>
                                <div class="form-text">支持抖音、快手、小红书平台的视频分享链接</div>
                            </div>

                            <!-- 同行视频链接 -->
                            <div class="mb-3">
                                <label class="form-label">同行直播视频链接 <span class="text-danger">*</span></label>
                                
                                <div class="mb-2">
                                    <label for="competitor_video_link_1" class="form-label">同行1</label>
                                    <input type="url" class="form-control" id="competitor_video_link_1" name="competitor_video_links[]" 
                                           placeholder="请粘贴同行1的直播视频分享链接" required>
                                </div>
                                
                                <div class="mb-2">
                                    <label for="competitor_video_link_2" class="form-label">同行2</label>
                                    <input type="url" class="form-control" id="competitor_video_link_2" name="competitor_video_links[]" 
                                           placeholder="请粘贴同行2的直播视频分享链接" required>
                                </div>
                                
                                <div class="form-text">请提供2个同行的直播视频链接用于对比分析</div>
                            </div>

                            <!-- 分析说明 -->
                            <div class="mb-3">
                                <label class="form-label">分析说明</label>
                                <div class="alert alert-light">
                                    <h6><i class="fas fa-lightbulb me-2"></i>AI将为您分析：</h6>
                                    <ul class="mb-0">
                                        <li><strong>时间线分析：</strong>每2-5分钟总结话术、互动、效果</li>
                                        <li><strong>主播表现：</strong>情绪曲线、语速节奏、肢体动作</li>
                                        <li><strong>话术结构：</strong>开场、卖点、价格策略、收尾</li>
                                        <li><strong>商品演示：</strong>演示方式、镜头配合、证据化</li>
                                        <li><strong>场景氛围：</strong>灯光、背景、音效、整体氛围</li>
                                        <li><strong>同行对比：</strong>节奏、话术、演示、场景差异</li>
                                        <li><strong>风险合规：</strong>敏感词汇、违规内容检查</li>
                                        <li><strong>话术库：</strong>推荐话术和优化建议</li>
                                    </ul>
                                </div>
                            </div>

                            <!-- 提交按钮 -->
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                                    <i class="fas fa-magic me-2"></i>创建视频分析
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- 处理流程说明 -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-cogs me-2"></i>处理流程</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 text-center mb-3">
                                <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                                    <i class="fas fa-upload"></i>
                                </div>
                                <h6 class="mt-2">提交链接</h6>
                                <small class="text-muted">上传视频分享链接</small>
                            </div>
                            <div class="col-md-3 text-center mb-3">
                                <div class="bg-warning text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                                    <i class="fas fa-eye"></i>
                                </div>
                                <h6 class="mt-2">人工审核</h6>
                                <small class="text-muted">工作人员审核链接</small>
                            </div>
                            <div class="col-md-3 text-center mb-3">
                                <div class="bg-info text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                                    <i class="fas fa-cog"></i>
                                </div>
                                <h6 class="mt-2">AI分析</h6>
                                <small class="text-muted">AI智能分析视频内容</small>
                            </div>
                            <div class="col-md-3 text-center mb-3">
                                <div class="bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                                    <i class="fas fa-file-alt"></i>
                                </div>
                                <h6 class="mt-2">生成报告</h6>
                                <small class="text-muted">生成专业分析报告</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
    $(document).ready(function() {
        // 表单提交处理
        $('#videoAnalysisForm').on('submit', function(e) {
            e.preventDefault();
            
            // 检查余额
            if (<?php echo $user['jingling_coins']; ?> < <?php echo $videoAnalysisCost; ?>) {
                alert('精灵币余额不足，请先充值！');
                return;
            }
            
            // 获取表单数据
            const formData = new FormData(this);
            const competitorLinks = [];
            
            // 收集同行链接
            $('input[name="competitor_video_links[]"]').each(function() {
                if ($(this).val().trim()) {
                    competitorLinks.push($(this).val().trim());
                }
            });
            
            formData.set('competitor_video_links', JSON.stringify(competitorLinks));
            
            // 禁用提交按钮
            $('#submitBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>提交中...');
            
            // 发送请求
            $.ajax({
                url: 'api/create_video_analysis.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert('视频分析订单创建成功！AI正在分析中，请耐心等待...');
                        window.location.href = 'my_orders.php';
                    } else {
                        alert('创建失败：' + response.message);
                        $('#submitBtn').prop('disabled', false).html('<i class="fas fa-magic me-2"></i>创建视频分析');
                    }
                },
                error: function() {
                    alert('网络错误，请稍后重试！');
                    $('#submitBtn').prop('disabled', false).html('<i class="fas fa-magic me-2"></i>创建视频分析');
                }
            });
        });
        
        // 链接格式验证
        $('input[type="url"]').on('blur', function() {
            const url = $(this).val().trim();
            if (url) {
                const isValid = isValidVideoLink(url);
                if (!isValid) {
                    $(this).addClass('is-invalid');
                    $(this).next('.invalid-feedback').remove();
                    $(this).after('<div class="invalid-feedback">请输入有效的视频分享链接（抖音/快手/小红书）</div>');
                } else {
                    $(this).removeClass('is-invalid');
                    $(this).next('.invalid-feedback').remove();
                }
            }
        });
        
        // 验证视频链接格式
        function isValidVideoLink(url) {
            const patterns = [
                /^https?:\/\/(www\.)?(douyin|iesdouyin)\.com\/video\/\d+/,
                /^https?:\/\/(www\.)?kuaishou\.com\/video\/\d+/,
                /^https?:\/\/(www\.)?xiaohongshu\.com\/explore\/\w+/
            ];
            
            return patterns.some(pattern => pattern.test(url));
        }
    });
    </script>
</body>
</html>
