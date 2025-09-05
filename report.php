<?php
/**
 * 分析报告页面
 */
require_once 'config/config.php';
require_once 'config/database.php';

// 获取订单ID
$orderId = $_GET['id'] ?? 0;
$isShare = isset($_GET['share']);

if (!$orderId) {
    header('Location: index.php');
    exit;
}

// 获取订单信息
$analysisOrder = new AnalysisOrder();
$order = $analysisOrder->getOrderById($orderId);

if (!$order) {
    header('Location: index.php');
    exit;
}

// 权限检查（非分享链接需要登录且是订单所有者）
if (!$isShare) {
    if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != $order['user_id']) {
        header('Location: login.php');
        exit;
    }
}

// 检查订单状态
if ($order['status'] !== 'completed') {
    if (!$isShare && isset($_SESSION['user_id'])) {
        header("Location: my_orders.php");
        exit;
    } else {
        header('Location: index.php');
        exit;
    }
}

// 解析报告数据
$screenshots = json_decode($order['live_screenshots'], true) ?: [];
$competitorScripts = json_decode($order['competitor_scripts'], true) ?: [];

// 等级映射
$levelMap = [
    'excellent' => ['text' => '优秀', 'class' => 'success'],
    'good' => ['text' => '良好', 'class' => 'info'],
    'average' => ['text' => '一般', 'class' => 'warning'],
    'poor' => ['text' => '较差', 'class' => 'warning'],
    'unqualified' => ['text' => '不合格', 'class' => 'danger']
];

