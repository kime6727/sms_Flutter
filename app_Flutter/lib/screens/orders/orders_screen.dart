import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import 'package:go_router/go_router.dart';
import '../../core/i18n/app_localizations.dart';
import '../../core/theme/app_theme.dart';
import '../../providers/order_provider.dart';
import '../../models/order_model.dart';
import '../../widgets/common_widgets.dart';
import '../../widgets/cms_image.dart';

class OrdersScreen extends StatefulWidget {
  const OrdersScreen({super.key});

  @override
  State<OrdersScreen> createState() => _OrdersScreenState();
}

class _OrdersScreenState extends State<OrdersScreen>
    with SingleTickerProviderStateMixin {
  late TabController _tabController;
  final Map<String, ScrollController> _scrollControllers = {};

  static const _statuses = ['pending', 'active', 'completed', 'expired'];

  ScrollController _getScrollController(String status) {
    return _scrollControllers.putIfAbsent(status, () {
      final controller = ScrollController();
      controller.addListener(() => _onScroll(status, controller));
      return controller;
    });
  }

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 4, vsync: this);
    _tabController.addListener(() {
      if (!_tabController.indexIsChanging) {
        _loadOrdersForTab(_statuses[_tabController.index]);
      }
    });
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _loadOrdersForTab('pending');
    });
  }

  @override
  void dispose() {
    _tabController.dispose();
    for (final controller in _scrollControllers.values) {
      controller.dispose();
    }
    super.dispose();
  }

  void _onScroll(String status, ScrollController controller) {
    if (controller.position.pixels >= controller.position.maxScrollExtent - 200) {
      _loadOrdersForTab(status);
    }
  }

  void _loadOrdersForTab(String status) {
    final orderProvider = context.read<OrderProvider>();
    if (!orderProvider.isLoadingFor(status)) {
      orderProvider.loadOrders(status: status);
    }
  }

  @override
  Widget build(BuildContext context) {
    final loc = context.loc;
    final orderProvider = context.watch<OrderProvider>();

    return Scaffold(
      appBar: AppBar(
        title: Text(loc.translate('orders')),
        bottom: TabBar(
          controller: _tabController,
          onTap: (index) => _loadOrdersForTab(_statuses[index]),
          tabs: [
            Tab(text: loc.translate('pending')),
            Tab(text: loc.translate('active')),
            Tab(text: loc.translate('completed')),
            Tab(text: loc.translate('expired')),
          ],
        ),
      ),
      body: TabBarView(
        controller: _tabController,
        children: List.generate(4, (index) {
          return _buildOrderList(orderProvider, _statuses[index]);
        }),
      ),
    );
  }

  Widget _buildOrderList(OrderProvider provider, String status) {
    final loc = context.loc;
    // B9: 每个 tab 读自己状态的订单，互不干扰
    final orders = provider.ordersByStatus(status);
    final isLoading = provider.isLoadingFor(status);
    final hasMore = provider.hasMoreFor(status);

    if (isLoading && orders.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }

    if (!isLoading && orders.isEmpty) {
      return _buildEmptyState(context, status);
    }

    return RefreshIndicator(
      onRefresh: () async => provider.loadOrders(refresh: true, status: status),
      child: ListView.builder(
        controller: _getScrollController(status),
        key: PageStorageKey('orders_$status'),
        padding: const EdgeInsets.all(16),
        itemCount: orders.length + (isLoading ? 1 : 0) + (!hasMore && orders.isNotEmpty ? 1 : 0),
        itemBuilder: (context, index) {
          if (index == orders.length && isLoading) {
            return const Center(
              child: Padding(
                padding: EdgeInsets.all(16),
                child: CircularProgressIndicator(),
              ),
            );
          }
          if (index == orders.length && !hasMore) {
            return Center(
              child: Padding(
                padding: const EdgeInsets.symmetric(vertical: 20),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(Icons.check_circle, size: 16, color: AppColors.success.withOpacity(0.6)),
                    const SizedBox(width: 6),
                    Text(
                      loc.translate('no_more_orders'),
                      style: TextStyle(
                        fontSize: 13,
                        color: Theme.of(context).colorScheme.onSurface.withOpacity(0.4),
                      ),
                    ),
                  ],
                ),
              ),
            );
          }
          return _OrderCard(order: orders[index]);
        },
      ),
    );
  }

  Widget _buildEmptyState(BuildContext context, String status) {
    final loc = context.loc;
    final subtitleMap = {
      'pending': loc.translate('no_pending_orders_subtitle'),
      'active': loc.translate('no_active_orders_subtitle'),
      'completed': loc.translate('no_completed_orders_subtitle'),
      'expired': loc.translate('no_expired_orders_subtitle'),
    };
    return EmptyWidget(
      icon: _getEmptyIcon(status),
      message: loc.translate('no_orders'),
      subtitle: subtitleMap[status] ?? loc.translate('no_orders_subtitle'),
      actionLabel: status == 'pending' ? loc.translate('go_purchase') : null,
      actionIcon: status == 'pending' ? Icons.shopping_bag : Icons.refresh,
      onAction: status == 'pending'
          ? () => context.go('/home')
          : () => _loadOrdersForTab(status),
    );
  }

  IconData _getEmptyIcon(String status) {
    switch (status) {
      case 'pending':
        return Icons.shopping_bag_outlined;
      case 'active':
        return Icons.hourglass_empty;
      case 'completed':
        return Icons.check_circle_outline;
      case 'expired':
        return Icons.access_time_outlined;
      default:
        return Icons.receipt_long_outlined;
    }
  }
}

