# 视频分析工作流程重新设计

## 🎯 业务需求分析

### 输入数据
- 1个本方直播间FLV地址
- 2个同行直播间FLV地址
- 录制时长：60秒（可配置）
- 切片时长：20秒（可配置）

### 输出结果
- 本方直播间分析报告
- 2个同行直播间分析报告
- 话术对比分析
- 学习建议

## 🔄 完整工作流程设计

### 阶段1：订单创建与验证
```
用户提交 → 创建订单 → 验证FLV地址 → 状态：pending
```

### 阶段2：视频录制
```
启动分析 → 状态：recording → 录制3个视频
├── 本方直播间录制（60秒）
├── 同行直播间1录制（60秒）
└── 同行直播间2录制（60秒）
录制完成 → 状态：recording_completed
```

### 阶段3：视频转码与切片
```
开始转码 → 状态：transcoding
├── 转码为适合AI分析的格式
├── 每20秒切片（本方：3片，同行1：3片，同行2：3片）
└── 上传到OSS
转码完成 → 状态：transcoding_completed
```

### 阶段4：AI视频分析
```
开始AI分析 → 状态：ai_analyzing
├── 调用阿里云大模型分析本方视频（3片）
├── 调用阿里云大模型分析同行1视频（3片）
└── 调用阿里云大模型分析同行2视频（3片）
AI分析完成 → 状态：ai_analysis_completed
```

### 阶段5：语音提取
```
开始语音提取 → 状态：speech_extracting
├── Whisper提取本方话术
├── Whisper提取同行1话术
└── Whisper提取同行2话术
语音提取完成 → 状态：speech_extraction_completed
```

### 阶段6：话术分析
```
开始话术分析 → 状态：script_analyzing
├── DeepSeek分析本方话术
├── DeepSeek分析同行1话术
├── DeepSeek分析同行2话术
└── 生成对比分析报告
话术分析完成 → 状态：script_analysis_completed
```

### 阶段7：报告生成
```
生成最终报告 → 状态：report_generating
├── 整合视频分析结果
├── 整合话术分析结果
├── 生成学习建议
└── 输出完整报告
报告生成完成 → 状态：completed
```

## 📊 状态管理设计

### 订单状态枚举
```php
enum OrderStatus {
    PENDING = 'pending',                    // 待处理
    RECORDING = 'recording',                // 录制中
    RECORDING_COMPLETED = 'recording_completed',  // 录制完成
    TRANSCODING = 'transcoding',            // 转码中
    TRANSCODING_COMPLETED = 'transcoding_completed', // 转码完成
    AI_ANALYZING = 'ai_analyzing',          // AI分析中
    AI_ANALYSIS_COMPLETED = 'ai_analysis_completed', // AI分析完成
    SPEECH_EXTRACTING = 'speech_extracting', // 语音提取中
    SPEECH_EXTRACTION_COMPLETED = 'speech_extraction_completed', // 语音提取完成
    SCRIPT_ANALYZING = 'script_analyzing',  // 话术分析中
    SCRIPT_ANALYSIS_COMPLETED = 'script_analysis_completed', // 话术分析完成
    REPORT_GENERATING = 'report_generating', // 报告生成中
    COMPLETED = 'completed',                // 完成
    FAILED = 'failed'                       // 失败
}
```

### 视频文件状态枚举
```php
enum VideoFileStatus {
    PENDING = 'pending',                    // 待处理
    RECORDING = 'recording',                // 录制中
    RECORDING_COMPLETED = 'recording_completed', // 录制完成
    TRANSCODING = 'transcoding',            // 转码中
    TRANSCODING_COMPLETED = 'transcoding_completed', // 转码完成
    AI_ANALYZING = 'ai_analyzing',          // AI分析中
    AI_ANALYSIS_COMPLETED = 'ai_analysis_completed', // AI分析完成
    SPEECH_EXTRACTING = 'speech_extracting', // 语音提取中
    SPEECH_EXTRACTION_COMPLETED = 'speech_extraction_completed', // 语音提取完成
    COMPLETED = 'completed',                // 完成
    FAILED = 'failed'                       // 失败
}
```

## 🗄️ 数据库表结构优化

