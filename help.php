<?php
/**
 * 复盘精灵 - 帮助中心页面
 */
require_once 'config/config.php';
require_once 'config/database.php';

// 检查用户登录状态
$isLoggedIn = isset($_SESSION['user_id']);
$user = null;

if ($isLoggedIn) {
    $userObj = new User();
    $user = $userObj->getUserById($_SESSION['user_id']);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>帮助中心 - <?php echo APP_NAME; ?></title>
    
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
                    <?php if ($isLoggedIn): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="create_analysis.php">创建分析</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="my_orders.php">我的订单</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="recharge.php">充值中心</a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link active" href="help.php">帮助中心</a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <?php if ($isLoggedIn): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($user['nickname']); ?>
                            <span class="badge bg-warning text-dark ms-2"><?php echo $user['jingling_coins']; ?>币</span>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user-edit me-2"></i>个人资料</a></li>
                            <li><a class="dropdown-item" href="coin_history.php"><i class="fas fa-coins me-2"></i>精灵币记录</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>退出登录</a></li>
                        </ul>
                    </li>
                    <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">登录</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="register.php">注册</a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- 主要内容 -->
    <main class="container my-5">
        <div class="row">
            <div class="col-lg-3">
                <!-- 帮助导航 -->
                <div class="card sticky-top" style="top: 20px;">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-question-circle me-2"></i>帮助导航
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <a href="#getting-started" class="list-group-item list-group-item-action">
                                <i class="fas fa-play-circle me-2"></i>快速开始
                            </a>
                            <a href="#how-to-analyze" class="list-group-item list-group-item-action">
                                <i class="fas fa-chart-line me-2"></i>如何分析
                            </a>
                            <a href="#coins-system" class="list-group-item list-group-item-action">
                                <i class="fas fa-coins me-2"></i>精灵币系统
                            </a>
                            <a href="#report-sharing" class="list-group-item list-group-item-action">
                                <i class="fas fa-share me-2"></i>报告分享
                            </a>
                            <a href="#faq" class="list-group-item list-group-item-action">
                                <i class="fas fa-question me-2"></i>常见问题
                            </a>
                            <a href="#contact" class="list-group-item list-group-item-action">
                                <i class="fas fa-phone me-2"></i>联系客服
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-9">
                <!-- 快速开始 -->
                <section id="getting-started" class="mb-5">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h4 class="card-title mb-0">
                                <i class="fas fa-play-circle me-2"></i>快速开始
                            </h4>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>第一步：注册账号</h6>
                                    <p>使用手机号注册账号，系统会发送短信验证码进行验证。</p>
                                    
                                    <h6>第二步：充值精灵币</h6>
                                    <p>通过兑换码充值精灵币，每次分析消费10个精灵币。</p>
                                </div>
                                <div class="col-md-6">
                                    <h6>第三步：创建分析</h6>
                                    <p>上传直播截图、封面图和话术内容，系统将进行AI分析。</p>
                                    
                                    <h6>第四步：查看报告</h6>
                                    <p>分析完成后会收到短信通知，可在"我的订单"中查看详细报告。</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- 如何分析 -->
                <section id="how-to-analyze" class="mb-5">
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h4 class="card-title mb-0">
                                <i class="fas fa-chart-line me-2"></i>如何创建分析
                            </h4>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-12">
                                    <h6>准备材料</h6>
                                    <ul>
                                        <li><strong>直播截图：</strong>上传5张关键直播截图，建议包含不同时段的观众互动画面</li>
                                        <li><strong>封面图：</strong>上传直播封面图，用于分析封面吸引力</li>
                                        <li><strong>本方话术：</strong>输入您在直播中使用的主要话术内容</li>
                                        <li><strong>同行话术：</strong>提供3个同行的优秀话术作为对比参考</li>
                                    </ul>
                                    
                                    <h6>分析过程</h6>
                                    <div class="row">
                                        <div class="col-md-3 text-center mb-3">
                                            <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                                                <i class="fas fa-upload fa-2x"></i>
                                            </div>
                                            <p class="mt-2 mb-0">上传材料</p>
                                        </div>
                                        <div class="col-md-3 text-center mb-3">
                                            <div class="bg-info text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                                                <i class="fas fa-robot fa-2x"></i>
                                            </div>
                                            <p class="mt-2 mb-0">AI分析</p>
                                        </div>
                                        <div class="col-md-3 text-center mb-3">
                                            <div class="bg-warning text-dark rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                                                <i class="fas fa-chart-bar fa-2x"></i>
                                            </div>
                                            <p class="mt-2 mb-0">生成报告</p>
                                        </div>
                                        <div class="col-md-3 text-center mb-3">
                                            <div class="bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                                                <i class="fas fa-sms fa-2x"></i>
                                            </div>
                                            <p class="mt-2 mb-0">短信通知</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- 精灵币系统 -->
                <section id="coins-system" class="mb-5">
                    <div class="card">
                        <div class="card-header bg-warning text-dark">
                            <h4 class="card-title mb-0">
                                <i class="fas fa-coins me-2"></i>精灵币系统
                            </h4>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>什么是精灵币？</h6>
                                    <p>精灵币是平台的虚拟货币，用于支付AI分析服务费用。每次分析消费10个精灵币。</p>
                                    
                                    <h6>如何获得精灵币？</h6>
                                    <ul>
                                        <li>使用兑换码充值</li>
                                        <li>参与平台活动获得奖励</li>
                                        <li>邀请好友注册获得奖励</li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <h6>精灵币使用规则</h6>
                                    <ul>
                                        <li>精灵币不可提现，仅用于平台服务消费</li>
                                        <li>分析失败时会自动退还精灵币</li>
                                        <li>精灵币永久有效，无过期时间</li>
                                        <li>可在"精灵币记录"中查看详细消费记录</li>
                                    </ul>
                                    
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <strong>提示：</strong>建议一次充值足够的精灵币，避免分析时余额不足。
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- 报告分享 -->
                <section id="report-sharing" class="mb-5">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h4 class="card-title mb-0">
                                <i class="fas fa-share me-2"></i>报告分享功能
                            </h4>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 text-center mb-4">
                                    <i class="fas fa-link fa-3x text-primary mb-3"></i>
                                    <h6>复制链接分享</h6>
                                    <p class="text-muted">生成分享链接，方便发送给团队成员查看分析报告。</p>
                                </div>
                                <div class="col-md-4 text-center mb-4">
                                    <i class="fas fa-image fa-3x text-success mb-3"></i>
                                    <h6>导出图片</h6>
                                    <p class="text-muted">将报告导出为高清图片，适合在社交媒体分享。</p>
                                </div>
                                <div class="col-md-4 text-center mb-4">
                                    <i class="fas fa-file-pdf fa-3x text-danger mb-3"></i>
                                    <h6>导出PDF</h6>
                                    <p class="text-muted">生成PDF格式报告，便于保存和打印。</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- 常见问题 -->
                <section id="faq" class="mb-5">
                    <div class="card">
                        <div class="card-header bg-dark text-white">
                            <h4 class="card-title mb-0">
                                <i class="fas fa-question me-2"></i>常见问题
                            </h4>
                        </div>
                        <div class="card-body">
                            <div class="accordion" id="faqAccordion">
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="faq1">
                                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapse1">
                                            分析需要多长时间？
                                        </button>
                                    </h2>
                                    <div id="collapse1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                                        <div class="accordion-body">
                                            通常情况下，AI分析需要1-3分钟完成。分析完成后会通过短信通知您，您也可以在"我的订单"页面查看分析进度。
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="faq2">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse2">
                                            上传的图片有什么要求？
                                        </button>
                                    </h2>
                                    <div id="collapse2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                        <div class="accordion-body">
                                            <ul>
                                                <li>支持JPG、PNG格式</li>
                                                <li>单个文件不超过10MB</li>
                                                <li>建议分辨率至少720p</li>
                                                <li>图片内容清晰，能看清弹幕和互动信息</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="faq3">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse3">
                                            分析失败了怎么办？
                                        </button>
                                    </h2>
                                    <div id="collapse3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                        <div class="accordion-body">
                                            如果分析失败，系统会自动退还消费的精灵币。常见失败原因：
                                            <ul>
                                                <li>上传的图片不清晰或格式不支持</li>
                                                <li>话术内容过短或不完整</li>
                                                <li>服务器临时故障</li>
                                            </ul>
                                            如果多次失败，请联系客服处理。
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="faq4">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse4">
                                            如何获得兑换码？
                                        </button>
                                    </h2>
                                    <div id="collapse4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                        <div class="accordion-body">
                                            目前兑换码通过以下方式获得：
                                            <ul>
                                                <li>联系客服购买</li>
                                                <li>参与平台活动获得</li>
                                                <li>邀请好友注册奖励</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="faq5">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse5">
                                            报告可以分享给别人吗？
                                        </button>
                                    </h2>
                                    <div id="collapse5" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                        <div class="accordion-body">
                                            可以！报告支持三种分享方式：
                                            <ul>
                                                <li><strong>复制链接：</strong>生成分享链接，任何人都可以通过链接查看</li>
                                                <li><strong>导出图片：</strong>将报告保存为图片格式</li>
                                                <li><strong>导出PDF：</strong>生成PDF文件，便于保存和打印</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- 联系客服 -->
                <section id="contact">
                    <div class="card">
                        <div class="card-header bg-secondary text-white">
                            <h4 class="card-title mb-0">
                                <i class="fas fa-phone me-2"></i>联系客服
                            </h4>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center mb-3">
                                        <i class="fas fa-phone fa-2x text-primary me-3"></i>
                                        <div>
                                            <h6 class="mb-1">客服热线</h6>
                                            <p class="mb-0 text-muted">400-123-4567</p>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex align-items-center mb-3">
                                        <i class="fab fa-qq fa-2x text-primary me-3"></i>
                                        <div>
                                            <h6 class="mb-1">客服QQ</h6>
                                            <p class="mb-0 text-muted">123456789</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center mb-3">
                                        <i class="fas fa-clock fa-2x text-primary me-3"></i>
                                        <div>
                                            <h6 class="mb-1">服务时间</h6>
                                            <p class="mb-0 text-muted">周一至周日 9:00-21:00</p>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex align-items-center mb-3">
                                        <i class="fas fa-envelope fa-2x text-primary me-3"></i>
                                        <div>
                                            <h6 class="mb-1">邮箱支持</h6>
                                            <p class="mb-0 text-muted">support@fpjinlin.com</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="alert alert-primary">
                                <i class="fas fa-lightbulb me-2"></i>
                                <strong>温馨提示：</strong>如果您在使用过程中遇到任何问题，请随时联系我们的客服团队，我们将竭诚为您服务！
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 平滑滚动到锚点
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                    
                    // 更新导航状态
                    document.querySelectorAll('.list-group-item').forEach(item => {
                        item.classList.remove('active');
                    });
                    this.classList.add('active');
                }
            });
        });

        // 滚动时更新导航状态
        window.addEventListener('scroll', function() {
            const sections = document.querySelectorAll('section[id]');
            const navLinks = document.querySelectorAll('.list-group-item[href^="#"]');
            
            let current = '';
            sections.forEach(section => {
                const sectionTop = section.offsetTop - 100;
                if (window.pageYOffset >= sectionTop) {
                    current = section.getAttribute('id');
                }
            });
            
            navLinks.forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('href') === '#' + current) {
                    link.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>