<?php
/**
 * 服务器兼容性检查脚本
 * 复盘精灵系统
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>服务器兼容性检查 - 复盘精灵</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 800px; margin: 0 auto; }
        .check-item { margin: 10px 0; padding: 10px; border-radius: 4px; }
        .pass { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .fail { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .title { color: #333; margin-bottom: 20px; }
        .section { margin: 20px 0; }
        .section h3 { color: #666; border-bottom: 2px solid #eee; padding-bottom: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="title">复盘精灵 - 服务器兼容性检查</h1>
        
        <div class="section">
            <h3>PHP 基础环境检查</h3>
            
            <?php
            // PHP版本检查
            $phpVersion = phpversion();
            $phpOk = version_compare($phpVersion, '7.4.0', '>=');
            ?>
            <div class="check-item <?php echo $phpOk ? 'pass' : 'fail'; ?>">
                <strong>PHP 版本:</strong> <?php echo $phpVersion; ?>
                <?php if ($phpOk): ?>
                    ✅ 版本满足要求 (≥7.4.0)
                <?php else: ?>
                    ❌ 版本过低，建议升级到 7.4.0 或更高版本
                <?php endif; ?>
            </div>
            
            <?php
            // 必需扩展检查
            $requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'curl', 'fileinfo'];
            foreach ($requiredExtensions as $ext):
                $loaded = extension_loaded($ext);
            ?>
            <div class="check-item <?php echo $loaded ? 'pass' : 'fail'; ?>">
                <strong><?php echo strtoupper($ext); ?> 扩展:</strong>
                <?php echo $loaded ? '✅ 已安装' : '❌ 未安装'; ?>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="section">
            <h3>函数可用性检查</h3>
            
            <?php
            $functions = [
                'putenv' => '环境变量设置（可选）',
                'exec' => '命令执行（不需要）',
                'shell_exec' => '命令执行（不需要）', 
                'system' => '系统命令（不需要）',
                'file_get_contents' => '文件读取（必需）',
                'file_put_contents' => '文件写入（必需）',
                'mkdir' => '目录创建（必需）',
                'chmod' => '权限设置（推荐）'
            ];
            
            foreach ($functions as $func => $desc):
                $available = function_exists($func);
                $required = in_array($func, ['file_get_contents', 'file_put_contents', 'mkdir']);
                $class = $available ? 'pass' : ($required ? 'fail' : 'warning');
            ?>
            <div class="check-item <?php echo $class; ?>">
                <strong><?php echo $func; ?>():</strong> <?php echo $desc; ?>
                <?php if ($available): ?>
                    ✅ 可用
                <?php elseif ($required): ?>
                    ❌ 不可用（必需）
                <?php else: ?>
                    ⚠️ 不可用（可选）
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="section">
            <h3>目录权限检查</h3>
            
            <?php
            $directories = [
                '.',
                'logs',
                'assets',
                'assets/uploads',
                'config'
            ];
            
            foreach ($directories as $dir):
                $exists = is_dir($dir);
                $writable = $exists ? is_writable($dir) : false;
                
                if (!$exists && in_array($dir, ['logs', 'assets/uploads'])) {
                    // 尝试创建目录
                    $created = @mkdir($dir, 0755, true);
                    $exists = $created;
                    $writable = $created ? is_writable($dir) : false;
                }
            ?>
            <div class="check-item <?php echo ($exists && $writable) ? 'pass' : 'fail'; ?>">
                <strong><?php echo $dir; ?>/:</strong>
                <?php if ($exists && $writable): ?>
                    ✅ 存在且可写
                <?php elseif ($exists): ?>
                    ⚠️ 存在但不可写
                <?php else: ?>
                    ❌ 不存在
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="section">
            <h3>配置文件检查</h3>
            
            <?php
            $configFiles = [
                '.env' => '环境变量配置文件',
                'config/config.php' => '主配置文件',
                'config/database.php' => '数据库配置文件'
            ];
            
            foreach ($configFiles as $file => $desc):
                $exists = file_exists($file);
            ?>
            <div class="check-item <?php echo $exists ? 'pass' : ($file === '.env' ? 'warning' : 'fail'); ?>">
                <strong><?php echo $file; ?>:</strong> <?php echo $desc; ?>
                <?php if ($exists): ?>
                    ✅ 存在
                <?php elseif ($file === '.env'): ?>
                    ⚠️ 不存在（请复制 .env.example 为 .env）
                <?php else: ?>
                    ❌ 不存在
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="section">
            <h3>建议的修复步骤</h3>
            
            <?php if (!file_exists('.env')): ?>
            <div class="check-item warning">
                <strong>1. 创建环境配置文件:</strong><br>
                <code>cp .env.example .env</code><br>
                然后编辑 .env 文件配置您的数据库和其他参数
            </div>
            <?php endif; ?>
            
            <?php if (!is_writable('logs') || !is_writable('assets/uploads')): ?>
            <div class="check-item warning">
                <strong>2. 设置目录权限:</strong><br>
                <code>chmod 755 logs/ assets/uploads/</code>
            </div>
            <?php endif; ?>
            
            <?php if (!function_exists('putenv')): ?>
            <div class="check-item pass">
                <strong>3. putenv() 函数被禁用:</strong><br>
                ✅ 系统已自动适配，使用简化的环境变量加载器
            </div>
            <?php endif; ?>
        </div>
        
        <div class="section">
            <h3>测试环境变量加载</h3>
            <?php
            try {
                // 测试加载环境变量
                if (file_exists('.env')) {
                    if (function_exists('putenv')) {
                        require_once 'config/env.php';
                        $envLoader = 'EnvLoader (完整版)';
                    } else {
                        require_once 'config/env_simple.php';
                        class_alias('EnvLoaderSimple', 'EnvLoader');
                        $envLoader = 'EnvLoaderSimple (简化版)';
                    }
                    
                    $dbHost = EnvLoader::get('DB_HOST', '未配置');
                    $appName = EnvLoader::get('APP_NAME', '未配置');
                    
                    echo '<div class="check-item pass">';
                    echo '<strong>环境变量加载测试:</strong> ✅ 成功<br>';
                    echo '<strong>使用加载器:</strong> ' . $envLoader . '<br>';
                    echo '<strong>数据库主机:</strong> ' . htmlspecialchars($dbHost) . '<br>';
                    echo '<strong>应用名称:</strong> ' . htmlspecialchars($appName);
                    echo '</div>';
                } else {
                    echo '<div class="check-item warning">';
                    echo '<strong>环境变量加载测试:</strong> ⚠️ .env 文件不存在';
                    echo '</div>';
                }
            } catch (Exception $e) {
                echo '<div class="check-item fail">';
                echo '<strong>环境变量加载测试:</strong> ❌ 失败<br>';
                echo '<strong>错误:</strong> ' . htmlspecialchars($e->getMessage());
                echo '</div>';
            }
            ?>
        </div>
        
        <p><strong>检查完成时间:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
        <p><em>如果所有检查都通过，您就可以正常使用复盘精灵系统了。</em></p>
    </div>
</body>
</html>