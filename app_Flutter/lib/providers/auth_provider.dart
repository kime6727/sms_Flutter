import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import '../models/user_model.dart';
import '../services/api_service.dart';
import '../core/i18n/app_localizations.dart';
import '../core/utils/auth_change_notifier.dart';

class AuthProvider extends ChangeNotifier {
  final ApiService _apiService;

  UserModel? _user;
  bool _isLoading = false;
  bool _isAuthenticated = false;
  String? _error;
  Locale _locale = const Locale('en');

  UserModel? get user => _user;
  bool get isLoading => _isLoading;
  bool get isAuthenticated => _isAuthenticated;
  String? get error => _error;
  Locale get locale => _locale;
  AppLocalizations get loc => AppLocalizations(_locale);
  int get points => _user?.points ?? 0;
  String get membershipLabel => _user?.membershipLabel ?? '';
  double get membershipProgress => _user?.membershipProgress ?? 0;
  bool get hasFirstTopupBonus => _user?.hasFirstTopupBonus ?? false;

  AuthProvider(this._apiService) {
    _checkAuthStatus();
    // 监听 401/403：清掉本地状态，让 GoRouter 的 redirect 跳转到登录页
    ApiService.addOnUnauthorizedListener(_handleUnauthorized);
  }

  @override
  void dispose() {
    ApiService.removeOnUnauthorizedListener(_handleUnauthorized);
    super.dispose();
  }

  /// 401 回调：清本地认证状态（AuthChangeNotifier 触发 GoRouter redirect）
  void _handleUnauthorized() {
    _user = null;
    _isAuthenticated = false;
    _error = '登录已过期，请重新登录';
    notifyListeners();
  }

  /// B27: 统一的 notify 入口，除通知 Provider 监听者外，
  /// 还通知 GoRouter 的 refreshListenable，让路由 redirect 重新判断
  @override
  void notifyListeners() {
    super.notifyListeners();
    AuthChangeNotifier.instance.notifyListeners();
  }

  void setLocale(Locale locale) {
    _locale = locale;
    notifyListeners();
  }

  Future<void> _checkAuthStatus() async {
    final token = _apiService.token;
    if (token != null && token.isNotEmpty) {
      _isAuthenticated = true;
      notifyListeners();
      await loadProfile();
    }
  }

  Map<String, dynamic>? _extractUser(Map<String, dynamic> response) {
    if (response['user'] != null) {
      return response['user'] as Map<String, dynamic>;
    }
    if (response['data'] != null && response['data']['user'] != null) {
      return response['data']['user'] as Map<String, dynamic>;
    }
    return null;
  }

  Future<bool> login(String loginValue, String password) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final response = await _apiService.login(
        login: loginValue,
        password: password,
      );

      final userData = _extractUser(response);
      final token = response['token'] as String?;

      if (userData != null && response['success'] == true) {
        _user = UserModel.fromJson(userData);
        if (token != null && token.isNotEmpty) {
          await _apiService.setToken(token);
        }
        final userId = userData['id']?.toString();
        if (userId != null && userId.isNotEmpty) {
          await _apiService.setUserId(userId);
        }
        _isAuthenticated = true;
        notifyListeners();
        return true;
      }
      return false;
    } on ApiException catch (e) {
      _error = e.message;
      _isAuthenticated = false;
      notifyListeners();
      return false;
    } catch (e) {
      _error = 'Network error';
      _isAuthenticated = false;
      notifyListeners();
      return false;
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<bool> register({
    required String password,
    required String email,
  }) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final response = await _apiService.register(
        password: password,
        email: email,
      );

      final userData = _extractUser(response);
      final token = response['token'] as String?;

      if (userData != null && response['success'] == true) {
        _user = UserModel.fromJson(userData);
        if (token != null && token.isNotEmpty) {
          await _apiService.setToken(token);
        }
        final userId = userData['id']?.toString();
        if (userId != null && userId.isNotEmpty) {
          await _apiService.setUserId(userId);
        }
        _isAuthenticated = true;
        notifyListeners();
        return true;
      }
      return false;
    } on ApiException catch (e) {
      _error = e.message;
      _isAuthenticated = false;
      notifyListeners();
      return false;
    } catch (e) {
      _error = 'Network error';
      _isAuthenticated = false;
      notifyListeners();
      return false;
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<Map<String, String>?> quickRegister({
    required String email,
    String? password,
  }) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final response = await _apiService.quickRegister(
        email: email,
        password: password,
      );

      if (response['success'] == true) {
        final credentials = response['credentials'];
        final username = credentials?['username'] as String? ??
                         response['user']?['username'] as String? ?? '';
        final pwd = credentials?['password'] as String? ?? '';
        final regEmail = response['user']?['email'] as String? ?? email;

        final token = response['token'] as String?;
        if (token != null && token.isNotEmpty) {
          await _apiService.setToken(token);
        }

        final userData = _extractUser(response);
        if (userData != null) {
          _user = UserModel.fromJson(userData);
          final userId = userData['id']?.toString();
          if (userId != null && userId.isNotEmpty) {
            await _apiService.setUserId(userId);
          }
        }
        _isAuthenticated = true;
        notifyListeners();
        return {'username': username, 'password': pwd, 'email': regEmail};
      }
      _error = response['message'] ?? 'Registration failed';
      return null;
    } catch (e) {
      _error = 'Network error: $e';
      return null;
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  ApiService get apiService => _apiService;

  Future<Map<String, dynamic>> forgotPassword(String email) async {
    return await _apiService.post('/auth/forgot-password', body: {
      'email': email,
    });
  }

  /// 改用 forgot-password 流程：后端生成新密码并直接返回，前端展示给用户
  /// 原 reset-password 流程需要 reset_token，过于复杂，对应场景已废弃
  Future<Map<String, dynamic>> resetPassword(String email, String newPassword) async {
    // 忽略前端传入的 newPassword，统一由后端生成（更安全，避免用户弱密码）
    return await forgotPassword(email);
  }

  Future<Map<String, dynamic>> updateProfile({
    String? nickname,
    String? avatar,
    String? oldPassword,
    String? newPassword,
    String? setPassword,
  }) async {
    final body = <String, dynamic>{};
    if (nickname != null) body['nickname'] = nickname;
    if (avatar != null) body['avatar'] = avatar;
    if (oldPassword != null && newPassword != null) {
      body['old_password'] = oldPassword;
      body['new_password'] = newPassword;
    }
    if (setPassword != null) {
      body['set_password'] = setPassword;
    }
    return await _apiService.put('/user/profile', body: body);
  }

  Future<void> loadProfile() async {
    if (!_isAuthenticated) return;

    try {
      final response = await _apiService.getProfile();
      if (response['data'] != null) {
        _user = UserModel.fromJson(response['data']);
        final userId = _user?.id?.toString();
        if (userId != null && userId.isNotEmpty) {
          await _apiService.setUserId(userId);
        }
        notifyListeners();
      }
    } catch (e) {
      debugPrint('Failed to load profile: $e');
    }
  }

  Future<void> logout() async {
    await _apiService.clearToken();
    _user = null;
    _isAuthenticated = false;
    _error = null;
    notifyListeners();
  }

  void clearError() {
    _error = null;
    notifyListeners();
  }
}
