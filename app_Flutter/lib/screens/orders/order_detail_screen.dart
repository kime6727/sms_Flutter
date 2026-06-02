import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:flutter/services.dart';
import 'dart:async';
import '../../core/i18n/app_localizations.dart';
import '../../core/theme/app_theme.dart';
import '../../providers/order_provider.dart';
import '../../models/order_model.dart';

class OrderDetailScreen extends StatefulWidget {
  final String orderId;
  const OrderDetailScreen({super.key, required this.orderId});

  @override
  State<OrderDetailScreen> createState() => _OrderDetailScreenState();
}

class _OrderDetailScreenState extends State<OrderDetailScreen> {
  Timer? _countdownTimer;
  Timer? _smsPollingTimer;
  Duration? _remainingTime;
  bool _isPolling = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<OrderProvider>().loadOrderDetail(widget.orderId);
      _startCountdown();
    });
  }

  @override
  void dispose() {
    _countdownTimer?.cancel();
    _smsPollingTimer?.cancel();
    super.dispose();
  }

  void _startSmsPolling() {
    _smsPollingTimer?.cancel();
    _isPolling = true;

    // 防重复标记
    bool smsReceived = false;
    // 记录轮询开始时间，用于自适应间隔
    final pollingStartTime = DateTime.now();

    // 使用递归定时器实现自适应轮询间隔
    void scheduleNextPoll() {
      if (smsReceived) return;

      final elapsed = DateTime.now().difference(pollingStartTime);
      // 前 30 秒每 5 秒轮询，之后每 10 秒轮询
      final nextDelay = elapsed.inSeconds < 30
          ? const Duration(seconds: 5)
          : const Duration(seconds: 10);

      _smsPollingTimer = Timer(nextDelay, () async {
        final order = context.read<OrderProvider>().currentOrder;
        if (order == null || !order.isActive || order.isCompleted || smsReceived) {
          _isPolling = false;
          return;
        }

        try {
          // 方式1：直接查询 HeroSMS（主动拉取，不依赖 webhook）
          final code = await context.read<OrderProvider>().getSmsCode(widget.orderId);

          if (code != null && code.isNotEmpty && mounted) {
            smsReceived = true;
            _isPolling = false;

            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(
                content: Text(context.loc.translate('sms_received')),
                backgroundColor: AppColors.success,
                action: SnackBarAction(
                  label: context.loc.translate('copy'),
                  textColor: Colors.white,
                  onPressed: () {
                    Clipboard.setData(ClipboardData(text: code));
                  },
                ),
              ),
            );
            return;
          }

          // 方式2：同时读取数据库（webhook 可能已更新）
          await context.read<OrderProvider>().loadOrderDetail(widget.orderId);
          final updatedOrder = context.read<OrderProvider>().currentOrder;

          if (updatedOrder != null && updatedOrder.smsCode != null && mounted) {
            smsReceived = true;
            _isPolling = false;

            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(
                content: Text(context.loc.translate('sms_received')),
                backgroundColor: AppColors.success,
                action: SnackBarAction(
                  label: context.loc.translate('copy'),
                  textColor: Colors.white,
                  onPressed: () {
                    if (updatedOrder.smsCode != null) {
                      Clipboard.setData(ClipboardData(text: updatedOrder.smsCode!));
                    }
                  },
                ),
              ),
            );
            return;
          }

          // 未收到短信，调度下一次轮询
          scheduleNextPoll();
        } catch (e) {
          // 轮询失败不中断，继续下一次尝试
          debugPrint('SMS polling error: $e');
          scheduleNextPoll();
        }
      });
    }

    // 启动第一次轮询
    scheduleNextPoll();
  }

  void _stopSmsPolling() {
    _smsPollingTimer?.cancel();
    _isPolling = false;
  }

  void _startCountdown() {
    _countdownTimer?.cancel();
    _countdownTimer = Timer.periodic(const Duration(seconds: 1), (timer) async {
      final order = context.read<OrderProvider>().currentOrder;
      if (order != null && order.isActive && order.expiresAt != null) {
        final expiresAt = DateTime.parse(order.expiresAt!);
        final now = DateTime.now();
        final remaining = expiresAt.difference(now);

        if (remaining.isNegative) {
          timer.cancel();
          await context.read<OrderProvider>().loadOrderDetail(widget.orderId);
          if (mounted) {
            final updatedOrder = context.read<OrderProvider>().currentOrder;
            if (updatedOrder != null && updatedOrder.isActive) {
              final updatedExpiresAt = DateTime.parse(updatedOrder.expiresAt!);
              final now2 = DateTime.now();
              if (updatedExpiresAt.isBefore(now2)) {
                setState(() {
                  _remainingTime = Duration.zero;
                });
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(
                    content: Text(context.loc.translate('order_expired')),
                    backgroundColor: AppColors.error,
                  ),
                );
              }
            } else {
              _startCountdown();
            }
          }
        } else {
          setState(() {
            _remainingTime = remaining;
          });
        }
      } else {
        timer.cancel();
      }
    });
  }

  Future<void> _handleActivate() async {
    final loc = context.loc;
    
    // 显示激活教程引导
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Row(
          children: [
            const Icon(Icons.info_outline, color: AppColors.primary),
            const SizedBox(width: 8),
            Text(loc.translate('activate_guide')),
          ],
        ),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _buildGuideStep('1', loc.translate('guide_step_1')),
            const SizedBox(height: 12),
            _buildGuideStep('2', loc.translate('guide_step_2')),
            const SizedBox(height: 12),
            _buildGuideStep('3', loc.translate('guide_step_3')),
            const SizedBox(height: 16),
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: AppColors.warning.withOpacity(0.1),
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: AppColors.warning.withOpacity(0.3)),
              ),
              child: Row(
                children: [
                  const Icon(Icons.access_time, color: AppColors.warning, size: 20),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      loc.translate('guide_timeout_warning'),
                      style: const TextStyle(
                        fontSize: 13,
                        color: AppColors.warning,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: Text(loc.translate('cancel')),
          ),
          ElevatedButton(
            onPressed: () => Navigator.pop(context, true),
            child: Text(loc.translate('understand_activate')),
          ),
        ],
      ),
    );

    if (confirmed == true && mounted) {
      final success = await context.read<OrderProvider>().activateOrder(widget.orderId);
      if (success && mounted) {
        _startCountdown();
        _startSmsPolling(); // 开始轮询短信
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(context.loc.translate('activate_success')),
            backgroundColor: AppColors.success,
          ),
        );
      }
    }
  }

  Widget _buildGuideStep(String number, String text) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: 24,
          height: 24,
          decoration: BoxDecoration(
            color: AppColors.primary,
            borderRadius: BorderRadius.circular(12),
          ),
          child: Center(
            child: Text(
              number,
              style: const TextStyle(
                color: Colors.white,
                fontSize: 12,
                fontWeight: FontWeight.bold,
              ),
            ),
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Text(
            text,
            style: const TextStyle(fontSize: 14, height: 1.5),
          ),
        ),
      ],
    );
  }

  Future<void> _handleCancel() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(context.loc.translate('confirm_cancel')),
        content: Text(context.loc.translate('confirm_cancel_message')),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: Text(context.loc.translate('cancel')),
          ),
          ElevatedButton(
            onPressed: () => Navigator.pop(context, true),
            child: Text(context.loc.translate('confirm')),
          ),
        ],
      ),
    );

    if (confirmed == true && mounted) {
      final success = await context.read<OrderProvider>().cancelOrder(widget.orderId);
      if (success && mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(context.loc.translate('cancel_success')),
            backgroundColor: AppColors.success,
          ),
        );
      }
    }
  }

  Future<void> _handleRefreshSms() async {
    await context.read<OrderProvider>().loadOrderDetail(widget.orderId);
    final order = context.read<OrderProvider>().currentOrder;
    if (order != null && order.smsCode != null && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('${context.loc.translate('sms_code')}: ${order.smsCode}'),
          backgroundColor: AppColors.success,
        ),
      );
    } else if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(context.loc.translate('waiting_sms')),
          backgroundColor: AppColors.info,
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final loc = context.loc;
    final orderProvider = context.watch<OrderProvider>();
    final order = orderProvider.currentOrder;

    if (order == null) {
      return Scaffold(
        appBar: AppBar(title: Text(loc.translate('order_detail'))),
        body: const Center(child: CircularProgressIndicator()),
      );
    }

    return Scaffold(
      appBar: AppBar(
        title: Text(loc.translate('order_detail')),
        actions: [
          if (order.isActive || order.isCompleted)
            IconButton(
              icon: const Icon(Icons.refresh),
              onPressed: _handleRefreshSms,
            ),
        ],
      ),
      body: SingleChildScrollView(
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
                    Row(
                      children: [
                        Container(
                          width: 56,
                          height: 56,
                          decoration: BoxDecoration(
                            color: AppColors.primary.withOpacity(0.1),
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: Center(
                            child: Text(
                              order.countryFlag ?? '',
                              style: const TextStyle(fontSize: 32),
                            ),
                          ),
                        ),
                        const SizedBox(width: 16),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                order.serviceDisplayName,
                                style: const TextStyle(
                                  fontSize: 18,
                                  fontWeight: FontWeight.bold,
                                ),
                              ),
                              Text(
                                order.countryDisplayName,
                                style: TextStyle(
                                  color: Theme.of(context).colorScheme.secondary,
                                ),
                              ),
                            ],
                          ),
                        ),
                        _StatusChip(status: order.status),
                      ],
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 16),
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      loc.translate('phone_number'),
                      style: const TextStyle(
                        fontSize: 14,
                        color: AppColors.secondary,
                      ),
                    ),
                    const SizedBox(height: 8),
                    if (order.phoneNumber != null)
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              order.phoneNumber!,
                              style: const TextStyle(
                                fontSize: 24,
                                fontWeight: FontWeight.bold,
                                letterSpacing: 1,
                              ),
                            ),
                          ),
                          IconButton(
                            icon: const Icon(Icons.copy),
                            onPressed: () {
                              Clipboard.setData(ClipboardData(text: order.phoneNumber!));
                              ScaffoldMessenger.of(context).showSnackBar(
                                SnackBar(content: Text(loc.translate('copied'))),
                              );
                            },
                          ),
                        ],
                      )
                    else
                      Text(
                        order.isPending
                            ? loc.translate('pending')
                            : '-',
                        style: const TextStyle(fontSize: 18),
                      ),
                  ],
                ),
              ),
            ),
            if (order.smsCode != null) ...[
              const SizedBox(height: 16),
              Card(
                color: AppColors.success.withOpacity(0.1),
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Row(
                            children: [
                              const Icon(Icons.message, color: AppColors.success),
                              const SizedBox(width: 8),
                              Text(
                                loc.translate('sms_received'),
                                style: const TextStyle(
                                  fontSize: 16,
                                  fontWeight: FontWeight.bold,
                                  color: AppColors.success,
                                ),
                              ),
                            ],
                          ),
                          TextButton.icon(
                            onPressed: () {
                              Clipboard.setData(ClipboardData(text: order.smsCode!));
                              ScaffoldMessenger.of(context).showSnackBar(
                                SnackBar(
                                  content: Text(loc.translate('copied')),
                                  backgroundColor: AppColors.success,
                                ),
                              );
                            },
                            icon: const Icon(Icons.copy, size: 18, color: AppColors.success),
                            label: Text(
                              loc.translate('copy'),
                              style: const TextStyle(color: AppColors.success),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 12),
                      Text(
                        order.smsCode!,
                        style: const TextStyle(
                          fontSize: 32,
                          fontWeight: FontWeight.bold,
                          letterSpacing: 4,
                        ),
                        textAlign: TextAlign.center,
                      ),
                    ],
                  ),
                ),
              ),
            ] else if (order.isActive) ...[
              const SizedBox(height: 16),
              Card(
                color: AppColors.info.withOpacity(0.1),
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          const Icon(Icons.hourglass_empty, color: AppColors.info),
                          const SizedBox(width: 8),
                          Text(
                            loc.translate('waiting_sms'),
                            style: const TextStyle(
                              fontSize: 16,
                              fontWeight: FontWeight.bold,
                              color: AppColors.info,
                            ),
                          ),
                        ],
                      ),
                      if (_remainingTime != null) ...[
                        const SizedBox(height: 16),
                        Container(
                          padding: const EdgeInsets.all(20),
                          decoration: BoxDecoration(
                            color: _remainingTime!.inMinutes < 5
                                ? AppColors.error.withOpacity(0.1)
                                : AppColors.warning.withOpacity(0.1),
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(
                              color: _remainingTime!.inMinutes < 5
                                  ? AppColors.error.withOpacity(0.3)
                                  : AppColors.warning.withOpacity(0.3),
                              width: 2,
                            ),
                          ),
                          child: Column(
                            children: [
                              Text(
                                loc.translate('remaining_time'),
                                style: TextStyle(
                                  fontSize: 14,
                                  color: _remainingTime!.inMinutes < 5
                                      ? AppColors.error
                                      : AppColors.warning,
                                ),
                              ),
                              const SizedBox(height: 8),
                              Text(
                                _formatDuration(_remainingTime!),
                                style: TextStyle(
                                  fontSize: 48,
                                  fontWeight: FontWeight.bold,
                                  color: _remainingTime!.inMinutes < 5
                                      ? AppColors.error
                                      : AppColors.warning,
                                  fontFeatures: const [FontFeature.tabularFigures()],
                                ),
                              ),
                              const SizedBox(height: 8),
                              Text(
                                loc.translate('timeout_warning'),
                                style: TextStyle(
                                  fontSize: 12,
                                  color: _remainingTime!.inMinutes < 5
                                      ? AppColors.error
                                      : AppColors.warning,
                                ),
                                textAlign: TextAlign.center,
                              ),
                            ],
                          ),
                        ),
                      ],
                      const SizedBox(height: 12),
                      if (_isPolling) ...[
                        Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            SizedBox(
                              width: 16,
                              height: 16,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                valueColor: AlwaysStoppedAnimation<Color>(AppColors.info),
                              ),
                            ),
                            const SizedBox(width: 8),
                            Text(
                              loc.translate('auto_checking_sms'),
                              style: const TextStyle(
                                fontSize: 14,
                                color: AppColors.info,
                                fontWeight: FontWeight.w500,
                              ),
                            ),
                          ],
                        ),
                      ] else ...[
                        const CircularProgressIndicator(),
                        const SizedBox(height: 12),
                        Text(
                          loc.translate('sms_checking'),
                          style: const TextStyle(
                            fontSize: 14,
                            color: AppColors.secondary,
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
              ),
            ],
            const SizedBox(height: 16),
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      loc.translate('order_detail'),
                      style: const TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const Divider(),
                    _buildInfoRow(loc.translate('price'), '${order.pricePoints} ${loc.translate('credits')}'),
                    _buildInfoRow(loc.translate('created_at'), order.createdAt),
                    if (order.activatedAt != null)
                      _buildInfoRow(loc.translate('activated_at'), order.activatedAt!),
                    if (order.expiresAt != null)
                      _buildInfoRow(loc.translate('expires_at'), order.expiresAt!),
                    if (order.completedAt != null)
                      _buildInfoRow(loc.translate('completed_at'), order.completedAt!),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 24),
            if (order.canActivate)
              SizedBox(
                width: double.infinity,
                height: 56,
                child: ElevatedButton.icon(
                  onPressed: _handleActivate,
                  icon: const Icon(Icons.play_arrow, size: 24),
                  label: Text(
                    loc.translate('activate'),
                    style: const TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
              ),
            if (order.canCancel) ...[
              const SizedBox(height: 12),
              SizedBox(
                width: double.infinity,
                height: 56,
                child: OutlinedButton.icon(
                  onPressed: _handleCancel,
                  icon: const Icon(Icons.cancel, size: 24),
                  label: Text(
                    loc.translate('cancel'),
                    style: const TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  style: OutlinedButton.styleFrom(
                    foregroundColor: AppColors.error,
                    side: const BorderSide(color: AppColors.error, width: 2),
                  ),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildInfoRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: const TextStyle(color: AppColors.secondary)),
          Text(value, style: const TextStyle(fontWeight: FontWeight.w500)),
        ],
      ),
    );
  }

  String _formatDuration(Duration duration) {
    final minutes = duration.inMinutes.remainder(60).toString().padLeft(2, '0');
    final seconds = duration.inSeconds.remainder(60).toString().padLeft(2, '0');
    return '$minutes:$seconds';
  }
}

class _StatusChip extends StatelessWidget {
  final String status;
  const _StatusChip({required this.status});

  @override
  Widget build(BuildContext context) {
    Color color;
    switch (status) {
      case 'pending':
        color = AppColors.warning;
        break;
      case 'active':
        color = AppColors.info;
        break;
      case 'completed':
        color = AppColors.success;
        break;
      case 'expired':
      case 'cancelled':
        color = AppColors.error;
        break;
      default:
        color = AppColors.secondary;
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
      decoration: BoxDecoration(
        color: color.withOpacity(0.1),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(
        _getStatusLabel(context, status),
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
