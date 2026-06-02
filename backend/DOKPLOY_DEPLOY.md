# Dokploy 部署指南（详细步骤）

本文档面向使用 **Dokploy** 部署 SMS 接码平台后端的运维人员。整个流程约 15-30 分钟。

## 0. 你需要准备的东西

| 项目 | 例子 | 备注 |
|---|---|---|
| 域名 | `sms.niceapp.eu.cc` | 已绑定 |
| 服务器 | Dokploy 所在 VPS | 已有 |
| GitHub 仓库 | https://github.com/kime6727/sms_Flutter | 已推送，main 分支 |
| MySQL 数据库 | 5.7+ / 8.0+ / **TiDB Cloud** | 见下方 §1.5（云数据库要 SSL） |

---

## 1. 在 Dokploy 中创建 MySQL 数据库（推荐）

> 如果你已有外部 MySQL，跳到 1.5。

1. 登录 Dokploy 控制台（`https://<你的-dokploy-域名>`）
2. 左侧菜单 **Database** → **Create Database**
3. 填写：
   - **Name**: `sms-mysql`
   - **Image**: `mysql:8.0`
   - **Database Name**: `sms_receiver`
   - **Username**: `sms_user`
   - **Password**: 点击 **Generate** 或自定义（**记下来！**）
4. 端口默认 3306，不要对外暴露，只在 Dokploy 内部网络访问
5. 点击 **Deploy**，等待 1-2 分钟
6. 进入数据库的 **General** 页面，复制 **Internal Host**（形如 `sms-mysql`）和端口

> ⚠️ **关键**：内部主机名是 Dokploy 自动生成的容器名（例如 `sms-mysql`），不是 `localhost`。

### 1.5 初始化数据库表结构

数据库服务跑起来后，需要执行 SQL 初始化脚本。两种方式二选一：

**方式 A：用 Dokploy 的 Web Terminal（推荐）**

1. 进入 MySQL 服务 → **Exec** 标签
2. 输入命令：
   ```bash
   # 先把所有迁移文件上传到容器（或在镜像里 COPY）
   # 然后在容器内执行：
   mysql -u sms_user -p sms_receiver < /path/to/all_migrations.sql
   ```
   更简单的方式是用 `mysql` 客户端连接后，逐个 source：

**方式 B：从本地用 mysql 客户端连接（更直观）**

```bash
# 假设 MySQL 容器已经通过 Dokploy 暴露了 3306 端口，或你用 SSH 隧道连进去
# 从项目仓库下载迁移文件并按文件名顺序执行：
cd backend/migrations
for f in $(ls *.sql | sort); do
  echo "Applying $f"
  mysql -h <dokploy-host> -P 3306 -u sms_user -p sms_receiver < "$f"
done
```

**迁移文件执行顺序**（按文件名字母序）：

```
add_account_system_safe.sql
add_account_system_simple.sql
add_account_system.sql
add_banners_table.sql
add_countries_unique_index.sql
add_performance_indexes.sql
20260516_account_system_optimization.sql
20260516_create_apple_transactions.sql
20260516_create_topup_packages.sql
20260602_add_rate_limits_and_reset_tokens.sql   ← 这次新增的安全表
```

> 💡 提示：可以一次性把所有 SQL 合并后执行，更不易出错。

### 1.6 使用 TiDB Cloud 等云数据库（SSL 必须）

如果你的 MySQL 托管在 **TiDB Cloud / 阿里云 RDS / AWS RDS / PlanetScale / Google Cloud SQL** 等需要 SSL 连接的云服务上，跳过 §1，在 Dokploy 的 Application 环境变量里按下面 §2.3 的 TiDB Cloud 模板填。

> **强烈建议**：在云数据库控制台**单独创建一个数据库**（不要用 `sys`、`mysql`、`information_schema` 等系统库），名字叫 `sms_receiver` 或自定义。

> 性能提示：TiDB Cloud 在 ap-southeast-1（新加坡）与你 Dokploy 服务器之间可能有 50-200ms 延迟，对接码业务影响不大；如果想用就近区域，可改用 TiDB Cloud 的香港/日本集群，或用 PlanetScale 的免费 MySQL。

