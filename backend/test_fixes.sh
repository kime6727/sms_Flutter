#!/bin/bash

# 后端修复验证脚本
# 用途: 快速验证所有修复是否生效

BASE_URL="https://smsapi2.niceapp.eu.cc"
API_KEY="YOUR_API_KEY"  # 请替换为实际的API Key
TOKEN="YOUR_TOKEN"      # 请替换为实际的Token

echo "========================================="
echo "后端修复验证测试"
echo "========================================="
echo ""

# 颜色定义
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# 测试计数器
PASSED=0
FAILED=0

# 测试函数
test_endpoint() {
    local name=$1
    local method=$2
    local endpoint=$3
    local expected_status=$4
    local extra_args=$5
    
    echo -n "测试: $name ... "
    
    if [ "$method" = "GET" ]; then
        response=$(curl -s -w "\n%{http_code}" -X GET "$BASE_URL$endpoint" \
            -H "X-API-Key: $API_KEY" \
            $extra_args)
    else
        response=$(curl -s -w "\n%{http_code}" -X $method "$BASE_URL$endpoint" \
            -H "X-API-Key: $API_KEY" \
            -H "Content-Type: application/json" \
            $extra_args)
    fi
    
    http_code=$(echo "$response" | tail -n1)
    body=$(echo "$response" | head -n-1)
    
    if [ "$http_code" = "$expected_status" ]; then
        echo -e "${GREEN}✓ PASSED${NC} (HTTP $http_code)"
        PASSED=$((PASSED + 1))
        return 0
    else
        echo -e "${RED}✗ FAILED${NC} (Expected: $expected_status, Got: $http_code)"
        echo "  Response: $body"
        FAILED=$((FAILED + 1))
        return 1
    fi
}

echo "========================================="
echo "1. 安全修复验证"
echo "========================================="
echo ""

# 测试1: API Key端点应该不存在
echo -n "测试: API Key端点已移除 ... "
response=$(curl -s -w "\n%{http_code}" "$BASE_URL/api-key")
http_code=$(echo "$response" | tail -n1)
body=$(echo "$response" | head -n-1)

if [ "$http_code" = "404" ] || [ "$http_code" = "401" ]; then
    echo -e "${GREEN}✓ PASSED${NC} (端点已移除)"
    PASSED=$((PASSED + 1))
else
    echo -e "${RED}✗ FAILED${NC} (端点仍然存在: HTTP $http_code)"
    FAILED=$((FAILED + 1))
fi

echo ""
echo "========================================="
echo "2. 基础功能验证"
echo "========================================="
echo ""

# 测试2: 健康检查
test_endpoint "健康检查" "GET" "/health" "200"

# 测试3: 服务列表
test_endpoint "服务列表" "GET" "/services" "200"

# 测试4: 充值套餐
test_endpoint "充值套餐" "GET" "/payment/packages" "200"

echo ""
echo "========================================="
echo "3. 日志文件验证"
echo "========================================="
echo ""

# 测试5: 检查日志文件是否存在
echo -n "测试: API日志文件 ... "
if [ -f "/var/log/sms-receiver/api.log" ]; then
    echo -e "${GREEN}✓ PASSED${NC} (文件存在)"
    PASSED=$((PASSED + 1))
    
    # 检查最近的日志
    echo "  最近的日志:"
    tail -n 3 /var/log/sms-receiver/api.log | sed 's/^/    /'
else
    echo -e "${YELLOW}⚠ WARNING${NC} (文件不存在，可能权限问题)"
    echo "  请检查: sudo mkdir -p /var/log/sms-receiver"
    echo "  请检查: sudo chown -R www-data:www-data /var/log/sms-receiver"
fi

echo ""
echo "========================================="
echo "4. 数据库索引验证"
echo "========================================="
echo ""

# 测试6: 检查索引是否创建
echo -n "测试: 数据库索引 ... "
if command -v mysql &> /dev/null; then
    index_count=$(mysql -u root -p newsms -se "
        SELECT COUNT(*) 
        FROM information_schema.STATISTICS 
        WHERE TABLE_SCHEMA = 'newsms' 
        AND INDEX_NAME LIKE 'idx_%'
    " 2>/dev/null)
    
    if [ "$index_count" -gt 10 ]; then
        echo -e "${GREEN}✓ PASSED${NC} (找到 $index_count 个索引)"
        PASSED=$((PASSED + 1))
    else
        echo -e "${YELLOW}⚠ WARNING${NC} (只找到 $index_count 个索引)"
        echo "  请执行: mysql -u root -p newsms < migrations/add_performance_indexes.sql"
    fi
else
    echo -e "${YELLOW}⚠ SKIPPED${NC} (mysql命令不可用)"
fi

echo ""
echo "========================================="
echo "5. 性能测试"
echo "========================================="
echo ""

# 测试7: 响应时间测试
echo -n "测试: API响应时间 ... "
start_time=$(date +%s%N)
curl -s "$BASE_URL/services" -H "X-API-Key: $API_KEY" > /dev/null
end_time=$(date +%s%N)
duration=$(( (end_time - start_time) / 1000000 ))

if [ $duration -lt 200 ]; then
    echo -e "${GREEN}✓ PASSED${NC} (${duration}ms < 200ms)"
    PASSED=$((PASSED + 1))
elif [ $duration -lt 500 ]; then
    echo -e "${YELLOW}⚠ WARNING${NC} (${duration}ms，可以优化)"
else
    echo -e "${RED}✗ FAILED${NC} (${duration}ms > 500ms)"
    FAILED=$((FAILED + 1))
fi

echo ""
echo "========================================="
echo "测试总结"
echo "========================================="
echo ""
echo -e "通过: ${GREEN}$PASSED${NC}"
echo -e "失败: ${RED}$FAILED${NC}"
echo ""

if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}✓ 所有测试通过！${NC}"
    exit 0
else
    echo -e "${RED}✗ 有 $FAILED 个测试失败${NC}"
    echo ""
    echo "请检查:"
    echo "1. API Key是否正确"
    echo "2. 服务器是否已重启"
    echo "3. 数据库索引是否已创建"
    echo "4. 日志目录权限是否正确"
    exit 1
fi
