<?php
/**
 * 分析报告展示页面
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
$orderId = intval($_GET['id'] ?? 0);
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

// 检查报告是否完成
if ($order['status'] !== 'completed') {
    header("Location: orders.php");
    exit;
}

// 解析AI报告内容
$reportData = json_decode($order['ai_report_content'], true);
if (!$reportData) {
    $reportData = [
        'score' => 0,
        'level' => '待评估',
        'analysis' => $order['ai_report_content'] ?? '报告生成中...',
        'comparison' => '',
        'suggestions' => ''
    ];
}

// 获取订单截图
$screenshots = $orderManager->getOrderScreenshots($orderId);
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>分析报告 - <?php echo htmlspecialchars($order['title']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
    
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
        
        .report-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border: none;
            margin-bottom: 2rem;
        }
        
        .report-header {
            background: var(--primary-gradient);
            color: white;
            padding: 2rem;
            border-radius: 15px 15px 0 0;
        }
        
        .score-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: bold;
            color: white;
            margin: 0 auto 1rem;
        }
        
        .score-excellent {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }
        
        .score-good {
            background: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%);
        }
        
        .score-average {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
        }
        
        .score-poor {
            background: linear-gradient(135deg, #fd7e14 0%, #dc3545 100%);
        }
        
        .score-fail {
            background: linear-gradient(135deg, #dc3545 0%, #6f42c1 100%);
        }
        
        .section-card {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .btn-gradient {
            background: var(--primary-gradient);
            border: none;
            color: white;
            padding: 0.5rem 1.5rem;
            border-radius: 20px;
            font-weight: 500;
        }
        
        .screenshot-gallery {
            display: flex;
            gap: 10px;
            overflow-x: auto;
            padding: 10px 0;
        }
        
        .screenshot-item {
            flex: 0 0 auto;
            width: 150px;
            height: 100px;
            border-radius: 8px;
            overflow: hidden;
            cursor: pointer;
            transition: transform 0.2s ease;
        }
        
        .screenshot-item:hover {
            transform: scale(1.05);
        }
        
        .screenshot-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
        }
        
        @media (max-width: 768px) {
            .report-header {
                padding: 1.5rem;
            }
            
            .score-circle {
                width: 80px;
                height: 80px;
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- 导航栏 -->
    <nav class="navbar navbar-expand-lg navbar-dark no-print">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-magic"></i> <?php echo SITE_NAME; ?>
            </a>
            
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="orders.php">
                    <i class="bi bi-list-ul"></i> 我的订单
                </a>
            </div>
        </div>
    </nav>
    
    <div class="container mt-4">
        <!-- 操作按钮 -->
        <div class="row mb-3 no-print">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <a href="orders.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> 返回订单
                    </a>
                    
                    <div class="btn-group">
                        <button class="btn btn-gradient" onclick="shareReport()">
                            <i class="bi bi-share"></i> 复制链接
                        </button>
                        <button class="btn btn-outline-primary" onclick="exportImage()">
                            <i class="bi bi-image"></i> 导出图片
                        </button>
                        <button class="btn btn-outline-danger" onclick="exportPDF()">
                            <i class="bi bi-file-pdf"></i> 导出PDF
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 报告内容 -->
        <div id="reportContent">
            <!-- 报告头部 -->
            <div class="report-container">
                <div class="report-header text-center">
                    <h2 class="mb-3"><?php echo htmlspecialchars($order['title']); ?></h2>
                    <div class="row align-items-center">
                        <div class="col-md-4">
                            <div class="score-circle <?php 
                                $score = $reportData['score'] ?? 0;
                                if ($score >= 90) echo 'score-excellent';
                                elseif ($score >= 80) echo 'score-good';
                                elseif ($score >= 70) echo 'score-average';
                                elseif ($score >= 60) echo 'score-poor';
                                else echo 'score-fail';
                            ?>">
                                <?php echo $score; ?>
                            </div>
                            <h5><?php echo $reportData['level'] ?? '待评估'; ?></h5>
                        </div>
                        <div class="col-md-8">
                            <div class="row text-start">
                                <div class="col-6">
                                    <h6>分析时间</h6>
                                    <p><?php echo date('Y年m月d日 H:i', strtotime($order['completed_at'])); ?></p>
                                </div>
                                <div class="col-6">
                                    <h6>订单编号</h6>
                                    <p><?php echo $order['order_no']; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 数据截图展示 -->
            <?php if (!empty($screenshots)): ?>
                <div class="report-container">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="mb-0">
                            <i class="bi bi-images"></i> 直播数据截图
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="screenshot-gallery">
                            <?php foreach ($screenshots as $screenshot): ?>
                                <div class="screenshot-item" onclick="viewImage('<?php echo UPLOAD_URL . $screenshot['image_path']; ?>')">
                                    <img src="<?php echo UPLOAD_URL . $screenshot['image_path']; ?>" 
                                         alt="数据截图" loading="lazy">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- 综合分析 -->
            <div class="report-container">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0">
                        <i class="bi bi-graph-up"></i> 综合分析评估
                    </h5>
                </div>
                <div class="card-body">
                    <div class="section-card">
                        <h6 class="text-primary mb-3">
                            <i class="bi bi-bullseye"></i> 整体表现分析
                        </h6>
                        <div class="analysis-content">
                            <?php echo nl2br(htmlspecialchars($reportData['analysis'] ?? '分析内容生成中...')); ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 同行对比 -->
            <?php if (!empty($reportData['comparison'])): ?>
                <div class="report-container">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="mb-0">
                            <i class="bi bi-people"></i> 同行对比分析
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="section-card">
                            <h6 class="text-primary mb-3">
                                <i class="bi bi-bar-chart"></i> 竞争对手分析
                            </h6>
                            <div class="comparison-content">
                                <?php echo nl2br(htmlspecialchars($reportData['comparison'])); ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- 改进建议 -->
            <div class="report-container">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0">
                        <i class="bi bi-lightbulb"></i> 优化建议
                    </h5>
                </div>
                <div class="card-body">
                    <div class="section-card">
                        <h6 class="text-primary mb-3">
                            <i class="bi bi-arrow-up-circle"></i> 改进方案
                        </h6>
                        <div class="suggestions-content">
                            <?php echo nl2br(htmlspecialchars($reportData['suggestions'] ?? '建议生成中...')); ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 报告底部信息 -->
            <div class="report-container">
                <div class="card-body text-center text-muted">
                    <small>
                        <i class="bi bi-magic"></i> 本报告由<?php echo SITE_NAME; ?>AI智能生成 | 
                        生成时间：<?php echo date('Y年m月d日 H:i:s', strtotime($order['completed_at'])); ?>
                    </small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 图片查看模态框 -->
    <div class="modal fade" id="imageModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">查看图片</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalImage" src="" class="img-fluid" alt="图片">
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 查看图片
        function viewImage(imageSrc) {
            document.getElementById('modalImage').src = imageSrc;
            const modal = new bootstrap.Modal(document.getElementById('imageModal'));
            modal.show();
        }
        
        // 分享报告
        function shareReport() {
            const shareUrl = `<?php echo SITE_URL; ?>/pages/user/report.php?id=<?php echo $orderId; ?>`;
            
            if (navigator.share) {
                navigator.share({
                    title: '<?php echo htmlspecialchars($order['title']); ?> - 分析报告',
                    text: '查看我的直播复盘分析报告',
                    url: shareUrl
                });
            } else {
                // 复制链接到剪贴板
                navigator.clipboard.writeText(shareUrl).then(function() {
                    alert('报告链接已复制到剪贴板');
                }).catch(function() {
                    // 降级方案
                    prompt('复制下面的链接:', shareUrl);
                });
            }
        }
        
        // 导出为图片
        function exportImage() {
            const button = event.target;
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="bi bi-arrow-clockwise"></i> 生成中...';
            button.disabled = true;
            
            // 隐藏不需要的元素
            const elementsToHide = document.querySelectorAll('.no-print');
            elementsToHide.forEach(el => el.style.display = 'none');
            
            html2canvas(document.getElementById('reportContent'), {
                scale: 2,
                useCORS: true,
                allowTaint: true,
                backgroundColor: '#ffffff'
            }).then(canvas => {
                // 恢复隐藏的元素
                elementsToHide.forEach(el => el.style.display = '');
                
                // 下载图片
                const link = document.createElement('a');
                link.download = `${<?php echo json_encode($order['title']); ?>}_分析报告.png`;
                link.href = canvas.toDataURL();
                link.click();
                
                button.innerHTML = originalText;
                button.disabled = false;
            }).catch(error => {
                console.error('导出图片失败:', error);
                alert('导出失败，请重试');
                
                // 恢复隐藏的元素
                elementsToHide.forEach(el => el.style.display = '');
                button.innerHTML = originalText;
                button.disabled = false;
            });
        }
        
        // 导出为PDF
        function exportPDF() {
            const button = event.target;
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="bi bi-arrow-clockwise"></i> 生成中...';
            button.disabled = true;
            
            // 隐藏不需要的元素
            const elementsToHide = document.querySelectorAll('.no-print');
            elementsToHide.forEach(el => el.style.display = 'none');
            
            html2canvas(document.getElementById('reportContent'), {
                scale: 1.5,
                useCORS: true,
                allowTaint: true,
                backgroundColor: '#ffffff'
            }).then(canvas => {
                // 恢复隐藏的元素
                elementsToHide.forEach(el => el.style.display = '');
                
                const { jsPDF } = window.jspdf;
                const pdf = new jsPDF('p', 'mm', 'a4');
                
                const imgWidth = 210;
                const imgHeight = canvas.height * imgWidth / canvas.width;
                let heightLeft = imgHeight;
                
                let position = 0;
                
                pdf.addImage(canvas.toDataURL('image/jpeg', 0.8), 'JPEG', 0, position, imgWidth, imgHeight);
                heightLeft -= 297;
                
                while (heightLeft >= 0) {
                    position = heightLeft - imgHeight;
                    pdf.addPage();
                    pdf.addImage(canvas.toDataURL('image/jpeg', 0.8), 'JPEG', 0, position, imgWidth, imgHeight);
                    heightLeft -= 297;
                }
                
                pdf.save(`${<?php echo json_encode($order['title']); ?>}_分析报告.pdf`);
                
                button.innerHTML = originalText;
                button.disabled = false;
            }).catch(error => {
                console.error('导出PDF失败:', error);
                alert('导出失败，请重试');
                
                // 恢复隐藏的元素
                elementsToHide.forEach(el => el.style.display = '');
                button.innerHTML = originalText;
                button.disabled = false;
            });
        }
        
        // 创建评分图表
        document.addEventListener('DOMContentLoaded', function() {
            const score = <?php echo $reportData['score'] ?? 0; ?>;
            
            // 可以在这里添加Chart.js图表
            // 例如雷达图、柱状图等来展示更详细的分析数据
        });
    </script>
</body>
</html>