---

## 2. 在 Dokploy 中创建 Application

1. 左侧菜单 **Projects** → 选一个 Project（或新建 `sms-receiver`）→ **Create Service** → **Application**
2. 填写基础信息：
   - **Name**: `sms-api`
   - **Description**: SMS 接码平台 PHP 后端

### 2.1 连接 GitHub 仓库

1. 切到 **Source** 标签
2. **Source Type**: `Git`
3. **Repository**: `https://github.com/kime6727/sms_Flutter`
4. **Branch**: `main`
5. **Build Path**: `./backend` ⬅️ **关键**：因为仓库根是 `sms_Flutter/`，后端在子目录
6. **Trigger**: 选 `Push` （每次 push 自动部署）
7. 点击 **Save**

### 2.2 配置构建方式

切到 **Build** 标签：

- **Build Type**: `Dockerfile`
- **Dockerfile Location**: `Dockerfile`（相对 Build Path，即 `./backend/Dockerfile`）
- **Docker Context**: `.`（即 `./backend`）
- 其他保持默认

### 2.3 配置端口和环境变量

切到 **Advanced** 或 **Environment** 标签：

**端口**：
- **Port**: `80`
- **Container Port**: `80`（容器内 Apache 监听端口）

#### 模板 A：自建 MySQL（无 SSL）

```bash
# ===== 数据库 =====
DB_HOST=sms-mysql            # ← 填 Dokploy 中 MySQL 容器的内部主机名
DB_PORT=3306
DB_NAME=sms_receiver
DB_USER=sms_user
DB_PASS=你的MySQL密码
DB_SSL_ENABLED=0

# ===== API 鉴权（务必重新生成）=====
# 在服务器上执行:  openssl rand -hex 32
API_KEY=新生成的64位hex
# 同时在 KeyManager / system_settings 表里同步这个值

# ===== HeroSMS =====
HEROSMS_BASE_URL=https://hero-sms.com/stubs/handler_api.php
HEROSMS_API_KEY=你的herosms_key

# ===== Apple IAP =====
APPLE_SHARED_SECRET=你的Apple共享密钥

# ===== 鉴权签名（务必重新生成）=====
# 在服务器上执行:  openssl rand -hex 32
AUTH_SECRET_KEY=新生成的64位hex

# ===== 应用 =====
APP_NAME=SMS 接码平台
APP_URL=https://sms.niceapp.eu.cc
APP_ENV=production

# ===== CORS（按需）=====
CORS_ALLOWED_ORIGINS=https://sms.niceapp.eu.cc

# ===== 日志（可选）=====
LOG_DIR=/var/log/sms-receiver
```

#### 模板 B：TiDB Cloud（必须 SSL）

```bash
# ===== 数据库 (TiDB Cloud) =====
DB_HOST=gateway01.ap-southeast-1.prod.alicloud.tidbcloud.com
DB_PORT=4000
DB_NAME=sms_receiver                # ← 在 TiDB Cloud 控制台单独建一个，不要用 sys
DB_USER=2tNw6XTWxveXcVU.root        # ← 你的 TiDB 用户
DB_PASS=***TiDB密码***                # ← 强烈建议用新生成的，**轮换一次**（你已在聊天里贴过）
DB_SSL_ENABLED=1
DB_SSL_CA_CONTENT=***见下方获取CA证书***   # ← base64 编码的 CA 证书
DB_SSL_VERIFY=true

# ===== API 鉴权（务必重新生成）=====
API_KEY=新生成的64位hex

# ===== HeroSMS =====
HEROSMS_BASE_URL=https://hero-sms.com/stubs/handler_api.php
HEROSMS_API_KEY=你的herosms_key

# ===== Apple IAP =====
APPLE_SHARED_SECRET=你的Apple共享密钥

# ===== 鉴权签名（务必重新生成）=====
AUTH_SECRET_KEY=新生成的64位hex

# ===== 应用 =====
APP_NAME=SMS 接码平台
APP_URL=https://sms.niceapp.eu.cc
APP_ENV=production

# ===== CORS =====
CORS_ALLOWED_ORIGINS=https://sms.niceapp.eu.cc

# ===== 日志 =====
LOG_DIR=/var/log/sms-receiver
```