$currentLevel = $levelMap[$order['report_level']] ?? ['text' => '未评级', 'class' => 'secondary'];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($order['title']); ?> - 分析报告 - <?php echo APP_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- html2canvas -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <!-- jsPDF -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <!-- 自定义样式 -->
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- 导航栏 -->
    <?php if (!$isShare): ?>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-magic me-2"></i><?php echo APP_NAME; ?>
            </a>
            
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="my_orders.php">
                    <i class="fas fa-list me-1"></i>我的订单
                </a>
                <a class="nav-link" href="index.php">
                    <i class="fas fa-home me-1"></i>返回首页
                </a>
            </div>
        </div>
    </nav>
    <?php endif; ?>

    <div class="container my-4">
        <!-- 报告头部 -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h4 class="mb-1"><?php echo htmlspecialchars($order['title']); ?></h4>
                                <p class="text-muted mb-0">
                                    <i class="fas fa-calendar me-1"></i>
                                    分析时间：<?php echo date('Y-m-d H:i', strtotime($order['completed_at'])); ?>
                                </p>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <!-- 操作按钮 -->
                                <div class="action-buttons">
                                    <button class="btn btn-outline-primary" onclick="shareReportLink(<?php echo $orderId; ?>)">
                                        <i class="fas fa-share-alt me-1"></i>复制链接
                                    </button>
                                    <button class="btn btn-outline-success" onclick="exportReportAsImage(<?php echo $orderId; ?>)">
                                        <i class="fas fa-image me-1"></i>导出图片
                                    </button>
                                    <button class="btn btn-outline-danger" onclick="exportReportAsPDF(<?php echo $orderId; ?>)">
                                        <i class="fas fa-file-pdf me-1"></i>导出PDF
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 综合评分 -->
        <div class="row mb-4">
            <div class="col-md-6 mx-auto">
                <div class="score-display">
                    <div class="score-number"><?php echo $order['report_score']; ?></div>
                    <div class="score-level">
                        <span class="badge bg-<?php echo $currentLevel['class']; ?> fs-6">
                            <?php echo $currentLevel['text']; ?>
                        </span>
                    </div>
                    <div class="mt-2">综合评分</div>
                </div>
            </div>
        </div>
        
        <!-- 分析报告内容 -->
        <div class="row">
            <div class="col-12">
                <div class="analysis-report">
                    <?php echo $order['ai_report']; ?>
                </div>
            </div>
        </div>
        
        <!-- 原始数据展示 -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-database me-2"></i>原始数据
                            <button class="btn btn-sm btn-outline-secondary float-end" type="button" 
                                    data-bs-toggle="collapse" data-bs-target="#raw-data">
                                <i class="fas fa-eye me-1"></i>查看详情
                            </button>
                        </h6>
                    </div>
                    <div class="collapse" id="raw-data">
                        <div class="card-body">
                            <div class="row">
                                <!-- 直播截图 -->
                                <div class="col-md-12 mb-4">
                                    <h6><i class="fas fa-images me-2"></i>直播截图</h6>
                                    <div class="row">
                                        <?php foreach ($screenshots as $index => $screenshot): ?>
                                        <div class="col-md-2 col-sm-4 mb-2">
                                            <img src="<?php echo htmlspecialchars($screenshot); ?>" 
                                                 class="img-fluid rounded shadow-sm" 
                                                 alt="截图<?php echo $index + 1; ?>"
                                                 data-bs-toggle="modal" 
                                                 data-bs-target="#imageModal"
                                                 onclick="showImage('<?php echo htmlspecialchars($screenshot); ?>')"
                                                 style="cursor: pointer;">
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                
                                <!-- 封面图 -->
                                <div class="col-md-4 mb-4">
                                    <h6><i class="fas fa-image me-2"></i>封面图</h6>
                                    <img src="<?php echo htmlspecialchars($order['cover_image']); ?>" 
                                         class="img-fluid rounded shadow-sm" 
                                         alt="封面图"
                                         data-bs-toggle="modal" 
                                         data-bs-target="#imageModal"
                                         onclick="showImage('<?php echo htmlspecialchars($order['cover_image']); ?>')"
                                         style="cursor: pointer;">
                                </div>
                                
                                <!-- 话术内容 -->
                                <div class="col-md-8">
                                    <h6><i class="fas fa-comments me-2"></i>话术内容</h6>
                                    
                                    <!-- 本方话术 -->
                                    <div class="mb-3">
                                        <strong>本方话术：</strong>
                                        <div class="border rounded p-3 bg-light">
                                            <?php echo nl2br(htmlspecialchars($order['self_script'])); ?>
                                        </div>
                                    </div>
                                    
                                    <!-- 同行话术 -->
                                    <?php foreach ($competitorScripts as $index => $script): ?>
                                    <div class="mb-3">
                                        <strong>同行话术<?php echo $index + 1; ?>：</strong>
                                        <div class="border rounded p-3 bg-light">
                                            <?php echo nl2br(htmlspecialchars($script)); ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 返回按钮 -->
        <?php if (!$isShare): ?>
        <div class="text-center mt-4">
            <a href="my_orders.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left me-1"></i>返回订单列表
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- 图片查看模态框 -->
    <div class="modal fade" id="imageModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">图片预览</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalImage" src="" class="img-fluid" alt="图片预览">
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
        // 显示图片
        function showImage(src) {
            $('#modalImage').attr('src', src);
        }
        
        // 页面加载完成后的处理
        $(document).ready(function() {
            // 如果是分享页面，添加水印
            <?php if ($isShare): ?>
            addWatermark();
            <?php endif; ?>
        });
        
        // 添加水印
        function addWatermark() {
            $('.analysis-report').append(`
                <div class="text-center mt-4 p-3 border-top">
                    <p class="text-muted mb-0">
                        <i class="fas fa-magic me-1"></i>
                        本报告由 <strong><?php echo APP_NAME; ?></strong> 生成
                        <br>
                        <small>专业的视频号直播复盘分析平台</small>
                    </p>
                </div>
            `);
        }
        
        // 重写导出函数以包含订单信息
        function exportReportAsImage(reportId) {
            const reportElement = document.querySelector('.analysis-report');
            
            if (!reportElement) {
                showAlert('danger', '报告内容未找到');
                return;
            }
            
            showAlert('info', '正在生成图片，请稍候...', 0);
            
            html2canvas(reportElement, {
                allowTaint: true,
                useCORS: true,
                scale: 2,
                backgroundColor: '#ffffff',
                width: reportElement.scrollWidth,
                height: reportElement.scrollHeight
            }).then(function(canvas) {
                const link = document.createElement('a');
                link.download = `<?php echo htmlspecialchars($order['title']); ?>_分析报告_${new Date().getTime()}.png`;
                link.href = canvas.toDataURL('image/png');
                
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                
                $('.alert').alert('close');
                showAlert('success', '图片导出成功');
            }).catch(function(error) {
                $('.alert').alert('close');
                showAlert('danger', '图片导出失败');
                console.error('导出失败:', error);
            });
        }
        
        function exportReportAsPDF(reportId) {
            const reportElement = document.querySelector('.analysis-report');
            
            if (!reportElement) {
                showAlert('danger', '报告内容未找到');
                return;
            }
            
            showAlert('info', '正在生成PDF，请稍候...', 0);
            
            html2canvas(reportElement, {
                allowTaint: true,
                useCORS: true,
                scale: 1.5,
                backgroundColor: '#ffffff'
            }).then(function(canvas) {
                const { jsPDF } = window.jspdf;
                const pdf = new jsPDF('p', 'mm', 'a4');
                
                const imgData = canvas.toDataURL('image/png');
                const imgWidth = 190;
                const pageHeight = 297;
                const imgHeight = (canvas.height * imgWidth) / canvas.width;
                let heightLeft = imgHeight;
                
                let position = 10;
                
                // 添加标题页
                pdf.setFontSize(16);
                pdf.text('<?php echo htmlspecialchars($order['title']); ?>', 105, 20, { align: 'center' });
                pdf.setFontSize(12);
                pdf.text('<?php echo APP_NAME; ?> - 分析报告', 105, 30, { align: 'center' });
                pdf.text('生成时间：<?php echo date('Y-m-d H:i', strtotime($order['completed_at'])); ?>', 105, 40, { align: 'center' });
                
                // 添加报告内容
                pdf.addImage(imgData, 'PNG', 10, 50, imgWidth, imgHeight);
                heightLeft -= (pageHeight - 50);
                
                // 如果内容超过一页，添加新页
                while (heightLeft >= 0) {
                    position = heightLeft - imgHeight + 10;
                    pdf.addPage();
                    pdf.addImage(imgData, 'PNG', 10, position, imgWidth, imgHeight);
                    heightLeft -= pageHeight;
                }
                
                // 下载PDF
                pdf.save(`<?php echo htmlspecialchars($order['title']); ?>_分析报告_${new Date().getTime()}.pdf`);
                
                $('.alert').alert('close');
                showAlert('success', 'PDF导出成功');
            }).catch(function(error) {
                $('.alert').alert('close');
                showAlert('danger', 'PDF导出失败');
                console.error('导出失败:', error);
            });
        }
    </script>
</body>
</html>