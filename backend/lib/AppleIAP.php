<?php
/**
 * Apple IAP 验证类
 */

class AppleIAP {
    private $sharedSecret;
    private $productionUrl = 'https://buy.itunes.apple.com/verifyReceipt';
    private $sandboxUrl = 'https://sandbox.itunes.apple.com/verifyReceipt';
    
    public function __construct($sharedSecret) {
        $this->sharedSecret = $sharedSecret;
    }
    
    /**
     * 验证收据
     */
    public function verifyReceipt($receiptData, $excludeOldTransactions = true) {
        // 先尝试生产环境
        $result = $this->callAppleVerify($this->productionUrl, $receiptData, $excludeOldTransactions);
        
        // 如果返回沙盒错误，尝试沙盒环境
        if (isset($result['status']) && $result['status'] == 21007) {
            $result = $this->callAppleVerify($this->sandboxUrl, $receiptData, $excludeOldTransactions);
        }
        
        return $this->parseResponse($result);
    }
    
    /**
     * 调用 Apple 验证接口
     */
    private function callAppleVerify($url, $receiptData, $excludeOldTransactions) {
        $requestBody = [
            'receipt-data' => $receiptData,
            'exclude-old-transactions' => $excludeOldTransactions
        ];
        
        if ($this->sharedSecret) {
            $requestBody['password'] = $this->sharedSecret;
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestBody));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true);
    }
    
    /**
     * 解析 Apple 响应
     */
    private function parseResponse($data) {
        if (!is_array($data)) {
            return [
                'success' => false,
                'error' => 'INVALID_RESPONSE',
                'message' => '无效的响应格式'
            ];
        }
        
        $status = $data['status'] ?? null;
        
        $statusMessages = [
            0 => '收据验证成功',
            21000 => 'App Store 无法读取提供的 JSON 数据',
            21002 => '收据数据格式错误',
            21003 => '收据无法被验证',
            21004 => '提供的共享密钥与账户文件中的密钥不匹配',
            21005 => '收据服务器当前不可用',
            21006 => '收据有效但订阅已过期',
            21007 => '收据来自沙盒环境，但发送到了生产环境验证',
            21008 => '收据来自生产环境，但发送到了沙盒环境验证',
            21009 => '内部数据访问错误',
            21010 => '用户账户找不到或已被删除'
        ];
        
        if ($status !== 0) {
            return [
                'success' => false,
                'status' => $status,
                'error' => 'VERIFICATION_FAILED',
                'message' => $statusMessages[$status] ?? "未知错误: $status"
            ];
        }
        
        $receipt = $data['receipt'] ?? [];
        $latestReceiptInfo = $data['latest_receipt_info'] ?? [];
        
        // 过滤有效的交易（未取消）
        $validTransactions = array_filter($latestReceiptInfo, function($t) {
            return empty($t['cancellation_date']);
        });
        
        return [
            'success' => true,
            'receipt' => [
                'bundleId' => $receipt['bundle_id'] ?? null,
                'environment' => $data['environment'] ?? 'Production',
                'originalTransactionId' => $receipt['original_transaction_id'] ?? null,
                'transactions' => array_map(function($t) {
                    return [
                        'transactionId' => $t['transaction_id'] ?? null,
                        'originalTransactionId' => $t['original_transaction_id'] ?? null,
                        'productId' => $t['product_id'] ?? null,
                        'purchaseDate' => isset($t['purchase_date_ms']) ? 
                            date('Y-m-d H:i:s', intval($t['purchase_date_ms']) / 1000) : null,
                        'expiresDate' => isset($t['expires_date_ms']) ? 
                            date('Y-m-d H:i:s', intval($t['expires_date_ms']) / 1000) : null,
                        'quantity' => intval($t['quantity'] ?? 1)
                    ];
                }, $validTransactions)
            ],
            'rawResponse' => $data
        ];
    }
    
    /**
     * 验证交易
     */
    public function validateTransaction($verificationResult, $expectedProductId, $expectedTransactionId) {
        if (!$verificationResult['success']) {
            return ['valid' => false, 'error' => $verificationResult['message']];
        }
        
        $transactions = $verificationResult['receipt']['transactions'] ?? [];
        
        $transaction = null;
        foreach ($transactions as $t) {
            if ($t['transactionId'] === $expectedTransactionId || 
                $t['originalTransactionId'] === $expectedTransactionId) {
                $transaction = $t;
                break;
            }
        }
        
        if (!$transaction) {
            return ['valid' => false, 'error' => '交易记录不存在'];
        }
        
        if ($expectedProductId && $transaction['productId'] !== $expectedProductId) {
            return ['valid' => false, 'error' => '产品 ID 不匹配'];
        }
        
        if ($transaction['expiresDate']) {
            $expiresTime = strtotime($transaction['expiresDate']);
            if ($expiresTime < time()) {
                return ['valid' => false, 'error' => '订阅已过期'];
            }
        }
        
        return [
            'valid' => true,
            'transaction' => $transaction,
            'environment' => $verificationResult['receipt']['environment']
        ];
    }
}
?>
