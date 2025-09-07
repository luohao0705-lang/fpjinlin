<?php
/**
 * 文件上传验证类
 * 复盘精灵系统
 */

class FileUploadValidator {
    
    // 允许的MIME类型
    private static $allowedMimeTypes = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/gif' => ['gif'],
        'text/plain' => ['txt']
    ];
    
    // 危险的文件扩展名
    private static $dangerousExtensions = [
        'php', 'php3', 'php4', 'php5', 'phtml', 'asp', 'aspx', 'jsp', 'js', 'html', 'htm',
        'exe', 'bat', 'cmd', 'com', 'scr', 'vbs', 'jar', 'sh', 'py', 'pl', 'rb'
    ];
    
    /**
     * 验证上传文件
     */
    public static function validate($file, $allowedTypes = ['image']) {
        if (!isset($file['error']) || is_array($file['error'])) {
            throw new Exception('文件上传参数无效');
        }
        
        // 检查上传错误
        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                throw new Exception('没有选择文件');
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new Exception('文件大小超过限制');
            default:
                throw new Exception('文件上传失败');
        }
        
        // 检查文件大小
        if ($file['size'] > MAX_UPLOAD_SIZE) {
            throw new Exception('文件大小超过限制：' . formatFileSize(MAX_UPLOAD_SIZE));
        }
        
        // 获取文件信息
        $fileInfo = pathinfo($file['name']);
        $extension = strtolower($fileInfo['extension'] ?? '');
        
        // 检查危险扩展名
        if (in_array($extension, self::$dangerousExtensions)) {
            throw new Exception('不允许上传此类型的文件');
        }
        
        // 验证MIME类型
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if (!$finfo) {
            throw new Exception('无法验证文件类型');
        }
        
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!$mimeType || !isset(self::$allowedMimeTypes[$mimeType])) {
            throw new Exception('不支持的文件类型');
        }
        
        // 验证扩展名与MIME类型匹配
        if (!in_array($extension, self::$allowedMimeTypes[$mimeType])) {
            throw new Exception('文件扩展名与内容不匹配');
        }
        
        // 根据允许的类型进行额外验证
        if (in_array('image', $allowedTypes) && strpos($mimeType, 'image/') === 0) {
            self::validateImage($file['tmp_name']);
        }
        
        return true;
    }
    
    /**
     * 验证图片文件
     */
    private static function validateImage($filePath) {
        // 使用getimagesize验证图片
        $imageInfo = getimagesize($filePath);
        if (!$imageInfo) {
            throw new Exception('无效的图片文件');
        }
        
        // 检查图片尺寸
        $width = $imageInfo[0];
        $height = $imageInfo[1];
        
        if ($width > 4096 || $height > 4096) {
            throw new Exception('图片尺寸过大，最大支持4096x4096');
        }
        
        if ($width < 10 || $height < 10) {
            throw new Exception('图片尺寸过小');
        }
        
        return true;
    }
    
    /**
     * 生成安全的文件名
     */
    public static function generateSafeFileName($originalName, $prefix = '') {
        $fileInfo = pathinfo($originalName);
        $extension = strtolower($fileInfo['extension'] ?? '');
        
        // 生成唯一文件名
        $safeName = $prefix . date('YmdHis') . '_' . bin2hex(random_bytes(8));
        
        return $safeName . ($extension ? '.' . $extension : '');
    }
    
    /**
     * 创建上传目录
     */
    public static function createUploadDirectory($subDir = '') {
        $uploadPath = UPLOAD_PATH;
        if ($subDir) {
            $uploadPath .= '/' . $subDir;
        }
        
        if (!is_dir($uploadPath)) {
            if (!mkdir($uploadPath, 0755, true)) {
                throw new Exception('无法创建上传目录');
            }
        }
        
        // 创建.htaccess文件防止直接访问PHP文件
        $htaccessFile = $uploadPath . '/.htaccess';
        if (!file_exists($htaccessFile)) {
            $htaccessContent = "# 禁止执行PHP文件\n";
            $htaccessContent .= "php_flag engine off\n";
            $htaccessContent .= "<Files \"*.php\">\n";
            $htaccessContent .= "    Deny from all\n";
            $htaccessContent .= "</Files>\n";
            
            file_put_contents($htaccessFile, $htaccessContent);
        }
        
        return $uploadPath;
    }
    
    /**
     * 移动上传文件到安全位置
     */
    public static function moveUploadedFile($file, $subDir = '', $prefix = '') {
        // 验证文件
        self::validate($file);
        
        // 创建上传目录
        $uploadDir = self::createUploadDirectory($subDir);
        
        // 生成安全文件名
        $safeFileName = self::generateSafeFileName($file['name'], $prefix);
        $targetPath = $uploadDir . '/' . $safeFileName;
        
        // 移动文件
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new Exception('文件保存失败');
        }
        
        // 设置文件权限
        chmod($targetPath, 0644);
        
        return [
            'filename' => $safeFileName,
            'path' => $targetPath,
            'url' => str_replace(BASE_PATH, '', $targetPath),
            'size' => $file['size']
        ];
    }
}