import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../models/payment_package_model.dart';
import '../services/api_service.dart';
import '../core/i18n/app_localizations.dart';
import 'package:in_app_purchase/in_app_purchase.dart';
import '../services/apple_iap_service.dart';

class PaymentProvider extends ChangeNotifier {
  final ApiService _apiService;

  PaymentProvider(this._apiService) {
    AppleIAPService().onPurchaseUpdated = _handlePurchaseUpdated;
    AppleIAPService().onError = (error) {
      _isPurchasing = false;
      _error = error;
      notifyListeners();
    };
  }

  List<PaymentPackageModel> _packages = [];
  bool _isLoading = false;
  bool _isPurchasing = false;
  String? _error;
  bool _isFirstTopup = true;
  DateTime? _firstTopupDeadline;
  final Map<String, PaymentPackageModel> _packageById = {};

  VoidCallback? onPurchaseSuccess;
  Function(String)? onPurchaseError;

  List<PaymentPackageModel> get packages => _packages;
  bool get isLoading => _isLoading;
  bool get isPurchasing => _isPurchasing;
  String? get error => _error;
  bool get isFirstTopup => _isFirstTopup;
  DateTime? get firstTopupDeadline => _firstTopupDeadline;

  Map<String, PaymentPackageModel> get packageByIdMap => _packageById;

  Future<void> loadPackages() async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final response = await _apiService.getPaymentPackages();

      if (response['success'] == true) {
        final packagesData = response['data'] as List<dynamic>? ?? [];
        _packages = packagesData
            .map((json) => PaymentPackageModel.fromJson(json as Map<String, dynamic>))
            .toList();

        _packageById.clear();
        for (final pkg in _packages) {
          _packageById[pkg.id.toString()] = pkg;
        }

        final productIds = _packages.map((e) => e.productId).where((id) => id.isNotEmpty).toList();
        if (productIds.isNotEmpty) {
          await AppleIAPService().loadProducts(productIds);
        }

        // 以服务端 has_topup_history 为准
        final userProfile = await _apiService.getProfile();
        if (userProfile['success'] == true) {
          final userData = userProfile['data'] as Map<String, dynamic>?;
          if (userData != null) {
            _isFirstTopup = !(userData['has_topup_history'] as bool? ?? true);
            
            if (_isFirstTopup && userData['first_topup_countdown_hours'] != null) {
              final hoursLeft = userData['first_topup_countdown_hours'] as num;
              _firstTopupDeadline = DateTime.now().add(Duration(hours: hoursLeft.toInt()));
            } else {
              _isFirstTopup = false;
            }
            
            // 同步到本地存储
            final prefs = await SharedPreferences.getInstance();
            await prefs.setBool('has_topup_history', !_isFirstTopup);
          }
        }
      }
    } catch (e) {
      _error = e.toString();
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<Map<String, dynamic>?> purchasePackage({
    required PaymentPackageModel package,
    String? appleReceiptData,
    String? appleTransactionId,
  }) async {
    _isPurchasing = true;
    _error = null;
    notifyListeners();

    try {
      final userId = _apiService.userId;
      if (userId == null) {
        _error = 'User not logged in';
        return null;
      }

      Map<String, dynamic>? response;

      if (appleReceiptData != null && appleTransactionId != null) {
        response = await _apiService.verifyAppleReceipt(
          receiptData: appleReceiptData,
          transactionId: appleTransactionId,
          userId: userId,
          productId: package.productId,
        );
      } else {
        // 非 iOS 平台不支持 Apple IAP，请使用其他支付方式
        _error = 'Apple IAP is not available on this platform';
        notifyListeners();
        return null;
      }

      if (response != null && response['success'] == true) {
        final newBalance = response['new_balance'];
        if (newBalance != null) {
          final prefs = await SharedPreferences.getInstance();
          await prefs.setInt('balance', newBalance);
        }

        // 后端 /verify-receipt 实际返回 is_first_topup 字段（兼容旧 first_topup_bonus）
        final isFirstTopup = response['is_first_topup'] == true ||
            response['first_topup_bonus'] == true;
        if (isFirstTopup) {
          _isFirstTopup = false;
          await SharedPreferences.getInstance().then((prefs) async {
            await prefs.setBool('has_topup_history', true);
            await prefs.remove('first_topup_key');
          });
        }

        return response;
      } else {
        _error = response?['message'] as String? ?? 'Purchase failed';
        return null;
      }
    } catch (e) {
      _error = e.toString();
      return null;
    } finally {
      _isPurchasing = false;
      notifyListeners();
    }
  }

  Future<void> markFirstTopupComplete() async {
    _isFirstTopup = false;
    _firstTopupDeadline = null;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool('has_topup_history', true);
    await prefs.remove('first_topup_key');
    notifyListeners();
  }

  void _handlePurchaseUpdated(PurchaseDetails purchase) async {
    if (purchase.status == PurchaseStatus.purchased || purchase.status == PurchaseStatus.restored) {
      final receipt = AppleIAPService().getReceipt(purchase);
      final transactionId = AppleIAPService().getTransactionId(purchase);
      
      if (receipt != null && transactionId != null) {
        final package = _packages.cast<PaymentPackageModel?>().firstWhere(
            (p) => p?.productId == purchase.productID, orElse: () => null);
        
        if (package != null) {
          final res = await purchasePackage(
            package: package,
            appleReceiptData: receipt,
            appleTransactionId: transactionId,
          );
          if (res != null) {
            onPurchaseSuccess?.call();
          } else {
            onPurchaseError?.call(_error ?? 'Purchase failed');
          }
        } else {
          _isPurchasing = false;
          _error = 'Package not found for product: ${purchase.productID}';
          onPurchaseError?.call(_error!);
          notifyListeners();
        }
      } else {
        _isPurchasing = false;
        _error = 'Failed to get receipt data';
        onPurchaseError?.call(_error!);
        notifyListeners();
      }
    } else if (purchase.status == PurchaseStatus.error) {
      _isPurchasing = false;
      _error = purchase.error?.message ?? 'Purchase error';
      onPurchaseError?.call(_error!);
      notifyListeners();
    }
  }

  Future<void> startApplePurchase(PaymentPackageModel package) async {
    _isPurchasing = true;
    _error = null;
    notifyListeners();

    final isAvailable = await AppleIAPService().isAvailable();
    if (!isAvailable) {
      _isPurchasing = false;
      _error = 'In-App Purchase is not available';
      onPurchaseError?.call(_error!);
      notifyListeners();
      return;
    }

    final product = AppleIAPService().products.cast<ProductDetails?>().firstWhere(
        (p) => p?.id == package.productId, orElse: () => null);

    if (product == null) {
      _isPurchasing = false;
      _error = 'Product not found in App Store. Please check configuration.';
      onPurchaseError?.call(_error!);
      notifyListeners();
      return;
    }

    final success = await AppleIAPService().purchaseProduct(product);
    if (!success) {
      _isPurchasing = false;
      notifyListeners();
    }
  }
}
