import 'dart:async';
import 'package:flutter/foundation.dart';
import 'package:in_app_purchase/in_app_purchase.dart';

class AppleIAPService {
  static final AppleIAPService _instance = AppleIAPService._internal();
  factory AppleIAPService() => _instance;
  AppleIAPService._internal();

  final InAppPurchase _inAppPurchase = InAppPurchase.instance;
  late StreamSubscription<List<PurchaseDetails>> _subscription;

  List<ProductDetails> _products = [];
  List<PurchaseDetails> _purchases = [];

  List<ProductDetails> get products => _products;
  List<PurchaseDetails> get purchases => _purchases;

  Function(PurchaseDetails)? onPurchaseUpdated;
  Function(String)? onError;

  Future<void> init() async {
    if (kIsWeb) return;

    _subscription = _inAppPurchase.purchaseStream.listen(
      (purchaseDetailsList) {
        _listenToPurchaseUpdated(purchaseDetailsList);
      },
      onDone: () {
        _subscription.cancel();
      },
      onError: (error) {
        onError?.call('Purchase stream error: $error');
      },
    );
  }

  Future<bool> isAvailable() async {
    if (kIsWeb) return false;
    return await _inAppPurchase.isAvailable();
  }

  Future<void> loadProducts(List<String> productIds) async {
    if (kIsWeb) return;

    final ProductDetailsResponse response =
        await _inAppPurchase.queryProductDetails(productIds.toSet());

    if (response.error != null) {
      onError?.call('Failed to load products: ${response.error}');
      return;
    }

    _products = response.productDetails;
  }

  Future<void> restorePurchases() async {
    if (kIsWeb) return;
    await _inAppPurchase.restorePurchases(
      applicationUserName: null,
    );
  }

  Future<bool> purchaseProduct(ProductDetails product) async {
    if (kIsWeb) return false;

    try {
      final PurchaseParam purchaseParam = PurchaseParam(
        productDetails: product,
      );
      await _inAppPurchase.buyConsumable(purchaseParam: purchaseParam);
      return true;
    } catch (e) {
      onError?.call('Purchase failed: $e');
      return false;
    }
  }

  void _listenToPurchaseUpdated(List<PurchaseDetails> purchaseDetailsList) {
    for (final purchaseDetails in purchaseDetailsList) {
      switch (purchaseDetails.status) {
        case PurchaseStatus.pending:
          onPurchaseUpdated?.call(purchaseDetails);
          break;
        case PurchaseStatus.purchased:
        case PurchaseStatus.restored:
          _purchases.add(purchaseDetails);
          onPurchaseUpdated?.call(purchaseDetails);
          if (purchaseDetails.pendingCompletePurchase) {
            _inAppPurchase.completePurchase(purchaseDetails);
          }
          break;
        case PurchaseStatus.error:
          onError?.call(purchaseDetails.error?.message ?? 'Unknown error');
          break;
        case PurchaseStatus.canceled:
          break;
      }
    }
  }

  String? getTransactionId(PurchaseDetails purchase) {
    try {
      final dynamic data = purchase.verificationData as dynamic;
      return data.transactionIdentifier ?? data.purchaseId;
    } catch (e) {
      debugPrint('getTransactionId error: $e');
    }
    return null;
  }

  String? getReceipt(PurchaseDetails purchase) {
    try {
      final dynamic data = purchase.verificationData as dynamic;
      return data.transactionReceipt ?? data.localVerificationData;
    } catch (e) {
      debugPrint('getReceipt error: $e');
    }
    return null;
  }

  void dispose() {
    _subscription.cancel();
  }
}
