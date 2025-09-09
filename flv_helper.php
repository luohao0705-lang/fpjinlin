<?php
/**
 * FLV地址获取工具
 * 帮助用户获取有效的FLV流地址
 */
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FLV地址获取工具 - 复盘精灵</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-link me-2"></i>FLV地址获取工具</h4>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle me-2"></i>使用说明</h6>
                            <p class="mb-2">由于抖音等直播平台的FLV地址具有时效性，需要实时获取。请按照以下步骤操作：</p>
                            <ol class="mb-0">
                                <li>在浏览器中打开抖音直播间</li>
                                <li>按F12打开开发者工具</li>
                                <li>切换到"Network"（网络）标签页</li>
                                <li>刷新页面或开始观看直播</li>
                                <li>在请求列表中找到包含".flv"的请求</li>
                                <li>复制完整的FLV地址到下方输入框</li>
                            </ol>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <h5>抖音直播间FLV获取步骤</h5>
                                <div class="card">
                                    <div class="card-body">
                                        <h6>方法1：开发者工具</h6>
                                        <ol>
                                            <li>打开抖音直播间</li>
                                            <li>按F12 → Network标签</li>
                                            <li>筛选"Media"类型</li>
                                            <li>找到.flv文件</li>
                                            <li>复制Request URL</li>
                                        </ol>
                                        
                                        <h6>方法2：浏览器插件</h6>
                                        <p>安装"直播助手"等插件，自动提取FLV地址</p>
                                        
                                        <h6>方法3：第三方工具</h6>
                                        <p>使用youtube-dl、you-get等工具获取</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <h5>FLV地址测试</h5>
                                <form method="POST">
                                    <div class="mb-3">
                                        <label for="flv_url" class="form-label">FLV地址</label>
                                        <textarea class="form-control" id="flv_url" name="flv_url" rows="3" 
                                                  placeholder="请粘贴完整的FLV地址，例如：http://pull-flv-xxx.douyincdn.com/xxx.flv?xxx"></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="duration" class="form-label">测试时长（秒）</label>
                                        <input type="number" class="form-control" id="duration" name="duration" 
                                               value="10" min="5" max="60">
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-play me-2"></i>测试FLV地址
                                    </button>
                                </form>
                                
                                <?php if ($_POST): ?>
                                    <div class="mt-3">
                                        <h6>测试结果</h6>
                                        <pre class="bg-light p-3 rounded"><?php
                                        $flvUrl = $_POST['flv_url'] ?? '';
                                        $duration = intval($_POST['duration'] ?? 10);
                                        
                                        if (empty($flvUrl)) {
                                            echo "请输入FLV地址";
                                        } else {
                                            echo "测试FLV地址: {$flvUrl}\n";
                                            echo "测试时长: {$duration}秒\n\n";
                                            
                                            // 检查地址格式
                                            if (strpos($flvUrl, '.flv') === false) {
                                                echo "❌ 警告: 地址中未包含.flv，可能不是有效的FLV流地址\n";
                                            }
                                            
                                            if (strpos($flvUrl, 'douyincdn.com') !== false) {
                                                echo "✅ 检测到抖音CDN地址\n";
                                            }
                                            
                                            // 测试连接
                                            $context = stream_context_create([
                                                'http' => [
                                                    'timeout' => 5,
                                                    'method' => 'HEAD',
                                                    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                                                ]
                                            ]);
                                            
                                            $headers = @get_headers($flvUrl, 1, $context);
                                            if ($headers) {
                                                echo "✅ 地址可访问: " . $headers[0] . "\n";
                                            } else {
                                                echo "⚠️ 地址检查失败，但可能仍然有效（某些流需要特殊处理）\n";
                                            }
                                            
                                            echo "\n建议：如果测试失败，请尝试以下方法：\n";
                                            echo "1. 确保FLV地址是最新的（重新获取）\n";
                                            echo "2. 检查直播间是否正在直播\n";
                                            echo "3. 尝试使用不同的浏览器或设备\n";
                                            echo "4. 联系技术支持\n";
                                        }
                                        ?></pre>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <h5>常见问题</h5>
                            <div class="accordion" id="faqAccordion">
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="faq1">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse1">
                                            FLV地址获取不到怎么办？
                                        </button>
                                    </h2>
                                    <div id="collapse1" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                        <div class="accordion-body">
                                            <ul>
                                                <li>确保直播间正在直播</li>
                                                <li>尝试刷新页面重新获取</li>
                                                <li>检查网络连接是否正常</li>
                                                <li>尝试使用不同的浏览器</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="faq2">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse2">
                                            FLV地址有时效性吗？
                                        </button>
                                    </h2>
                                    <div id="collapse2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                        <div class="accordion-body">
                                            是的，FLV地址通常具有时效性，一般几分钟到几小时就会失效。建议在获取地址后立即使用。
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="faq3">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse3">
                                            录制失败怎么办？
                                        </button>
                                    </h2>
                                    <div id="collapse3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                        <div class="accordion-body">
                                            <ul>
                                                <li>检查FFmpeg是否正确安装</li>
                                                <li>确保服务器有足够的存储空间</li>
                                                <li>检查网络连接是否稳定</li>
                                                <li>尝试使用不同的FLV地址</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4 text-center">
                            <a href="test_flv_recording.php" class="btn btn-success">
                                <i class="fas fa-video me-2"></i>开始录制测试
                            </a>
                            <a href="admin/video_order_detail.php?id=16" class="btn btn-secondary ms-2">
                                <i class="fas fa-arrow-left me-2"></i>返回订单详情
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
