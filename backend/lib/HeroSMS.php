<?php
/**
 * HeroSMS 服务类
 */

class HeroSMS {
    private $apiKey;
    private $baseUrl;
    
    private static $phoneCodeMap = [
        0 => '7', 1 => '380', 2 => '7', 3 => '86', 4 => '63', 5 => '95', 6 => '62', 7 => '60', 8 => '254', 9 => '255',
        10 => '84', 11 => '234', 12 => '998', 13 => '20', 14 => '91', 15 => '353', 16 => '855', 17 => '856', 18 => '509', 19 => '225',
        20 => '220', 21 => '381', 22 => '967', 23 => '27', 24 => '40', 25 => '57', 26 => '372', 27 => '994', 28 => '212', 29 => '233',
        30 => '54', 31 => '998', 32 => '237', 33 => '235', 34 => '49', 35 => '370', 36 => '385', 37 => '46', 38 => '964', 39 => '31',
        40 => '371', 41 => '43', 42 => '375', 43 => '66', 44 => '966', 45 => '52', 46 => '886', 47 => '34', 48 => '98', 49 => '213',
        50 => '386', 51 => '880', 52 => '221', 53 => '90', 54 => '94', 55 => '51', 56 => '92', 57 => '64', 58 => '224', 59 => '223',
        60 => '58', 61 => '251', 62 => '976', 63 => '55', 64 => '93', 65 => '256', 66 => '244', 67 => '357', 68 => '33', 69 => '675',
        70 => '258', 71 => '977', 72 => '232', 73 => '420', 74 => '1', 75 => '48', 76 => '44', 77 => '81', 78 => '591', 79 => '226',
        80 => '351', 81 => '216', 82 => '261', 83 => '228', 84 => '220', 85 => '243', 86 => '593', 87 => '352', 88 => '218', 89 => '968',
        90 => '961', 91 => '359', 92 => '971', 93 => '992', 94 => '267', 95 => '212', 96 => '993', 97 => '222', 98 => '249', 99 => '228', 100 => '995',
        101 => '30', 102 => '502', 103 => '972', 104 => '504', 105 => '503', 106 => '505', 107 => '1', 108 => '39', 109 => '1',
        110 => '506', 111 => '507', 112 => '592', 113 => '595', 114 => '598', 115 => '501', 116 => '231', 117 => '1', 118 => '1', 119 => '1',
        120 => '1', 121 => '670', 122 => '263', 123 => '265', 124 => '239', 125 => '245', 126 => '266', 127 => '268', 128 => '269', 129 => '247',
        130 => '242', 131 => '241', 132 => '250', 133 => '252', 134 => '253', 135 => '257', 136 => '258', 137 => '260', 138 => '262', 139 => '264',
        140 => '290', 141 => '291', 142 => '297', 143 => '298', 144 => '299', 145 => '350', 146 => '354', 147 => '355', 148 => '356', 149 => '358',
        150 => '373', 151 => '374', 152 => '376', 153 => '377', 154 => '378', 155 => '382', 156 => '387', 157 => '389', 158 => '421', 159 => '423',
        160 => '41', 161 => '45', 162 => '47', 163 => '420', 164 => '32', 165 => '692', 166 => '674', 167 => '597', 168 => '685', 169 => '679',
        170 => '686', 171 => '678', 172 => '681', 173 => '688', 174 => '683', 175 => '687', 176 => '689', 177 => '677', 178 => '676', 179 => '691',
        180 => '673', 181 => '672', 182 => '680', 183 => '682', 184 => '960', 185 => '975', 186 => '1', 187 => '973', 188 => '974', 189 => '965',
        190 => '962', 191 => '963', 192 => '1', 193 => '1', 194 => '1', 195 => '1', 196 => '1', 197 => '1', 198 => '1', 199 => '1',
        200 => '1', 201 => '1', 202 => '1', 203 => '1', 204 => '246'
    ];
    
    public function __construct($apiKey, $baseUrl = 'https://hero-sms.com/stubs/handler_api.php') {
        $this->apiKey = $apiKey;
        $this->baseUrl = $baseUrl;
    }

    /**
     * 根据国家ID获取区号 (内置映射)
     */
    public function getPhoneCodeByCountryId($countryId) {
        return self::$phoneCodeMap[$countryId] ?? '';
    }
    
