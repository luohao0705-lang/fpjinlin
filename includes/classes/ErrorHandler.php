<?php
/**
 * 统一错误处理类
 * 复盘精灵系统
 */

class ErrorHandler {
    
    /**
     * 设置错误处理器
     */
    public static function init() {
        // 设置异常处理器
        set_exception_handler([self::class, 'handleException']);
        
        // 设置错误处理器
        set_error_handler([self::class, 'handleError']);
        
        // 设置致命错误处理器
        register_shutdown_function([self::class, 'handleFatalError']);
    }
    
    /**
     * 处理异常
     */
    public static function handleException($exception) {
        $error = [
            'type' => 'Exception',
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ];
        
        self::logError($error);
        
        // 根据请求类型返回相应格式
        if (self::isAjaxRequest()) {
            self::sendJsonError($exception->getMessage());
        } else {
            self::showErrorPage($exception->getMessage());
        }
    }
    
    /**
     * 处理错误
     */
    public static function handleError($severity, $message, $file, $line) {
        // 只处理配置的错误级别
        if (!(error_reporting() & $severity)) {
            return false;
        }
        
        $error = [
            'type' => self::getErrorType($severity),
            'message' => $message,
            'file' => $file,
            'line' => $line
        ];
        
        self::logError($error);
        
        // 对于严重错误，抛出异常
        if ($severity & (E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR)) {
            throw new ErrorException($message, 0, $severity, $file, $line);
        }
        
        return true;
    }
    
    /**
     * 处理致命错误
     */
    public static function handleFatalError() {
        $error = error_get_last();
        
        if ($error && ($error['type'] & (E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR))) {
            $errorInfo = [
                'type' => 'Fatal Error',
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line']
            ];
            
            self::logError($errorInfo);
            
            if (self::isAjaxRequest()) {
                self::sendJsonError('系统发生致命错误');
            } else {
                self::showErrorPage('系统发生致命错误');
            }
        }
    }
    
    /**
     * 记录错误日志
     */
    private static function logError($error) {
        $logMessage = sprintf(
            "[%s] %s: %s in %s on line %d",
            date('Y-m-d H:i:s'),
            $error['type'],
            $error['message'],
            $error['file'],
            $error['line']
        );
        
        if (isset($error['trace'])) {
            $logMessage .= "\nStack trace:\n" . $error['trace'];
        }
        
        error_log($logMessage);
        
        // 如果是数据库相关错误，记录到数据库
        try {
            if (class_exists('Database')) {
                $db = new Database();
                $db->insert(
                    "INSERT INTO error_logs (error_type, error_message, file_path, line_number, user_id, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)",
                    [
                        $error['type'],
                        $error['message'],
                        $error['file'],
                        $error['line'],
                        SessionManager::getUserId() ?? null,
                        $_SERVER['REMOTE_ADDR'] ?? '',
                        $_SERVER['HTTP_USER_AGENT'] ?? ''
                    ]
                );
            }
        } catch (Exception $e) {
            // 数据库记录失败，只记录到文件
            error_log("Failed to log error to database: " . $e->getMessage());
        }
    }
    
    /**
     * 发送JSON错误响应
     */
    private static function sendJsonError($message, $code = 500) {
        if (!headers_sent()) {
            http_response_code($code);
            header('Content-Type: application/json; charset=utf-8');
        }
        
        $response = [
            'success' => false,
            'message' => EnvLoader::get('APP_DEBUG', 'false') === 'true' ? $message : '系统错误，请稍后重试',
            'error_code' => $code
        ];
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    /**
     * 显示错误页面
     */
    private static function showErrorPage($message) {
        if (!headers_sent()) {
            http_response_code(500);
        }
        
        $displayMessage = EnvLoader::get('APP_DEBUG', 'false') === 'true' ? $message : '系统错误，请稍后重试';
        
        echo '<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统错误 - ' . APP_NAME . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 50px; background: #f5f5f5; }
        .error-container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 600px; margin: 0 auto; }
        .error-title { color: #d32f2f; font-size: 24px; margin-bottom: 20px; }
        .error-message { color: #666; line-height: 1.6; }
        .back-link { display: inline-block; margin-top: 20px; color: #1976d2; text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="error-container">
        <h1 class="error-title">系统错误</h1>
        <p class="error-message">' . htmlspecialchars($displayMessage) . '</p>
        <a href="javascript:history.back()" class="back-link">← 返回上一页</a>
    </div>
</body>
</html>';
        exit;
    }
    
    /**
     * 检查是否为AJAX请求
     */
    private static function isAjaxRequest() {
        return (
            !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
        ) || (
            !empty($_SERVER['CONTENT_TYPE']) && 
            strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false
        ) || (
            isset($_GET['ajax']) || isset($_POST['ajax'])
        );
    }
    
    /**
     * 获取错误类型名称
     */
    private static function getErrorType($severity) {
        switch ($severity) {
            case E_ERROR:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
            case E_USER_ERROR:
                return 'Fatal Error';
            case E_WARNING:
            case E_CORE_WARNING:
            case E_COMPILE_WARNING:
            case E_USER_WARNING:
                return 'Warning';
            case E_NOTICE:
            case E_USER_NOTICE:
                return 'Notice';
            case E_STRICT:
                return 'Strict';
            case E_DEPRECATED:
            case E_USER_DEPRECATED:
                return 'Deprecated';
            default:
                return 'Unknown Error';
        }
    }
    
    /**
     * 安全的错误响应（用于API）
     */
    public static function apiError($message, $code = 400) {
        self::sendJsonError($message, $code);
    }
    
    /**
     * 安全的成功响应（用于API）
     */
    public static function apiSuccess($data = [], $message = 'success', $code = 200) {
        if (!headers_sent()) {
            http_response_code($code);
            header('Content-Type: application/json; charset=utf-8');
        }
        
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data
        ];
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
}