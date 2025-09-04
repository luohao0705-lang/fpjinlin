<?php
/**
 * 兑换码管理页面
 */
require_once '../config/config.php';
require_once '../config/database.php';

// 检查管理员登录
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$admin = (new Database())->fetchOne("SELECT * FROM admins WHERE id = ?", [$_SESSION['admin_id']]);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>兑换码管理 - <?php echo APP_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- 自定义样式 -->
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- 导航栏 -->
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-cog me-2"></i><?php echo APP_NAME; ?> 管理后台
            </a>
            <div class="navbar-nav flex-row">
                <a class="nav-link text-light" href="index.php">
                    <i class="fas fa-tachometer-alt me-1"></i>仪表盘
                </a>
                <a class="nav-link text-light" href="logout.php">
                    <i class="fas fa-sign-out-alt me-1"></i>退出
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- 侧边栏 -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="index.php">
                                <i class="fas fa-tachometer-alt me-2"></i>仪表盘
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="users.php">
                                <i class="fas fa-users me-2"></i>用户管理
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="orders.php">
                                <i class="fas fa-list-alt me-2"></i>订单管理
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="exchange_codes.php">
                                <i class="fas fa-ticket-alt me-2"></i>兑换码管理
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="system_config.php">
                                <i class="fas fa-cogs me-2"></i>系统配置
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="statistics.php">
                                <i class="fas fa-chart-bar me-2"></i>数据统计
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="logs.php">
                                <i class="fas fa-history me-2"></i>操作日志
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- 主要内容区域 -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="pt-3 pb-2 mb-3 border-bottom d-flex justify-content-between align-items-center">
                    <h1 class="h2">兑换码管理</h1>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#generateModal">
                        <i class="fas fa-plus me-1"></i>生成兑换码
                    </button>
                </div>

                <!-- 统计卡片 -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card text-white bg-primary">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <div class="card-title h5">总数量</div>
                                        <div class="h3" id="total-codes">-</div>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-ticket-alt fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="card text-white bg-success">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <div class="card-title h5">已使用</div>
                                        <div class="h3" id="used-codes">-</div>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-check-circle fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="card text-white bg-warning">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <div class="card-title h5">未使用</div>
                                        <div class="h3" id="unused-codes">-</div>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-clock fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="card text-white bg-info">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <div class="card-title h5">使用率</div>
                                        <div class="h3" id="usage-rate">-</div>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-chart-pie fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 筛选和搜索 -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row align-items-end">
                            <div class="col-md-2">
                                <label class="form-label">批次号</label>
                                <select class="form-select" id="batch-filter">
                                    <option value="">全部批次</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">使用状态</label>
                                <select class="form-select" id="status-filter">
                                    <option value="">全部状态</option>
                                    <option value="0">未使用</option>
                                    <option value="1">已使用</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">面值</label>
                                <select class="form-select" id="value-filter">
                                    <option value="">全部面值</option>
                                    <option value="10">10币</option>
                                    <option value="50">50币</option>
                                    <option value="100">100币</option>
                                    <option value="500">500币</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">兑换码搜索</label>
                                <input type="text" class="form-control" id="code-search" placeholder="输入兑换码搜索...">
                            </div>
                            <div class="col-md-2">
                                <button class="btn btn-outline-primary w-100" onclick="loadCodes()">
                                    <i class="fas fa-search me-1"></i>搜索
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 兑换码列表 -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">兑换码列表</h6>
                        <div>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteSelected()" id="delete-selected-btn" style="display: none;">
                                <i class="fas fa-trash me-1"></i>删除选中
                            </button>
                            <button class="btn btn-sm btn-outline-success" onclick="exportCodes()">
                                <i class="fas fa-download me-1"></i>导出
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="codes-container">
                            <!-- 兑换码列表将通过AJAX加载 -->
                            <div class="text-center py-5">
                                <div class="loading"></div>
                                <p class="text-muted mt-3">加载中...</p>
                            </div>
                        </div>
                        
                        <!-- 分页 -->
                        <nav id="pagination-container" class="mt-4" style="display: none;">
                            <ul class="pagination justify-content-center" id="pagination">
                            </ul>
                        </nav>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- 生成兑换码模态框 -->
    <div class="modal fade" id="generateModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus me-2"></i>生成兑换码
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="generate-form">
                        <div class="form-group mb-3">
                            <label for="code-count" class="form-label">生成数量</label>
                            <input type="number" class="form-control" id="code-count" name="count" 
                                   min="1" max="1000" value="10" required>
                            <div class="form-text">一次最多生成1000个兑换码</div>
                        </div>
                        
                        <div class="form-group mb-3">
                            <label for="code-value" class="form-label">面值（精灵币）</label>
                            <select class="form-select" id="code-value" name="value" required>
                                <option value="">请选择面值</option>
                                <option value="10">10个精灵币</option>
                                <option value="50">50个精灵币</option>
                                <option value="100">100个精灵币</option>
                                <option value="500">500个精灵币</option>
                                <option value="1000">1000个精灵币</option>
                            </select>
                        </div>
                        
                        <div class="form-group mb-3">
                            <label for="expires-at" class="form-label">过期时间（可选）</label>
                            <input type="datetime-local" class="form-control" id="expires-at" name="expires_at">
                            <div class="form-text">留空表示永不过期</div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="button" class="btn btn-primary" onclick="generateCodes()">
                        <i class="fas fa-magic me-1"></i>生成兑换码
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- 自定义JS -->
    <script src="../assets/js/app.js"></script>
    
    <script>
        let currentPage = 1;
        let selectedCodes = [];
        
        // 页面加载完成
        $(document).ready(function() {
            loadStatistics();
            loadBatches();
            loadCodes();
            
            // 绑定筛选器事件
            $('#batch-filter, #status-filter, #value-filter').on('change', function() {
                currentPage = 1;
                loadCodes();
            });
            
            // 搜索框防抖
            $('#code-search').on('input', debounce(function() {
                currentPage = 1;
                loadCodes();
            }, 500));
        });
        
        // 加载统计数据
        function loadStatistics() {
            $.get('api/exchange_code_stats.php', function(response) {
                if (response.success) {
                    $('#total-codes').text(response.data.total_codes.toLocaleString());
                    $('#used-codes').text(response.data.used_codes.toLocaleString());
                    $('#unused-codes').text(response.data.unused_codes.toLocaleString());
                    $('#usage-rate').text(response.data.usage_rate + '%');
                }
            });
        }
        
        // 加载批次列表
        function loadBatches() {
            $.get('api/exchange_code_batches.php', function(response) {
                if (response.success) {
                    let options = '<option value="">全部批次</option>';
                    response.data.forEach(function(batch) {
                        options += `<option value="${batch.batch_no}">${batch.batch_no} (${batch.total_count}个)</option>`;
                    });
                    $('#batch-filter').html(options);
                }
            });
        }
        
        // 加载兑换码列表
        function loadCodes(page = 1) {
            currentPage = page;
            
            const filters = {
                batch_no: $('#batch-filter').val(),
                is_used: $('#status-filter').val(),
                value: $('#value-filter').val(),
                code: $('#code-search').val().trim(),
                page: page,
                pageSize: 20
            };
            
            $('#codes-container').html(`
                <div class="text-center py-5">
                    <div class="loading"></div>
                    <p class="text-muted mt-3">加载中...</p>
                </div>
            `);
            
            $.get('api/exchange_codes.php', filters, function(response) {
                if (response.success) {
                    displayCodes(response.data);
                    displayPagination(response.data);
                } else {
                    $('#codes-container').html(`
                        <div class="empty-state">
                            <i class="fas fa-exclamation-circle"></i>
                            <h5>加载失败</h5>
                            <p>${response.message}</p>
                        </div>
                    `);
                }
            }).fail(function() {
                $('#codes-container').html(`
                    <div class="empty-state">
                        <i class="fas fa-wifi"></i>
                        <h5>网络错误</h5>
                        <p>请检查网络连接后重试</p>
                    </div>
                `);
            });
        }
        
        // 显示兑换码列表
        function displayCodes(data) {
            if (!data.codes || data.codes.length === 0) {
                $('#codes-container').html(`
                    <div class="empty-state">
                        <i class="fas fa-ticket-alt"></i>
                        <h5>暂无兑换码</h5>
                        <p>点击"生成兑换码"创建新的兑换码</p>
                    </div>
                `);
                return;
            }
            
            let html = '<div class="table-responsive">';
            html += '<table class="table table-hover">';
            html += '<thead class="table-light">';
            html += '<tr>';
            html += '<th><input type="checkbox" id="select-all" onchange="toggleSelectAll()"></th>';
            html += '<th>兑换码</th><th>面值</th><th>批次号</th><th>状态</th><th>使用者</th><th>创建时间</th><th>使用时间</th><th>操作</th>';
            html += '</tr></thead><tbody>';
            
            data.codes.forEach(function(code) {
                const statusBadge = code.is_used ? 
                    '<span class="badge bg-success">已使用</span>' : 
                    '<span class="badge bg-warning">未使用</span>';
                
                const usedBy = code.used_by_phone || '-';
                const usedAt = code.used_at ? formatDateTime(code.used_at) : '-';
                
                html += `<tr>
                    <td>
                        ${!code.is_used ? `<input type="checkbox" class="code-checkbox" value="${code.id}">` : ''}
                    </td>
                    <td><code>${code.code}</code></td>
                    <td><strong class="text-primary">${code.value}</strong></td>
                    <td><small>${code.batch_no}</small></td>
                    <td>${statusBadge}</td>
                    <td><small>${usedBy}</small></td>
                    <td><small>${formatDateTime(code.created_at)}</small></td>
                    <td><small>${usedAt}</small></td>
                    <td>
                        ${!code.is_used ? 
                            `<button class="btn btn-sm btn-outline-danger" onclick="deleteCode(${code.id})">删除</button>` : 
                            '<span class="text-muted small">已使用</span>'
                        }
                    </td>
                </tr>`;
            });
            
            html += '</tbody></table></div>';
            
            $('#codes-container').html(html);
            
            // 绑定复选框事件
            $('.code-checkbox').on('change', updateSelectedCodes);
        }
        
        // 生成兑换码
        function generateCodes() {
            const count = parseInt($('#code-count').val());
            const value = parseInt($('#code-value').val());
            const expiresAt = $('#expires-at').val();
            
            if (!count || count < 1 || count > 1000) {
                showAlert('warning', '请输入有效的生成数量（1-1000）');
                return;
            }
            
            if (!value) {
                showAlert('warning', '请选择兑换码面值');
                return;
            }
            
            const $modal = $('#generateModal');
            const $btn = $modal.find('.btn-primary');
            $btn.prop('disabled', true).html('<span class="loading"></span> 生成中...');
            
            $.post('api/generate_codes.php', {
                count: count,
                value: value,
                expires_at: expiresAt
            }, function(response) {
                if (response.success) {
                    showAlert('success', `成功生成${response.data.count}个兑换码`);
                    $modal.modal('hide');
                    loadStatistics();
                    loadBatches();
                    loadCodes();
                    
                    // 显示生成结果
                    showGenerateResult(response.data);
                } else {
                    showAlert('danger', response.message || '生成失败');
                }
                
                $btn.prop('disabled', false).html('<i class="fas fa-magic me-1"></i>生成兑换码');
            }).fail(function() {
                showAlert('danger', '网络错误，请重试');
                $btn.prop('disabled', false).html('<i class="fas fa-magic me-1"></i>生成兑换码');
            });
        }
        
        // 显示生成结果
        function showGenerateResult(data) {
            let html = `
                <div class="modal fade" id="resultModal" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">兑换码生成成功</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle me-2"></i>
                                    成功生成 ${data.count} 个面值 ${data.value} 的兑换码
                                </div>
                                <p><strong>批次号：</strong>${data.batchNo}</p>
                                <div class="mb-3">
                                    <button class="btn btn-primary" onclick="exportBatch('${data.batchNo}')">
                                        <i class="fas fa-download me-1"></i>导出兑换码
                                    </button>
                                </div>
                                <div style="max-height: 300px; overflow-y: auto;">
                                    <table class="table table-sm">
                                        <thead><tr><th>兑换码</th></tr></thead>
                                        <tbody>`;
            
            data.codes.forEach(function(code) {
                html += `<tr><td><code>${code}</code></td></tr>`;
            });
            
            html += `</tbody></table></div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            $('body').append(html);
            $('#resultModal').modal('show');
            
            // 模态框关闭后移除
            $('#resultModal').on('hidden.bs.modal', function() {
                $(this).remove();
            });
        }
        
        // 切换全选
        function toggleSelectAll() {
            const checked = $('#select-all').prop('checked');
            $('.code-checkbox').prop('checked', checked);
            updateSelectedCodes();
        }
        
        // 更新选中的兑换码
        function updateSelectedCodes() {
            selectedCodes = [];
            $('.code-checkbox:checked').each(function() {
                selectedCodes.push($(this).val());
            });
            
            $('#delete-selected-btn').toggle(selectedCodes.length > 0);
        }
        
        // 删除选中的兑换码
        function deleteSelected() {
            if (selectedCodes.length === 0) {
                return;
            }
            
            if (!confirm(`确定要删除选中的 ${selectedCodes.length} 个兑换码吗？`)) {
                return;
            }
            
            $.post('api/delete_codes.php', {
                code_ids: selectedCodes
            }, function(response) {
                if (response.success) {
                    showAlert('success', '删除成功');
                    loadStatistics();
                    loadCodes();
                    selectedCodes = [];
                } else {
                    showAlert('danger', response.message || '删除失败');
                }
            }).fail(function() {
                showAlert('danger', '网络错误，请重试');
            });
        }
        
        // 删除单个兑换码
        function deleteCode(codeId) {
            if (!confirm('确定要删除这个兑换码吗？')) {
                return;
            }
            
            $.post('api/delete_codes.php', {
                code_ids: [codeId]
            }, function(response) {
                if (response.success) {
                    showAlert('success', '删除成功');
                    loadStatistics();
                    loadCodes();
                } else {
                    showAlert('danger', response.message || '删除失败');
                }
            }).fail(function() {
                showAlert('danger', '网络错误，请重试');
            });
        }
        
        // 导出兑换码
        function exportCodes() {
            const batchNo = $('#batch-filter').val();
            if (!batchNo) {
                showAlert('warning', '请选择要导出的批次');
                return;
            }
            
            window.open(`api/export_codes.php?batch=${batchNo}`, '_blank');
        }
        
        // 导出指定批次
        function exportBatch(batchNo) {
            window.open(`api/export_codes.php?batch=${batchNo}`, '_blank');
        }
        
        // 显示分页（复用之前的函数）
        function displayPagination(data) {
            if (data.totalPages <= 1) {
                $('#pagination-container').hide();
                return;
            }
            
            $('#pagination-container').show();
            
            let html = '';
            
            if (data.page > 1) {
                html += `<li class="page-item">
                    <a class="page-link" href="#" onclick="loadCodes(${data.page - 1})">上一页</a>
                </li>`;
            }
            
            const startPage = Math.max(1, data.page - 2);
            const endPage = Math.min(data.totalPages, data.page + 2);
            
            for (let i = startPage; i <= endPage; i++) {
                html += `<li class="page-item ${i === data.page ? 'active' : ''}">
                    <a class="page-link" href="#" onclick="loadCodes(${i})">${i}</a>
                </li>`;
            }
            
            if (data.page < data.totalPages) {
                html += `<li class="page-item">
                    <a class="page-link" href="#" onclick="loadCodes(${data.page + 1})">下一页</a>
                </li>`;
            }
            
            $('#pagination').html(html);
        }
    </script>
</body>
</html>