### video_analysis_orders 表新增字段
```sql
ALTER TABLE `video_analysis_orders` 
ADD COLUMN `current_stage` varchar(50) DEFAULT 'pending' COMMENT '当前处理阶段',
ADD COLUMN `stage_progress` tinyint(3) DEFAULT '0' COMMENT '阶段进度(0-100)',
ADD COLUMN `stage_message` varchar(255) DEFAULT NULL COMMENT '阶段描述信息',
ADD COLUMN `recording_started_at` timestamp NULL DEFAULT NULL COMMENT '录制开始时间',
ADD COLUMN `recording_completed_at` timestamp NULL DEFAULT NULL COMMENT '录制完成时间',
ADD COLUMN `transcoding_started_at` timestamp NULL DEFAULT NULL COMMENT '转码开始时间',
ADD COLUMN `transcoding_completed_at` timestamp NULL DEFAULT NULL COMMENT '转码完成时间',
ADD COLUMN `ai_analysis_started_at` timestamp NULL DEFAULT NULL COMMENT 'AI分析开始时间',
ADD COLUMN `ai_analysis_completed_at` timestamp NULL DEFAULT NULL COMMENT 'AI分析完成时间',
ADD COLUMN `speech_extraction_started_at` timestamp NULL DEFAULT NULL COMMENT '语音提取开始时间',
ADD COLUMN `speech_extraction_completed_at` timestamp NULL DEFAULT NULL COMMENT '语音提取完成时间',
ADD COLUMN `script_analysis_started_at` timestamp NULL DEFAULT NULL COMMENT '话术分析开始时间',
ADD COLUMN `script_analysis_completed_at` timestamp NULL DEFAULT NULL COMMENT '话术分析完成时间',
ADD COLUMN `report_generation_started_at` timestamp NULL DEFAULT NULL COMMENT '报告生成开始时间',
ADD COLUMN `report_generation_completed_at` timestamp NULL DEFAULT NULL COMMENT '报告生成完成时间';
```

### video_files 表新增字段
```sql
ALTER TABLE `video_files` 
ADD COLUMN `video_analysis_result` json DEFAULT NULL COMMENT '视频分析结果',
ADD COLUMN `speech_transcript` text DEFAULT NULL COMMENT '语音转录文本',
ADD COLUMN `speech_analysis_result` json DEFAULT NULL COMMENT '语音分析结果',
ADD COLUMN `processing_stage` varchar(50) DEFAULT 'pending' COMMENT '当前处理阶段',
ADD COLUMN `stage_progress` tinyint(3) DEFAULT '0' COMMENT '阶段进度(0-100)';
```

## 🔧 核心处理类设计

### VideoAnalysisWorkflow 主工作流类
```php
class VideoAnalysisWorkflow {
    public function startAnalysis($orderId);
    public function processRecording($orderId);
    public function processTranscoding($orderId);
    public function processAIAnalysis($orderId);
    public function processSpeechExtraction($orderId);
    public function processScriptAnalysis($orderId);
    public function generateReport($orderId);
    public function updateOrderStatus($orderId, $status, $stage, $progress, $message);
}
```

### VideoRecorder 录制类
```php
class VideoRecorder {
    public function recordVideo($flvUrl, $duration, $outputPath);
    public function getRecordingProgress($videoFileId);
    public function validateRecording($videoFileId);
}
```

### VideoTranscoder 转码类
```php
class VideoTranscoder {
    public function transcodeVideo($inputPath, $outputPath, $resolution, $bitrate);
    public function segmentVideo($videoPath, $segmentDuration);
    public function uploadToOSS($filePath, $ossKey);
}
```

### AIAnalysisService AI分析服务
```php
class AIAnalysisService {
    public function analyzeVideoWithQwenOmni($videoPath);
    public function analyzeScriptWithDeepSeek($scriptText);
    public function generateComparisonReport($selfAnalysis, $competitorAnalyses);
}
```

### SpeechExtractionService 语音提取服务
```php
class SpeechExtractionService {
    public function extractSpeechWithWhisper($videoPath);
    public function processTranscript($transcript);
    public function analyzeSpeechPattern($transcript);
}
```

## 📈 进度跟踪设计

### 后台进度显示
- 当前阶段：录制中/转码中/AI分析中/语音提取中/话术分析中/报告生成中
- 阶段进度：0-100%
- 详细描述：正在录制本方直播间/正在分析同行1视频等
- 预计完成时间：基于历史数据估算

### 实时状态更新
- WebSocket推送进度更新
- 定时刷新页面状态
- 错误状态及时通知

## 🎯 报告内容设计

### 最终报告结构
```json
{
    "self_analysis": {
        "video_analysis": "本方视频分析结果",
        "speech_analysis": "本方话术分析结果",
        "strengths": "优势点",
        "weaknesses": "待改进点"
    },
    "competitor_analyses": [
        {
            "competitor_name": "同行1",
            "video_analysis": "同行1视频分析结果",
            "speech_analysis": "同行1话术分析结果",
            "learnable_points": "可学习点"
        },
        {
            "competitor_name": "同行2", 
            "video_analysis": "同行2视频分析结果",
            "speech_analysis": "同行2话术分析结果",
            "learnable_points": "可学习点"
        }
    ],
    "comparison_analysis": {
        "overall_comparison": "整体对比分析",
        "key_differences": "关键差异点",
        "learning_suggestions": "学习建议",
        "improvement_plan": "改进计划"
    },
    "summary": {
        "total_score": 85,
        "level": "good",
        "key_insights": "核心洞察",
        "action_items": "行动建议"
    }
}
```

## ⚡ 性能优化

### 并发处理
- 3个视频同时录制
- 3个视频同时转码
- 3个视频同时进行AI分析
- 3个话术同时提取

### 错误处理
- 每个阶段都有重试机制
- 失败时回滚到上一个稳定状态
- 详细的错误日志记录

### 资源管理
- 临时文件自动清理
- OSS存储优化
- 数据库连接池管理
