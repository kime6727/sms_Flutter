import 'package:flutter/foundation.dart';
import '../services/api_service.dart';

class NotificationProvider extends ChangeNotifier {
  final ApiService _apiService;

  List<Map<String, dynamic>> _notifications = [];
  bool _isLoading = false;
  String? _error;
  int _unreadCount = 0;

  List<Map<String, dynamic>> get notifications => _notifications;
  bool get isLoading => _isLoading;
  String? get error => _error;
  int get unreadCount => _unreadCount;

  NotificationProvider(this._apiService);

  bool _isNotificationUnread(Map<String, dynamic> n) {
    final readValue = n['read'] ?? n['is_read'];
    if (readValue is bool) return !readValue;
    if (readValue is int) return readValue == 0;
    if (readValue is String) return readValue == '0' || readValue == 'false';
    return false;
  }

  Future<void> loadNotifications({bool refresh = false}) async {
    _isLoading = true;
    notifyListeners();

    try {
      final response = await _apiService.getNotifications();
      if (response['data'] != null) {
        final data = response['data'];
        List<dynamic> list;
        if (data is List) {
          list = data;
        } else if (data is Map && data['list'] != null) {
          list = data['list'] as List;
        } else {
          list = [];
        }
        _notifications = list.map((e) => Map<String, dynamic>.from(e as Map)).toList();
        _unreadCount = _notifications.where(_isNotificationUnread).length;
      }
    } on ApiException catch (e) {
      _error = e.message;
    } catch (e) {
      _error = 'Failed to load notifications';
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<void> markAsRead(dynamic notificationId) async {
    try {
      await _apiService.markNotificationRead(notificationId);
      final index = _notifications.indexWhere((n) => n['id']?.toString() == notificationId.toString());
      if (index != -1) {
        _notifications[index]['read'] = true;
        _notifications[index]['is_read'] = true;
        _unreadCount = _unreadCount > 0 ? _unreadCount - 1 : 0;
        notifyListeners();
      }
    } catch (e) {
      debugPrint('Failed to mark notification as read: $e');
    }
  }

  Future<void> markAllAsRead() async {
    try {
      await _apiService.markAllNotificationsRead();
      for (var notification in _notifications) {
        notification['read'] = true;
        notification['is_read'] = true;
      }
      _unreadCount = 0;
      notifyListeners();
    } catch (e) {
      debugPrint('Failed to mark all notifications as read: $e');
    }
  }

  void clearError() {
    _error = null;
    notifyListeners();
  }
}
