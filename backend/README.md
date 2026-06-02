# 🚀 PHP 后端快速部署指南

**项目名称：** SMS 接码平台 - PHP 版本  
**版本：** v1.0  
**最后更新：** 2026-03-19

---

## 📋 项目结构

```
backend/
├── config/
│   └── database.php          # 数据库配置
├── lib/
│   ├── Database.php          # 数据库类
│   ├── HeroSMS.php           # HeroSMS 服务
│   └── AppleIAP.php          # Apple IAP 验证
├── index.php                 # 主入口文件
├── .env                      # 环境变量配置
├── .htaccess                 # Apache 配置
├── database.sql              # 数据库初始化脚本
└── README.md                 # 本文件
```

---

## 🔧 部署环境要求

### 最小配置
- PHP 7.4+
- MySQL 5.7+
- Apache 或 Nginx
- cURL 扩展
- PDO MySQL 扩展

### 推荐配置
- PHP 8.0+
- MySQL 8.0+
- Nginx + PHP-FPM
- Redis (可选，用于缓存)

---

## 🚀 快速部署（5分钟）

### 方案 A：使用宝塔面板（推荐）

**第 1 步：安装宝塔面板**
```bash
wget -O install.sh http://download.bt.cn/install/install_6.0.sh
sudo bash install.sh
```

**第 2 步：登录宝塔面板**
```
http://your-server-ip:8888
```

**第 3 步：一键安装 LNMP**
- 在宝塔面板中选择：Nginx + MySQL 8.0 + PHP 8.0

**第 4 步：创建网站**
1. 点击"网站" → "添加网站"
2. 输入域名
3. 选择 PHP 8.0
4. 创建数据库

**第 5 步：上传代码**
```bash
# 通过 FTP 或 Git 上传代码到网站根目录
git clone <repo-url> /www/wwwroot/your-domain.com
```

**第 6 步：配置数据库**
```bash
# 导入数据库初始化脚本
mysql -u root -p sms_receiver < database.sql
```

**第 7 步：配置环境变量**
```bash
# 编辑 .env 文件，填入数据库信息
nano .env
```

**第 8 步：配置伪静态**
在宝塔面板中：
1. 选择网站 → 设置
2. 进入"伪静态"标签
3. 选择"Apache" 或 "Nginx"
4. 保存

**第 9 步：配置 SSL**
在宝塔面板中：
1. 选择网站 → 设置
2. 进入"SSL"标签
3. 申请免费 SSL 证书
4. 启用 HTTPS

**完成！** 访问 `https://your-domain.com/api/health` 测试

---

### 方案 B：手动部署（Ubuntu/Debian）

**第 1 步：安装 PHP 和 MySQL**
```bash
# 更新系统
sudo apt-get update
sudo apt-get upgrade -y

# 安装 PHP 8.0
sudo apt-get install -y php8.0 php8.0-fpm php8.0-mysql php8.0-curl

# 安装 MySQL
sudo apt-get install -y mysql-server

# 安装 Nginx
sudo apt-get install -y nginx
```

**第 2 步：创建数据库**
```bash
# 登录 MySQL
mysql -u root -p

# 执行初始化脚本
source database.sql;
exit;
```

**第 3 步：配置 Nginx**
```bash
# 创建 Nginx 配置文件
sudo nano /etc/nginx/sites-available/sms-api

# 添加以下内容：
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/sms-api;
    index index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.0-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
}

# 启用配置
sudo ln -s /etc/nginx/sites-available/sms-api /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

**第 4 步：上传代码**
```bash
# 克隆代码到 /var/www/sms-api
sudo git clone <repo-url> /var/www/sms-api
sudo chown -R www-data:www-data /var/www/sms-api
```

**第 5 步：配置环境变量**
```bash
cd /var/www/sms-api
nano .env
```

**第 6 步：配置 SSL（使用 Let's Encrypt）**
```bash
# 安装 Certbot
sudo apt-get install -y certbot python3-certbot-nginx

# 申请证书
sudo certbot certonly --nginx -d your-domain.com

# 更新 Nginx 配置使用 HTTPS
# 参考宝塔面板的 SSL 配置
```

**完成！** 访问 `https://your-domain.com/api/health` 测试

---

## 📝 配置说明

### .env 文件配置

