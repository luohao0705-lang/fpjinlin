<?php
/**
 * 录制进度测试页面
 * 用于测试录制进度监控功能
 */
require_once 'config/config.php';
require_once 'config/database.php';

$orderId = intval($_GET['order_id'] ?? 16); // 默认测试订单ID
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>录制进度测试 - 复盘精灵</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-video me-2"></i>录制进度测试</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h5>测试控制</h5>
                                <div class="mb-3">
                                    <label for="orderId" class="form-label">订单ID</label>
                                    <input type="number" class="form-control" id="orderId" value="<?php echo $orderId; ?>">
                                </div>
                                
                                <button type="button" class="btn btn-primary" id="loadProgressBtn">
                                    <i class="fas fa-sync me-2"></i>加载进度
                                </button>
                                
                                <button type="button" class="btn btn-success" id="startRecordingBtn">
                                    <i class="fas fa-play me-2"></i>开始录制
                                </button>
                                
                                <button type="button" class="btn btn-warning" id="processTasksBtn">
                                    <i class="fas fa-cogs me-2"></i>处理任务
                                </button>
                            </div>
                            
                            <div class="col-md-6">
                                <h5>录制进度</h5>
                                <div id="recordingProgressContainer">
                                    <div class="text-center">
                                        <div class="spinner-border text-info" role="status">
                                            <span class="visually-hidden">加载中...</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <h5>进度日志</h5>
                            <div id="progressLogContainer" style="max-height: 300px; overflow-y: auto; background: #f8f9fa; padding: 15px; border-radius: 5px;">
                                <div class="text-muted">等待加载进度信息...</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        let autoRefresh = false;
        
        // 加载录制进度
        function loadRecordingProgress() {
            const orderId = $('#orderId').val();
            $.get(`admin/api/recording_progress.php?order_id=${orderId}`, function(data) {
                if (data.success) {
                    displayRecordingProgress(data.data);
                    addLog(`✅ 加载进度成功: 订单 ${orderId}`);
                } else {
                    addLog(`❌ 加载进度失败: ${data.message}`);
                }
            }).fail(function() {
                addLog('❌ 加载进度请求失败');
            });
        }
        
        // 显示录制进度
        function displayRecordingProgress(data) {
            if (!data || data.length === 0) {
                $('#recordingProgressContainer').html('<div class="text-muted">暂无录制进度信息</div>');
                return;
            }
            
            let html = '';
            data.forEach(function(videoFile) {
                const statusClass = getRecordingStatusClass(videoFile.recording_status);
                const progress = videoFile.recording_progress || 0;
                const message = videoFile.latest_progress ? videoFile.latest_progress.message : '等待开始';
                
                html += `
                    <div class="card mb-2">
                        <div class="card-body p-2">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge ${statusClass}">${getRecordingStatusText(videoFile.recording_status)}</span>
                                <small class="text-muted">${videoFile.video_type === 'self' ? '本方视频' : '同行视频' + videoFile.video_index}</small>
                            </div>
                            <div class="progress mb-2" style="height: 20px;">
                                <div class="progress-bar ${getProgressBarClass(videoFile.recording_status)}" 
                                     role="progressbar" style="width: ${progress}%" 
                                     aria-valuenow="${progress}" aria-valuemin="0" aria-valuemax="100">
                                    ${progress}%
                                </div>
                            </div>
                            <div class="small text-muted">
                                ${message}
                                ${videoFile.duration ? ` | 时长: ${videoFile.duration}秒` : ''}
                                ${videoFile.file_size ? ` | 大小: ${formatFileSize(videoFile.file_size)}` : ''}
                            </div>
                        </div>
                    </div>
                `;
            });
            
            $('#recordingProgressContainer').html(html);
        }
        
        // 获取录制状态样式类
        function getRecordingStatusClass(status) {
            switch(status) {
                case 'recording': return 'bg-primary';
                case 'completed': return 'bg-success';
                case 'failed': return 'bg-danger';
                default: return 'bg-secondary';
            }
        }
        
        // 获取录制状态文本
        function getRecordingStatusText(status) {
            switch(status) {
                case 'pending': return '等待录制';
                case 'recording': return '录制中';
                case 'completed': return '录制完成';
                case 'failed': return '录制失败';
                default: return '未知状态';
            }
        }
        
        // 获取进度条样式类
        function getProgressBarClass(status) {
            switch(status) {
                case 'recording': return 'progress-bar-striped progress-bar-animated';
                case 'completed': return 'bg-success';
                case 'failed': return 'bg-danger';
                default: return 'bg-secondary';
            }
        }
        
        // 格式化文件大小
        function formatFileSize(bytes) {
            if (!bytes) return '0 B';
            const units = ['B', 'KB', 'MB', 'GB'];
            let unitIndex = 0;
            while (bytes >= 1024 && unitIndex < units.length - 1) {
                bytes /= 1024;
                unitIndex++;
            }
            return Math.round(bytes * 100) / 100 + ' ' + units[unitIndex];
        }
        
        // 添加日志
        function addLog(message) {
            const timestamp = new Date().toLocaleTimeString();
            const logHtml = `<div class="small text-muted mb-1">[${timestamp}] ${message}</div>`;
            $('#progressLogContainer').prepend(logHtml);
            
            // 保持日志条数在合理范围内
            const logs = $('#progressLogContainer .small');
            if (logs.length > 50) {
                logs.slice(50).remove();
            }
        }
        
        // 事件绑定
        $(document).ready(function() {
            // 加载进度按钮
            $('#loadProgressBtn').click(function() {
                loadRecordingProgress();
            });
            
            // 开始录制按钮
            $('#startRecordingBtn').click(function() {
                const orderId = $('#orderId').val();
                addLog(`🎬 开始录制测试: 订单 ${orderId}`);
                
                $.get(`admin/api/process_video_tasks.php?order_id=${orderId}`, function(response) {
                    if (response.success) {
                        addLog(`✅ 录制任务启动成功: ${response.message}`);
                        loadRecordingProgress();
                    } else {
                        addLog(`❌ 录制任务启动失败: ${response.message}`);
                    }
                }).fail(function() {
                    addLog('❌ 录制任务启动请求失败');
                });
            });
            
            // 处理任务按钮
            $('#processTasksBtn').click(function() {
                const orderId = $('#orderId').val();
                addLog(`⚙️ 处理任务: 订单 ${orderId}`);
                
                $.get(`admin/api/process_video_tasks.php?order_id=${orderId}`, function(response) {
                    if (response.success) {
                        addLog(`✅ 任务处理成功: ${response.message}`);
                        loadRecordingProgress();
                    } else {
                        addLog(`❌ 任务处理失败: ${response.message}`);
                    }
                }).fail(function() {
                    addLog('❌ 任务处理请求失败');
                });
            });
            
            // 自动刷新开关
            $('#autoRefreshBtn').click(function() {
                autoRefresh = !autoRefresh;
                if (autoRefresh) {
                    $(this).removeClass('btn-outline-success').addClass('btn-success').html('<i class="fas fa-pause me-2"></i>停止自动刷新');
                    setInterval(loadRecordingProgress, 3000);
                    addLog('🔄 开始自动刷新');
                } else {
                    $(this).removeClass('btn-success').addClass('btn-outline-success').html('<i class="fas fa-play me-2"></i>开始自动刷新');
                    addLog('⏸️ 停止自动刷新');
                }
            });
            
            // 初始加载
            loadRecordingProgress();
        });
    </script>
</body>
</html>
