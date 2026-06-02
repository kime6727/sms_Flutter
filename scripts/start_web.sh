#!/bin/bash

# Flutter Web 启动脚本
# 用途：快速启动 Flutter Web 应用进行测试

echo "🚀 启动 Flutter Web 应用..."
echo "📍 后端地址: https://smsapi2.niceapp.eu.cc"
echo "🌐 Web地址: http://localhost:9090"
echo ""

# 进入 Flutter 项目目录
cd /Volumes/ssd/aicode_new0421/sms/sms_Flutter/app_Flutter

# 设置 Flutter SDK 路径
export PATH="/Volumes/ssd/aicode_new0421/SDK/FlutterSDK/flutter/bin:$PATH"

# 启动 Flutter Web
echo "⏳ 正在启动，请稍候..."
flutter run -d chrome --web-port=9090 --web-hostname=localhost

# 注意：
# 1. 使用 Ctrl+C 停止服务
# 2. 如需修改端口，编辑 --web-port 参数
# 3. 后端地址配置在 lib/core/config/app_config.dart
