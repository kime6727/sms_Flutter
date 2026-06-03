# 部署故障排查与恢复指南

## 当前状态
- 代码已多次推送到 GitHub（main 分支最新 commit: 4e0506e）
- `https://sms.niceapp.eu.cc/health` 持续返回 `502 Bad Gateway`
- 构建本身是成功的（"=== Successfully Built! ==="），所以问题在运行时/部署层

## 502 根因
Traefik（dokploy 反向代理）无法连接到 PHP 容器。可能原因：
1. **新代码未触发重新部署**（最可能）— dokploy 自动部署没配置或 webhook 没触发
2. 容器启动后立即崩溃
3. 端口不匹配（容器监听端口 ≠ Traefik 路由端口）

## 排查步骤

### 第 1 步：确认 dokploy 自动部署是否工作
打开 dokploy → `sms-receiver` 服务 → **Deployments** 选项卡
- 看是否有最新 commit (4e0506e) 对应的部署记录
- 如果没有 → 自动部署没工作，**需要手动点 Redeploy**

### 第 2 步：手动触发部署
dokploy → `sms-receiver` 服务 → 顶部 **Redeploy** 按钮
- 等待 2-3 分钟
- 部署完成后会自动启动容器

### 第 3 步：部署后访问诊断端点
- `https://sms.niceapp.eu.cc/health`  - 应该返回 200 OK
- `https://sms.niceapp.eu.cc/debug.php` - 详细诊断信息
- `https://sms.niceapp.eu.cc/install` - 安装向导（首次访问会建库）

### 第 4 步：看容器日志（如果还是 502）
dokploy → `sms-receiver` → **Logs** 选项卡
- 找最近启动的容器日志
- 看 PHP 是不是真的启动起来了，看有没有错误

## 当前 nixpacks.toml 关键配置
- 启动命令：`exec php -S 0.0.0.0:80 -t /app /app/router.php`
- 监听端口：**80**（固定，不再读环境变量）
- 路由：所有非静态文件请求 → `index.php`

## 如果还是 502，把容器日志截图发我
特别是这几行：
- `php -S` 启动那几行
- 任何 error / fatal / exception