```env
# 数据库配置
DB_HOST=localhost          # MySQL 主机
DB_PORT=3306              # MySQL 端口
DB_NAME=sms_receiver      # 数据库名
DB_USER=root              # 数据库用户
DB_PASS=                  # 数据库密码

# API 配置
API_KEY=your_random_api_key_here  # API 密钥（客户端鉴权）
# 注意：HEROSMS_API_KEY 不再在 .env 中配置，必须通过运营后台数据库 system_settings 表设置
# 登录运营后台 -> 系统设置 -> 添加 key=hero_sms_api_key 的记录

# 应用配置
APP_NAME=SMS 接码平台
APP_URL=https://your-domain.com
APP_ENV=production        # 生产环境
```

---

## 🧪 测试 API

### 健康检查
```bash
curl -X GET https://newsms.weburl.cloudns.be/api/health \
  -H "X-API-Key: YOUR_API_KEY_HERE"
```

### 获取服务列表
```bash
curl -X GET https://newsms.weburl.cloudns.be/api/services \
  -H "X-API-Key: YOUR_API_KEY_HERE"
```

### 获取国家列表
```bash
curl -X GET "https://newsms.weburl.cloudns.be/api/countries?service_id=1" \
  -H "X-API-Key: YOUR_API_KEY_HERE"
```

### 获取 HeroSMS 余额
```bash
curl -X GET https://newsms.weburl.cloudns.be/api/herosms/balance \
  -H "X-API-Key: YOUR_API_KEY_HERE"
```

---

## 🔐 安全建议

### 1. 更改 API Key
```bash
# 生成新的 API Key
php -r "echo bin2hex(random_bytes(16));"

# 更新 .env 文件
API_KEY=your-new-api-key
```

### 2. 配置防火墙
```bash
# 只允许特定 IP 访问
sudo ufw allow from 192.168.1.0/24 to any port 80
sudo ufw allow from 192.168.1.0/24 to any port 443
```

### 3. 启用 HTTPS
```bash
# 在 .env 中设置
APP_URL=https://your-domain.com
```

### 4. 定期备份
```bash
# 备份数据库
mysqldump -u root -p sms_receiver > backup.sql

# 备份代码
tar -czf backup.tar.gz /var/www/sms-api
```

---

## 📊 性能优化

### 1. 启用 PHP 缓存
```bash
# 安装 OPcache
sudo apt-get install -y php8.0-opcache

# 编辑 php.ini
sudo nano /etc/php/8.0/fpm/php.ini

# 添加以下配置
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=4000
```

### 2. 启用 MySQL 查询缓存
```sql
-- 编辑 /etc/mysql/mysql.conf.d/mysqld.cnf
query_cache_type=1
query_cache_size=16M
```

### 3. 启用 Gzip 压缩
```nginx
# 在 Nginx 配置中添加
gzip on;
gzip_types text/plain text/css application/json application/javascript;
gzip_min_length 1000;
```

---

## 🆘 故障排查

### 问题 1：数据库连接失败
```bash
# 检查 MySQL 是否运行
sudo systemctl status mysql

# 检查数据库凭证
mysql -u root -p -h localhost

# 检查 .env 文件配置
cat .env
```

### 问题 2：权限错误
```bash
# 修复文件权限
sudo chown -R www-data:www-data /var/www/sms-api
sudo chmod -R 755 /var/www/sms-api
```

### 问题 3：API 返回 401
```bash
# 检查 API Key
grep "API_KEY" .env

# 确保请求头中包含正确的 X-API-Key
curl -H "X-API-Key: your-api-key" http://localhost:8000/api/health
```

### 问题 4：HeroSMS API 调用失败
```bash
# 测试 HeroSMS API
curl "https://hero-sms.com/stubs/handler_api.php?api_key=YOUR_KEY&action=getBalance"

# 检查数据库中 HeroSMS API Key 是否正确配置
# 登录运营后台 -> 系统设置 -> 查看 hero_sms_api_key
```

---

## 📚 相关文档

- [PHP 官方文档](https://www.php.net/manual/zh/)
- [MySQL 官方文档](https://dev.mysql.com/doc/)
- [Nginx 官方文档](https://nginx.org/en/docs/)
- [宝塔面板文档](https://www.bt.cn/bbs/thread-1186-1-1.html)

---

**版本：** v1.0  
**最后更新：** 2026-03-19  
**状态：** ✅ 可用
