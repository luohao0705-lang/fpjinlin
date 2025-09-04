<?php
/**
 * 系统安装脚本
 * 复盘精灵系统
 */

// 安全检查：只允许在未安装时运行
if (file_exists(__DIR__ . '/config/installed.lock')) {
    die('系统已经安装，如需重新安装请删除 config/installed.lock 文件');
}

$step = intval($_GET['step'] ?? 1);
$error = '';
$success = '';

// 处理安装步骤
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($_POST['step'] ?? '') {
        case '2':
            // 数据库配置测试
            try {
                $host = $_POST['db_host'] ?? 'localhost';
                $name = $_POST['db_name'] ?? 'fpjinlin';
                $user = $_POST['db_user'] ?? 'root';
                $pass = $_POST['db_pass'] ?? '';
                
                // 测试数据库连接
                $dsn = "mysql:host={$host};charset=utf8mb4";
                $pdo = new PDO($dsn, $user, $pass);
                
                // 创建数据库（如果不存在）
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                
                // 保存数据库配置
                $configContent = "<?php\n";
                $configContent .= "define('DB_HOST', '{$host}');\n";
                $configContent .= "define('DB_NAME', '{$name}');\n";
                $configContent .= "define('DB_USER', '{$user}');\n";
                $configContent .= "define('DB_PASS', '{$pass}');\n";
                $configContent .= "define('DB_CHARSET', 'utf8mb4');\n";
                $configContent .= "?>";
                
                file_put_contents(__DIR__ . '/config/database_temp.php', $configContent);
                
                $success = '数据库连接成功！';
                $step = 3;
                
            } catch (Exception $e) {
                $error = '数据库连接失败: ' . $e->getMessage();
            }
            break;
            
        case '3':
            // 导入数据库结构
            try {
                require_once __DIR__ . '/config/database_temp.php';
                
                $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
                $pdo = new PDO($dsn, DB_USER, DB_PASS);
                
                // 读取SQL文件
                $sql = file_get_contents(__DIR__ . '/sql/database_design.sql');
                
                // 执行SQL语句
                $statements = array_filter(array_map('trim', explode(';', $sql)));
                foreach ($statements as $statement) {
                    if (!empty($statement)) {
                        $pdo->exec($statement);
                    }
                }
                
                // 重命名配置文件
                rename(__DIR__ . '/config/database_temp.php', __DIR__ . '/config/database.php');
                
                $success = '数据库初始化成功！';
                $step = 4;
                
            } catch (Exception $e) {
                $error = '数据库初始化失败: ' . $e->getMessage();
            }
            break;
            
        case '4':
            // 完成安装
            try {
                $siteUrl = rtrim($_POST['site_url'] ?? 'http://localhost/fpjinlin', '/');
                $adminPassword = $_POST['admin_password'] ?? 'admin123';
                
                // 更新配置文件中的网站URL
                $configFile = __DIR__ . '/config/config.php';
                $configContent = file_get_contents($configFile);
                $configContent = preg_replace(
                    "/define\('SITE_URL',\s*'[^']*'\);/",
                    "define('SITE_URL', '{$siteUrl}');",
                    $configContent
                );
                file_put_contents($configFile, $configContent);
                
                // 更新管理员密码
                require_once __DIR__ . '/config/config.php';
                $db = Database::getInstance();
                $hashedPassword = password_hash($adminPassword, PASSWORD_DEFAULT);
                $db->query(
                    "UPDATE admins SET password = ? WHERE username = 'admin'",
                    [$hashedPassword]
                );
                
                // 创建安装锁文件
                file_put_contents(__DIR__ . '/config/installed.lock', date('Y-m-d H:i:s'));
                
                $success = '安装完成！';
                $step = 5;
                
            } catch (Exception $e) {
                $error = '安装失败: ' . $e->getMessage();
            }
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统安装 - 复盘精灵</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .install-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            margin: 2rem auto;
            max-width: 600px;
        }
        .install-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 15px 15px 0 0;
            text-align: center;
        }
        .install-body {
            padding: 2rem;
        }
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 2rem;
        }
        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 10px;
            background: #e9ecef;
            color: #6c757d;
        }
        .step.active {
            background: #667eea;
            color: white;
        }
        .step.completed {
            background: #28a745;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="install-container">
            <div class="install-header">
                <h2><i class="bi bi-magic"></i> 复盘精灵</h2>
                <p class="mb-0">系统安装向导</p>
            </div>
            
            <div class="install-body">
                <!-- 步骤指示器 -->
                <div class="step-indicator">
                    <div class="step <?php echo $step >= 1 ? ($step == 1 ? 'active' : 'completed') : ''; ?>">
                        <?php echo $step > 1 ? '<i class="bi bi-check"></i>' : '1'; ?>
                    </div>
                    <div class="step <?php echo $step >= 2 ? ($step == 2 ? 'active' : 'completed') : ''; ?>">
                        <?php echo $step > 2 ? '<i class="bi bi-check"></i>' : '2'; ?>
                    </div>
                    <div class="step <?php echo $step >= 3 ? ($step == 3 ? 'active' : 'completed') : ''; ?>">
                        <?php echo $step > 3 ? '<i class="bi bi-check"></i>' : '3'; ?>
                    </div>
                    <div class="step <?php echo $step >= 4 ? ($step == 4 ? 'active' : 'completed') : ''; ?>">
                        <?php echo $step > 4 ? '<i class="bi bi-check"></i>' : '4'; ?>
                    </div>
                    <div class="step <?php echo $step >= 5 ? 'active' : ''; ?>">
                        <?php echo $step >= 5 ? '<i class="bi bi-check"></i>' : '5'; ?>
                    </div>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>
                
                <?php switch ($step): case 1: ?>
                    <!-- 步骤1: 环境检查 -->
                    <h4>步骤1: 环境检查</h4>
                    <p class="text-muted">检查服务器环境是否满足运行要求</p>
                    
                    <div class="list-group">
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            PHP版本 (≥7.4)
                            <span class="badge bg-<?php echo version_compare(PHP_VERSION, '7.4.0') >= 0 ? 'success' : 'danger'; ?>">
                                <?php echo PHP_VERSION; ?>
                            </span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            PDO扩展
                            <span class="badge bg-<?php echo extension_loaded('pdo') ? 'success' : 'danger'; ?>">
                                <?php echo extension_loaded('pdo') ? '已安装' : '未安装'; ?>
                            </span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            cURL扩展
                            <span class="badge bg-<?php echo extension_loaded('curl') ? 'success' : 'danger'; ?>">
                                <?php echo extension_loaded('curl') ? '已安装' : '未安装'; ?>
                            </span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            GD扩展
                            <span class="badge bg-<?php echo extension_loaded('gd') ? 'success' : 'danger'; ?>">
                                <?php echo extension_loaded('gd') ? '已安装' : '未安装'; ?>
                            </span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            uploads目录可写
                            <span class="badge bg-<?php echo is_writable(__DIR__ . '/assets/uploads') ? 'success' : 'danger'; ?>">
                                <?php echo is_writable(__DIR__ . '/assets/uploads') ? '可写' : '不可写'; ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="text-center mt-4">
                        <a href="?step=2" class="btn btn-primary btn-lg">
                            下一步 <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                    
                <?php break; case 2: ?>
                    <!-- 步骤2: 数据库配置 -->
                    <h4>步骤2: 数据库配置</h4>
                    <p class="text-muted">配置数据库连接信息</p>
                    
                    <form method="POST">
                        <input type="hidden" name="step" value="2">
                        
                        <div class="mb-3">
                            <label for="db_host" class="form-label">数据库主机</label>
                            <input type="text" class="form-control" id="db_host" name="db_host" 
                                   value="localhost" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="db_name" class="form-label">数据库名称</label>
                            <input type="text" class="form-control" id="db_name" name="db_name" 
                                   value="fpjinlin" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="db_user" class="form-label">数据库用户名</label>
                            <input type="text" class="form-control" id="db_user" name="db_user" 
                                   value="root" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="db_pass" class="form-label">数据库密码</label>
                            <input type="password" class="form-control" id="db_pass" name="db_pass">
                        </div>
                        
                        <div class="text-center">
                            <button type="submit" class="btn btn-primary btn-lg">
                                测试连接并继续 <i class="bi bi-arrow-right"></i>
                            </button>
                        </div>
                    </form>
                    
                <?php break; case 3: ?>
                    <!-- 步骤3: 初始化数据库 -->
                    <h4>步骤3: 初始化数据库</h4>
                    <p class="text-muted">创建数据库表结构和初始数据</p>
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> 
                        将创建所需的数据表并插入初始配置数据
                    </div>
                    
                    <form method="POST">
                        <input type="hidden" name="step" value="3">
                        
                        <div class="text-center">
                            <button type="submit" class="btn btn-primary btn-lg">
                                初始化数据库 <i class="bi bi-arrow-right"></i>
                            </button>
                        </div>
                    </form>
                    
                <?php break; case 4: ?>
                    <!-- 步骤4: 基础配置 -->
                    <h4>步骤4: 基础配置</h4>
                    <p class="text-muted">配置网站基础信息</p>
                    
                    <form method="POST">
                        <input type="hidden" name="step" value="4">
                        
                        <div class="mb-3">
                            <label for="site_url" class="form-label">网站地址</label>
                            <input type="url" class="form-control" id="site_url" name="site_url" 
                                   value="http://<?php echo $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']); ?>" required>
                            <div class="form-text">请确保地址正确，影响文件访问和链接生成</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="admin_password" class="form-label">管理员密码</label>
                            <input type="password" class="form-control" id="admin_password" name="admin_password" 
                                   value="admin123" required minlength="6">
                            <div class="form-text">默认管理员用户名: admin</div>
                        </div>
                        
                        <div class="text-center">
                            <button type="submit" class="btn btn-success btn-lg">
                                完成安装 <i class="bi bi-check-circle"></i>
                            </button>
                        </div>
                    </form>
                    
                <?php break; case 5: ?>
                    <!-- 步骤5: 安装完成 -->
                    <h4 class="text-success">
                        <i class="bi bi-check-circle"></i> 安装完成！
                    </h4>
                    <p class="text-muted">复盘精灵系统已成功安装</p>
                    
                    <div class="alert alert-success">
                        <h6>安装信息</h6>
                        <ul class="mb-0">
                            <li>管理员账号: <strong>admin</strong></li>
                            <li>管理员密码: <strong>您刚才设置的密码</strong></li>
                            <li>数据库: <strong><?php echo DB_NAME ?? 'fpjinlin'; ?></strong></li>
                        </ul>
                    </div>
                    
                    <div class="alert alert-warning">
                        <h6>重要提醒</h6>
                        <ul class="mb-0">
                            <li>请及时配置DeepSeek API密钥</li>
                            <li>请配置阿里云SMS服务</li>
                            <li>建议删除install.php文件</li>
                            <li>生产环境请关闭错误显示</li>
                        </ul>
                    </div>
                    
                    <div class="text-center">
                        <a href="admin/login.php" class="btn btn-primary btn-lg me-3">
                            <i class="bi bi-shield-lock"></i> 进入后台管理
                        </a>
                        <a href="pages/user/login.php" class="btn btn-outline-primary btn-lg">
                            <i class="bi bi-house"></i> 访问前台
                        </a>
                    </div>
                    
                <?php break; endswitch; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>