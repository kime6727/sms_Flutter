import 'dart:convert';
import 'dart:math';
import 'package:http/http.dart' as http;
import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../core/config/app_config.dart';
import '../core/utils/storage_service.dart';
import 'http_client_factory.dart';

class ApiService {
  String? _token;
  String? _deviceId;
  String? _userId;
  String? _apiKey;
  late final http.Client _httpClient;
  static const int maxRetries = 3;
  static const Duration retryDelay = Duration(milliseconds: 500);

  String? get token => _token;
  String? get deviceId => _deviceId;
  String? get userId => _userId;

  ApiService() {
    _httpClient = createPlatformClient();
  }

  Future<void> init() async {
    _token = await StorageService.getStringAsync('auth_token');
    _deviceId = await StorageService.getStringAsync('device_id');
    _userId = await StorageService.getStringAsync('user_id');
    _apiKey = AppConfig.apiKey;
    if (_deviceId == null || _deviceId!.isEmpty) {
      _deviceId = _generateDeviceId();
      await StorageService.setString('device_id', _deviceId!);
    }
    if (_apiKey == null || _apiKey!.isEmpty) {
      throw StateError(
        'API_KEY 未配置。请使用 --dart-define=API_KEY=xxxx 构建。',
      );
    }
  }

  Future<Map<String, dynamic>> _retryRequest(Future<Map<String, dynamic>> Function() request) async {
    int attempt = 0;
    while (true) {
      try {
        return await request();
      } catch (e) {
        attempt++;
        if (attempt >= maxRetries) {
          rethrow;
        }
        debugPrint('Request failed, retrying ($attempt/$maxRetries)...');
        debugPrint('Error: $e');
        await Future.delayed(retryDelay * attempt);
      }
    }
  }

  String _generateDeviceId() {
    final timestamp = DateTime.now().millisecondsSinceEpoch;
    final random = DateTime.now().microsecond;
    return 'device_${timestamp}_$random';
  }

  Future<void> setToken(String token) async {
    _token = token;
    await StorageService.setString('auth_token', token);
  }

  Future<void> setUserId(String userId) async {
    _userId = userId;
    await StorageService.setString('user_id', userId);
  }

  Future<void> clearToken() async {
    _token = null;
    _userId = null;
    await StorageService.remove('auth_token');
    await StorageService.remove('user_id');
  }

