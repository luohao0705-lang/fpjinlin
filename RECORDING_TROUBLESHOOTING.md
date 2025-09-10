# 录制功能故障排除指南

## 🎯 优化后的录制流程

### 核心原则
1. **极简设计** - 只专注于录制，不处理复杂逻辑
2. **快速执行** - 避免卡死，快速返回结果
3. **清晰错误** - 明确的错误信息，便于调试
4. **独立运行** - 不依赖其他复杂组件

### 文件结构
```
SimpleRecorder.php          # 简单录制器（核心）
test_recording_only.php     # 只测试录制功能
test_recording_simple.php   # 简单录制测试
debug_recording.php         # 录制调试脚本
```

## 🚀 使用方法

### 1. 基本录制
```php
require_once 'SimpleRecorder.php';

$recorder = new SimpleRecorder();
$result = $recorder->recordVideo($orderId, $flvUrl, $maxDuration);

if ($result['success']) {
    echo "录制成功！";
    echo "文件路径: " . $result['file_path'];
} else {
    echo "录制失败: " . $result['error'];
}
```

### 2. 测试录制功能
```bash
# 在服务器上运行
php test_recording_only.php
```

### 3. 调试录制问题
```bash
# 运行调试脚本
php debug_recording.php
```

## 🔧 常见问题解决

### 问题1：FFmpeg命令失败
**症状**：返回码不为0，输出错误信息
**解决方案**：
1. 检查FFmpeg是否安装：`which ffmpeg`
2. 检查FLV地址是否有效
3. 检查磁盘空间是否足够
4. 检查文件权限

### 问题2：文件权限问题
**症状**：无法创建目录或文件
**解决方案**：
```bash
# 检查临时目录权限
ls -la /tmp/

# 创建测试目录
mkdir -p /tmp/recording_test
chmod 777 /tmp/recording_test

# 检查PHP用户权限
whoami
```

### 问题3：网络连接问题
**症状**：无法下载FLV流
**解决方案**：
1. 检查网络连接
2. 验证FLV地址有效性
3. 检查防火墙设置
4. 使用curl测试连接

### 问题4：录制文件损坏
**症状**：文件存在但无法播放
**解决方案**：
1. 检查FLV地址是否过期
2. 验证网络稳定性
3. 调整录制参数
4. 检查FFmpeg版本

## 📋 调试步骤

### 步骤1：运行调试脚本
```bash
php debug_recording.php
```

### 步骤2：检查系统环境
- PHP版本和配置
- FFmpeg安装和版本
- 目录权限
- 网络连接

### 步骤3：测试基本功能
```bash
php test_recording_only.php
```

### 步骤4：检查错误日志
```bash
tail -f /var/log/php_errors.log
```

## ⚙️ 配置优化

### PHP配置
```ini
max_execution_time = 300
memory_limit = 256M
upload_max_filesize = 100M
post_max_size = 100M
```

### FFmpeg参数优化
```bash
# 基本录制
ffmpeg -i "FLV地址" -t 60 -c copy output.mp4

# 高质量录制
ffmpeg -i "FLV地址" -t 60 -c:v libx264 -c:a aac output.mp4

# 低延迟录制
ffmpeg -i "FLV地址" -t 60 -c copy -avoid_negative_ts make_zero output.mp4
```

## 🎯 最佳实践

### 1. 录制前检查
- 验证FLV地址有效性
- 检查系统资源
- 确保目录权限
- 测试网络连接

### 2. 录制过程监控
- 监控磁盘空间
- 检查进程状态
- 记录错误信息
- 设置超时时间

### 3. 录制后处理
- 验证文件完整性
- 获取视频信息
- 保存到数据库
- 清理临时文件

## 🔍 错误代码对照

| 错误代码 | 含义 | 解决方案 |
|---------|------|----------|
| 1 | FFmpeg命令失败 | 检查FFmpeg安装和参数 |
| 2 | 文件权限问题 | 检查目录权限 |
| 3 | 网络连接失败 | 检查网络和FLV地址 |
| 4 | 文件创建失败 | 检查磁盘空间 |
| 5 | 数据库操作失败 | 检查数据库连接 |

## 📞 技术支持

如果问题仍然存在，请提供以下信息：
1. 调试脚本输出
2. 错误日志
3. 系统环境信息
4. 具体的错误信息

## 🎉 成功标志

录制成功的标志：
1. 返回码为0
2. 文件存在且大小合理
3. 数据库记录正确
4. 视频可以正常播放
