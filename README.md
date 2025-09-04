# 复盘精灵 - 视频号直播复盘分析系统

## 项目简介

复盘精灵是一个专业的视频号直播复盘分析SaaS平台，基于PHP 7.4开发，集成DeepSeek AI大模型，为直播从业者提供智能化的话术分析和优化建议。

## 核心功能

### 前台用户系统
- 🔐 手机号注册/登录（短信验证）
- 📊 上传直播数据（5张截图、封面图、本方话术、3个同行话术）
- 💰 精灵币消费系统
- 📱 AI分析完成短信通知
- 🔗 报告分享（复制链接、导出图片、导出PDF）

### 兑换码系统
- 🎫 后台生成不同面值兑换码
- 💳 前台兑换码充值
- 📈 兑换码使用统计

### 后台管理系统
- 👥 用户管理
- 📋 订单管理
- 🎫 兑换码生成和管理
- ⚙️ 系统配置（DeepSeek API密钥等）
- 📊 数据统计和分析

### AI报告生成
- 🤖 DeepSeek API集成
- 📊 综合评分（0-100分）
- 🏆 等级划分（优秀/良好/一般/较差/不合格）
- 📝 深度分析报告（本方分析、同行对比、改进方案）

## 技术栈

- **后端：** PHP 7.4 + MySQL
- **前端：** Bootstrap 5 + jQuery + Chart.js
- **AI服务：** DeepSeek API
- **短信服务：** 阿里云SMS
- **文件导出：** html2canvas + jsPDF

## 目录结构

```
/workspace/
├── config/                 # 配置文件
│   ├── config.php          # 系统配置
│   └── database.php        # 数据库配置
├── includes/               # 核心类库
│   └── classes/            # 业务类
│       ├── User.php        # 用户类
│       ├── SmsService.php  # 短信服务
│       ├── AnalysisOrder.php # 分析订单类
│       ├── ExchangeCode.php  # 兑换码类
│       ├── DeepSeekService.php # AI服务类
│       └── OperationLog.php  # 操作日志类
├── api/                    # API接口
│   ├── register.php        # 用户注册
│   ├── login.php           # 用户登录
│   ├── send_sms.php        # 发送短信
│   ├── upload.php          # 文件上传
│   ├── create_analysis.php # 创建分析
│   ├── user_orders.php     # 用户订单
│   ├── use_exchange_code.php # 使用兑换码
│   └── coin_transactions.php # 交易记录
├── admin/                  # 后台管理
│   ├── index.php           # 后台首页
│   ├── login.php           # 管理员登录
│   ├── exchange_codes.php  # 兑换码管理
│   └── api/                # 后台API
├── assets/                 # 静态资源
│   ├── css/style.css       # 样式文件
│   ├── js/app.js           # 前端脚本
│   ├── images/             # 图片资源
│   └── uploads/            # 上传文件
├── scripts/                # 脚本文件
│   └── process_analysis.php # AI分析处理脚本
├── database/               # 数据库文件
│   └── schema.sql          # 数据库结构
├── index.php               # 前台首页
├── register.php            # 用户注册页
├── login.php               # 用户登录页
├── create_analysis.php     # 创建分析页
├── my_orders.php           # 我的订单页
├── recharge.php            # 充值中心页
├── report.php              # 分析报告页
└── logout.php              # 退出登录
```

## 数据库设计

### 核心表结构

1. **users** - 用户表
2. **sms_codes** - 短信验证码表
3. **analysis_orders** - 分析订单表
4. **exchange_codes** - 兑换码表
5. **coin_transactions** - 精灵币交易记录表
6. **admins** - 管理员表
7. **system_configs** - 系统配置表
8. **operation_logs** - 操作日志表
9. **file_uploads** - 文件上传记录表

## 安装部署

### 1. 环境要求

- PHP 7.4+
- MySQL 5.7+
- Apache/Nginx
- cURL扩展
- PDO扩展

### 2. 安装步骤

1. **克隆项目**
```bash
git clone [项目地址]
cd fupan-jingling
```

2. **导入数据库**
```bash
mysql -u root -p < database/schema.sql
```

3. **配置环境变量**
```bash
# 创建环境变量文件
cp .env.example .env

# 编辑配置
DB_HOST=localhost
DB_NAME=fupan_jingling
DB_USER=root
DB_PASS=your_password
```

4. **设置目录权限**
```bash
chmod 755 assets/uploads
chmod 755 logs
chmod +x scripts/process_analysis.php
```

5. **配置Web服务器**
   - 将项目根目录设置为网站根目录
   - 确保支持URL重写
   - 设置上传文件大小限制

### 3. 系统配置

登录后台管理系统（`/admin/`）：
- 默认用户名：`admin`
- 默认密码：`admin123`

在系统配置中设置：
- DeepSeek API密钥
- 阿里云短信配置
- 其他系统参数

## 功能特色

### 🎯 智能分析
- 基于DeepSeek大模型的深度分析
- 多维度评分体系
- 专业的改进建议

### 📊 可视化报告
- 清晰的评分展示
- 详细的分析内容
- 支持多种导出格式

### 🔄 完整闭环
- 从数据上传到报告生成的完整流程
- 实时状态更新
- 短信通知提醒

### 🛡️ 安全可靠
- 完善的权限控制
- 操作日志记录
- 数据安全保护

## API接口

### 用户相关
- `POST /api/register.php` - 用户注册
- `POST /api/login.php` - 用户登录
- `POST /api/send_sms.php` - 发送短信验证码

### 订单相关
- `POST /api/create_analysis.php` - 创建分析订单
- `GET /api/user_orders.php` - 获取用户订单
- `GET /api/order_status.php` - 查询订单状态

### 兑换码相关
- `POST /api/use_exchange_code.php` - 使用兑换码
- `GET /api/coin_transactions.php` - 交易记录

### 文件相关
- `POST /api/upload.php` - 文件上传

## 开发指南

### 添加新功能
1. 在 `includes/classes/` 中创建业务类
2. 在 `api/` 中创建API接口
3. 创建对应的前端页面
4. 更新数据库结构（如需要）

### 代码规范
- 使用PSR-4自动加载
- 遵循PSR-12编码规范
- 添加详细的注释
- 使用异常处理机制

### 安全注意事项
- 所有用户输入都要验证和过滤
- 使用参数化查询防止SQL注入
- 敏感配置信息加密存储
- 定期更新依赖库

## 许可证

本项目采用 MIT 许可证。详情请查看 [LICENSE](LICENSE) 文件。

## 技术支持

如有问题，请联系：
- 邮箱：support@fupanjingling.com
- 技术文档：[链接地址]

---

**复盘精灵** - 让直播复盘更智能，让话术优化更专业！