class _OrderCard extends StatelessWidget {
  final OrderModel order;
  const _OrderCard({required this.order});

  Future<void> _activateOrder(BuildContext context) async {
    final loc = context.loc;
    final orderProvider = context.read<OrderProvider>();

    final success = await orderProvider.activateOrder(order.id);
    if (context.mounted) {
      if (success) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(loc.translate('activate_success')),
            backgroundColor: AppColors.success,
          ),
        );
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(orderProvider.error ?? loc.translate('error')),
            backgroundColor: AppColors.error,
          ),
        );
      }
    }
  }

  Future<void> _cancelOrder(BuildContext context) async {
    final loc = context.loc;
    final orderProvider = context.read<OrderProvider>();

    final confirm = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(loc.translate('confirm_cancel')),
        content: Text(loc.translate('confirm_cancel_message')),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: Text(loc.translate('cancel')),
          ),
          ElevatedButton(
            onPressed: () => Navigator.pop(context, true),
            child: Text(loc.translate('confirm')),
          ),
        ],
      ),
    );

    if (confirm == true && context.mounted) {
      final success = await orderProvider.cancelOrder(order.id);
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              success
                  ? loc.translate('cancel_success')
                  : orderProvider.error ?? loc.translate('error'),
            ),
            backgroundColor: success ? AppColors.success : AppColors.error,
          ),
        );
      }
    }
  }

  void _viewSmsCode(BuildContext context) {
    context.push('/orders/${order.id}');
  }

  Widget _buildActionButton(BuildContext context, String action, OrderModel order) {
    final loc = context.loc;
    return TextButton.icon(
      onPressed: action == 'activate' ? () => _activateOrder(context) : null,
      icon: const Icon(Icons.play_arrow, size: 16),
      label: Text(
        action == 'activate'
            ? loc.translate('activate')
            : loc.translate('view_details'),
        style: const TextStyle(fontSize: 13),
      ),
    );
  }

  Widget _buildSmsCodeButton(BuildContext context, OrderModel order) {
    return TextButton.icon(
      onPressed: () => _viewSmsCode(context),
      icon: const Icon(Icons.qr_code, size: 16),
      label: Text(
        context.loc.translate('sms_code'),
        style: const TextStyle(fontSize: 13),
      ),
    );
  }

  Widget _buildCancelButton(BuildContext context, OrderModel order) {
    return TextButton.icon(
      onPressed: () => _cancelOrder(context),
      icon: const Icon(Icons.cancel_outlined, size: 16),
      label: Text(
        context.loc.translate('cancel'),
        style: const TextStyle(fontSize: 13, color: AppColors.error),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    // 判断是否是接码失败（completed 但没有短信）
    final bool isSmsFailed = order.status == 'completed' && order.smsCode == null;
    final bool isBatch = order.batchId != null && order.batchId!.isNotEmpty;

    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: InkWell(
        onTap: () => context.push('/orders/${order.id}'),
        borderRadius: BorderRadius.circular(16),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (isBatch)
                Padding(
                  padding: const EdgeInsets.only(bottom: 8),
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                    decoration: BoxDecoration(
                      color: AppColors.primary.withOpacity(0.1),
                      borderRadius: BorderRadius.circular(6),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(Icons.inventory_2_outlined, size: 14, color: AppColors.primary),
                        const SizedBox(width: 4),
                        Text(
                          '批量订单 · ${order.batchId!.substring(0, 8)}',
                          style: const TextStyle(fontSize: 11, color: AppColors.primary, fontWeight: FontWeight.w600),
                        ),
                      ],
                    ),
                  ),
                ),
              Row(
                children: [
                  CmsImage(
                    kind: 'service',
                    heroId: order.serviceCode,
                    fallbackText: (order.serviceName ?? '?').isEmpty ? '?' : (order.serviceName ?? '?').characters.first.toUpperCase(),
                    width: 36,
                    height: 36,
                    borderRadius: BorderRadius.circular(10),
                    fit: BoxFit.contain,
                  ),
                  const SizedBox(width: 10),
                  CmsImage(
                    kind: 'country',
                    heroId: order.heroCountryId,
                    fallbackText: (order.countryCode ?? order.countryName ?? '?').isEmpty
                        ? '?'
                        : ((order.countryCode?.isNotEmpty == true)
                            ? order.countryCode!.toUpperCase()
                            : (order.countryName ?? '?').characters.first.toUpperCase()),
                    width: 48,
                    height: 48,
                    borderRadius: BorderRadius.circular(12),
                    fit: BoxFit.cover,
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          order.serviceName ?? '',
                          style: const TextStyle(
                            fontSize: 16,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          order.countryName ?? '',
                          style: TextStyle(
                            color: Theme.of(context).colorScheme.secondary,
                            fontSize: 14,
                          ),
                        ),
                        // 显示接码失败提示
                        if (isSmsFailed) ...[
                          const SizedBox(height: 4),
                          Text(
                            '接码失败（超时未收到验证码）',
                            style: TextStyle(
                              color: AppColors.error,
                              fontSize: 12,
                              fontWeight: FontWeight.w500,
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                  _StatusChip(status: order.status, isSmsFailed: isSmsFailed),
                ],
              ),
              // 操作按钮行
              Row(
                mainAxisAlignment: MainAxisAlignment.end,
                children: [
                  if (order.status == 'pending')
                    _buildActionButton(context, 'activate', order)
                  else if (order.status == 'completed' && !isSmsFailed)
                    _buildSmsCodeButton(context, order),
                  if (order.status == 'pending')
                    _buildCancelButton(context, order),
                ],
              ),
              if (order.phoneNumber != null) ...[
                const Divider(),
                Row(
                  children: [
                    const Icon(Icons.phone, size: 16),
                    const SizedBox(width: 8),
                    Text(
                      order.phoneNumber!,
                      style: const TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                    const Spacer(),
                    IconButton(
                      icon: const Icon(Icons.copy, size: 20),
                      onPressed: () {
                        Clipboard.setData(ClipboardData(text: order.phoneNumber!));
                        ScaffoldMessenger.of(context).showSnackBar(
                          SnackBar(
                            content: Text(context.loc.translate('copied')),
                            backgroundColor: AppColors.success,
                            duration: const Duration(seconds: 1),
                          ),
                        );
                      },
                    ),
                  ],
                ),
              ],
              const Divider(),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Row(
                    children: [
                      Image.asset(
                        'assets/icons/jifen.webp',
                        width: 14,
                        height: 14,
                      ),
                      const SizedBox(width: 4),
                      Text(
                        '${order.pricePoints}',
                        style: const TextStyle(
                          fontWeight: FontWeight.w600,
                          color: AppColors.primary,
                        ),
                      ),
                    ],
                  ),
                  Text(
                    order.createdAt,
                    style: TextStyle(
                      color: Theme.of(context).colorScheme.secondary,
                      fontSize: 12,
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _StatusChip extends StatelessWidget {
  final String status;
  final bool isSmsFailed;
  const _StatusChip({required this.status, this.isSmsFailed = false});

  @override
  Widget build(BuildContext context) {
    Color color;
    String label;
    
    // 特殊处理：completed 但接码失败
    if (status == 'completed' && isSmsFailed) {
      color = AppColors.error;
      label = '接码失败';
    } else {
      switch (status) {
        case 'pending':
          color = AppColors.warning;
          label = _getStatusLabel(context, status);
          break;
        case 'active':
          color = AppColors.info;
          label = _getStatusLabel(context, status);
          break;
        case 'completed':
          color = AppColors.success;
          label = _getStatusLabel(context, status);
          break;
        case 'expired':
        case 'cancelled':
          color = AppColors.error;
          label = _getStatusLabel(context, status);
          break;
        default:
          color = AppColors.secondary;
          label = status;
      }
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
      decoration: BoxDecoration(
        color: color.withOpacity(0.1),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: color,
          fontSize: 12,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }

  String _getStatusLabel(BuildContext context, String status) {
    final loc = context.loc;
    switch (status) {
      case 'pending':
        return loc.translate('pending');
      case 'active':
        return loc.translate('waiting_sms');
      case 'completed':
        return loc.translate('completed');
      case 'expired':
        return loc.translate('expired');
      case 'cancelled':
        return loc.translate('cancelled');
      default:
        return status;
    }
  }
}