##### TiDB Cloud CA 证书获取方法

TiDB Cloud 使用 ISRG / DigiCert 系列 CA。你有三种方式，推荐**方式 ①**：

**方式 ①：用 TiDB Cloud 控制台下载的 CA（推荐）**

1. 登录 TiDB Cloud → 你的 Cluster → **Connect** → **General** 标签
2. 在 "CA根证书" 区域下载 `tidb-ca.pem` 文件
3. 在本地把它转成单行 base64：
   ```bash
   base64 -w 0 tidb-ca.pem > tidb-ca.b64
   cat tidb-ca.b64   # 复制这一整行（很长，几 KB）粘贴到 DB_SSL_CA_CONTENT
   ```

**方式 ②：直接用 ISRG Root X1（Let's Encrypt 根 CA）**

TiDB Cloud 多数节点证书链用 Let's Encrypt 签发：
```bash
# 在你本地执行，获取单行 base64
curl -sS https://letsencrypt.org/certs/isrgrootx1.pem | base64 -w 0
# 复制输出，粘贴到 DB_SSL_CA_CONTENT
```

**方式 ③：跳过证书校验（不推荐，仅调试）**

实在拿不到证书，把 `DB_SSL_VERIFY` 改成 `false`，但仍要 `DB_SSL_ENABLED=1`：
```bash
DB_SSL_ENABLED=1
DB_SSL_VERIFY=false
# DB_SSL_CA_CONTENT 留空
```
> ⚠️ 这样会走加密连接但不验证服务端身份，**仅用于排查**。生产环境必须用方式 ① 或 ②。

> ⚠️ **两个密钥必须重新生成**，不能复用代码里或旧部署里的：
> - `API_KEY`：客户端发请求时 `X-API-Key` 头携带的值
> - `AUTH_SECRET_KEY`：用户 Token 签名密钥
>
> 生成命令（在本地或服务器上执行均可）：
> ```bash
> openssl rand -hex 32
> ```

> 🔒 **特别提醒**：你的 TiDB 密码已在本对话中明文出现，**强烈建议**到 TiDB Cloud 控制台 `Reset Password` 重置一次，把新密码填到 Dokploy。旧密码虽然 Dokploy 那侧不会泄露，但聊天记录/截图/Claude 历史里都会有痕迹。

---

## 3. 配置域名 + HTTPS

1. 切到 **Domains** 标签
2. 点击 **Add Domain**
3. 填写：
   - **Host**: `sms.niceapp.eu.cc`
   - **Port**: `80`（容器内）
   - **HTTPS**: ✅ 启用（让 Dokploy/Traefik 自动申请 Let's Encrypt 证书）
4. 保存

DNS 上确保 `sms.niceapp.eu.cc` 解析到 Dokploy 所在服务器的 IP（A 记录）。如果使用 Cloudflare，开启代理（橙色云朵）也行。

---

## 4. 第一次部署

1. 切回 **Deployments** 标签
2. 点击 **Deploy** 按钮
3. 等待构建（首次约 2-3 分钟，之后会快）
4. 查看 **Logs**：应看到 `apache2 -foreground` 启动
5. 部署完成后，浏览器访问 `https://sms.niceapp.eu.cc/api/health` 应返回 `{"success":true,...}`

---

## 5. 部署后验证清单

跑一遍下面这些 curl，确认后端正常工作：

```bash
# 1. 健康检查（无需鉴权）
curl https://sms.niceapp.eu.cc/api/health

# 2. 携带 API Key 的健康检查
curl -H "X-API-Key: 你的API_KEY" https://sms.niceapp.eu.cc/api/health

# 3. 获取轮播图
curl -H "X-API-Key: 你的API_KEY" https://sms.niceapp.eu.cc/api/banners

# 4. 获取系统设置
curl -H "X-API-Key: 你的API_KEY" https://sms.niceapp.eu.cc/api/settings

# 5. 测试用户注册（应返回新账号）
curl -X POST https://sms.niceapp.eu.cc/api/auth/manual-register \
  -H "X-API-Key: 你的API_KEY" \
  -H "Content-Type: application/json" \
  -d '{}'
```

