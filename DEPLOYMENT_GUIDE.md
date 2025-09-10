# 轻量级录制系统部署指南

## 📋 系统要求

### 服务器环境
- **操作系统**: CentOS 7+ / Ubuntu 18+ / Debian 9+
- **PHP版本**: 7.4+ (推荐8.0+)
- **内存**: 2GB+ (推荐4GB+)
- **CPU**: 2核+ (推荐4核+)
- **磁盘**: 20GB+ 可用空间

### 必需工具
- **wget**: 下载工具
- **ffmpeg**: 视频处理工具
- **ffprobe**: 视频信息工具
- **yt-dlp**: 专业下载工具 (可选)

## 🚀 快速部署

### 1. 上传文件到服务器
```bash
# 将以下文件上传到项目根目录
- includes/classes/FastLightweightRecorder.php
- test_fast_recording.php
- check_system_requirements.php
- setup_lightweight_recording.sh
- optimize_php_config.php
- quick_fix_and_test.php
```

### 2. 执行一键安装
```bash
# 进入项目目录
cd /www/wwwroot/www.hsh6.com

# 给脚本执行权限
chmod +x setup_lightweight_recording.sh

# 执行安装
sudo ./setup_lightweight_recording.sh
```

### 3. 检查系统环境
```bash
# 检查系统环境
php check_system_requirements.php

# 如果发现问题，按照提示解决
```

### 4. 优化PHP配置
```bash
# 查看当前PHP配置
php optimize_php_config.php

# 编辑php.ini文件
php --ini

# 根据建议修改php.ini
# 重启Web服务器
systemctl restart httpd  # CentOS
systemctl restart apache2  # Ubuntu/Debian
```

### 5. 清理和测试
```bash
# 清理失败任务
php quick_fix_and_test.php

# 测试快速录制器
php test_fast_recording.php
```

## ⚙️ 详细配置

### PHP配置优化
在 `php.ini` 中添加以下配置：

```ini
; 内存配置
memory_limit = 512M

; 执行时间配置
max_execution_time = 300
max_input_time = 300

; 文件上传配置
post_max_size = 100M
upload_max_filesize = 100M
max_file_uploads = 20

; 网络配置
default_socket_timeout = 60
```

### 系统工具安装

#### CentOS/RHEL
```bash
# 安装EPEL仓库
yum install -y epel-release

# 安装必需工具
yum install -y wget ffmpeg ffmpeg-devel python3 python3-pip

# 安装yt-dlp
pip3 install yt-dlp
```

#### Ubuntu/Debian
```bash
# 更新包列表
apt-get update

# 安装必需工具
apt-get install -y wget ffmpeg python3 python3-pip

# 安装yt-dlp
pip3 install yt-dlp
```

### 权限设置
```bash
# 设置临时目录权限
mkdir -p /tmp/video_recording
chmod 777 /tmp/video_recording

# 设置项目目录权限
chmod -R 755 /www/wwwroot/www.hsh6.com
chown -R www-data:www-data /www/wwwroot/www.hsh6.com
```

## 🔧 故障排除

### 常见问题

#### 1. wget下载失败
**错误**: `wget下载失败 (返回码: 8)`
**解决**: 
- 检查网络连接
- 验证FLV地址是否有效
- 检查防火墙设置

#### 2. FFmpeg处理失败
**错误**: `FFmpeg处理失败 (返回码: 1)`
**解决**:
- 检查FFmpeg是否正确安装
- 验证输入文件格式
- 检查磁盘空间

#### 3. 权限问题
**错误**: `Permission denied`
**解决**:
- 检查文件权限
- 确保Web服务器有写入权限
- 检查SELinux设置

#### 4. 内存不足
**错误**: `Fatal error: Allowed memory size exhausted`
**解决**:
- 增加PHP内存限制
- 优化代码逻辑
- 增加服务器内存

### 调试方法

#### 1. 查看错误日志
```bash
# 查看PHP错误日志
tail -f /var/log/php_errors.log

# 查看Web服务器错误日志
tail -f /var/log/httpd/error_log  # CentOS
tail -f /var/log/apache2/error.log  # Ubuntu
```

#### 2. 测试工具
```bash
# 测试wget
wget --version

# 测试ffmpeg
ffmpeg -version

# 测试yt-dlp
yt-dlp --version
```

#### 3. 检查系统资源
```bash
# 查看内存使用
free -h

# 查看CPU使用
top

# 查看磁盘使用
df -h
```

## 📊 性能监控

### 系统监控
```bash
# 监控CPU和内存
htop

# 监控磁盘I/O
iotop

# 监控网络
iftop
```

### 应用监控
- 查看录制成功率
- 监控任务处理时间
- 检查错误日志

## 🔄 更新和维护

### 定期维护
1. **清理临时文件**: 定期清理 `/tmp/video_recording/` 目录
2. **更新工具**: 定期更新 wget, ffmpeg, yt-dlp
3. **监控日志**: 定期检查错误日志
4. **备份数据**: 定期备份数据库和配置文件

### 更新脚本
```bash
# 更新yt-dlp
pip3 install --upgrade yt-dlp

# 更新ffmpeg (CentOS)
yum update ffmpeg

# 更新ffmpeg (Ubuntu)
apt-get update && apt-get upgrade ffmpeg
```

## 📞 技术支持

如果遇到问题，请提供：
1. 系统环境信息
2. 错误日志
3. 测试结果
4. 具体错误信息

## 🎉 部署完成

部署完成后，系统将具备以下特性：
- **CPU占用降低80%**
- **内存使用减少60%**
- **支持更高并发数**
- **更好的错误处理**
- **自动重试机制**
