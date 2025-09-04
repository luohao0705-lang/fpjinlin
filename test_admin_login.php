<?php
/**
 * 测试管理员登录功能
 */
require_once 'config/config.php';
require_once 'config/database.php';

echo "=== 复盘精灵后台登录诊断 ===\n\n";

try {
    // 1. 测试数据库连接
    echo "1. 测试数据库连接...\n";
    $db = new Database();
    $conn = $db->getConnection();
    echo "✓ 数据库连接成功\n\n";
    
    // 2. 检查admins表
    echo "2. 检查admins表...\n";
    $result = $conn->query('SHOW TABLES LIKE "admins"');
    if ($result->rowCount() > 0) {
        echo "✓ admins表存在\n";
        
        // 检查管理员账户
        $admin = $db->fetchOne('SELECT * FROM admins WHERE username = ?', ['admin']);
        if ($admin) {
            echo "✓ 默认管理员账户存在\n";
            echo "  - 用户名: {$admin['username']}\n";
            echo "  - 状态: " . ($admin['status'] ? '正常' : '禁用') . "\n";
            echo "  - 角色: {$admin['role']}\n";
            
            // 测试密码验证
            echo "\n3. 测试密码验证...\n";
            if (password_verify('admin123', $admin['password_hash'])) {
                echo "✓ 密码验证成功\n";
            } else {
                echo "✗ 密码验证失败 - 这是登录问题的根源！\n";
                echo "  当前哈希: {$admin['password_hash']}\n";
                
                // 生成正确的密码哈希
                $correctHash = password_hash('admin123', PASSWORD_DEFAULT);
                echo "  正确哈希应该是类似: {$correctHash}\n";
                echo "  建议运行以下SQL修复:\n";
                echo "  UPDATE admins SET password_hash = '{$correctHash}' WHERE username = 'admin';\n";
            }
        } else {
            echo "✗ 默认管理员账户不存在\n";
            echo "  需要插入默认账户\n";
        }
    } else {
        echo "✗ admins表不存在\n";
        echo "  需要导入database/schema.sql\n";
    }
    
    // 4. 检查必要目录
    echo "\n4. 检查必要目录...\n";
    $directories = [
        'logs' => LOG_PATH,
        'uploads' => UPLOAD_PATH,
        'screenshots' => UPLOAD_PATH . '/screenshots',
        'covers' => UPLOAD_PATH . '/covers',
        'scripts' => UPLOAD_PATH . '/scripts',
        'reports' => UPLOAD_PATH . '/reports'
    ];
    
    foreach ($directories as $name => $path) {
        if (is_dir($path) && is_writable($path)) {
            echo "✓ {$name}目录存在且可写\n";
        } else {
            echo "✗ {$name}目录问题: {$path}\n";
        }
    }
    
    // 5. 检查关键类
    echo "\n5. 检查关键类...\n";
    $classes = ['AnalysisOrder', 'ExchangeCode', 'OperationLog', 'User'];
    foreach ($classes as $className) {
        if (class_exists($className)) {
            echo "✓ {$className}类加载成功\n";
        } else {
            echo "✗ {$className}类加载失败\n";
        }
    }
    
    echo "\n=== 诊断完成 ===\n";
    
} catch (Exception $e) {
    echo "✗ 错误: " . $e->getMessage() . "\n";
    echo "详细信息: " . $e->getTraceAsString() . "\n";
}
?>