如果 `/api/health` 返回 200、`/api/banners` 能返回数据，部署就成功了 ✅。

---

## 6. 常见问题排查

### Q1: 部署成功但访问 502 Bad Gateway
- 检查容器是否启动：`Project → sms-api → Logs`
- 确认 `DB_HOST` 是 Dokploy MySQL 容器的**内部主机名**（不是 `localhost`）
- 在 Dokploy → Application → **Exec** 标签里进入容器，跑：
  ```bash
  php -r "require 'config/database.php'; require 'lib/Database.php'; \$db = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT); var_dump(\$db->query('SELECT 1')->fetchAll());"
  ```

### Q1.5: TiDB Cloud 连接报错 "SSL operation failed" / "certificate verify failed"
- 说明 SSL 配置有问题
- 检查 `DB_SSL_ENABLED=1`
- `DB_SSL_CA_CONTENT` 是否是**单行 base64**（用 `base64 -w 0` 而不是普通 base64）
- 如果用方式 ③ 跳过校验：`DB_SSL_VERIFY=false`
- 终极排查：在容器 Exec 里手测：
  ```bash
  php -r "
  \$_ENV['DB_SSL_CA_CONTENT'] = getenv('DB_SSL_CA_CONTENT');
  require 'config/database.php';
  require 'lib/Database.php';
  \$db = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT);
  print_r(\$db->query('SELECT VERSION() AS v')->fetch());
  "
  ```

### Q2: API 返回 401 Unauthorized
- `X-API-Key` 头不正确。检查环境变量里 `API_KEY` 是不是新生成的那个
- 用 `/api/health`（带 Key）确认服务端能识别

### Q3: 登录返回 500
- 数据库连接失败。看 Logs
- 数据库表是否都迁移完了（执行第 1.5 节）

### Q4: 数据库不存在 / 表不存在
- 重新执行第 1.5 节的迁移脚本
- 推荐用 `SHOW TABLES;` 在 MySQL 容器 Exec 里检查

### Q5: 静态资源（国家 svg、服务 webp）404
- 检查 `pic/` 目录是否在镜像里。Dockerfile 已经 `COPY .` 了，应该在
- 检查 `.htaccess` 中的缓存规则

### Q6: 部署后旧 token 全部失效
- 这是因为 `AUTH_SECRET_KEY` 改了（每次重新生成密钥，旧 Token 都作废）
- 用户需要重新登录。这是预期行为

### Q7: 邮件发不出去（忘记密码功能）
- PHP `mail()` 在 Docker 镜像里默认**没有**SMTP 配置
- 建议：换用 PHPMailer + SMTP，或在 Dokploy 中挂载一个 SMTP relay
- 短期方案：直接看 `password_reset_tokens` 表，把 token 取出来给用户

---

## 7. 后续更新部署

日常开发后，只要 push 到 GitHub 的 `main` 分支：

```bash
git add -A
git commit -m "feat: xxx"
git push origin main
```

Dokploy 会自动：
1. 拉取新代码
2. 重新构建 Docker 镜像
3. 重启容器
4. 保留环境变量和挂载的卷

> 💡 建议：第一次部署成功后，测试一下自动部署是否正常。

---

## 8. 数据备份

在 Dokploy → MySQL 服务 → **Backups** 标签里：
- 启用 **Scheduled Backup**
- 设置每日备份，保留 7 天
- 备份到 S3 / SFTP / 本地存储

---

## 9. 监控

- **Logs**: `Project → sms-api → Logs` 实时看 stdout/stderr
- **Stats**: 同一页面有 CPU/内存曲线
- **建议**: 在外部加一个 UptimeRobot（免费）监控 `https://sms.niceapp.eu.cc/api/health`，挂了发邮件/微信通知

---

**部署完成！** 🎉
