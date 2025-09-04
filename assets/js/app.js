/**
 * 复盘精灵 - 前端JS功能
 */

// 全局变量
let uploadedFiles = {
    screenshots: [],
    cover: null,
    scripts: []
};

// 页面加载完成
$(document).ready(function() {
    // 初始化文件上传
    initFileUpload();
    
    // 初始化表单验证
    initFormValidation();
    
    // 初始化工具提示
    initTooltips();
});

/**
 * 初始化文件上传功能
 */
function initFileUpload() {
    // 截图上传
    $('#screenshot-upload').on('change', function(e) {
        handleFileUpload(e.target.files, 'screenshots', 5);
    });
    
    // 封面上传
    $('#cover-upload').on('change', function(e) {
        handleFileUpload(e.target.files, 'cover', 1);
    });
    
    // 拖拽上传
    $('.upload-area').on('dragover', function(e) {
        e.preventDefault();
        $(this).addClass('dragover');
    });
    
    $('.upload-area').on('dragleave', function(e) {
        e.preventDefault();
        $(this).removeClass('dragover');
    });
    
    $('.upload-area').on('drop', function(e) {
        e.preventDefault();
        $(this).removeClass('dragover');
        
        let fileType = $(this).data('type');
        let maxFiles = fileType === 'screenshots' ? 5 : 1;
        handleFileUpload(e.originalEvent.dataTransfer.files, fileType, maxFiles);
    });
}

/**
 * 处理文件上传
 */
function handleFileUpload(files, type, maxFiles) {
    if (files.length === 0) return;
    
    if (files.length > maxFiles) {
        showAlert('warning', `最多只能上传${maxFiles}个文件`);
        return;
    }
    
    for (let file of files) {
        if (!validateFile(file, type)) {
            continue;
        }
        
        uploadFile(file, type);
    }
}

/**
 * 验证文件
 */
function validateFile(file, type) {
    const maxSize = 10 * 1024 * 1024; // 10MB
    
    if (file.size > maxSize) {
        showAlert('warning', `文件 ${file.name} 超过10MB限制`);
        return false;
    }
    
    if (type === 'screenshots' || type === 'cover') {
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        if (!allowedTypes.includes(file.type)) {
            showAlert('warning', `文件 ${file.name} 格式不支持，请上传图片文件`);
            return false;
        }
    }
    
    return true;
}

/**
 * 上传文件到服务器
 */
function uploadFile(file, type) {
    const formData = new FormData();
    formData.append('file', file);
    formData.append('type', type);
    
    // 显示上传进度
    showUploadProgress(file.name);
    
    $.ajax({
        url: 'api/upload.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            hideUploadProgress();
            
            if (response.success) {
                addFilePreview(response.data, type);
                showAlert('success', '文件上传成功');
            } else {
                showAlert('danger', response.message || '上传失败');
            }
        },
        error: function() {
            hideUploadProgress();
            showAlert('danger', '网络错误，上传失败');
        }
    });
}

/**
 * 添加文件预览
 */
function addFilePreview(fileData, type) {
    const previewContainer = $(`#${type}-preview`);
    
    if (type === 'screenshots') {
        uploadedFiles.screenshots.push(fileData);
        
        const previewHtml = `
            <div class="image-preview" data-file-id="${fileData.id}">
                <img src="${fileData.url}" alt="截图预览" class="upload-preview">
                <button type="button" class="remove-btn" onclick="removeFile('${fileData.id}', 'screenshots')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        previewContainer.append(previewHtml);
        
    } else if (type === 'cover') {
        uploadedFiles.cover = fileData;
        
        const previewHtml = `
            <div class="image-preview" data-file-id="${fileData.id}">
                <img src="${fileData.url}" alt="封面预览" class="upload-preview">
                <button type="button" class="remove-btn" onclick="removeFile('${fileData.id}', 'cover')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        previewContainer.html(previewHtml);
    }
    
    updateUploadStatus();
}

/**
 * 移除文件
 */
function removeFile(fileId, type) {
    if (type === 'screenshots') {
        uploadedFiles.screenshots = uploadedFiles.screenshots.filter(f => f.id !== fileId);
    } else if (type === 'cover') {
        uploadedFiles.cover = null;
    }
    
    $(`.image-preview[data-file-id="${fileId}"]`).remove();
    updateUploadStatus();
}

/**
 * 更新上传状态
 */
