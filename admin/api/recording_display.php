<?php
/**
 * 录制进度显示组件
 * 包含实时视频流和计时器
 */
require_once '../../config/config.php';
require_once '../../config/database.php';

// 启动session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$orderId = intval($_GET['order_id'] ?? 0);
if (!$orderId) {
    die('订单ID不能为空');
}

$db = new Database();

// 获取订单信息
$order = $db->fetchOne(
    "SELECT * FROM video_analysis_orders WHERE id = ?",
    [$orderId]
);

if (!$order) {
    die('订单不存在');
}

// 获取视频文件信息
$videoFiles = $db->fetchAll(
    "SELECT vf.*, 
            CASE 
                WHEN vf.video_type = 'self' THEN '本方视频'
                WHEN vf.video_type = 'competitor' THEN CONCAT('同行视频', vf.video_index)
                ELSE '未知类型'
            END as display_name
     FROM video_files vf 
     WHERE vf.order_id = ? 
     ORDER BY vf.video_type, vf.video_index",
    [$orderId]
);
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>实时录制监控 - 订单 #<?php echo $orderId; ?></title>
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
            height: 200px;
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
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
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
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4><i class="fas fa-video me-2"></i>实时录制监控</h4>
                    <div>
                        <span class="badge bg-primary">订单 #<?php echo $orderId; ?></span>
                        <span class="badge bg-info"><?php echo htmlspecialchars($order['title']); ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row" id="videoFilesContainer">
            <!-- 视频文件将通过JavaScript动态加载 -->
        </div>
        
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-tasks me-2"></i>工作流进度</h6>
                    </div>
                    <div class="card-body">
                        <div id="workflowProgress">
                            <div class="text-center">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">加载中...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
    let recordingTimers = {};
    
    $(document).ready(function() {
        loadVideoFiles();
        loadWorkflowProgress();
        
        // 每2秒刷新一次
        setInterval(function() {
            loadVideoFiles();
            loadWorkflowProgress();
        }, 2000);
    });
    
    function loadVideoFiles() {
        $.get('enhanced_recording_progress.php?order_id=<?php echo $orderId; ?>', function(response) {
            if (response.success) {
                displayVideoFiles(response.data.video_files);
            }
        }).fail(function() {
            $('#videoFilesContainer').html('<div class="col-12"><div class="alert alert-danger">加载视频文件失败</div></div>');
        });
    }
    
    function displayVideoFiles(videoFiles) {
        let html = '';
        
        videoFiles.forEach(function(videoFile) {
            const statusClass = getStatusClass(videoFile.recording_status);
            const progress = videoFile.recording_progress || 0;
            
            html += `
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card progress-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="mb-0">${videoFile.display_name}</h6>
                                <span class="badge ${statusClass} status-badge">${getStatusText(videoFile.recording_status)}</span>
                            </div>
                            
                            <!-- 视频流显示区域 -->
                            <div class="video-container mb-3">
                                ${getVideoStreamHtml(videoFile)}
                                <div class="video-overlay">
                                    <i class="fas fa-video me-1"></i>${videoFile.display_name}
                                </div>
                                ${videoFile.is_recording ? '<div class="recording-timer" id="timer-' + videoFile.id + '">00:00</div>' : ''}
                            </div>
                            
                            <!-- 进度条 -->
                            <div class="mb-2">
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar ${getProgressBarClass(videoFile.recording_status)}" 
                                         style="width: ${progress}%"></div>
                                </div>
                                <small class="text-muted">${progress}%</small>
                            </div>
                            
                            <!-- 文件信息 -->
                            <div class="file-info">
                                <div class="row">
                                    <div class="col-6">
                                        <i class="fas fa-clock me-1"></i>
                                        <span id="duration-${videoFile.id}">${videoFile.recording_duration_formatted || '0秒'}</span>
                                    </div>
                                    <div class="col-6">
                                        <i class="fas fa-hdd me-1"></i>
                                        <span id="size-${videoFile.id}">${videoFile.file_size_formatted || '0 B'}</span>
                                    </div>
                                </div>
                                ${videoFile.latest_progress ? '<div class="mt-1"><small class="text-muted">' + videoFile.latest_progress.message + '</small></div>' : ''}
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        
        $('#videoFilesContainer').html(html);
        
        // 启动录制计时器
        videoFiles.forEach(function(videoFile) {
            if (videoFile.is_recording) {
                startRecordingTimer(videoFile.id, videoFile.recording_duration || 0);
            }
        });
    }
    
    function getVideoStreamHtml(videoFile) {
        if (videoFile.flv_url) {
            return `
                <video class="video-stream" controls muted>
                    <source src="${videoFile.flv_url}" type="video/mp4">
                    您的浏览器不支持视频播放
                </video>
            `;
        } else {
            return `
                <div class="video-stream d-flex align-items-center justify-content-center text-white">
                    <div class="text-center">
                        <i class="fas fa-video-slash fa-2x mb-2"></i>
                        <div>等待FLV地址配置</div>
                    </div>
                </div>
            `;
        }
    }
    
    function getStatusClass(status) {
        switch(status) {
            case 'recording': return 'bg-danger';
            case 'completed': return 'bg-success';
            case 'failed': return 'bg-danger';
            case 'stopped': return 'bg-warning';
            default: return 'bg-secondary';
        }
    }
    
    function getStatusText(status) {
        switch(status) {
            case 'pending': return '等待录制';
            case 'recording': return '录制中';
            case 'completed': return '录制完成';
            case 'failed': return '录制失败';
            case 'stopped': return '已停止';
            default: return '未知状态';
        }
    }
    
    function getProgressBarClass(status) {
        switch(status) {
            case 'recording': return 'progress-bar-striped progress-bar-animated';
            case 'completed': return 'bg-success';
            case 'failed': return 'bg-danger';
            default: return 'bg-secondary';
        }
    }
    
    function startRecordingTimer(videoFileId, startDuration) {
        if (recordingTimers[videoFileId]) {
            clearInterval(recordingTimers[videoFileId]);
        }
        
        let duration = startDuration;
        
        recordingTimers[videoFileId] = setInterval(function() {
            duration++;
            const minutes = Math.floor(duration / 60);
            const seconds = duration % 60;
            const timeString = String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
            
            const timerElement = document.getElementById('timer-' + videoFileId);
            if (timerElement) {
                timerElement.textContent = timeString;
            }
            
            // 更新时长显示
            const durationElement = document.getElementById('duration-' + videoFileId);
            if (durationElement) {
                durationElement.textContent = formatDuration(duration);
            }
        }, 1000);
    }
    
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
    
    function loadWorkflowProgress() {
        $.get('enhanced_recording_progress.php?order_id=<?php echo $orderId; ?>', function(response) {
            if (response.success) {
                displayWorkflowProgress(response.data.workflow_logs);
            }
        });
    }
    
    function displayWorkflowProgress(logs) {
        let html = '';
        
        if (logs && logs.length > 0) {
            logs.forEach(function(log) {
                const time = new Date(log.created_at).toLocaleTimeString();
                html += `
                    <div class="d-flex align-items-center mb-2">
                        <div class="flex-shrink-0">
                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 30px; height: 30px; font-size: 12px;">
                                ${log.progress}%
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <div class="fw-bold">${log.stage}</div>
                            <div class="text-muted small">${log.message}</div>
                        </div>
                        <div class="flex-shrink-0 text-muted small">${time}</div>
                    </div>
                `;
            });
        } else {
            html = '<div class="text-muted">暂无工作流进度信息</div>';
        }
        
        $('#workflowProgress').html(html);
    }
    </script>
</body>
</html>
