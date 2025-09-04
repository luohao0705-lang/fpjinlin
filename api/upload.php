<?php
/**
 * 文件上传API
 */
require_once '../config/config.php';
require_once '../config/database.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // 检查用户登录
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('请先登录');
    }
    
    // 检查请求方法
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('请求方法错误');
    }
    
    // 检查是否有文件上传
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('文件上传失败');
    }
    
    $file = $_FILES['file'];
    $type = $_POST['type'] ?? '';
    $userId = $_SESSION['user_id'];
    
    // 验证文件类型
    if (!in_array($type, ['screenshots', 'cover', 'script'])) {
        throw new Exception('文件类型错误');
    }
    
    // 验证文件大小
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        throw new Exception('文件大小超过限制');
    }
    
    // 验证文件格式
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if ($type === 'screenshots' || $type === 'cover') {
        if (!in_array($fileExtension, ALLOWED_IMAGE_TYPES)) {
            throw new Exception('只支持图片格式：' . implode(', ', ALLOWED_IMAGE_TYPES));
        }
    } elseif ($type === 'script') {
        if (!in_array($fileExtension, ALLOWED_SCRIPT_TYPES)) {
            throw new Exception('只支持文本格式：' . implode(', ', ALLOWED_SCRIPT_TYPES));
        }
    }
    
    // 生成安全的文件名
    $safeFileName = safeFileName($file['name']);
    
    // 确定保存路径
    $uploadDir = UPLOAD_PATH . '/' . $type . '/';
    $filePath = $uploadDir . $safeFileName;
    $relativePath = '/assets/uploads/' . $type . '/' . $safeFileName;
    
    // 创建目录（如果不存在）
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // 移动上传的文件
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        throw new Exception('文件保存失败');
    }
    
    // 记录文件上传信息到数据库
    $db = new Database();
    $fileId = $db->insert(
        "INSERT INTO file_uploads (user_id, original_name, file_path, file_size, file_type, file_category, upload_ip) 
         VALUES (?, ?, ?, ?, ?, ?, ?)",
        [
            $userId,
            $file['name'],
            $relativePath,
            $file['size'],
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
            'filename' => $safeFileName,
            'original_name' => $file['name'],
            'path' => $relativePath,
            'url' => $relativePath,
            'size' => $file['size'],
            'type' => $type
        ]
    ]);
    
} catch (Exception $e) {
    // 如果文件已经移动，删除它
    if (isset($filePath) && file_exists($filePath)) {
        unlink($filePath);
    }
    
    jsonResponse([
        'success' => false,
        'message' => $e->getMessage()
    ], 400);
}