function updateUploadStatus() {
    // 更新截图计数
    $('#screenshot-count').text(uploadedFiles.screenshots.length);
    
    // 检查是否可以提交
    const canSubmit = uploadedFiles.screenshots.length >= 5 && 
                     uploadedFiles.cover && 
                     $('#self-script').val().trim() && 
                     $('#competitor-script-1').val().trim() && 
                     $('#competitor-script-2').val().trim() && 
                     $('#competitor-script-3').val().trim();
    
    $('#submit-analysis').prop('disabled', !canSubmit);
}

/**
 * 显示上传进度
 */
function showUploadProgress(filename) {
    const progressHtml = `
        <div class="upload-progress alert alert-info">
            <i class="fas fa-spinner fa-spin me-2"></i>
            正在上传 ${filename}...
        </div>
    `;
    $('body').append(progressHtml);
}

/**
 * 隐藏上传进度
 */
function hideUploadProgress() {
    $('.upload-progress').remove();
}

/**
 * 显示提示消息
 */
function showAlert(type, message, duration = 5000) {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show position-fixed" 
             style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
            <span>${message}</span>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    $('body').append(alertHtml);
    
    // 自动隐藏
    if (duration > 0) {
        setTimeout(function() {
            $('.alert').last().alert('close');
        }, duration);
    }
}

/**
 * 初始化表单验证
 */
function initFormValidation() {
    // 手机号验证
    $('.phone-input').on('input', function() {
        const phone = $(this).val();
        const isValid = /^1[3-9]\d{9}$/.test(phone);
        
        if (phone && !isValid) {
            $(this).addClass('is-invalid');
            $(this).siblings('.invalid-feedback').text('请输入正确的手机号');
        } else {
            $(this).removeClass('is-invalid');
        }
    });
    
    // 密码强度验证
    $('.password-input').on('input', function() {
        const password = $(this).val();
        const strength = checkPasswordStrength(password);
        
        const strengthBar = $(this).siblings('.password-strength');
        if (strengthBar.length) {
            updatePasswordStrength(strengthBar, strength);
        }
    });
}

/**
 * 检查密码强度
 */
function checkPasswordStrength(password) {
    let score = 0;
    
    if (password.length >= 6) score += 1;
    if (password.length >= 8) score += 1;
    if (/[a-z]/.test(password)) score += 1;
    if (/[A-Z]/.test(password)) score += 1;
    if (/[0-9]/.test(password)) score += 1;
    if (/[^A-Za-z0-9]/.test(password)) score += 1;
    
    return score;
}

/**
 * 更新密码强度显示
 */
function updatePasswordStrength(element, strength) {
    const levels = ['很弱', '弱', '一般', '强', '很强'];
    const colors = ['danger', 'warning', 'info', 'success', 'success'];
    
    const level = Math.min(strength, levels.length - 1);
    const percentage = (strength / 5) * 100;
    
    element.html(`
        <div class="progress mt-2" style="height: 6px;">
            <div class="progress-bar bg-${colors[level]}" style="width: ${percentage}%"></div>
        </div>
        <small class="text-${colors[level]}">${levels[level]}</small>
    `);
}

/**
 * 初始化工具提示
 */
function initTooltips() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

/**
 * 发送短信验证码
 */
function sendSmsCode(phone, type, button) {
    if (!phone || !/^1[3-9]\d{9}$/.test(phone)) {
        showAlert('warning', '请输入正确的手机号');
        return;
    }
    
    const $btn = $(button);
    const originalText = $btn.text();
    
    // 禁用按钮
    $btn.prop('disabled', true).html('<span class="loading"></span> 发送中...');
    
    $.post('api/send_sms.php', {
        phone: phone,
        type: type
    }, function(response) {
        if (response.success) {
            showAlert('success', '验证码发送成功');
            startCountdown($btn, 60);
        } else {
            showAlert('danger', response.message || '发送失败');
            $btn.prop('disabled', false).text(originalText);
        }
    }, 'json').fail(function(xhr, status, error) {
        console.error('SMS发送失败:', xhr.responseText);
        
        // 尝试解析错误响应
        let errorMessage = '网络错误，请重试';
        try {
            const errorResponse = JSON.parse(xhr.responseText);
            if (errorResponse && errorResponse.message) {
                errorMessage = errorResponse.message;
            }
        } catch (e) {
            // 如果解析失败，使用默认错误信息
            console.error('解析错误响应失败:', e);
        }
        
        showAlert('danger', errorMessage);
        $btn.prop('disabled', false).text(originalText);
    });
}

/**
 * 倒计时功能
 */
function startCountdown(button, seconds) {
    let remaining = seconds;
    
    const timer = setInterval(function() {
        button.text(`${remaining}秒后重发`);
        remaining--;
        
        if (remaining < 0) {
            clearInterval(timer);
            button.prop('disabled', false).text('发送验证码');
        }
    }, 1000);
}

