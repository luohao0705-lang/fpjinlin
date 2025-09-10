<?php
/**
 * 实时录制控制界面
 * 支持点击录制按钮，实时查看视频流和录制进度
 */
require_once '../config/config.php';
require_once '../config/database.php';

// 检查管理员登录
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$orderId = intval($_GET['order_id'] ?? 0);
if (!$orderId) {
    die('订单ID不能为空');
}

$db = new Database();
$pdo = $db->getConnection();

// 获取订单信息
$order = $pdo->query("SELECT * FROM video_analysis_orders WHERE id = {$orderId}")->fetch(PDO::FETCH_ASSOC);
if (!$order) {
    die('订单不存在');
}

// 获取视频文件信息
$videoFiles = $pdo->query(
    "SELECT vf.*, 
            CASE 
                WHEN vf.video_type = 'self' THEN '本方视频'
                WHEN vf.video_type = 'competitor' THEN CONCAT('同行视频', vf.video_index)
                ELSE '未知类型'
            END as display_name
     FROM video_files vf 
     WHERE vf.order_id = {$orderId} 
     ORDER BY vf.video_type, vf.video_index"
)->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>实时录制控制 - 订单 #<?php echo $orderId; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .video-container {
            position: relative;
            background: #000;
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 15px;
        }
        .video-stream {
            width: 100%;
            height: 300px;
            object-fit: cover;
        }
        .video-overlay {
            position: absolute;
            top: 10px;
            left: 10px;
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
        }
        .recording-timer {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #dc3545;
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-weight: bold;
            animation: pulse 1s infinite;
        }
        .recording-indicator {
            position: absolute;
            top: 50px;
            right: 10px;
            background: #dc3545;
            color: white;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            animation: blink 1s infinite;
        }
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        @keyframes blink {
            0%, 50% { opacity: 1; }
            51%, 100% { opacity: 0; }
        }
        .control-panel {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .progress-card {
            border-left: 4px solid #007bff;
        }
        .status-badge {
            font-size: 11px;
            padding: 4px 8px;
        }
        .file-info {
            font-size: 12px;
            color: #6c757d;
        }
        .timeline {
            background: #e9ecef;
            height: 20px;
            border-radius: 10px;
            position: relative;
            overflow: hidden;
        }
        .timeline-progress {
            background: linear-gradient(90deg, #007bff, #28a745);
            height: 100%;
            border-radius: 10px;
            transition: width 0.3s ease;
        }
        .timeline-marker {
            position: absolute;
            top: 0;
            height: 100%;
            width: 2px;
            background: #dc3545;
            z-index: 10;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4><i class="fas fa-video me-2"></i>实时录制控制</h4>
                    <div>
                        <span class="badge bg-primary">订单 #<?php echo $orderId; ?></span>
                        <span class="badge bg-info"><?php echo htmlspecialchars($order['title']); ?></span>
                        <a href="video_order_detail.php?id=<?php echo $orderId; ?>" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-arrow-left me-1"></i>返回详情
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 控制面板 -->
        <div class="control-panel">
            <div class="row">
                <div class="col-md-6">
                    <h6><i class="fas fa-cogs me-2"></i>录制控制</h6>
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-success" id="startAllBtn">
                            <i class="fas fa-play me-1"></i>全部开始
                        </button>
                        <button type="button" class="btn btn-danger" id="stopAllBtn">
                            <i class="fas fa-stop me-1"></i>全部停止
                        </button>
                        <button type="button" class="btn btn-info" id="refreshBtn">
                            <i class="fas fa-sync me-1"></i>刷新状态
                        </button>
                    </div>
                </div>
                <div class="col-md-6">
                    <h6><i class="fas fa-info-circle me-2"></i>系统状态</h6>
                    <div id="systemStatus">
                        <span class="badge bg-secondary">检查中...</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 视频文件列表 -->
        <div class="row" id="videoFilesContainer">
            <?php foreach ($videoFiles as $videoFile): ?>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card progress-card" id="card-<?php echo $videoFile['id']; ?>">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0"><?php echo $videoFile['display_name']; ?></h6>
                            <span class="badge bg-secondary status-badge" id="status-<?php echo $videoFile['id']; ?>">
                                <?php echo $videoFile['recording_status'] ?: 'pending'; ?>
                            </span>
                        </div>
                        
                        <!-- 视频流显示区域 -->
                        <div class="video-container mb-3" id="video-<?php echo $videoFile['id']; ?>">
                            <div class="video-stream d-flex align-items-center justify-content-center text-white" id="stream-<?php echo $videoFile['id']; ?>">
                                <div class="text-center">
                                    <i class="fas fa-video-slash fa-2x mb-2"></i>
                                    <div>点击开始录制</div>
                                </div>
                            </div>
                            <div class="video-overlay">
                                <i class="fas fa-video me-1"></i><?php echo $videoFile['display_name']; ?>
                            </div>
                            <div class="recording-timer" id="timer-<?php echo $videoFile['id']; ?>" style="display: none;">00:00</div>
                            <div class="recording-indicator" id="indicator-<?php echo $videoFile['id']; ?>" style="display: none;">● 录制中</div>
                        </div>
                        
                        <!-- 时间线 -->
                        <div class="mb-3">
                            <div class="timeline" id="timeline-<?php echo $videoFile['id']; ?>">
                                <div class="timeline-progress" id="progress-<?php echo $videoFile['id']; ?>" style="width: 0%;"></div>
                                <div class="timeline-marker" id="marker-<?php echo $videoFile['id']; ?>" style="left: 0%;"></div>
                            </div>
                            <div class="d-flex justify-content-between mt-1">
                                <small class="text-muted" id="time-<?php echo $videoFile['id']; ?>">0秒</small>
                                <small class="text-muted" id="progress-text-<?php echo $videoFile['id']; ?>">0%</small>
                            </div>
                        </div>
                        
                        <!-- 控制按钮 -->
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-success" onclick="startRecording(<?php echo $videoFile['id']; ?>)" id="start-btn-<?php echo $videoFile['id']; ?>">
                                <i class="fas fa-play me-1"></i>开始录制
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="stopRecording(<?php echo $videoFile['id']; ?>)" id="stop-btn-<?php echo $videoFile['id']; ?>" style="display: none;">
                                <i class="fas fa-stop me-1"></i>停止录制
                            </button>
                            <button class="btn btn-sm btn-info" onclick="refreshStatus(<?php echo $videoFile['id']; ?>)">
                                <i class="fas fa-sync me-1"></i>刷新
                            </button>
                        </div>
                        
                        <!-- 文件信息 -->
                        <div class="file-info mt-2">
                            <div class="row">
                                <div class="col-6">
                                    <i class="fas fa-clock me-1"></i>
                                    <span id="duration-<?php echo $videoFile['id']; ?>">0秒</span>
                                </div>
                                <div class="col-6">
                                    <i class="fas fa-hdd me-1"></i>
                                    <span id="size-<?php echo $videoFile['id']; ?>">0 B</span>
                                </div>
                            </div>
                            <div id="message-<?php echo $videoFile['id']; ?>" class="mt-1">
                                <small class="text-muted">等待开始录制</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
    let recordingTimers = {};
    let statusTimers = {};
    
    $(document).ready(function() {
        // 初始化状态
        refreshAllStatus();
        
        // 每2秒刷新状态
        setInterval(refreshAllStatus, 2000);
    });
    
    // 开始录制
    function startRecording(videoFileId) {
        $.post('real_time_recorder.php?action=start', {
            video_file_id: videoFileId
        }, function(response) {
            if (response.success) {
                // 更新UI
                updateRecordingUI(videoFileId, 'recording');
                
                // 启动计时器
                startRecordingTimer(videoFileId);
                
                // 显示视频流
                if (response.flv_url) {
                    showVideoStream(videoFileId, response.flv_url);
                }
                
                showMessage(videoFileId, '录制已开始...', 'success');
            } else {
                showMessage(videoFileId, '开始录制失败: ' + response.message, 'danger');
            }
        });
    }
    
    // 停止录制
    function stopRecording(videoFileId) {
        $.post('real_time_recorder.php?action=stop', {
            video_file_id: videoFileId
        }, function(response) {
            if (response.success) {
                // 停止计时器
                stopRecordingTimer(videoFileId);
                
                // 更新UI
                updateRecordingUI(videoFileId, 'stopped');
                
                showMessage(videoFileId, '录制已停止', 'warning');
            } else {
                showMessage(videoFileId, '停止录制失败: ' + response.message, 'danger');
            }
        });
    }
    
    // 刷新状态
    function refreshStatus(videoFileId) {
        $.get('real_time_recorder.php?action=status&video_file_id=' + videoFileId, function(response) {
            if (response.success) {
                updateVideoFileStatus(response.data);
            }
        });
    }
    
    // 刷新所有状态
    function refreshAllStatus() {
        <?php foreach ($videoFiles as $videoFile): ?>
        refreshStatus(<?php echo $videoFile['id']; ?>);
        <?php endforeach; ?>
    }
    
    // 更新录制UI
    function updateRecordingUI(videoFileId, status) {
        const statusMap = {
            'pending': { class: 'bg-secondary', text: '等待录制' },
            'recording': { class: 'bg-danger', text: '录制中' },
            'completed': { class: 'bg-success', text: '录制完成' },
            'failed': { class: 'bg-danger', text: '录制失败' },
            'stopped': { class: 'bg-warning', text: '已停止' }
        };
        
        const statusInfo = statusMap[status] || statusMap['pending'];
        
        // 更新状态徽章
        $(`#status-${videoFileId}`).removeClass().addClass('badge status-badge ' + statusInfo.class).text(statusInfo.text);
        
        // 更新按钮
        if (status === 'recording') {
            $(`#start-btn-${videoFileId}`).hide();
            $(`#stop-btn-${videoFileId}`).show();
            $(`#timer-${videoFileId}`).show();
            $(`#indicator-${videoFileId}`).show();
        } else {
            $(`#start-btn-${videoFileId}`).show();
            $(`#stop-btn-${videoFileId}`).hide();
            $(`#timer-${videoFileId}`).hide();
            $(`#indicator-${videoFileId}`).hide();
        }
    }
    
    // 更新视频文件状态
    function updateVideoFileStatus(data) {
        updateRecordingUI(data.id, data.recording_status);
        
        // 更新时长
        $(`#duration-${data.id}`).text(data.recording_duration_formatted);
        $(`#time-${data.id}`).text(data.recording_duration_formatted);
        
        // 更新文件大小
        $(`#size-${data.id}`).text(data.file_size_formatted);
        
        // 更新进度
        $(`#progress-${data.id}`).css('width', data.recording_progress + '%');
        $(`#progress-text-${data.id}`).text(data.recording_progress + '%');
        
        // 更新消息
        if (data.latest_progress) {
            showMessage(data.id, data.latest_progress.message, 'info');
        }
        
        // 如果正在录制，启动计时器
        if (data.is_recording && !recordingTimers[data.id]) {
            startRecordingTimer(data.id, data.recording_duration || 0);
        }
    }
    
    // 显示视频流
    function showVideoStream(videoFileId, flvUrl) {
        const streamHtml = `
            <video class="video-stream" controls muted>
                <source src="${flvUrl}" type="video/mp4">
                您的浏览器不支持视频播放
            </video>
        `;
        $(`#stream-${videoFileId}`).html(streamHtml);
    }
    
    // 启动录制计时器
    function startRecordingTimer(videoFileId, startDuration = 0) {
        if (recordingTimers[videoFileId]) {
            clearInterval(recordingTimers[videoFileId]);
        }
        
        let duration = startDuration;
        
        recordingTimers[videoFileId] = setInterval(function() {
            duration++;
            const minutes = Math.floor(duration / 60);
            const seconds = duration % 60;
            const timeString = String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
            
            $(`#timer-${videoFileId}`).text(timeString);
            $(`#duration-${videoFileId}`).text(formatDuration(duration));
            $(`#time-${videoFileId}`).text(formatDuration(duration));
        }, 1000);
    }
    
    // 停止录制计时器
    function stopRecordingTimer(videoFileId) {
        if (recordingTimers[videoFileId]) {
            clearInterval(recordingTimers[videoFileId]);
            delete recordingTimers[videoFileId];
        }
    }
    
    // 显示消息
    function showMessage(videoFileId, message, type = 'info') {
        const typeClass = {
            'success': 'text-success',
            'danger': 'text-danger',
            'warning': 'text-warning',
            'info': 'text-info'
        }[type] || 'text-muted';
        
        $(`#message-${videoFileId}`).html(`<small class="${typeClass}">${message}</small>`);
    }
    
    // 格式化时长
    function formatDuration(seconds) {
        if (seconds < 60) {
            return seconds + '秒';
        } else if (seconds < 3600) {
            const minutes = Math.floor(seconds / 60);
            const remainingSeconds = seconds % 60;
            return minutes + '分' + remainingSeconds + '秒';
        } else {
            const hours = Math.floor(seconds / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            return hours + '小时' + minutes + '分钟';
        }
    }
    
    // 全部开始
    $('#startAllBtn').click(function() {
        <?php foreach ($videoFiles as $videoFile): ?>
        startRecording(<?php echo $videoFile['id']; ?>);
        <?php endforeach; ?>
    });
    
    // 全部停止
    $('#stopAllBtn').click(function() {
        <?php foreach ($videoFiles as $videoFile): ?>
        stopRecording(<?php echo $videoFile['id']; ?>);
        <?php endforeach; ?>
    });
    
    // 刷新按钮
    $('#refreshBtn').click(function() {
        refreshAllStatus();
    });
    </script>
</body>
</html>
