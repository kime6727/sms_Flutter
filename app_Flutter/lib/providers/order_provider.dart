import 'package:flutter/foundation.dart';
import '../models/order_model.dart';
import '../services/api_service.dart';

class OrderProvider extends ChangeNotifier {
  final ApiService _apiService;

  // B9: 按 status 拆开存储，避免 tab 切换时数据互相污染
  final Map<String, List<OrderModel>> _ordersByStatus = {};
  final Map<String, bool> _hasMoreByStatus = {};
  final Map<String, bool> _isLoadingByStatus = {};

  OrderModel? _currentOrder;
  bool _isLoading = false;
  String? _error;

  /// 当前激活的 tab（用于 loadOrders 不带参时使用）
  String _activeStatus = 'pending';

  List<OrderModel> get orders => _ordersByStatus[_activeStatus] ?? const [];
  OrderModel? get currentOrder => _currentOrder;
  bool get isLoading =>
      _isLoading || (_isLoadingByStatus[_activeStatus] ?? false);
  String? get error => _error;
  bool get hasMore => _hasMoreByStatus[_activeStatus] ?? true;
  String? get activeStatus => _activeStatus;

  /// 给 UI 按 status 读取的便捷 getter
  List<OrderModel> ordersByStatus(String status) =>
      _ordersByStatus[status] ?? const [];
  bool isLoadingFor(String status) => _isLoadingByStatus[status] ?? false;
  bool hasMoreFor(String status) => _hasMoreByStatus[status] ?? true;

  OrderProvider(this._apiService);

  Future<void> loadOrders({
    bool refresh = false,
    String? status,
  }) async {
    final target = status ?? _activeStatus;
    _activeStatus = target;

    if (_isLoadingByStatus[target] == true) return;
    final current = _ordersByStatus[target] ?? const [];
    if (!refresh && (_hasMoreByStatus[target] ?? true) == false) return;

    _isLoadingByStatus[target] = true;
    notifyListeners();

    try {
      final response = await _apiService.getOrders(
        status: target,
        limit: 20,
        offset: refresh ? 0 : current.length,
      );

      if (response['data'] != null) {
        final listData = response['data'];
        final List<dynamic> list;
        if (listData is List) {
          list = listData;
        } else if (listData is Map && listData['list'] != null) {
          list = listData['list'] as List;
        } else {
          list = [];
        }
        final newOrders = list
            .map((e) => OrderModel.fromJson(Map<String, dynamic>.from(e as Map)))
            .toList();

        _ordersByStatus[target] = refresh
            ? newOrders
            : [...current, ...newOrders];
        _hasMoreByStatus[target] = newOrders.length >= 20;
      }
    } on ApiException catch (e) {
      _error = e.message;
    } catch (e) {
      _error = 'Failed to load orders';
    } finally {
      _isLoadingByStatus[target] = false;
      notifyListeners();
    }
  }

  Future<List<OrderModel>?> createOrder({
    required int serviceId,
    required int countryId,
    int quantity = 1,
    int? pricePoints,
  }) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final response = await _apiService.createOrder(
        serviceId: serviceId,
        countryId: countryId,
        quantity: quantity,
        pricePoints: pricePoints,
      );

      if (response['data'] != null) {
        final data = response['data'];
        List<OrderModel> createdOrders = [];

        if (data is List) {
          createdOrders = data
              .map((e) =>
                  OrderModel.fromJson(Map<String, dynamic>.from(e as Map)))
              .toList();
        } else if (data is Map) {
          if (data['orders'] != null) {
            createdOrders = (data['orders'] as List)
                .map((e) =>
                    OrderModel.fromJson(Map<String, dynamic>.from(e as Map)))
                .toList();
          } else {
            createdOrders = [
              OrderModel.fromJson(Map<String, dynamic>.from(data))
            ];
          }
        }

        // 新订单进 pending tab
        final pendingList = List<OrderModel>.from(
            _ordersByStatus['pending'] ?? const []);
        pendingList.insertAll(0, createdOrders);
        _ordersByStatus['pending'] = pendingList;
        notifyListeners();
        return createdOrders;
      }
      return null;
    } on ApiException catch (e) {
      _error = e.message;
      notifyListeners();
      return null;
    } catch (e) {
      _error = 'Failed to create order';
      notifyListeners();
      return null;
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<bool> activateOrder(String orderId) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final response = await _apiService.activateOrder(orderId);

      if (response['data'] != null) {
        final updatedOrder =
            OrderModel.fromJson(Map<String, dynamic>.from(response['data'] as Map));
        _replaceOrderEverywhere(updatedOrder);
        if (_currentOrder?.id == orderId) {
          _currentOrder = updatedOrder;
        }
        notifyListeners();
        return true;
      }
      return false;
    } on ApiException catch (e) {
      _error = e.message;
      notifyListeners();
      return false;
    } catch (e) {
      _error = 'Failed to activate order';
      notifyListeners();
      return false;
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<bool> cancelOrder(String orderId) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final response = await _apiService.cancelOrder(orderId);

      if (response['success'] == true) {
        final now = DateTime.now().toIso8601String();
        for (final status in _ordersByStatus.keys.toList()) {
          final list = _ordersByStatus[status];
          if (list == null) continue;
          final idx = list.indexWhere((o) => o.id == orderId);
          if (idx != -1) {
            // B8: copyWith 一行解决
            list[idx] = list[idx].copyWith(
              status: 'cancelled',
              updatedAt: now,
            );
          }
        }
        if (_currentOrder?.id == orderId) {
          _currentOrder = _currentOrder!.copyWith(
            status: 'cancelled',
            updatedAt: now,
          );
        }
        notifyListeners();
        return true;
      }
      return false;
    } on ApiException catch (e) {
      _error = e.message;
      notifyListeners();
      return false;
    } catch (e) {
      _error = 'Failed to cancel order';
      notifyListeners();
      return false;
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<void> loadOrderDetail(String orderId) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final response = await _apiService.getOrderDetail(orderId);

      if (response['data'] != null) {
        _currentOrder =
            OrderModel.fromJson(Map<String, dynamic>.from(response['data'] as Map));
        _replaceOrderEverywhere(_currentOrder!);
      }
    } on ApiException catch (e) {
      _error = e.message;
    } catch (e) {
      _error = 'Failed to load order detail';
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<String?> getSmsCode(String orderId) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final response = await _apiService.getSmsCode(orderId);

      if (response['data'] != null) {
        final smsData = response['data'];
        String? code;

        if (smsData is List && smsData.isNotEmpty) {
          code = smsData[0]['code'] as String?;
          if (_currentOrder?.id == orderId && code != null) {
            // B8: copyWith 一行解决
            final updated = _currentOrder!.copyWith(
              smsCode: code,
              updatedAt: DateTime.now().toIso8601String(),
            );
            _currentOrder = updated;
            _replaceOrderEverywhere(updated);
          }
        } else if (smsData is Map) {
          code = smsData['code'] as String?;
        }

        notifyListeners();
        return code;
      }
      return null;
    } on ApiException catch (e) {
      _error = e.message;
      notifyListeners();
      return null;
    } catch (e) {
      _error = 'Failed to get SMS code';
      notifyListeners();
      return null;
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  void _replaceOrderEverywhere(OrderModel updated) {
    for (final list in _ordersByStatus.values) {
      final idx = list.indexWhere((o) => o.id == updated.id);
      if (idx != -1) list[idx] = updated;
    }
  }

  void clearError() {
    _error = null;
    notifyListeners();
  }

  void clearCurrentOrder() {
    _currentOrder = null;
    notifyListeners();
  }
}