/**
 * 格式化日期时间
 */
function formatDateTime(datetime) {
    const date = new Date(datetime);
    return date.toLocaleString('zh-CN');
}

/**
 * 获取状态徽章HTML
 */
function getStatusBadge(status) {
    const statusMap = {
        'pending': '<span class="badge status-pending">待处理</span>',
        'processing': '<span class="badge status-processing">处理中</span>',
        'completed': '<span class="badge status-completed">已完成</span>',
        'failed': '<span class="badge status-failed">失败</span>'
    };
    
    return statusMap[status] || '<span class="badge bg-secondary">未知</span>';
}

/**
 * 获取等级徽章HTML
 */
function getLevelBadge(level) {
    const levelMap = {
        'excellent': '<span class="badge level-excellent">优秀</span>',
        'good': '<span class="badge level-good">良好</span>',
        'average': '<span class="badge level-average">一般</span>',
        'poor': '<span class="badge level-poor">较差</span>',
        'unqualified': '<span class="badge level-unqualified">不合格</span>'
    };
    
    return levelMap[level] || '<span class="badge bg-secondary">未评级</span>';
}

/**
 * 复制文本到剪贴板
 */
function copyToClipboard(text) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(function() {
            showAlert('success', '复制成功');
        }).catch(function() {
            showAlert('danger', '复制失败');
        });
    } else {
        // 兼容旧浏览器
        const textArea = document.createElement('textarea');
        textArea.value = text;
        document.body.appendChild(textArea);
        textArea.select();
        
        try {
            document.execCommand('copy');
            showAlert('success', '复制成功');
        } catch (err) {
            showAlert('danger', '复制失败');
        }
        
        document.body.removeChild(textArea);
    }
}

/**
 * 导出报告为图片
 */
