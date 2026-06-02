import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../../core/theme/app_theme.dart';
import '../../core/config/app_config.dart';
import '../../services/api_service.dart';
import 'package:provider/provider.dart';
import '../../providers/auth_provider.dart';

class ApiHealthCheckScreen extends StatefulWidget {
  const ApiHealthCheckScreen({super.key});

  @override
  State<ApiHealthCheckScreen> createState() => _ApiHealthCheckScreenState();
}

class _ApiHealthCheckScreenState extends State<ApiHealthCheckScreen> {
  final Map<String, ApiCheckResult> _results = {};
  bool _isChecking = false;
  int _totalTests = 0;
  int _passedTests = 0;
  int _failedTests = 0;

  @override
  void initState() {
    super.initState();
    _runHealthCheck();
  }

  Future<void> _runHealthCheck() async {
    setState(() {
      _isChecking = true;
      _results.clear();
      _totalTests = 0;
      _passedTests = 0;
      _failedTests = 0;
    });

    final apiService = ApiService();
    final authProvider = context.read<AuthProvider>();

    // 定义所有要测试的接口
    final tests = [
      ApiTest(
        name: '健康检查',
        endpoint: '/health',
        test: () => apiService.get('/health'),
      ),
      ApiTest(
        name: '系统设置',
        endpoint: '/settings',
        test: () => apiService.getSettings(),
      ),
      ApiTest(
        name: '服务列表',
        endpoint: '/services',
        test: () => apiService.getServices(),
      ),
      ApiTest(
        name: '服务国家列表',
        endpoint: '/service-countries/published',
        test: () => apiService.getPublishedServiceCountries(),
      ),
      ApiTest(
        name: '用户信息',
        endpoint: '/user/profile',
        test: () => apiService.getProfile(),
        requiresAuth: true,
      ),
      ApiTest(
        name: '订单列表',
        endpoint: '/orders',
        test: () => apiService.getOrders(),
        requiresAuth: true,
      ),
      ApiTest(
        name: '交易记录',
        endpoint: '/transactions',
        test: () => apiService.getTransactions(),
        requiresAuth: true,
      ),
      ApiTest(
        name: '通知列表',
        endpoint: '/notifications',
        test: () => apiService.getNotifications(),
        requiresAuth: true,
      ),
    ];

    _totalTests = tests.length;

    for (final test in tests) {
      if (test.requiresAuth && !authProvider.isAuthenticated) {
        _updateResult(test.name, ApiCheckResult(
          success: false,
          message: '需要登录',
          statusCode: 0,
          responseTime: 0,
          skipped: true,
        ));
        continue;
      }

      await _runTest(test);
      await Future.delayed(const Duration(milliseconds: 300));
    }

    setState(() {
      _isChecking = false;
    });
  }

  Future<void> _runTest(ApiTest test) async {
    final startTime = DateTime.now();
    
    try {
      final response = await test.test().timeout(
        const Duration(seconds: 10),
        onTimeout: () {
          throw Exception('请求超时（10秒）');
        },
      );
      final endTime = DateTime.now();
      final responseTime = endTime.difference(startTime).inMilliseconds;

      final success = response['success'] == true || response['data'] != null || response['status'] == 'ok';
      
      _updateResult(test.name, ApiCheckResult(
        success: success,
        message: success ? '连接正常' : (response['error'] ?? '未知错误'),
        statusCode: 200,
        responseTime: responseTime,
        data: response,
      ));

      if (success) {
        _passedTests++;
      } else {
        _failedTests++;
      }
    } catch (e) {
      final endTime = DateTime.now();
      final responseTime = endTime.difference(startTime).inMilliseconds;
      
      String errorMessage = e.toString();
      if (errorMessage.contains('ApiException')) {
        errorMessage = errorMessage.replaceAll('ApiException(', '').replaceAll(')', '');
      }
      
      _updateResult(test.name, ApiCheckResult(
        success: false,
        message: errorMessage,
        statusCode: 0,
        responseTime: responseTime,
      ));
      _failedTests++;
    }
  }

