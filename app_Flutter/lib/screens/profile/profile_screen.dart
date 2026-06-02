import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:go_router/go_router.dart';
import '../../core/theme/app_theme.dart';
import '../../core/config/app_config.dart';
import '../../core/i18n/app_localizations.dart';
import '../../providers/auth_provider.dart';
import '../../providers/app_provider.dart';
import '../../providers/notification_provider.dart';

class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key});

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  int _tapCount = 0;
  DateTime? _lastTapTime;

  void _onHiddenAreaTap() {
    final now = DateTime.now();
    
    // 如果距离上次点击超过2秒，重置计数
    if (_lastTapTime != null && now.difference(_lastTapTime!).inSeconds > 2) {
      _tapCount = 0;
    }
    
    _lastTapTime = now;
    _tapCount++;

    if (_tapCount >= 5) {
      _tapCount = 0;
      _lastTapTime = null;
      
      // 显示提示并跳转
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('🔧 开发者模式已激活'),
          duration: Duration(seconds: 1),
        ),
      );
      
      context.push('/profile/api-health-check');
    } else if (_tapCount >= 3) {
      // 给用户一些反馈
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('再点击 ${5 - _tapCount} 次...'),
          duration: const Duration(milliseconds: 500),
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final loc = context.loc;
    final authProvider = context.watch<AuthProvider>();
    final user = authProvider.user;

    return Scaffold(
      appBar: AppBar(
        title: Text(loc.translate('profile')),
        actions: [
          IconButton(
            icon: const Icon(Icons.settings_outlined),
            onPressed: () {
              context.push('/profile/settings');
            },
          ),
        ],
      ),
      body: user == null
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: () => authProvider.loadProfile(),
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  _buildHeader(context, user),
                  const SizedBox(height: 24),
                  _buildBalanceCard(context, user),
                  const SizedBox(height: 24),
                  _buildMembershipCard(context, user),
                  const SizedBox(height: 24),
                  _buildFeaturePromo(context),
                  const SizedBox(height: 24),
                  _buildLanguageSelector(context),
                  const SizedBox(height: 16),
                  _buildMenuList(context),
                  const SizedBox(height: 24),
                  _buildLogoutButton(context),
                  const SizedBox(height: 16),
                  // 隐藏入口：连续点击5次进入开发者模式
                  GestureDetector(
                    onTap: _onHiddenAreaTap,
                    child: Container(
                      height: 60,
                      color: Colors.transparent,
                      alignment: Alignment.center,
                      child: Text(
                        'v${AppConfig.appVersion}',
                        style: TextStyle(
                          fontSize: 11,
                          color: Theme.of(context).colorScheme.onSurface.withOpacity(0.3),
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),
                ],
              ),
            ),
    );
  }

  Widget _buildHeader(BuildContext context, dynamic user) {
    final loc = context.loc;
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Row(
          children: [
            CircleAvatar(
              radius: 32,
              backgroundColor: AppColors.primary.withOpacity(0.1),
              child: Text(
                user.username[0].toUpperCase(),
                style: const TextStyle(
                  fontSize: 28,
                  fontWeight: FontWeight.bold,
                  color: AppColors.primary,
                ),
              ),
            ),
            const SizedBox(width: 16),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    user.username,
                    style: const TextStyle(
                      fontSize: 20,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    user.email,
                    style: TextStyle(
                      fontSize: 14,
                      color: Theme.of(context).colorScheme.onSurface.withOpacity(0.6),
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    'ID: ${user.id}',
                    style: TextStyle(
                      fontSize: 12,
                      color: Theme.of(context).colorScheme.onSurface.withOpacity(0.4),
                    ),
                  ),
                ],
              ),
            ),
            IconButton(
              icon: const Icon(Icons.chevron_right),
              onPressed: () => context.push('/profile/account'),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildBalanceCard(BuildContext context, dynamic user) {
    final loc = context.loc;
    final isBalanceLow = user.points < 100;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  loc.translate('balance'),
                  style: TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w600,
                    color: Theme.of(context).colorScheme.onSurface.withOpacity(0.7),
                  ),
                ),
                if (user.hasFirstTopupBonus)
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                    decoration: BoxDecoration(
                      color: AppColors.warning.withOpacity(0.1),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const Icon(Icons.star, size: 14, color: AppColors.warning),
                        const SizedBox(width: 4),
                        Text(
                          loc.translate('first_topup_bonus'),
                          style: const TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.w600,
                            color: AppColors.warning,
                          ),
                        ),
                      ],
                    ),
                  ),
              ],
            ),
            const SizedBox(height: 12),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Row(
                  children: [
                    Image.asset(
                      'assets/icons/jifen.webp',
                      width: 32,
                      height: 32,
                    ),
                    const SizedBox(width: 8),
                    Text(
                      '${user.points}',
                      style: const TextStyle(
                        fontSize: 36,
                        fontWeight: FontWeight.bold,
                        color: AppColors.primary,
                      ),
                    ),
                  ],
                ),
                ElevatedButton.icon(
                  onPressed: () {
                    context.push('/payment');
                  },
                  icon: const Icon(Icons.add, size: 18),
                  label: Text(loc.translate('top_up')),
                  style: ElevatedButton.styleFrom(
                    padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
                  ),
                ),
              ],
            ),
            if (isBalanceLow) ...[
              const SizedBox(height: 12),
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: AppColors.warning.withOpacity(0.1),
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: AppColors.warning.withOpacity(0.3)),
                ),
                child: Row(
                  children: [
                    const Icon(Icons.info_outline, color: AppColors.warning, size: 20),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        loc.translate('balance_low_hint'),
                        style: const TextStyle(
                          fontSize: 13,
                          color: AppColors.warning,
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildMembershipCard(BuildContext context, dynamic user) {
    final loc = context.loc;
    final membership = user.membership;
    final nextLevel = user.nextLevel;
    final progress = user.progress;
    
    final levelColor = membership != null
        ? (membership.color != null
            ? Color(int.parse('0xFF${membership.color!.replaceAll('#', '')}'))
            : const Color(0xFFFBBF24))
        : AppColors.secondary;
    final levelLabel = user.membershipLabel;
    final progressValue = user.membershipProgress / 100;

    return GestureDetector(
      onTap: nextLevel != null
          ? () => _showMembershipDetails(context, user)
          : null,
      child: Card(
        child: Padding(
          padding: const EdgeInsets.all(20),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Row(
                    children: [
                      Icon(
                        membership != null ? Icons.workspace_premium : Icons.person_outline,
                        color: levelColor,
                        size: 24,
                      ),
                      const SizedBox(width: 8),
                      Text(
                        loc.translate('membership_level'),
                        style: const TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ],
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        colors: [
                          levelColor.withOpacity(0.2),
                          levelColor.withOpacity(0.1),
                        ],
                      ),
                      borderRadius: BorderRadius.circular(16),
                      border: Border.all(color: levelColor.withOpacity(0.3)),
                    ),
                    child: Text(
                      levelLabel,
                      style: TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                        color: levelColor,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              if (membership != null) ...[
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(
                      '${loc.translate('current_discount')}: ${((1 - membership.discount) * 100).toInt()}% OFF',
                      style: TextStyle(
                        fontSize: 13,
                        color: Theme.of(context).colorScheme.onSurface.withOpacity(0.6),
                      ),
                    ),
                    if (nextLevel != null)
                      Text(
                        '${loc.translate('upgrade_need')} ¥${nextLevel.minSpent - (progress?.current ?? 0)}',
                        style: TextStyle(
                          fontSize: 12,
                          color: levelColor,
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                  ],
                ),
                const SizedBox(height: 8),
              ],
              ClipRRect(
                borderRadius: BorderRadius.circular(4),
                child: LinearProgressIndicator(
                  value: nextLevel != null ? progressValue : 1.0,
                  backgroundColor: Theme.of(context).colorScheme.onSurface.withOpacity(0.1),
                  valueColor: AlwaysStoppedAnimation<Color>(levelColor),
                  minHeight: 8,
                ),
              ),
              const SizedBox(height: 8),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    nextLevel != null
                        ? '${loc.translate('spent')} ¥${(progress?.current ?? 0).toInt()}'
                        : loc.translate('max_level_reached'),
                    style: TextStyle(
                      fontSize: 12,
                      color: Theme.of(context).colorScheme.onSurface.withOpacity(0.5),
                    ),
                  ),
                  if (nextLevel != null)
                    Text(
                      '${loc.translate('next_level')}: ${nextLevel.levelCn}',
                      style: TextStyle(
                        fontSize: 12,
                        color: levelColor,
                        fontWeight: FontWeight.w500,
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

  void _showMembershipDetails(BuildContext context, dynamic user) {
    final loc = context.loc;
    final membership = user.membership;
    final nextLevel = user.nextLevel;
    final allLevels = user.allLevels;
    
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(loc.translate('membership_level')),
        content: SizedBox(
          width: double.maxFinite,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (membership != null) ...[
                Text(
                  '${loc.translate('current_level')}: ${membership.levelCn}',
                  style: const TextStyle(fontWeight: FontWeight.w600),
                ),
                const SizedBox(height: 4),
                Text('${loc.translate('discount')}: ${((1 - membership.discount) * 100).toInt()}% OFF'),
                const SizedBox(height: 4),
                Text('${loc.translate('total_spent')}: ¥${membership.minSpent}'),
                const SizedBox(height: 16),
              ],
              if (nextLevel != null) ...[
                Text(
                  '${loc.translate('next_level')}: ${nextLevel.levelCn}',
                  style: const TextStyle(fontWeight: FontWeight.w600),
                ),
                const SizedBox(height: 4),
                Text('${loc.translate('discount')}: ${((1 - nextLevel.discount) * 100).toInt()}% OFF'),
                const SizedBox(height: 4),
                Text('${loc.translate('need_spend')}: ¥${nextLevel.minSpent}'),
                const SizedBox(height: 16),
              ],
              if (allLevels != null) ...[
                Text(
                  loc.translate('level_list'),
                  style: const TextStyle(fontWeight: FontWeight.w600),
                ),
                const SizedBox(height: 8),
                ...allLevels.map((level) => Padding(
                  padding: const EdgeInsets.only(bottom: 4),
                  child: Text(
                    '${level.nameCn} - ${loc.translate('spend')}¥${level.minSpent} - ${((1 - level.discount) * 100).toInt()}% OFF',
                  ),
                )),
              ],
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: Text(loc.translate('close')),
          ),
        ],
      ),
    );
  }

  Widget _buildFeaturePromo(BuildContext context) {
    final loc = context.loc;
    final isChinese = loc.locale.languageCode == 'zh';

    final features = [
      {
        'icon': Icons.smartphone,
        'iconBg': const Color(0xFF4F46E5),
        'title': isChinese ? '全球号码' : 'Global Numbers',
        'desc': isChinese ? '100+国家，即时接码' : '100+ countries, instant SMS',
      },
      {
        'icon': Icons.flash_on,
        'iconBg': const Color(0xFFF59E0B),
        'title': isChinese ? '极速响应' : 'Fast Response',
        'desc': isChinese ? '验证码秒级到达' : 'SMS codes in seconds',
      },
      {
        'icon': Icons.shield,
        'iconBg': const Color(0xFF10B981),
        'title': isChinese ? '隐私保护' : 'Privacy Safe',
        'desc': isChinese ? '虚拟号码，安全可靠' : 'Virtual numbers, secure',
      },
      {
        'icon': Icons.attach_money,
        'iconBg': const Color(0xFFEF4444),
        'title': isChinese ? '超值优惠' : 'Great Value',
        'desc': isChinese ? '首充双倍积分' : 'Double credits on first top-up',
      },
    ];

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  loc.translate('feature_highlights'),
                  style: const TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                TextButton(
                  onPressed: () => context.go('/home'),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        loc.translate('start_now'),
                        style: const TextStyle(fontSize: 13),
                      ),
                      const Icon(Icons.arrow_forward, size: 14),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),
            SizedBox(
              height: 120,
              child: ListView.builder(
                scrollDirection: Axis.horizontal,
                itemCount: features.length,
                itemBuilder: (context, index) {
                  final feature = features[index];
                  return _buildFeatureCard(context, feature, isLast: index == features.length - 1);
                },
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildFeatureCard(BuildContext context, Map<String, dynamic> feature, {bool isLast = false}) {
    return Container(
      width: 140,
      margin: EdgeInsets.only(right: isLast ? 0 : 12),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [
            (feature['iconBg'] as Color).withOpacity(0.15),
            (feature['iconBg'] as Color).withOpacity(0.05),
          ],
        ),
        borderRadius: BorderRadius.circular(16),
      ),
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              color: feature['iconBg'] as Color,
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(
              feature['icon'] as IconData,
              color: Colors.white,
              size: 20,
            ),
          ),
          const Spacer(),
          Text(
            feature['title'] as String,
            style: const TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            feature['desc'] as String,
            style: TextStyle(
              fontSize: 11,
              color: Theme.of(context).colorScheme.onSurface.withOpacity(0.5),
              height: 1.3,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildLanguageSelector(BuildContext context) {
    final loc = context.loc;
    final appProvider = context.watch<AppProvider>();
    final currentLocale = appProvider.locale;

    return Card(
      child: ListTile(
        leading: const Icon(Icons.language, color: AppColors.primary),
        title: Text(loc.translate('language')),
        subtitle: Text(_getLocaleDisplayText(currentLocale, loc)),
        trailing: const Icon(Icons.chevron_right),
        onTap: () => _showLanguagePicker(context, appProvider),
      ),
    );
  }

  String _getLocaleDisplayText(Locale? locale, AppLocalizations loc) {
    if (locale == null) return loc.translate('follow_system');
    switch (locale.languageCode) {
      case 'zh':
        return '中文';
      case 'en':
        return 'English';
      default:
        return locale.languageCode;
    }
  }

  void _showLanguagePicker(BuildContext context, AppProvider appProvider) {
    final loc = context.loc;
    showDialog(
      context: context,
      builder: (context) => SimpleDialog(
        title: Text(loc.translate('language_select')),
        children: [
          SimpleDialogOption(
            onPressed: () {
              appProvider.setLocale(null);
              Navigator.pop(context);
            },
            child: Row(
              children: [
                if (appProvider.locale == null)
                  const Icon(Icons.check, color: AppColors.primary),
                const SizedBox(width: 8),
                Text(loc.translate('follow_system')),
              ],
            ),
          ),
          SimpleDialogOption(
            onPressed: () {
              appProvider.setLocale(const Locale('zh'));
              Navigator.pop(context);
            },
            child: Row(
              children: [
                if (appProvider.locale?.languageCode == 'zh')
                  const Icon(Icons.check, color: AppColors.primary),
                const SizedBox(width: 8),
                const Text('中文'),
              ],
            ),
          ),
          SimpleDialogOption(
            onPressed: () {
              appProvider.setLocale(const Locale('en'));
              Navigator.pop(context);
            },
            child: Row(
              children: [
                if (appProvider.locale?.languageCode == 'en')
                  const Icon(Icons.check, color: AppColors.primary),
                const SizedBox(width: 8),
                const Text('English'),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildMenuList(BuildContext context) {
    final loc = context.loc;
    final menuItems = [
      {
        'icon': Icons.receipt_long_outlined,
        'title': loc.translate('orders'),
        'route': '/orders',
      },
      {
        'icon': Icons.history,
        'title': loc.translate('transaction_history'),
        'route': '/profile/transactions',
      },
      {
        'icon': Icons.notifications_outlined,
        'title': loc.translate('notifications'),
        'route': '/profile/notifications',
      },
      {
        'icon': Icons.help_outline,
        'title': loc.translate('faq'),
        'route': '/profile/help',
      },
      {
        'icon': Icons.contact_support_outlined,
        'title': loc.translate('contact_us'),
        'route': '/profile/contact',
      },
      {
        'icon': Icons.info_outline,
        'title': loc.translate('about'),
        'route': '/profile/about',
      },
    ];

    return Card(
      child: Column(
        children: menuItems.asMap().entries.map((entry) {
          final index = entry.key;
          final item = entry.value;
          return Column(
            children: [
              if (index > 0)
                Divider(
                  height: 1,
                  indent: 16,
                  endIndent: 16,
                  color: Theme.of(context).dividerTheme.color,
                ),
              ListTile(
                leading: Icon(
                  item['icon'] as IconData,
                  color: AppColors.primary,
                ),
                title: Text(item['title'] as String),
                trailing: item['route'] == '/profile/notifications'
                    ? Consumer<NotificationProvider>(
                        builder: (context, notificationProvider, _) {
                          final unreadCount = notificationProvider.unreadCount;
                          return Stack(
                            children: [
                              const Icon(Icons.chevron_right),
                              if (unreadCount > 0)
                                Positioned(
                                  right: 0,
                                  top: 0,
                                  child: Container(
                                    padding: const EdgeInsets.all(2),
                                    decoration: const BoxDecoration(
                                      color: AppColors.error,
                                      shape: BoxShape.circle,
                                    ),
                                    constraints: const BoxConstraints(
                                      minWidth: 16,
                                      minHeight: 16,
                                    ),
                                    child: Text(
                                      unreadCount > 99 ? '99+' : '$unreadCount',
                                      style: const TextStyle(
                                        color: Colors.white,
                                        fontSize: 10,
                                        fontWeight: FontWeight.bold,
                                      ),
                                      textAlign: TextAlign.center,
                                    ),
                                  ),
                                ),
                            ],
                          );
                        },
                      )
                    : const Icon(Icons.chevron_right),
                onTap: () {
                  if (item['route'] != null) {
                    context.push(item['route'] as String);
                  }
                },
              ),
            ],
          );
        }).toList(),
      ),
    );
  }

  Widget _buildLogoutButton(BuildContext context) {
    final loc = context.loc;
    return Center(
      child: TextButton.icon(
        onPressed: () async {
          final confirmed = await showDialog<bool>(
            context: context,
            builder: (context) => AlertDialog(
              title: Text(loc.translate('logout')),
              content: Text('${loc.translate('confirm')}?'),
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

          if (confirmed == true && context.mounted) {
            await context.read<AuthProvider>().logout();
            if (context.mounted) {
              context.go('/login');
            }
          }
        },
        icon: const Icon(Icons.logout, color: AppColors.error),
        label: Text(
          loc.translate('logout'),
          style: const TextStyle(color: AppColors.error),
        ),
      ),
    );
  }
}
