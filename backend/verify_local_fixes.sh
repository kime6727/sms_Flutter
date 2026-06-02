#!/bin/bash

# 验证本地修复脚本
# 用于确认本地 index.php 文件包含所有修复

echo "🔍 验证本地 index.php 修复状态"
echo "================================"
echo ""

BACKEND_DIR="/Volumes/ssd/aicode_new0421/sms/sms_Flutter/backend"
INDEX_PHP="${BACKEND_DIR}/index.php"

if [ ! -f "$INDEX_PHP" ]; then
    echo "❌ 错误: 找不到 index.php 文件"
    exit 1
fi

echo "📁 文件路径: $INDEX_PHP"
echo ""

# 检查 1: autoExpireOrders 调用
echo "1️⃣ 检查 autoExpireOrders 调用是否已移除"
if grep -q "autoExpireOrders(\$db, \$heroSMS)" "$INDEX_PHP"; then
    echo "   ❌ 失败: 仍然存在 autoExpireOrders(\$db, \$heroSMS) 调用"
    grep -n "autoExpireOrders(\$db, \$heroSMS)" "$INDEX_PHP"
else
    echo "   ✅ 通过: autoExpireOrders 调用已移除"
fi
echo ""

# 检查 2: API Key 端点移除标记
echo "2️⃣ 检查 API Key 端点移除标记"
if grep -q "API Key 端点已移除" "$INDEX_PHP"; then
    echo "   ✅ 通过: API Key 端点移除标记存在"
    grep -n "API Key 端点已移除" "$INDEX_PHP" | head -1
else
    echo "   ❌ 失败: 找不到 API Key 端点移除标记"
fi
echo ""

# 检查 3: 订单取消退款逻辑
echo "3️⃣ 检查订单取消退款逻辑标记"
if grep -q "pending状态全额退款" "$INDEX_PHP"; then
    echo "   ✅ 通过: 订单取消退款逻辑标记存在"
    grep -n "pending状态全额退款" "$INDEX_PHP" | head -1
else
    echo "   ❌ 失败: 找不到订单取消退款逻辑标记"
fi
echo ""

# 检查 4: Logger 调用
echo "4️⃣ 检查 Logger::logRequest 调用"
if grep -q "Logger::logRequest" "$INDEX_PHP"; then
    echo "   ✅ 通过: Logger::logRequest 调用存在"
    grep -n "Logger::logRequest" "$INDEX_PHP" | head -1
else
    echo "   ⚠️  警告: 找不到 Logger::logRequest 调用"
fi
echo ""

# 检查 5: 文件大小
echo "5️⃣ 文件信息"
FILE_SIZE=$(wc -c < "$INDEX_PHP")
LINE_COUNT=$(wc -l < "$INDEX_PHP")
echo "   文件大小: $FILE_SIZE 字节"
echo "   行数: $LINE_COUNT 行"
echo ""

# 检查新建文件
echo "6️⃣ 检查新建文件"
if [ -f "${BACKEND_DIR}/config/constants.php" ]; then
    echo "   ✅ config/constants.php 存在"
else
    echo "   ❌ config/constants.php 不存在"
fi

if [ -f "${BACKEND_DIR}/lib/Logger.php" ]; then
    echo "   ✅ lib/Logger.php 存在"
else
    echo "   ❌ lib/Logger.php 不存在"
fi

if [ -f "${BACKEND_DIR}/migrations/add_performance_indexes.sql" ]; then
    echo "   ✅ migrations/add_performance_indexes.sql 存在"
else
    echo "   ❌ migrations/add_performance_indexes.sql 不存在"
fi

if [ -f "${BACKEND_DIR}/test/deploy_check.php" ]; then
    echo "   ✅ test/deploy_check.php 存在"
else
    echo "   ❌ test/deploy_check.php 不存在"
fi
echo ""

# 总结
echo "================================"
echo "📊 验证总结"
echo "================================"
echo ""

PASS_COUNT=0
FAIL_COUNT=0

# 统计结果
if ! grep -q "autoExpireOrders(\$db, \$heroSMS)" "$INDEX_PHP"; then
    ((PASS_COUNT++))
else
    ((FAIL_COUNT++))
fi

if grep -q "API Key 端点已移除" "$INDEX_PHP"; then
    ((PASS_COUNT++))
else
    ((FAIL_COUNT++))
fi

if grep -q "pending状态全额退款" "$INDEX_PHP"; then
    ((PASS_COUNT++))
else
    ((FAIL_COUNT++))
fi

if grep -q "Logger::logRequest" "$INDEX_PHP"; then
    ((PASS_COUNT++))
fi

if [ -f "${BACKEND_DIR}/config/constants.php" ]; then
    ((PASS_COUNT++))
else
    ((FAIL_COUNT++))
fi

if [ -f "${BACKEND_DIR}/lib/Logger.php" ]; then
    ((PASS_COUNT++))
else
    ((FAIL_COUNT++))
fi

if [ -f "${BACKEND_DIR}/migrations/add_performance_indexes.sql" ]; then
    ((PASS_COUNT++))
else
    ((FAIL_COUNT++))
fi

echo "✅ 通过: $PASS_COUNT 项"
echo "❌ 失败: $FAIL_COUNT 项"
echo ""

if [ $FAIL_COUNT -eq 0 ]; then
    echo "🎉 所有本地修复验证通过！"
    echo ""
    echo "📤 下一步: 上传文件到服务器"
    echo "   scp $INDEX_PHP user@server:/path/to/backend/"
    echo "   scp ${BACKEND_DIR}/config/constants.php user@server:/path/to/backend/config/"
    echo "   scp ${BACKEND_DIR}/lib/Logger.php user@server:/path/to/backend/lib/"
    echo "   scp ${BACKEND_DIR}/migrations/add_performance_indexes.sql user@server:/path/to/backend/migrations/"
    echo "   scp ${BACKEND_DIR}/test/deploy_check.php user@server:/path/to/backend/test/"
else
    echo "⚠️  存在失败项，请检查修复"
fi

echo ""
echo "🔗 验证完成"
