<?php
/**
 * 文件上传API
 */
require_once '../config/config.php';
require_once '../config/database.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // 检查用户登录
    if (!SessionManager::isLoggedIn('user')) {
        throw new Exception('请先登录');
    }
    
    // 检查请求方法
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('请求方法错误');
    }
    
    // 检查是否有文件上传
    if (!isset($_FILES['file'])) {
        throw new Exception('没有文件上传');
    }
    
    $file = $_FILES['file'];
    $type = $_POST['type'] ?? '';
    $userId = SessionManager::getUserId('user');
    
    // 验证文件类型参数
    if (!in_array($type, ['screenshots', 'cover', 'script'])) {
        throw new Exception('文件类型错误');
    }
    
    // 使用安全的文件上传验证器
    $uploadResult = FileUploadValidator::moveUploadedFile($file, $type, $userId . '_');
    
    // 记录文件上传信息到数据库
    $db = new Database();
    $fileId = $db->insert(
        "INSERT INTO file_uploads (user_id, original_name, file_path, file_size, file_type, file_category, upload_ip) 
         VALUES (?, ?, ?, ?, ?, ?, ?)",
        [
            $userId,
            $file['name'],
            $uploadResult['url'],
            $uploadResult['size'],
            $file['type'],
            $type === 'screenshots' ? 'screenshot' : ($type === 'cover' ? 'cover' : 'script'),
            $_SERVER['REMOTE_ADDR'] ?? ''
        ]
    );
    
    // 记录操作日志
    $operationLog = new OperationLog();
    $operationLog->log('user', $userId, 'upload_file', 'file', $fileId, "上传{$type}文件: {$file['name']}");
    
    jsonResponse([
        'success' => true,
        'message' => '文件上传成功',
        'data' => [
            'id' => $fileId,
            'filename' => $uploadResult['filename'],
            'original_name' => $file['name'],
            'path' => $uploadResult['url'],
            'url' => $uploadResult['url'],
            'size' => $uploadResult['size'],
            'type' => $type
        ]
    ]);
    
} catch (Exception $e) {
    // 如果文件已经移动，删除它
    if (isset($uploadResult) && isset($uploadResult['path']) && file_exists($uploadResult['path'])) {
        unlink($uploadResult['path']);
    }
    
    ErrorHandler::apiError($e->getMessage());
}