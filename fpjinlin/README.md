# 复盘精灵 - 视频号直播复盘分析SaaS平台

## 项目简介

复盘精灵是一个基于AI的视频号直播复盘分析平台，帮助主播通过智能分析提升直播效果。

### 核心功能

- **用户系统**: 手机短信注册/登录，精灵币充值消费
- **订单管理**: 上传直播数据，创建分析订单
- **AI分析**: 集成DeepSeek API，生成专业分析报告
- **报告导出**: 支持PDF、图片导出和链接分享
- **后台管理**: 用户管理、订单管理、兑换码管理、系统配置

## 技术栈

- **后端**: PHP 7.4 + MySQL
- **前端**: Bootstrap 5 + Chart.js
- **AI服务**: DeepSeek API
- **短信服务**: 阿里云SMS
- **文件处理**: html2canvas + jsPDF

## 安装部署

### 1. 环境要求

- PHP 7.4+
- MySQL 5.7+ / MariaDB 10.3+
- Apache/Nginx Web服务器
- cURL扩展
- PDO扩展
- GD扩展（图片处理）

### 2. 数据库初始化

```bash
# 导入数据库结构
mysql -u root -p < sql/database_design.sql
```

### 3. 配置文件

编辑 `config/database.php` 配置数据库连接：

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'fpjinlin');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
```

编辑 `config/config.php` 配置网站信息：

```php
define('SITE_URL', 'http://your-domain.com');
```

### 4. 目录权限

```bash
# 设置上传目录权限
chmod -R 755 assets/uploads/
chown -R www-data:www-data assets/uploads/
```

### 5. 系统配置

登录后台管理系统（默认账号: admin / admin123），配置：

- DeepSeek API密钥
- 阿里云SMS配置
- 其他系统参数

### 6. 定时任务（可选）

添加crontab定时处理AI分析：

```bash
# 每5分钟处理一次待分析订单
*/5 * * * * /usr/bin/php /path/to/fpjinlin/scripts/process_pending_orders.php
```

## 目录结构

```
fpjinlin/
├── admin/                  # 后台管理页面
│   ├── index.php          # 管理后台首页
│   ├── login.php          # 管理员登录
│   ├── users.php          # 用户管理
│   ├── orders.php         # 订单管理
│   ├── exchange_codes.php # 兑换码管理
│   └── settings.php       # 系统设置
├── api/                   # API接口
│   ├── send_sms.php       # 发送短信验证码
│   └── process_analysis.php # AI分析处理
├── assets/                # 静态资源
│   ├── css/              # 样式文件
│   ├── js/               # JavaScript文件
│   ├── images/           # 图片资源
│   └── uploads/          # 上传文件目录
│       ├── covers/       # 封面图片
│       ├── screenshots/  # 截图文件
│       └── reports/      # 生成的报告
├── config/               # 配置文件
│   ├── config.php        # 主配置文件
│   └── database.php      # 数据库配置
├── includes/             # 核心类文件
│   ├── User.php          # 用户管理类
│   ├── AnalysisOrder.php # 订单管理类
│   ├── ExchangeCode.php  # 兑换码管理类
│   ├── Admin.php         # 管理员类
│   └── AIAnalyzer.php    # AI分析器类
├── pages/                # 页面文件
│   ├── user/             # 用户页面
│   │   ├── index.php     # 用户首页
│   │   ├── login.php     # 登录页面
│   │   ├── register.php  # 注册页面
│   │   ├── create_order.php # 创建订单
│   │   ├── upload.php    # 文件上传
│   │   ├── report.php    # 报告展示
│   │   └── recharge.php  # 充值页面
│   └── admin/            # 管理员页面
├── scripts/              # 脚本文件
│   └── process_pending_orders.php # 定时处理脚本
├── sql/                  # SQL文件
│   └── database_design.sql # 数据库结构
├── index.php             # 入口文件
└── README.md             # 项目说明
```

## 数据库表结构

### 核心表

1. **users** - 用户表
2. **analysis_orders** - 分析订单表
3. **order_screenshots** - 订单截图表
4. **exchange_codes** - 兑换码表
5. **admins** - 管理员表
6. **system_configs** - 系统配置表
7. **coin_transactions** - 精灵币交易记录表
8. **sms_codes** - 短信验证码表
9. **admin_logs** - 管理员操作日志表

## API接口

### 用户接口

- `POST /api/send_sms.php` - 发送短信验证码
- `POST /api/process_analysis.php` - 处理AI分析

### 管理接口

- 后台管理页面提供完整的管理功能

## 使用流程

### 用户使用流程

1. **注册/登录**: 手机号 + 短信验证码
2. **充值精灵币**: 使用兑换码充值
3. **创建订单**: 填写标题、本方话术、同行话术
4. **上传文件**: 上传5张数据截图和封面图片
5. **提交分析**: 消耗精灵币，开始AI分析
6. **查看报告**: 分析完成后查看详细报告
7. **导出分享**: 导出PDF/图片，分享报告链接

### 管理员使用流程

1. **登录后台**: 管理员账号登录
2. **生成兑换码**: 创建不同面值的兑换码
3. **用户管理**: 查看用户信息，调整余额
4. **订单监控**: 监控分析订单状态
5. **系统配置**: 配置API密钥等参数

## 配置说明

### DeepSeek API配置

在后台管理 -> 系统设置中配置：

- `deepseek_api_key`: DeepSeek API密钥
- `deepseek_api_url`: API地址（默认官方地址）

### 阿里云SMS配置

- `sms_access_key`: AccessKey ID
- `sms_secret_key`: AccessKey Secret
- `sms_sign_name`: 短信签名
- `sms_template_*`: 短信模板代码

### 其他配置

- `analysis_cost_coins`: 单次分析消耗精灵币（默认100）
- `max_upload_size`: 最大上传文件大小（MB）
- `report_retention_days`: 报告保留天数

## 安全注意事项

1. **密码安全**: 使用PHP `password_hash()` 加密存储
2. **SQL注入防护**: 使用PDO预处理语句
3. **XSS防护**: 输出时使用 `htmlspecialchars()` 转义
4. **文件上传安全**: 严格验证文件类型和大小
5. **API密钥保护**: 敏感配置存储在数据库中
6. **会话安全**: 设置安全的会话参数

## 开发说明

### 默认账号

- **管理员**: admin / admin123
- **测试用户**: 需要通过注册流程创建

### 开发环境

- 短信验证码会记录到错误日志中
- API调用失败时会有详细错误信息
- 可以通过日志文件调试问题

### 扩展功能

1. **队列系统**: 可集成Redis队列处理AI分析
2. **缓存系统**: 可添加Redis缓存提升性能
3. **CDN集成**: 可配置OSS存储和CDN加速
4. **微信登录**: 可扩展微信授权登录
5. **支付集成**: 可集成微信支付/支付宝

## 常见问题

### Q: AI分析失败怎么办？
A: 检查DeepSeek API配置，查看错误日志，可在后台重新触发分析

### Q: 短信发送失败？
A: 检查阿里云SMS配置，确认模板和签名已审核通过

### Q: 文件上传失败？
A: 检查upload目录权限，确认文件大小和格式符合要求

### Q: 如何备份数据？
A: 定期备份MySQL数据库和uploads目录

## 技术支持

如需技术支持，请联系开发团队。

---

**版本**: v1.0.0  
**更新时间**: 2024年  
**开发语言**: PHP 7.4  
**数据库**: MySQL 5.7+