  Map<String, String> get _headers {
    final headers = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-Device-Id': _deviceId ?? '',
      'X-API-Key': _apiKey ?? AppConfig.apiKey,
    };
    if (_token != null && _token!.isNotEmpty) {
      headers['Authorization'] = 'Bearer $_token';
    }
    return headers;
  }

  Future<Map<String, dynamic>> get(
    String path, {
    Map<String, dynamic>? queryParameters,
  }) async {
    return _retryRequest(() async {
      final mergedParams = <String, dynamic>{};
      if (queryParameters != null) {
        mergedParams.addAll(queryParameters);
      }
      final uri = Uri.parse('${AppConfig.fullApiUrl}$path').replace(
        queryParameters: mergedParams.map(
          (key, value) => MapEntry(key, value.toString()),
        ),
      );

      debugPrint('API GET: $uri');
      final response = await _httpClient.get(uri, headers: _headers).timeout(
        AppConfig.apiTimeout,
      );

      return _handleResponse(response);
    });
  }

  Future<Map<String, dynamic>> post(
    String path, {
    Map<String, dynamic>? body,
  }) async {
    return _retryRequest(() async {
      final uri = Uri.parse('${AppConfig.fullApiUrl}$path');
      debugPrint('API POST: $uri');

      final response = await _httpClient
          .post(
            uri,
            headers: _headers,
            body: body != null ? jsonEncode(body) : null,
          )
          .timeout(AppConfig.apiTimeout);

      return _handleResponse(response);
    });
  }

  Future<Map<String, dynamic>> put(
    String path, {
    Map<String, dynamic>? body,
  }) async {
    return _retryRequest(() async {
      final uri = Uri.parse('${AppConfig.fullApiUrl}$path');
      debugPrint('API PUT: $uri');

      final response = await _httpClient
          .put(
            uri,
            headers: _headers,
            body: body != null ? jsonEncode(body) : null,
          )
          .timeout(AppConfig.apiTimeout);

      return _handleResponse(response);
    });
  }

  Future<Map<String, dynamic>> delete(
    String path, {
    Map<String, dynamic>? queryParameters,
  }) async {
    return _retryRequest(() async {
      final mergedParams = <String, dynamic>{};
      if (queryParameters != null) {
        mergedParams.addAll(queryParameters);
      }
      final uri = Uri.parse('${AppConfig.fullApiUrl}$path').replace(
        queryParameters: mergedParams.map(
          (key, value) => MapEntry(key, value.toString()),
        ),
      );
      debugPrint('API DELETE: $uri');

      final response = await _httpClient
          .delete(uri, headers: _headers)
          .timeout(AppConfig.apiTimeout);

      return _handleResponse(response);
    });
  }

  Map<String, dynamic> _handleResponse(http.Response response) {
    debugPrint('Response status: ${response.statusCode}');
    debugPrint('Response body: ${response.body}');

    Map<String, dynamic> data;
    try {
      data = jsonDecode(response.body);
    } catch (e) {
      throw ApiException(
        code: 'PARSE_ERROR',
        message: 'Failed to parse response',
        statusCode: response.statusCode,
      );
    }

    if (response.statusCode == 401) {
      clearToken();
      throw ApiException(
        code: 'UNAUTHORIZED',
        message: 'Token expired or invalid, please login again',
        statusCode: 401,
        data: data,
      );
    }

    if (response.statusCode >= 200 && response.statusCode < 300) {
      if (data['success'] == true || response.statusCode == 200) {
        return data;
      }
    }

    final message = data['message'] ?? data['error'] ?? 'Unknown error';
    final code = data['code'] ?? 'ERROR';

    throw ApiException(
      code: code,
      message: message,
      statusCode: response.statusCode,
      data: data,
    );
  }

  Future<Map<String, dynamic>> login({
    required String login,
    required String password,
  }) async {
    final response = await post('/auth/password-login', body: {
      'login': login,
      'password': password,
    });

    if (response['token'] != null) {
      await setToken(response['token']);
    }
    if (response['user']?['id'] != null) {
      await setUserId(response['user']['id'].toString());
    }

    return response;
  }

  Future<Map<String, dynamic>> register({
    required String password,
    required String email,
  }) async {
    final response = await post('/auth/manual-register', body: {
      'email': email,
      'password': password,
      'device_id': _deviceId,
    });

    if (response['token'] != null) {
      await setToken(response['token']);
    }
    if (response['user']?['id'] != null) {
      await setUserId(response['user']['id'].toString());
    }

    return response;
  }

  Future<Map<String, dynamic>> quickRegister({
    required String email,
    String? password,
  }) async {
    final response = await post('/auth/manual-register', body: {
      'email': email,
      'password': password ?? _generateRandomPassword(),
      'device_id': _deviceId,
    });

    if (response['token'] != null) {
      await setToken(response['token']);
    }
    if (response['user']?['id'] != null) {
      await setUserId(response['user']['id'].toString());
    }

    return response;
  }

  String _generateRandomPassword() {
    const chars =
        'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    final secureRng = Random.secure();
    final List<int> byteList = List<int>.generate(10, (_) => secureRng.nextInt(256));
    final buffer = StringBuffer();
    for (final b in byteList) {
      buffer.write(chars[b % chars.length]);
    }
    return buffer.toString();
  }

  Future<Map<String, dynamic>> getProfile() async {
    return get('/user/profile', queryParameters: {
      if (_userId != null) 'user_id': _userId!,
    });
  }

  Future<Map<String, dynamic>> getServices() async {
    return get('/services');
  }

  Future<Map<String, dynamic>> getBanners() async {
    return get('/banners');
  }

  Future<Map<String, dynamic>> getSettings() async {
    return get('/settings');
  }

  Future<Map<String, dynamic>> getCountries() async {
    return get('/countries');
  }

  Future<Map<String, dynamic>> getServiceCountries({
    int? serviceId,
    int? countryId,
  }) async {
    final params = <String, dynamic>{};
    if (serviceId != null) params['service_id'] = serviceId;
    if (countryId != null) params['country_id'] = countryId;
    if (_userId != null) params['user_id'] = _userId!;
    return get('/service-countries', queryParameters: params);
  }

  Future<Map<String, dynamic>> getPublishedServiceCountries({int? serviceId}) async {
    final params = <String, dynamic>{};
    if (serviceId != null) params['service_id'] = serviceId;
    return get('/service-countries/published', queryParameters: params);
  }

  Future<Map<String, dynamic>> calculatePrice({
    required int serviceId,
    required int countryId,
  }) async {
    final params = <String, dynamic>{
      'service_id': serviceId,
      'country_id': countryId,
    };
    if (_userId != null) params['user_id'] = _userId!;
    return get('/price/calculate', queryParameters: params);
  }

  Future<Map<String, dynamic>> createOrder({
    required int serviceId,
    required int countryId,
    int quantity = 1,
    int? pricePoints,
  }) async {
    final body = <String, dynamic>{
      'service_id': serviceId,
      'country_id': countryId,
      'quantity': quantity,
      if (_userId != null) 'user_id': _userId!,
      if (pricePoints != null) 'price_points': pricePoints,
    };
    return post('/orders/create', body: body);
  }

  Future<Map<String, dynamic>> activateOrder(dynamic orderId) async {
    return post('/orders/$orderId/activate');
  }

  Future<Map<String, dynamic>> cancelOrder(dynamic orderId) async {
    return post('/orders/$orderId/cancel');
  }

  Future<Map<String, dynamic>> getOrders({
    String? status,
    int limit = 50,
    int offset = 0,
  }) async {
    final params = <String, dynamic>{
      'limit': limit,
      'offset': offset,
    };
    if (_userId != null) params['user_id'] = _userId!;
    if (status != null) params['status'] = status;
    return get('/orders', queryParameters: params);
  }

  Future<Map<String, dynamic>> getOrderDetail(dynamic orderId) async {
    return get('/orders/$orderId');
  }

  Future<Map<String, dynamic>> getSmsCode(dynamic orderId) async {
    return get('/orders/$orderId/sms');
  }

  Future<Map<String, dynamic>> getPaymentPackages() async {
    return get('/payment/packages');
  }

  Future<Map<String, dynamic>> verifyAppleReceipt({
    required String receiptData,
    required String transactionId,
    required String userId,
    required String productId,
  }) async {
    return post('/verify-receipt', body: {
      'receipt_data': receiptData,
      'transaction_id': transactionId,
      'user_id': userId,
      'product_id': productId,
    });
  }

  Future<Map<String, dynamic>> getTransactions({
    int page = 1,
    int limit = 20,
  }) async {
    final params = <String, dynamic>{
      'page': page,
      'limit': limit,
    };
    if (_userId != null) params['user_id'] = _userId!;
    return get('/user/transactions', queryParameters: params);
  }

  Future<Map<String, dynamic>> updateProfile({
    String? nickname,
    String? avatar,
    String? email,
    String? oldPassword,
    String? newPassword,
  }) async {
    final body = <String, dynamic>{};
    if (nickname != null) body['nickname'] = nickname;
    if (avatar != null) body['avatar'] = avatar;
    if (email != null) body['email'] = email;
    if (oldPassword != null) body['old_password'] = oldPassword;
    if (newPassword != null) body['new_password'] = newPassword;
    return put('/user/profile', body: body);
  }

  Future<Map<String, dynamic>> deleteAccount() async {
    return delete('/user/delete', queryParameters: {
      if (_userId != null) 'user_id': _userId!,
    });
  }

  Future<Map<String, dynamic>> getNotifications({
    int page = 1,
    int limit = 20,
  }) async {
    final params = <String, dynamic>{
      'page': page,
      'limit': limit,
    };
    if (_userId != null) params['user_id'] = _userId!;
    return get('/notifications', queryParameters: params);
  }

  Future<Map<String, dynamic>> markNotificationRead(dynamic notificationId) async {
    return post('/notifications/$notificationId/read');
  }

  Future<Map<String, dynamic>> markAllNotificationsRead() async {
    return post('/notifications/read-all', body: {
      if (_userId != null) 'user_id': _userId!,
    });
  }

  Future<Map<String, dynamic>> getSystemSettings() async {
    return get('/settings');
  }

  Future<Map<String, dynamic>> getBalance() async {
    final params = <String, dynamic>{};
    if (_userId != null) params['user_id'] = _userId!;
    return get('/user/balance', queryParameters: params);
  }
}

class ApiException implements Exception {
  final String code;
  final String message;
  final int statusCode;
  final Map<String, dynamic>? data;

  ApiException({
    required this.code,
    required this.message,
    required this.statusCode,
    this.data,
  });

  @override
  String toString() => 'ApiException($code): $message';
}
