#!/bin/bash
# 重新加载 nginx 配置
# 使用方法：在终端中运行此脚本

echo "正在测试 nginx 配置..."
/Applications/ServBay/package/nginx/1.29.3/sbin/nginx -t

if [ $? -eq 0 ]; then
    echo "配置测试通过，正在重新加载 nginx..."
    sudo /Applications/ServBay/package/nginx/1.29.3/sbin/nginx -s reload
    echo "nginx 已重新加载！"
else
    echo "nginx 配置测试失败，请检查配置"
fi
