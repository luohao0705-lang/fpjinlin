# 视频分析功能安装部署指南

## 概述

本文档介绍如何在复盘精灵系统中安装和配置视频分析功能。该功能支持抖音、快手、小红书平台的视频链接分析，使用FFmpeg进行视频处理，Whisper进行语音识别，阿里云Qwen-Omni进行视频理解。

## 系统要求

### 基础环境
- PHP 7.4+
- MySQL 5.7+
- Apache/Nginx
- FFmpeg 4.0+
- Python 3.8+ (用于Whisper)

### 扩展要求
- cURL扩展
- PDO扩展
- GD扩展
- exec()函数可用
- 足够的磁盘空间（建议50GB+）

## 安装步骤

### 1. 数据库更新

```bash
# 导入视频分析相关表结构
mysql -u root -p < database/video_analysis_schema.sql

# 更新系统配置
php update_system_config.php
```

### 2. 安装FFmpeg

#### Ubuntu/Debian
```bash
sudo apt update
sudo apt install ffmpeg
```

#### CentOS/RHEL
```bash
sudo yum install epel-release
sudo yum install ffmpeg
```

#### Windows
1. 下载FFmpeg: https://ffmpeg.org/download.html
2. 解压到系统目录
3. 添加到PATH环境变量

### 3. 安装Whisper

```bash
# 安装Python依赖
pip install openai-whisper

# 下载模型（选择base模型，平衡速度和精度）
whisper --model base --download-only

# 或者下载其他模型
whisper --model small --download-only  # 更高精度
whisper --model large --download-only  # 最高精度
```

### 4. 配置阿里云OSS

1. 登录阿里云控制台
2. 创建OSS存储桶
3. 获取AccessKey和SecretKey
4. 在后台管理 -> 系统配置中设置：
   - OSS存储桶名称
   - OSS端点
   - AccessKey ID
   - AccessKey Secret

### 5. 配置阿里云Qwen-Omni

1. 登录阿里云控制台
2. 开通通义千问服务
3. 获取API密钥
4. 在后台管理 -> 系统配置中设置：
   - Qwen-Omni API密钥
   - API地址（默认即可）

### 6. 设置目录权限

```bash
# 设置上传目录权限
chmod -R 755 assets/uploads/
chown -R www-data:www-data assets/uploads/

# 设置脚本执行权限
chmod +x scripts/video_processing_worker.php
```

### 7. 配置定时任务

```bash
# 编辑crontab
crontab -e

# 添加以下任务（每5分钟检查一次队列）
*/5 * * * * /usr/bin/php /path/to/your/project/scripts/video_processing_worker.php

# 或者使用systemd服务（推荐）
```

### 8. 创建systemd服务（推荐）

创建文件 `/etc/systemd/system/video-processing.service`:

```ini
[Unit]
Description=Video Processing Worker
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/path/to/your/project
ExecStart=/usr/bin/php scripts/video_processing_worker.php
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

启动服务：
```bash
sudo systemctl daemon-reload
sudo systemctl enable video-processing
sudo systemctl start video-processing
```

## 配置说明

### 系统配置参数

| 配置项 | 默认值 | 说明 |
|--------|--------|------|
| video_analysis_cost_coins | 50 | 视频分析消耗精灵币数量 |
| max_video_duration | 3600 | 最大视频时长(秒) |
| video_segment_duration | 120 | 视频切片时长(秒) |
| video_resolution | 720p | 视频转码分辨率 |
| video_bitrate | 1500k | 视频转码码率 |
| audio_bitrate | 64k | 音频转码码率 |
| max_concurrent_processing | 3 | 最大并发处理数量 |
| video_retention_days | 30 | 视频文件保留天数 |

### 性能优化建议

1. **服务器配置**
   - CPU: 4核以上
   - 内存: 8GB以上
   - 磁盘: SSD，50GB以上可用空间

2. **FFmpeg优化**
   ```bash
   # 检查FFmpeg版本和编解码器
   ffmpeg -version
   ffmpeg -encoders | grep x264
   ```

3. **Whisper优化**
   - 使用GPU加速（如果可用）
   - 选择合适的模型大小
   - 调整批处理大小

4. **数据库优化**
   - 为视频分析相关表添加索引
   - 定期清理过期数据
   - 使用连接池

## 测试验证

### 1. 功能测试

1. 访问前台创建视频分析订单
2. 在后台审核订单
3. 检查处理队列状态
4. 验证报告生成

### 2. 性能测试

```bash
# 测试FFmpeg
ffmpeg -i input.mp4 -t 10 -c:v libx264 -preset fast output.mp4

# 测试Whisper
whisper test_audio.wav --model base

# 测试API连接
curl -X POST "https://dashscope.aliyuncs.com/api/v1/services/aigc/video-understanding/generation" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json"
```

### 3. 监控检查

1. 检查日志文件
2. 监控磁盘空间
3. 检查队列处理状态
4. 验证报告质量

## 故障排除

### 常见问题

1. **FFmpeg不可用**
   - 检查FFmpeg是否正确安装
   - 验证PATH环境变量
   - 检查exec()函数是否被禁用

2. **Whisper识别失败**
   - 检查Python环境
   - 验证模型文件是否存在
   - 检查音频文件格式

3. **OSS上传失败**
   - 验证AccessKey和SecretKey
   - 检查存储桶权限
   - 确认网络连接

4. **队列处理卡住**
   - 检查工作进程状态
   - 查看错误日志
   - 重启处理服务

### 日志位置

- 系统错误日志: `logs/error.log`
- 视频处理日志: 查看PHP错误日志
- 队列处理日志: 查看systemd日志

```bash
# 查看systemd服务日志
sudo journalctl -u video-processing -f

# 查看PHP错误日志
tail -f /var/log/php/error.log
```

## 维护建议

### 定期维护

1. **清理过期文件**
   - 定期删除过期的视频文件
   - 清理临时文件

2. **数据库维护**
   - 定期备份数据库
   - 清理过期的分析结果

3. **性能监控**
   - 监控磁盘使用率
   - 检查处理队列积压
   - 优化处理参数

### 安全建议

1. 定期更新依赖库
2. 限制文件上传大小
3. 设置适当的文件权限
4. 监控异常访问

## 技术支持

如遇到问题，请：

1. 查看错误日志
2. 检查系统配置
3. 验证依赖环境
4. 联系技术支持

---

**注意**: 视频分析功能需要较高的服务器配置和稳定的网络环境，建议在生产环境部署前进行充分测试。
