# 简化录制流程指南

## 🎯 新的录制流程设计

### 问题分析
原来的录制流程存在以下问题：
1. **复杂度过高** - 录制、转码、切片、AI分析等步骤混在一起
2. **卡死问题** - 一个步骤失败导致整个流程卡死
3. **难以调试** - 问题定位困难
4. **资源浪费** - 不必要的并发处理

### 新的简化流程

```
用户输入FLV地址
    ↓
立即开始录制视频
    ↓
录制完成后保存文件
    ↓
更新订单状态为"已完成"
    ↓
后续处理（转码、切片、AI分析等）
```

## 🔧 核心组件

### 1. FastRecorder.php - 快速录制器
- **职责**：专注于录制视频
- **特点**：简单、快速、可靠
- **功能**：
  - 验证FLV地址
  - 创建录制目录
  - 执行FFmpeg录制
  - 保存录制结果
  - 清理临时文件

### 2. SimpleRecordingFlow.php - 简化录制流程
- **职责**：管理录制流程
- **特点**：流程清晰、易于维护
- **功能**：
  - 检查订单状态
  - 创建录制任务
  - 执行录制
  - 更新数据库

### 3. 修改后的VideoAnalysisOrder.php
- **职责**：简化的分析启动
- **特点**：只处理录制，不处理复杂逻辑
- **功能**：
  - 检查订单状态
  - 调用快速录制器
  - 返回录制结果

## 🚀 使用方法

### 1. 基本录制
```php
require_once 'FastRecorder.php';

$recorder = new FastRecorder();
$result = $recorder->recordVideo($orderId, $flvUrl, $maxDuration);

if ($result['success']) {
    echo "录制成功！";
    echo "文件路径: " . $result['file_path'];
    echo "文件大小: " . $result['file_size'];
    echo "视频时长: " . $result['duration'];
} else {
    echo "录制失败: " . $result['error'];
}
```

### 2. 通过订单启动录制
```php
require_once 'includes/classes/VideoAnalysisOrder.php';

$videoOrder = new VideoAnalysisOrder();
$result = $videoOrder->startAnalysis($orderId);

if ($result['success']) {
    echo "录制已启动！";
} else {
    echo "启动失败: " . $result['message'];
}
```

### 3. 测试录制流程
```bash
# 在服务器上运行
php test_simple_recording.php
```

## 📋 录制流程步骤

### 步骤1：验证输入
- 检查订单是否存在
- 验证FLV地址格式
- 检查地址是否过期

### 步骤2：准备录制
- 创建录制目录
- 设置文件权限
- 构建FFmpeg命令

### 步骤3：执行录制
- 运行FFmpeg命令
- 监控录制过程
- 处理录制错误

### 步骤4：保存结果
- 检查录制文件
- 获取视频信息
- 更新数据库状态

### 步骤5：清理资源
- 清理临时文件
- 释放系统资源

## ⚙️ 配置参数

### 录制参数
- `maxDuration`: 最大录制时长（秒）
- `recordingDir`: 录制目录路径
- `ffmpegPath`: FFmpeg可执行文件路径

### 数据库字段
- `video_analysis_orders.status`: 订单状态
- `video_files.file_path`: 视频文件路径
- `video_files.file_size`: 文件大小
- `video_files.duration`: 视频时长

## 🔍 故障排除

### 常见问题
1. **FFmpeg命令失败**
   - 检查FFmpeg是否安装
   - 验证FLV地址是否有效
   - 检查磁盘空间

2. **文件权限问题**
   - 检查录制目录权限
   - 确保PHP有写入权限

3. **录制文件损坏**
   - 检查网络连接
   - 验证FLV地址有效性
   - 调整录制参数

### 调试方法
1. **查看日志**
   ```bash
   tail -f /var/log/php_errors.log
   ```

2. **检查录制文件**
   ```bash
   ls -la /tmp/fast_recording_*
   ```

3. **测试FFmpeg**
   ```bash
   ffmpeg -i "FLV地址" -t 10 test.mp4
   ```

## 🎉 优势

### 1. 简单可靠
- 专注于录制功能
- 减少复杂逻辑
- 提高成功率

### 2. 易于维护
- 代码结构清晰
- 问题定位容易
- 修改影响范围小

### 3. 性能优化
- 减少资源占用
- 提高处理速度
- 降低系统负载

### 4. 扩展性好
- 模块化设计
- 易于添加新功能
- 支持后续处理

## 📝 后续处理

录制完成后，可以添加以下处理步骤：

1. **转码处理** - 将视频转换为标准格式
2. **切片处理** - 将长视频切分为小段
3. **语音识别** - 提取音频并转换为文字
4. **AI分析** - 使用AI模型分析视频内容
5. **报告生成** - 生成分析报告

每个步骤都可以独立处理，不会影响录制功能。