    /**
     * 获取账户余额
     */
    public function getBalance() {
        try {
            $response = $this->request([
                'api_key' => $this->apiKey,
                'action' => 'getBalance'
            ]);
            
            if (is_array($response) && isset($response['balance'])) {
                return [
                    'success' => true,
                    'balance' => floatval($response['balance'])
                ];
            } elseif (is_string($response) && strpos($response, 'ACCESS_BALANCE') === 0) {
                $parts = explode(':', $response);
                return [
                    'success' => true,
                    'balance' => floatval($parts[1] ?? 0)
                ];
            }
            
            return ['success' => false, 'error' => '无法解析余额'];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * 获取号码
     */
    public function getNumber($serviceCode, $countryId) {
        try {
            $response = $this->request([
                'api_key' => $this->apiKey,
                'action' => 'getNumber',
                'service' => $serviceCode,
                'country' => $countryId
            ]);
            
            if (is_string($response)) {
                if (strpos($response, 'ACCESS_NUMBER') === 0) {
                    $parts = explode(':', $response);
                    return [
                        'success' => true,
                        'heroOrderId' => $parts[1] ?? null,
                        'phoneNumber' => $parts[2] ?? null
                    ];
                } elseif ($response === 'NO_NUMBERS') {
                    return ['success' => false, 'error' => 'NO_NUMBERS', 'message' => '当前没有可用号码'];
                } elseif ($response === 'NO_BALANCE') {
                    return ['success' => false, 'error' => 'NO_BALANCE', 'message' => '余额不足'];
                } elseif ($response === 'BAD_SERVICE') {
                    return ['success' => false, 'error' => 'BAD_SERVICE', 'message' => '无效的服务'];
                } elseif ($response === 'BAD_KEY') {
                    return ['success' => false, 'error' => 'BAD_KEY', 'message' => '无效的 API Key'];
                }
            }
            
            return ['success' => false, 'error' => 'UNKNOWN', 'message' => $response];
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'NETWORK_ERROR', 'message' => $e->getMessage()];
        }
    }
    
    /**
     * 获取短信
     */
    public function getSMS($heroOrderId) {
        try {
            $response = $this->request([
                'api_key' => $this->apiKey,
                'action' => 'getSms',
                'id' => $heroOrderId
            ]);
            
            if (is_string($response)) {
                if (strpos($response, 'SMS_RECEIVED') === 0) {
                    $parts = explode(':', $response);
                    return [
                        'success' => true,
                        'sms' => $parts[1] ?? null
                    ];
                } elseif ($response === 'NO_SMS') {
                    return ['success' => false, 'error' => 'NO_SMS', 'message' => '短信未到达'];
                } elseif ($response === 'SMS_CANCELLED') {
                    return ['success' => false, 'error' => 'SMS_CANCELLED', 'message' => '短信已取消'];
                }
            }
            
            return ['success' => false, 'error' => 'UNKNOWN', 'message' => $response];
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'NETWORK_ERROR', 'message' => $e->getMessage()];
        }
    }
    
