<?php
/**
 * 存储测试API
 * 复盘精灵系统 - 后台管理
 */
require_once '../../config/config.php';
require_once '../../config/database.php';

// 启动session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

try {
    // 检查管理员登录
    if (!isset($_SESSION['admin_id'])) {
        throw new Exception('请先登录');
    }
    
    require_once '../../includes/classes/StorageManager.php';
    $storageManager = new StorageManager();
    
    // 创建测试文件
    $testContent = "测试文件内容 - " . date('Y-m-d H:i:s');
    $testFile = sys_get_temp_dir() . '/storage_test_' . time() . '.txt';
    file_put_contents($testFile, $testContent);
    
    // 测试上传
    $ossKey = 'test/storage_test_' . time() . '.txt';
    $uploadedKey = $storageManager->upload($testFile, $ossKey);
    
    // 测试下载
    $downloadedFile = $storageManager->download($uploadedKey);
    $downloadedContent = file_get_contents($downloadedFile);
    
    // 验证内容
    if ($downloadedContent === $testContent) {
        // 测试删除
        $storageManager->delete($uploadedKey);
        
        // 清理临时文件
        unlink($testFile);
        if ($downloadedFile !== $testFile) {
            unlink($downloadedFile);
        }
        
        echo json_encode([
            'success' => true,
            'message' => '存储功能正常，文件上传、下载、删除测试通过'
        ]);
    } else {
        throw new Exception('文件内容验证失败');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