  void _updateResult(String name, ApiCheckResult result) {
    setState(() {
      _results[name] = result;
    });
  }

  @override
  Widget build(BuildContext context) {
    final authProvider = context.watch<AuthProvider>();
    
    return Scaffold(
      appBar: AppBar(
        title: const Text('🔧 后端接口检测'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: _isChecking ? null : _runHealthCheck,
          ),
          IconButton(
            icon: const Icon(Icons.copy),
            onPressed: () => _copyResults(),
          ),
        ],
      ),
      body: Column(
        children: [
          _buildHeader(),
          _buildSummary(),
          Expanded(
            child: _buildResultsList(),
          ),
        ],
      ),
    );
  }

  Widget _buildHeader() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [
            AppColors.primary.withOpacity(0.1),
            AppColors.primary.withOpacity(0.05),
          ],
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(Icons.api, color: AppColors.primary, size: 20),
              const SizedBox(width: 8),
              const Text(
                '后端地址',
                style: TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(8),
              border: Border.all(color: AppColors.primary.withOpacity(0.2)),
            ),
            child: Row(
              children: [
                Expanded(
                  child: Text(
                    AppConfig.apiBaseUrl,
                    style: const TextStyle(
                      fontSize: 13,
                      fontFamily: 'monospace',
                      color: AppColors.primary,
                    ),
                  ),
                ),
                IconButton(
                  icon: const Icon(Icons.copy, size: 18),
                  onPressed: () {
                    Clipboard.setData(ClipboardData(text: AppConfig.apiBaseUrl));
                    ScaffoldMessenger.of(context).showSnackBar(
                      const SnackBar(content: Text('已复制到剪贴板')),
                    );
                  },
                  padding: EdgeInsets.zero,
                  constraints: const BoxConstraints(),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildSummary() {
    final progress = _totalTests > 0 ? (_passedTests + _failedTests) / _totalTests : 0.0;
    
    return Container(
      padding: const EdgeInsets.all(16),
      child: Column(
        children: [
          Row(
            children: [
              Expanded(
                child: _buildSummaryCard(
                  '总计',
                  _totalTests.toString(),
                  Icons.list_alt,
                  Colors.blue,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: _buildSummaryCard(
                  '通过',
                  _passedTests.toString(),
                  Icons.check_circle,
                  Colors.green,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: _buildSummaryCard(
                  '失败',
                  _failedTests.toString(),
                  Icons.error,
                  Colors.red,
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          ClipRRect(
            borderRadius: BorderRadius.circular(8),
            child: LinearProgressIndicator(
              value: _isChecking ? null : progress,
              minHeight: 8,
              backgroundColor: Colors.grey[200],
              valueColor: AlwaysStoppedAnimation<Color>(
                _failedTests == 0 ? Colors.green : Colors.orange,
              ),
            ),
          ),
          if (_isChecking) ...[
            const SizedBox(height: 12),
            const Text(
              '正在检测中...',
              style: TextStyle(
                fontSize: 13,
                color: Colors.grey,
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildSummaryCard(String label, String value, IconData icon, Color color) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: color.withOpacity(0.1),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: color.withOpacity(0.3)),
      ),
      child: Column(
        children: [
          Icon(icon, color: color, size: 24),
          const SizedBox(height: 8),
          Text(
            value,
            style: TextStyle(
              fontSize: 24,
              fontWeight: FontWeight.bold,
              color: color,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            label,
            style: TextStyle(
              fontSize: 12,
              color: color,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildResultsList() {
    if (_results.isEmpty) {
      return const Center(
        child: CircularProgressIndicator(),
      );
    }

    return ListView.builder(
      padding: const EdgeInsets.all(16),
      itemCount: _results.length,
      itemBuilder: (context, index) {
        final entry = _results.entries.elementAt(index);
        return _buildResultCard(entry.key, entry.value);
      },
    );
  }

  Widget _buildResultCard(String name, ApiCheckResult result) {
    final color = result.skipped
        ? Colors.grey
        : (result.success ? Colors.green : Colors.red);
    
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: ExpansionTile(
        leading: Icon(
          result.skipped
              ? Icons.remove_circle_outline
              : (result.success ? Icons.check_circle : Icons.error),
          color: color,
        ),
        title: Text(
          name,
          style: const TextStyle(
            fontSize: 15,
            fontWeight: FontWeight.w600,
          ),
        ),
        subtitle: Text(
          result.message,
          style: TextStyle(
            fontSize: 13,
            color: color,
          ),
        ),
        trailing: result.skipped
            ? null
            : Text(
                '${result.responseTime}ms',
                style: TextStyle(
                  fontSize: 12,
                  color: Colors.grey[600],
                ),
              ),
        children: [
          Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                _buildDetailRow('状态码', result.statusCode.toString()),
                _buildDetailRow('响应时间', '${result.responseTime}ms'),
                _buildDetailRow('状态', result.success ? '✅ 成功' : '❌ 失败'),
                if (result.data != null) ...[
                  const SizedBox(height: 12),
                  const Text(
                    '响应数据:',
                    style: TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: Colors.grey[100],
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Text(
                      _formatJson(result.data!),
                      style: const TextStyle(
                        fontSize: 11,
                        fontFamily: 'monospace',
                      ),
                    ),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildDetailRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        children: [
          SizedBox(
            width: 80,
            child: Text(
              label,
              style: TextStyle(
                fontSize: 13,
                color: Colors.grey[600],
              ),
            ),
          ),
          Expanded(
            child: Text(
              value,
              style: const TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w500,
              ),
            ),
          ),
        ],
      ),
    );
  }

  String _formatJson(Map<String, dynamic> json) {
    try {
      // 简化显示，只显示关键信息
      final simplified = <String, dynamic>{};
      if (json['success'] != null) simplified['success'] = json['success'];
      if (json['message'] != null) simplified['message'] = json['message'];
      if (json['data'] != null) {
        if (json['data'] is List) {
          simplified['data'] = '${(json['data'] as List).length} items';
        } else if (json['data'] is Map) {
          simplified['data'] = '${(json['data'] as Map).keys.length} fields';
        } else {
          simplified['data'] = json['data'];
        }
      }
      return simplified.toString();
    } catch (e) {
      return json.toString();
    }
  }

  void _copyResults() {
    final buffer = StringBuffer();
    buffer.writeln('=== 后端接口检测报告 ===');
    buffer.writeln('后端地址: ${AppConfig.apiBaseUrl}');
    buffer.writeln('检测时间: ${DateTime.now()}');
    buffer.writeln('总计: $_totalTests | 通过: $_passedTests | 失败: $_failedTests');
    buffer.writeln('');
    
    _results.forEach((name, result) {
      buffer.writeln('[$name]');
      buffer.writeln('  状态: ${result.success ? "✅ 成功" : "❌ 失败"}');
      buffer.writeln('  消息: ${result.message}');
      buffer.writeln('  响应时间: ${result.responseTime}ms');
      buffer.writeln('');
    });

    Clipboard.setData(ClipboardData(text: buffer.toString()));
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('检测结果已复制到剪贴板')),
    );
  }
}

class ApiTest {
  final String name;
  final String endpoint;
  final Future<Map<String, dynamic>> Function() test;
  final bool requiresAuth;

  ApiTest({
    required this.name,
    required this.endpoint,
    required this.test,
    this.requiresAuth = false,
  });
}

class ApiCheckResult {
  final bool success;
  final String message;
  final int statusCode;
  final int responseTime;
  final Map<String, dynamic>? data;
  final bool skipped;

  ApiCheckResult({
    required this.success,
    required this.message,
    required this.statusCode,
    required this.responseTime,
    this.data,
    this.skipped = false,
  });
}
