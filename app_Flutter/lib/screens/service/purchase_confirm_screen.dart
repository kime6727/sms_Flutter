import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:go_router/go_router.dart';
import '../../core/i18n/app_localizations.dart';
import '../../core/theme/app_theme.dart';
import '../../providers/service_provider.dart';
import '../../providers/order_provider.dart';
import '../../providers/auth_provider.dart';
import '../../widgets/dialogs/insufficient_credits_dialog.dart';

class PurchaseConfirmScreen extends StatefulWidget {
  final int serviceId;
  final int countryId;
  final String? serviceName;
  final String? countryName;
  const PurchaseConfirmScreen({
    super.key,
    required this.serviceId,
    required this.countryId,
    this.serviceName,
    this.countryName,
  });

  @override
  State<PurchaseConfirmScreen> createState() => _PurchaseConfirmScreenState();
}

class _PurchaseConfirmScreenState extends State<PurchaseConfirmScreen> {
  int _quantity = 1;
  Map<String, dynamic>? _priceInfo;
  bool _isLoading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _loadPrice();
  }

  Future<void> _loadPrice() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });
    try {
      final response = await context.read<ServiceProvider>().calculatePrice(
            serviceId: widget.serviceId,
            countryId: widget.countryId,
          );
      if (response['data'] != null) {
        setState(() {
          _priceInfo = response['data'];
        });
      } else {
        setState(() {
          _error = 'Failed to get price';
        });
      }
    } catch (e) {
      setState(() {
        _error = e.toString();
      });
    } finally {
      setState(() => _isLoading = false);
    }
  }

  Future<void> _handlePurchase() async {
    final loc = context.loc;
    final pricePoints = _priceInfo != null
        ? (_priceInfo!['price_points'] as int?) ?? 0
        : 0;
    final totalCost = pricePoints * _quantity;
    
    if (_priceInfo == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(context.loc.translate('price_load_failed')),
          backgroundColor: AppColors.error,
        ),
      );
      return;
    }

    final orderProvider = context.read<OrderProvider>();
    final order = await orderProvider.createOrder(
      serviceId: widget.serviceId,
      countryId: widget.countryId,
      quantity: _quantity,
      pricePoints: pricePoints,
    );

    if (order != null && order.isNotEmpty && mounted) {
      final orderId = order.first.id;
      await showDialog(
        context: context,
        barrierDismissible: false,
        builder: (context) => AlertDialog(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 64,
                height: 64,
                decoration: BoxDecoration(
                  color: AppColors.success.withOpacity(0.1),
                  shape: BoxShape.circle,
                ),
                child: const Icon(Icons.check_circle, size: 48, color: AppColors.success),
              ),
              const SizedBox(height: 16),
              Text(
                loc.translate('purchase_success'),
                style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold),
              ),
              const SizedBox(height: 8),
              Text(
                '${widget.serviceName ?? ''} ${widget.countryName ?? ''}',
                textAlign: TextAlign.center,
                style: TextStyle(
                  fontSize: 14,
                  color: Theme.of(context).colorScheme.onSurface.withOpacity(0.6),
                ),
              ),
              const SizedBox(height: 4),
              Text(
                '${loc.translate('quantity')}: $_quantity',
                style: TextStyle(
                  fontSize: 14,
                  color: Theme.of(context).colorScheme.onSurface.withOpacity(0.6),
                ),
              ),
            ],
          ),
          actions: [
            Row(
              children: [
                Expanded(
                  child: TextButton(
                    onPressed: () {
                      Navigator.pop(context);
                      context.go('/orders');
                    },
                    child: Text(loc.translate('view_orders')),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: ElevatedButton(
                    onPressed: () {
                      Navigator.pop(context);
                      context.push('/orders/$orderId/activate');
                    },
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppColors.primary,
                      foregroundColor: Colors.white,
                    ),
                    child: Text(loc.translate('activate_now')),
                  ),
                ),
              ],
            ),
          ],
          actionsPadding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
        ),
      );
    } else if (mounted) {
      final errorMsg = orderProvider.error ?? 'Purchase failed';
      final isInsufficient = errorMsg.toLowerCase().contains('balance') || 
                             errorMsg.toLowerCase().contains('credit') ||
                             errorMsg.toLowerCase().contains('积分') ||
                             errorMsg.toLowerCase().contains('余额');
      
      if (isInsufficient) {
        showInsufficientCreditsDialog(context, totalCost.toDouble());
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(errorMsg),
            backgroundColor: AppColors.error,
            action: SnackBarAction(
              label: loc.translate('try_again'),
              textColor: Colors.white,
              onPressed: _handlePurchase,
            ),
          ),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final loc = context.loc;
    final authProvider = context.watch<AuthProvider>();
    final pricePoints = _priceInfo != null
        ? (_priceInfo!['price_points'] as int?) ?? 0
        : 0;
    final totalCost = pricePoints * _quantity;
    final remainingBalance = authProvider.points - totalCost;
    final canPurchase = _priceInfo != null && remainingBalance >= 0 && !_isLoading;

    if (_error != null && !_isLoading) {
      return Scaffold(
        appBar: AppBar(
          title: Text(loc.translate('confirm_purchase')),
        ),
        body: Center(
          child: Padding(
            padding: const EdgeInsets.all(32),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Icon(
                  Icons.error_outline,
                  size: 80,
                  color: AppColors.error,
                ),
                const SizedBox(height: 20),
                Text(
                  loc.translate('price_load_failed'),
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    fontSize: 17,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(height: 10),
                Text(
                  _error!,
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontSize: 14,
                    color: Theme.of(context).colorScheme.onSurface.withOpacity(0.5),
                  ),
                ),
                const SizedBox(height: 28),
                ElevatedButton.icon(
                  onPressed: _loadPrice,
                  icon: const Icon(Icons.refresh, size: 18),
                  label: Text(loc.translate('retry')),
                ),
              ],
            ),
          ),
        ),
      );
    }

    return Scaffold(
      appBar: AppBar(
        title: Text(loc.translate('confirm_purchase')),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : SingleChildScrollView(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.all(16),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            loc.translate('order_detail'),
                            style: const TextStyle(
                              fontSize: 18,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                          const Divider(),
                          if (widget.serviceName != null || widget.countryName != null) ...[
                            _buildInfoRow(
                              loc.translate('service'),
                              widget.serviceName ?? '-',
                            ),
                            _buildInfoRow(
                              loc.translate('country'),
                              widget.countryName ?? '-',
                            ),
                            const Divider(),
                          ],
                          _buildInfoRow(
                            loc.translate('price'),
                            '$pricePoints ${loc.translate('credits')}',
                          ),
                          _buildInfoRow(
                            loc.translate('quantity'),
                            _quantity.toString(),
                          ),
                          _buildInfoRow(
                            loc.translate('total_cost'),
                            '$totalCost ${loc.translate('credits')}',
                            isTotal: true,
                          ),
                          _buildInfoRow(
                            loc.translate('remaining_balance'),
                            '$remainingBalance ${loc.translate('credits')}',
                            isBalance: true,
                          ),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: 24),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      IconButton(
                        onPressed: _quantity > 1
                            ? () => setState(() => _quantity--)
                            : null,
                        icon: const Icon(Icons.remove_circle_outline),
                        iconSize: 32,
                      ),
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 24,
                          vertical: 8,
                        ),
                        decoration: BoxDecoration(
                          border: Border.all(color: AppColors.primary),
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: Text(
                          _quantity.toString(),
                          style: const TextStyle(
                            fontSize: 24,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                      ),
                      IconButton(
                        onPressed: _quantity < 10
                            ? () => setState(() => _quantity++)
                            : null,
                        icon: const Icon(Icons.add_circle_outline),
                        iconSize: 32,
                      ),
                    ],
                  ),
                  if (_quantity == 1) ...[
                    const SizedBox(height: 8),
                    Text(
                      loc.translate('min_quantity_hint'),
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        fontSize: 12,
                        color: Theme.of(context).colorScheme.onSurface.withOpacity(0.4),
                      ),
                    ),
                  ],
                  const SizedBox(height: 32),
                  ElevatedButton(
                    onPressed: canPurchase
                        ? _handlePurchase
                        : (remainingBalance < 0
                            ? () => showInsufficientCreditsDialog(
                                  context,
                                  totalCost.toDouble(),
                                )
                            : null),
                    child: Text(
                      remainingBalance < 0
                          ? loc.translate('top_up')
                          : loc.translate('confirm_purchase'),
                    ),
                  ),
                  if (remainingBalance < 0) ...[
                    const SizedBox(height: 12),
                    Text(
                      loc.translate('insufficient_balance_hint'),
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        fontSize: 13,
                        color: AppColors.error.withOpacity(0.8),
                      ),
                    ),
                  ],
                ],
              ),
            ),
    );
  }

  Widget _buildInfoRow(String label, String value,
      {bool isTotal = false, bool isBalance = false}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            label,
            style: TextStyle(
              fontSize: isTotal || isBalance ? 16 : 14,
              fontWeight: isTotal || isBalance ? FontWeight.bold : null,
              color: isBalance
                  ? (int.tryParse(value.split(' ').first) ?? 0) < 0
                      ? AppColors.error
                      : AppColors.success
                  : null,
            ),
          ),
          Text(
            value,
            style: TextStyle(
              fontSize: isTotal || isBalance ? 16 : 14,
              fontWeight: isTotal || isBalance ? FontWeight.bold : null,
              color: isBalance
                  ? (int.tryParse(value.split(' ').first) ?? 0) < 0
                      ? AppColors.error
                      : AppColors.success
                  : null,
            ),
          ),
        ],
      ),
    );
  }
}
