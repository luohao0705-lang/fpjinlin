<?php
/**
 * 测试管理员登录功能
 */

// 设置错误报告级别，避免显示警告
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 1);

// 输出HTML头部，确保正确显示中文
header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>\n";
echo "<html lang='zh-CN'>\n";
echo "<head>\n";
echo "<meta charset='UTF-8'>\n";
echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>\n";
echo "<title>复盘精灵系统诊断</title>\n";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;line-height:1.6;} .success{color:green;} .error{color:red;} .warning{color:orange;} pre{background:#f5f5f5;padding:10px;border-radius:5px;}</style>\n";
echo "</head>\n";
echo "<body>\n";
echo "<h1>复盘精灵后台登录诊断</h1>\n";

try {
    require_once 'config/config.php';
    require_once 'config/database.php';
    echo "<p class='success'>✓ 配置文件加载成功</p>\n";

    // 1. 测试数据库连接
    echo "<h2>1. 测试数据库连接</h2>\n";
    $db = new Database();
    $conn = $db->getConnection();
    echo "<p class='success'>✓ 数据库连接成功</p>\n";
    
    // 2. 检查admins表
    echo "<h2>2. 检查admins表</h2>\n";
    $result = $conn->query('SHOW TABLES LIKE "admins"');
    if ($result->rowCount() > 0) {
        echo "<p class='success'>✓ admins表存在</p>\n";
        
        // 检查管理员账户
        $admin = $db->fetchOne('SELECT * FROM admins WHERE username = ?', ['admin']);
        if ($admin) {
            echo "<p class='success'>✓ 默认管理员账户存在</p>\n";
            echo "<ul>\n";
            echo "<li>用户名: " . htmlspecialchars($admin['username']) . "</li>\n";
            echo "<li>状态: " . ($admin['status'] ? '正常' : '禁用') . "</li>\n";
            echo "<li>角色: " . htmlspecialchars($admin['role']) . "</li>\n";
            echo "</ul>\n";
            
            // 测试密码验证
            echo "<h2>3. 测试密码验证</h2>\n";
            if (password_verify('admin123', $admin['password_hash'])) {
                echo "<p class='success'>✓ 密码验证成功</p>\n";
            } else {
                echo "<p class='error'>✗ 密码验证失败 - 这是登录问题的根源！</p>\n";
                echo "<p>当前哈希: <code>" . htmlspecialchars($admin['password_hash']) . "</code></p>\n";
                
                // 生成正确的密码哈希
                $correctHash = password_hash('admin123', PASSWORD_DEFAULT);
                echo "<p>正确哈希应该是类似: <code>" . htmlspecialchars($correctHash) . "</code></p>\n";
                echo "<p class='warning'>建议运行以下SQL修复:</p>\n";
                echo "<pre>UPDATE admins SET password_hash = '" . htmlspecialchars($correctHash) . "' WHERE username = 'admin';</pre>\n";
            }
        } else {
            echo "<p class='error'>✗ 默认管理员账户不存在</p>\n";
            echo "<p class='warning'>需要插入默认账户</p>\n";
        }
    } else {
        echo "<p class='error'>✗ admins表不存在</p>\n";
        echo "<p class='warning'>需要导入database/schema.sql</p>\n";
    }
    
    // 4. 检查必要目录
    echo "<h2>4. 检查必要目录</h2>\n";
    $directories = [
        'logs' => LOG_PATH,
        'uploads' => UPLOAD_PATH,
        'screenshots' => UPLOAD_PATH . '/screenshots',
        'covers' => UPLOAD_PATH . '/covers',
        'scripts' => UPLOAD_PATH . '/scripts',
        'reports' => UPLOAD_PATH . '/reports'
    ];
    
    echo "<ul>\n";
    foreach ($directories as $name => $path) {
        if (is_dir($path) && is_writable($path)) {
            echo "<li class='success'>✓ {$name}目录存在且可写: " . htmlspecialchars($path) . "</li>\n";
        } else {
            echo "<li class='error'>✗ {$name}目录问题: " . htmlspecialchars($path) . "</li>\n";
        }
    }
    echo "</ul>\n";
    
    // 5. 检查关键类文件
    echo "<h2>5. 检查关键类文件</h2>\n";
    echo "<ul>\n";
    
    $classes = [
        'AnalysisOrder' => [
            'fpjinlin/includes/AnalysisOrder.php'
        ],
        'ExchangeCode' => [
            'includes/classes/ExchangeCode.php',
            'fpjinlin/includes/ExchangeCode.php'
        ],
        'OperationLog' => [
            'includes/classes/OperationLog.php'
        ],
        'User' => [
            'includes/classes/User.php',
            'fpjinlin/includes/User.php'
        ]
    ];
    
    foreach ($classes as $className => $possiblePaths) {
        echo "<li><strong>{$className}类:</strong><ul>\n";
        
        $foundFiles = [];
        foreach ($possiblePaths as $relativePath) {
            $fullPath = BASE_PATH . '/' . $relativePath;
            if (file_exists($fullPath)) {
                $foundFiles[] = $relativePath;
                echo "<li class='success'>✓ 文件存在: {$relativePath}</li>\n";
                
                // 检查文件内容而不实际包含（避免依赖问题）
                try {
                    $content = file_get_contents($fullPath);
                    if ($content === false) {
                        echo "<li class='error'>✗ 无法读取文件内容</li>\n";
                    } else {
                        // 基本语法检查
                        $hasPhpTag = strpos($content, '<?php') !== false;
                        $hasClassDef = preg_match('/class\s+' . preg_quote($className) . '\s*\{/', $content);
                        $hasUnmatchedBraces = substr_count($content, '{') !== substr_count($content, '}');
                        
                        if (!$hasPhpTag) {
                            echo "<li class='error'>✗ 缺少PHP开始标签</li>\n";
                        } elseif (!$hasClassDef) {
                            echo "<li class='error'>✗ 未找到类定义</li>\n";
                        } elseif ($hasUnmatchedBraces) {
                            echo "<li class='error'>✗ 花括号不匹配</li>\n";
                        } else {
                            echo "<li class='success'>✓ 基本语法检查通过</li>\n";
                            
                            // 检查潜在的依赖问题
                            $dependencies = [];
                            if (strpos($content, 'Database::getInstance()') !== false) {
                                $dependencies[] = 'Database::getInstance() 方法不存在';
                            }
                            if (strpos($content, 'getSystemConfig(') !== false && !function_exists('getSystemConfig')) {
                                $dependencies[] = 'getSystemConfig() 函数可能未加载';
                            }
                            
                            if (!empty($dependencies)) {
                                echo "<li class='warning'>⚠ 发现潜在问题: " . implode(', ', $dependencies) . "</li>\n";
                            }
                        }
                    }
                } catch (Exception $e) {
                    echo "<li class='error'>✗ 文件检查时出错: " . htmlspecialchars($e->getMessage()) . "</li>\n";
                }
            } else {
                echo "<li class='warning'>⚠ 文件不存在: {$relativePath}</li>\n";
            }
        }
        
        if (empty($foundFiles)) {
            echo "<li class='error'>✗ 没有找到 {$className} 类的文件</li>\n";
        } elseif (count($foundFiles) > 1) {
            echo "<li class='warning'>⚠ 发现多个 {$className} 类文件，可能导致冲突</li>\n";
        }
        
        echo "</ul></li>\n";
    }
    
    echo "</ul>\n";
    
    echo "<h2 class='success'>诊断完成</h2>\n";
    echo "<p><a href='javascript:history.back()'>← 返回上一页</a></p>\n";
    
} catch (Exception $e) {
    echo "<p class='error'>✗ 错误: " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "<details><summary>详细信息</summary><pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre></details>\n";
} catch (Error $e) {
    echo "<p class='error'>✗ 致命错误: " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "<details><summary>详细信息</summary><pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre></details>\n";
}

echo "</body></html>\n";
?>