    /**
     * 获取服务列表
     */
    public function getServicesList() {
        try {
            $response = $this->request([
                'api_key' => $this->apiKey,
                'action' => 'getServicesList'
            ]);

            if (is_array($response)) {
                // API返回结构: {"status": "success", "services": [...]}
                $services = $response['services'] ?? $response;
                return ['success' => true, 'services' => $services];
            }

            return ['success' => false, 'error' => '无法解析服务列表'];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * 获取国家列表
     */
    public function getCountries() {
        try {
            $response = $this->request([
                'api_key' => $this->apiKey,
                'action' => 'getCountries'
            ]);

            if (is_array($response)) {
                // API返回结构: {"status": "success", "countries": [...]}
                $countries = $response['countries'] ?? $response;
                return ['success' => true, 'countries' => $countries];
            }

            return ['success' => false, 'error' => '无法解析国家列表'];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * 获取指定服务的国家列表和价格
     */
    public function getServiceCountries($serviceCode) {
        try {
            $response = $this->request([
                'api_key' => $this->apiKey,
                'action' => 'getPrices',
                'service' => $serviceCode
            ]);

            if (is_array($response)) {
                // API返回结构: {country_id: {service_code: {cost, count, physicalCount}}}
                // country_id 是键，service_code 在里面
                $countries = [];
                foreach ($response as $countryId => $services) {
                    if (!is_array($services)) continue;
                    if (!isset($services[$serviceCode])) continue;
                    $costData = $services[$serviceCode];
                    $countries[] = [
                        'id' => $countryId,
                        'cost' => floatval($costData['cost'] ?? 0),
                        'count' => intval($costData['count'] ?? 0),
                        'physicalCount' => intval($costData['physicalCount'] ?? 0)
                    ];
                }
                return ['success' => true, 'countries' => $countries];
            }

            return ['success' => false, 'error' => '无法解析价格列表'];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * 取消号码
     */
    public function cancelNumber($heroOrderId) {
        try {
            $response = $this->request([
                'api_key' => $this->apiKey,
                'action' => 'cancelNumber',
                'id' => $heroOrderId
            ]);
            
            if ($response === 'ACCESS_CANCEL') {
                return ['success' => true, 'message' => '号码已取消'];
            } elseif ($response === 'NO_NUMBERS') {
                return ['success' => false, 'error' => 'NO_NUMBERS', 'message' => '当前没有可用号码'];
            }
            
            return ['success' => false, 'error' => 'UNKNOWN', 'message' => $response];
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'NETWORK_ERROR', 'message' => $e->getMessage()];
        }
    }

    /**
     * 取消订单 (别名)
     */
    public function cancelOrder($heroOrderId) {
        return $this->cancelNumber($heroOrderId);
    }
    
    /**
     * 获取号码状态
     */
    public function getStatus($heroOrderId) {
        try {
            $response = $this->request([
                'api_key' => $this->apiKey,
                'action' => 'getStatus',
                'id' => $heroOrderId
            ]);
            
            if (is_string($response)) {
                // 解析状态码
                $parsedStatus = $this->parseStatusString($response);
                if ($parsedStatus) {
                    return $parsedStatus;
                }
                // 无法解析，返回原始响应
                return ['success' => false, 'error' => 'UNKNOWN_STATUS', 'message' => $response];
            }
            
            return ['success' => true, 'status' => $response];
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'NETWORK_ERROR', 'message' => $e->getMessage()];
        }
    }
    
    /**
     * 设置激活状态
     * 
     * 状态说明:
     * 1 - 已准备好接收短信
     * 3 - 请求重发短信
     * 6 - 完成激活
     * 8 - 取消激活（退款）
     */
    public function setStatus($heroOrderId, $status) {
        try {
            $response = $this->request([
                'api_key' => $this->apiKey,
                'action' => 'setStatus',
                'id' => $heroOrderId,
                'status' => $status
            ]);
            
            if (is_string($response)) {
                if (strpos($response, 'ACCESS') === 0 || $response === '1') {
                    return ['success' => true, 'message' => '状态已更新'];
                }
                return ['success' => false, 'error' => 'SET_STATUS_FAILED', 'message' => $response];
            }
            
            return ['success' => false, 'error' => 'UNKNOWN_RESPONSE', 'message' => $response];
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'NETWORK_ERROR', 'message' => $e->getMessage()];
        }
    }
    
    /**
     * 标记为准备接收短信（状态 1）
     * 获取号码后调用此方法，告诉 HeroSMS 开始等待短信
     */
    public function markReady($heroOrderId) {
        return $this->setStatus($heroOrderId, 1);
    }
    
    /**
     * 请求重发短信（状态 3）
     */
    public function requestResend($heroOrderId) {
        return $this->setStatus($heroOrderId, 3);
    }
    
    /**
     * 完成激活（状态 6）
     * 收到短信后调用此方法，告诉 HeroSMS 激活已完成，停止计费
     */
    public function complete($heroOrderId) {
        return $this->setStatus($heroOrderId, 6);
    }
    
    /**
     * 取消激活（状态 8）
     */
    public function cancel($heroOrderId) {
        return $this->setStatus($heroOrderId, 8);
    }
    
    /**
     * 获取激活状态（V2版本，返回更详细信息）
     */
    public function getStatusV2($heroOrderId) {
        try {
            $response = $this->request([
                'api_key' => $this->apiKey,
                'action' => 'getStatusV2',
                'id' => $heroOrderId
            ]);
            
            if (is_string($response)) {
                $parsedStatus = $this->parseStatusString($response);
                if ($parsedStatus) {
                    $parsedStatus['raw'] = $response;
                    return $parsedStatus;
                }
                return ['success' => false, 'error' => 'UNKNOWN_STATUS', 'message' => $response];
            }
            
            return ['success' => true, 'status' => $response];
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'NETWORK_ERROR', 'message' => $e->getMessage()];
        }
    }
    
    /**
     * 解析 HeroSMS 状态字符串
     * 
     * 状态码说明:
     * STATUS_WAIT_CODE - 等待短信
     * STATUS_WAIT_RESEND - 等待重发
     * STATUS_CANCEL - 已取消
     * STATUS_OK - 收到短信 (格式: STATUS_OK:<短信内容>)
     */
    private function parseStatusString($response) {
        if (strpos($response, 'STATUS_OK') === 0) {
            // 收到短信: STATUS_OK:<短信内容>
            $parts = explode(':', $response, 2);
            return [
                'success' => true,
                'statusCode' => 6, // COMPLETE
                'status' => 'STATUS_OK',
                'sms' => isset($parts[1]) ? $parts[1] : null,
                'message' => '收到短信'
            ];
        } elseif ($response === 'STATUS_WAIT_CODE') {
            return [
                'success' => true,
                'statusCode' => 1, // SMS_SENT
                'status' => 'STATUS_WAIT_CODE',
                'message' => '等待短信'
            ];
        } elseif ($response === 'STATUS_WAIT_RESEND') {
            return [
                'success' => true,
                'statusCode' => 3, // REQUEST_RESEND
                'status' => 'STATUS_WAIT_RESEND',
                'message' => '等待重发'
            ];
        } elseif ($response === 'STATUS_CANCEL') {
            return [
                'success' => true,
                'statusCode' => 8, // CANCEL
                'status' => 'STATUS_CANCEL',
                'message' => '已取消'
            ];
        } elseif (strpos($response, 'BAD_STATUS') === 0) {
            return [
                'success' => false,
                'error' => 'BAD_STATUS',
                'message' => '无效的状态码'
            ];
        }
        
        return null;
    }
    
    /**
     * 设置 Webhook 地址
     */
    public function setWebhookUrl($url) {
        try {
            $response = $this->request([
                'api_key' => $this->apiKey,
                'action' => 'setUserSetting',
                'setting' => 'webhook_url',
                'value' => $url
            ]);
            
            if (is_array($response) && isset($response['status']) && $response['status'] === 'success') {
                return ['success' => true, 'message' => 'Webhook URL 已设置'];
            } elseif (is_string($response) && strpos($response, 'ACCESS') === 0) {
                return ['success' => true, 'message' => 'Webhook URL 已设置'];
            }
            
            return ['success' => false, 'error' => '设置失败', 'response' => $response];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * 获取当前 Webhook 设置
     */
    public function getWebhookUrl() {
        try {
            $response = $this->request([
                'api_key' => $this->apiKey,
                'action' => 'getUserSetting',
                'setting' => 'webhook_url'
            ]);
            
            if (is_array($response) && isset($response['value'])) {
                return ['success' => true, 'url' => $response['value']];
            }
            
            return ['success' => false, 'error' => '获取失败'];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * 发送 HTTP 请求
     */
    private function request($params) {
        $url = $this->baseUrl . '?' . http_build_query($params);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            throw new Exception('CURL 错误: ' . curl_error($ch));
        }
        
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("HTTP 错误: $httpCode");
        }
        
        // 尝试解析为 JSON
        $json = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $json;
        }
        
        // 否则返回原始字符串
        return $response;
    }

    /**
     * 获取国家列表 (别名)
     */
    public function getCountriesList() {
        return $this->getCountries();
    }

    /**
     * 获取所有服务价格
     */
    public function getPrices() {
        try {
            $response = $this->request([
                'api_key' => $this->apiKey,
                'action' => 'getPrices'
            ]);

            if (is_array($response)) {
                // API返回结构可能是嵌套的或直接的数组
                $prices = $response['prices'] ?? $response;
                return ['success' => true, 'prices' => $prices];
            }

            return ['success' => false, 'error' => '无法解析价格列表'];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * 检查库存数量
     */
    public function checkStock($serviceCode, $countryId) {
        try {
            $response = $this->request([
                'api_key' => $this->apiKey,
                'action' => 'getNumbersStatus',
                'service' => $serviceCode,
                'country' => $countryId
            ]);
            
            if (is_string($response) && is_numeric($response)) {
                // 返回可用数量
                return [
                    'success' => true,
                    'available' => intval($response)
                ];
            }
            
            // 如果API不支持，返回默认值（假设有库存）
            return [
                'success' => true,
                'available' => 999 // 默认假设有足够库存
            ];
        } catch (Exception $e) {
            // 网络错误时假设有库存，避免阻塞用户
            return [
                'success' => true,
                'available' => 999
            ];
        }
    }
}
?>