function exportReportAsImage(reportId) {
    const reportElement = document.querySelector('.analysis-report');
    
    if (!reportElement) {
        showAlert('danger', '报告内容未找到');
        return;
    }
    
    // 显示加载状态
    showAlert('info', '正在生成图片，请稍候...', 0);
    
    html2canvas(reportElement, {
        allowTaint: true,
        useCORS: true,
        scale: 2,
        backgroundColor: '#ffffff'
    }).then(function(canvas) {
        // 创建下载链接
        const link = document.createElement('a');
        link.download = `复盘报告_${reportId}_${new Date().getTime()}.png`;
        link.href = canvas.toDataURL('image/png');
        
        // 触发下载
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

/**
 * 导出报告为PDF
 */
function exportReportAsPDF(reportId) {
    const reportElement = document.querySelector('.analysis-report');
    
    if (!reportElement) {
        showAlert('danger', '报告内容未找到');
        return;
    }
    
    // 显示加载状态
    showAlert('info', '正在生成PDF，请稍候...', 0);
    
    html2canvas(reportElement, {
        allowTaint: true,
        useCORS: true,
        scale: 2,
        backgroundColor: '#ffffff'
    }).then(function(canvas) {
        const imgData = canvas.toDataURL('image/png');
        const pdf = new jsPDF('p', 'mm', 'a4');
        
        const imgWidth = 190;
        const pageHeight = 297;
        const imgHeight = (canvas.height * imgWidth) / canvas.width;
        let heightLeft = imgHeight;
        
        let position = 10;
        
        // 添加第一页
        pdf.addImage(imgData, 'PNG', 10, position, imgWidth, imgHeight);
        heightLeft -= pageHeight;
        
        // 如果内容超过一页，添加新页
        while (heightLeft >= 0) {
            position = heightLeft - imgHeight + 10;
            pdf.addPage();
            pdf.addImage(imgData, 'PNG', 10, position, imgWidth, imgHeight);
            heightLeft -= pageHeight;
        }
        
        // 下载PDF
        pdf.save(`复盘报告_${reportId}_${new Date().getTime()}.pdf`);
        
        $('.alert').alert('close');
        showAlert('success', 'PDF导出成功');
    }).catch(function(error) {
        $('.alert').alert('close');
        showAlert('danger', 'PDF导出失败');
        console.error('导出失败:', error);
    });
}

/**
 * 分享报告链接
 */
function shareReportLink(reportId) {
    const shareUrl = `${window.location.origin}/report.php?id=${reportId}&share=1`;
    copyToClipboard(shareUrl);
}

/**
 * 提交分析订单
 */
function submitAnalysis() {
    const title = $('#analysis-title').val().trim();
    const selfScript = $('#self-script').val().trim();
    const competitorScripts = [
        $('#competitor-script-1').val().trim(),
        $('#competitor-script-2').val().trim(),
        $('#competitor-script-3').val().trim()
    ];
    
    // 验证数据
    if (!title) {
        showAlert('warning', '请输入分析标题');
        return;
    }
    
    if (uploadedFiles.screenshots.length < 5) {
        showAlert('warning', '请上传5张直播截图');
        return;
    }
    
    if (!uploadedFiles.cover) {
        showAlert('warning', '请上传封面图');
        return;
    }
    
    if (!selfScript) {
        showAlert('warning', '请输入本方话术');
        return;
    }
    
    for (let i = 0; i < competitorScripts.length; i++) {
        if (!competitorScripts[i]) {
            showAlert('warning', `请输入同行${i + 1}的话术`);
            return;
        }
    }
    
    // 禁用提交按钮
    const $submitBtn = $('#submit-analysis');
    $submitBtn.prop('disabled', true).html('<span class="loading"></span> 提交中...');
    
    // 提交数据
    $.post('api/create_analysis.php', {
        title: title,
        screenshots: uploadedFiles.screenshots.map(f => f.path),
        cover: uploadedFiles.cover.path,
        self_script: selfScript,
        competitor_scripts: competitorScripts
    }, function(response) {
        if (response.success) {
            showAlert('success', '分析订单创建成功，AI正在分析中...');
            
            // 跳转到订单页面
            setTimeout(function() {
                window.location.href = `order_detail.php?id=${response.data.orderId}`;
            }, 2000);
        } else {
            showAlert('danger', response.message || '提交失败');
            $submitBtn.prop('disabled', false).text('提交分析');
        }
    }).fail(function() {
        showAlert('danger', '网络错误，请重试');
        $submitBtn.prop('disabled', false).text('提交分析');
    });
}

/**
 * 使用兑换码
 */
function useExchangeCode() {
    const code = $('#exchange-code').val().trim();
    
    if (!code) {
        showAlert('warning', '请输入兑换码');
        return;
    }
    
    const $submitBtn = $('#use-code-btn');
    $submitBtn.prop('disabled', true).html('<span class="loading"></span> 兑换中...');
    
    $.post('api/use_exchange_code.php', {
        code: code
    }, function(response) {
        if (response.success) {
            showAlert('success', response.message);
            $('#exchange-code').val('');
            
            // 更新余额显示
            setTimeout(function() {
                location.reload();
            }, 1500);
        } else {
            showAlert('danger', response.message || '兑换失败');
        }
        
        $submitBtn.prop('disabled', false).text('立即兑换');
    }).fail(function() {
        showAlert('danger', '网络错误，请重试');
        $submitBtn.prop('disabled', false).text('立即兑换');
    });
}

/**
 * 轮询订单状态
 */
function pollOrderStatus(orderId) {
    const pollInterval = setInterval(function() {
        $.get(`api/order_status.php?id=${orderId}`, function(response) {
            if (response.success) {
                const order = response.data;
                
                // 更新状态显示
                updateOrderStatusDisplay(order);
                
                // 如果订单完成或失败，停止轮询
                if (order.status === 'completed' || order.status === 'failed') {
                    clearInterval(pollInterval);
                    
                    if (order.status === 'completed') {
                        showAlert('success', '分析完成！');
                        // 刷新页面显示报告
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        showAlert('danger', '分析失败：' + order.error_message);
                    }
                }
            }
        });
    }, 5000); // 每5秒轮询一次
    
    // 5分钟后停止轮询
    setTimeout(function() {
        clearInterval(pollInterval);
    }, 300000);
}

/**
 * 更新订单状态显示
 */
function updateOrderStatusDisplay(order) {
    $('#order-status').html(getStatusBadge(order.status));
    
    if (order.status === 'processing') {
        $('#status-message').html('<i class="fas fa-cog fa-spin me-2"></i>AI正在分析中，请耐心等待...');
    }
}

/**
 * 确认删除
 */
function confirmDelete(message, callback) {
    if (confirm(message || '确定要删除吗？此操作不可恢复。')) {
        callback();
    }
}

/**
 * 格式化文件大小
 */
function formatFileSize(bytes) {
    const units = ['B', 'KB', 'MB', 'GB'];
    let i = 0;
    
    while (bytes >= 1024 && i < units.length - 1) {
        bytes /= 1024;
        i++;
    }
    
    return Math.round(bytes * 100) / 100 + ' ' + units[i];
}

/**
 * 防抖函数
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/**
 * 节流函数
 */
function throttle(func, limit) {
    let inThrottle;
    return function() {
        const args = arguments;
        const context = this;
        if (!inThrottle) {
            func.apply(context, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    }
}