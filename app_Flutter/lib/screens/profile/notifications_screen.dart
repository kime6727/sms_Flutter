import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/theme/app_theme.dart';
import '../../core/i18n/app_localizations.dart';
import '../../providers/notification_provider.dart';
import '../../widgets/common_widgets.dart';

class NotificationsScreen extends StatefulWidget {
  const NotificationsScreen({super.key});

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<NotificationProvider>().loadNotifications(refresh: true);
    });
  }

  @override
  Widget build(BuildContext context) {
    final loc = context.loc;
    final notificationProvider = context.watch<NotificationProvider>();

    return Scaffold(
      appBar: AppBar(
        title: Text(loc.translate('notifications')),
        actions: [
          if (notificationProvider.notifications.isNotEmpty)
            TextButton(
              onPressed: () {
                context.read<NotificationProvider>().markAllAsRead();
              },
              child: Text(
                loc.translate('mark_all_read'),
                style: const TextStyle(fontSize: 14),
              ),
            ),
        ],
      ),
      body: notificationProvider.isLoading && notificationProvider.notifications.isEmpty
          ? const Center(child: CircularProgressIndicator())
          : notificationProvider.notifications.isEmpty
              ? _buildEmptyState(context)
              : RefreshIndicator(
                  onRefresh: () => notificationProvider.loadNotifications(refresh: true),
                  child: ListView.separated(
                    padding: const EdgeInsets.all(16),
                    itemCount: notificationProvider.notifications.length,
                    separatorBuilder: (context, index) => const SizedBox(height: 8),
                    itemBuilder: (context, index) {
                      final notification = notificationProvider.notifications[index];
                      return _buildNotificationCard(context, notification);
                    },
                  ),
                ),
    );
  }

  Widget _buildNotificationCard(BuildContext context, dynamic notification) {
    final loc = context.loc;
    final readValue = notification['read'] ?? notification['is_read'];
    final isRead = readValue == true || readValue == 1;

    return Card(
      child: ListTile(
        leading: Container(
          width: 40,
          height: 40,
          decoration: BoxDecoration(
            color: _getNotificationColor(notification['type']).withOpacity(0.1),
            borderRadius: BorderRadius.circular(10),
          ),
          child: Stack(
            children: [
              Icon(
                _getNotificationIcon(notification['type']),
                color: _getNotificationColor(notification['type']),
                size: 20,
              ),
              if (!isRead)
                Positioned(
                  right: 0,
                  top: 0,
                  child: Container(
                    width: 8,
                    height: 8,
                    decoration: BoxDecoration(
                      color: AppColors.error,
                      shape: BoxShape.circle,
                    ),
                  ),
                ),
            ],
          ),
        ),
        title: Text(
          notification['title'] ?? '',
          style: TextStyle(
            fontWeight: isRead ? FontWeight.normal : FontWeight.w600,
          ),
        ),
        subtitle: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const SizedBox(height: 4),
            Text(notification['content'] ?? ''),
            const SizedBox(height: 4),
            Text(
              _formatTime(notification['created_at']),
              style: TextStyle(
                fontSize: 12,
                color: Theme.of(context).colorScheme.onSurface.withOpacity(0.4),
              ),
            ),
          ],
        ),
        isThreeLine: true,
        onTap: () {
          if (!isRead) {
            context.read<NotificationProvider>().markAsRead(notification['id']);
          }
        },
      ),
    );
  }

  Widget _buildEmptyState(BuildContext context) {
    final loc = context.loc;
    return EmptyWidget(
      icon: Icons.notifications_none,
      message: loc.translate('no_notifications'),
      subtitle: loc.translate('no_notifications_subtitle'),
      actionLabel: loc.translate('subscribe_notifications'),
      actionIcon: Icons.notifications_active,
      onAction: () => context.read<NotificationProvider>().loadNotifications(refresh: true),
    );
  }

  Color _getNotificationColor(String? type) {
    switch (type) {
      case 'sms':
        return AppColors.success;
      case 'order':
        return AppColors.info;
      case 'payment':
        return AppColors.warning;
      case 'system':
        return AppColors.secondary;
      default:
        return AppColors.primary;
    }
  }

  IconData _getNotificationIcon(String? type) {
    switch (type) {
      case 'sms':
        return Icons.sms;
      case 'order':
        return Icons.receipt_long;
      case 'payment':
        return Icons.payment;
      case 'system':
        return Icons.info;
      default:
        return Icons.notifications;
    }
  }

  String _formatTime(String? time) {
    if (time == null) return '';
    final loc = context.loc;
    try {
      final dateTime = DateTime.parse(time);
      final now = DateTime.now();
      final difference = now.difference(dateTime);

      if (difference.inMinutes < 1) {
        return loc.translate('just_now');
      } else if (difference.inMinutes < 60) {
        return '${difference.inMinutes} ${loc.translate('minutes_ago')}';
      } else if (difference.inHours < 24) {
        return '${difference.inHours} ${loc.translate('hours_ago')}';
      } else if (difference.inDays < 7) {
        return '${difference.inDays} ${loc.translate('days_ago')}';
      } else {
        return '${dateTime.month}/${dateTime.day}';
      }
    } catch (e) {
      return '';
    }
  }
}
