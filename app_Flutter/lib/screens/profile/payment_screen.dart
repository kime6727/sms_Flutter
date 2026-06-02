import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:go_router/go_router.dart';
import '../../core/theme/app_theme.dart';
import '../../core/i18n/app_localizations.dart';
import '../../providers/payment_provider.dart';
import '../../providers/auth_provider.dart';
import '../../models/payment_package_model.dart';
import '../../widgets/common_widgets.dart';
import '../../services/apple_iap_service.dart';

class PaymentScreen extends StatefulWidget {
  const PaymentScreen({super.key});

  @override
  State<PaymentScreen> createState() => _PaymentScreenState();
}

class _PaymentScreenState extends State<PaymentScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final paymentProvider = context.read<PaymentProvider>();
      paymentProvider.loadPackages();
      context.read<AuthProvider>().loadProfile();

      paymentProvider.onPurchaseSuccess = () {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(context.loc.translate('purchase_success')),
              backgroundColor: AppColors.success,
            ),
          );
          context.read<AuthProvider>().loadProfile();
        }
      };

      paymentProvider.onPurchaseError = (error) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(error),
              backgroundColor: AppColors.error,
            ),
          );
        }
      };
    });
  }

  @override
  Widget build(BuildContext context) {
    final loc = context.loc;
    final paymentProvider = context.watch<PaymentProvider>();
    final authProvider = context.watch<AuthProvider>();
    final user = authProvider.user;

    return Scaffold(
      appBar: AppBar(
        title: Text(loc.translate('top_up_packages')),
      ),
      body: paymentProvider.isLoading && paymentProvider.packages.isEmpty
          ? const Center(child: CircularProgressIndicator())
          : paymentProvider.packages.isEmpty
              ? EmptyWidget(
                  icon: Icons.storefront_outlined,
                  message: loc.translate('no_packages'),
                  subtitle: loc.translate('no_packages_subtitle'),
                  onAction: () => context.go('/home'),
                  actionIcon: Icons.shopping_bag,
                  actionLabel: loc.translate('go_purchase'),
                )
              : RefreshIndicator(
                  onRefresh: () async {
                    await paymentProvider.loadPackages();
                    await authProvider.loadProfile();
                  },
                  child: ListView(
                    padding: const EdgeInsets.all(16),
                    children: [
                      _buildBalanceHeader(context, user),
                      const SizedBox(height: 16),
                      if (user != null && user.hasFirstTopupBonus)
                        _buildFirstTopupBanner(context),
                      if (user != null && user.hasFirstTopupBonus)
                        const SizedBox(height: 16),
                      _buildSectionTitle(loc.translate('top_up_packages')),
                      const SizedBox(height: 12),
                      ...paymentProvider.packages.map(
                        (pkg) => Padding(
                          padding: const EdgeInsets.only(bottom: 12),
                          child: _buildPackageCard(context, pkg),
                        ),
                      ),
                      const SizedBox(height: 16),
                      _buildRestoreButton(context),
                    ],
                  ),
                ),
    );
  }

  Widget _buildBalanceHeader(BuildContext context, dynamic user) {
    final loc = context.loc;
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [AppColors.primary, Color(0xFF8B5CF6)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    loc.translate('balance'),
                    style: const TextStyle(
                      fontSize: 14,
                      color: Colors.white70,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      Image.asset(
                        'assets/icons/jifen.webp',
                        width: 24,
                        height: 24,
                      ),
                      const SizedBox(width: 8),
                      Text(
                        '${user?.points ?? 0}',
                        style: const TextStyle(
                          fontSize: 32,
                          fontWeight: FontWeight.bold,
                          color: Colors.white,
                        ),
                      ),
                    ],
                  ),
                ],
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Text(
                  loc.translate('top_up'),
                  style: const TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w600,
                    color: AppColors.primary,
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildFirstTopupBanner(BuildContext context) {
    final loc = context.loc;
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFFF59E0B), Color(0xFFEF4444)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFFF59E0B).withOpacity(0.3),
            blurRadius: 12,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: Colors.white.withOpacity(0.2),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: const Icon(Icons.card_giftcard, color: Colors.white, size: 28),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      loc.translate('first_topup_bonus'),
                      style: const TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.bold,
                        color: Colors.white,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      loc.translate('first_topup_double'),
                      style: const TextStyle(
                        fontSize: 14,
                        color: Colors.white,
                      ),
                    ),
                  ],
                ),
              ),
              const Icon(Icons.arrow_forward_ios, color: Colors.white, size: 16),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildSectionTitle(String title) {
    return Text(
      title,
      style: const TextStyle(
        fontSize: 18,
        fontWeight: FontWeight.bold,
      ),
    );
  }

  Widget _buildPackageCard(BuildContext context, PaymentPackageModel pkg) {
    final loc = context.loc;
    final paymentProvider = context.read<PaymentProvider>();

    return Container(
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.surface,
        borderRadius: BorderRadius.circular(16),
        border: pkg.isRecommended
            ? Border.all(color: AppColors.primary, width: 2)
            : null,
        boxShadow: [
          BoxShadow(
            color: pkg.isRecommended
                ? AppColors.primary.withOpacity(0.1)
                : Colors.black.withOpacity(0.05),
            blurRadius: pkg.isRecommended ? 12 : 8,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Stack(
        children: [
          Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            pkg.displayName,
                            style: const TextStyle(
                              fontSize: 20,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                          const SizedBox(height: 8),
                          Row(
                            children: [
                              Image.asset(
                                'assets/icons/jifen.webp',
                                width: 20,
                                height: 20,
                              ),
                              const SizedBox(width: 6),
                              Text(
                                '${pkg.points}',
                                style: const TextStyle(
                                  fontSize: 28,
                                  fontWeight: FontWeight.bold,
                                  color: AppColors.primary,
                                ),
                              ),
                              const SizedBox(width: 4),
                              Text(
                                loc.translate('credits'),
                                style: TextStyle(
                                  fontSize: 14,
                                  color: Theme.of(context).colorScheme.onSurface.withOpacity(0.6),
                                ),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        Text(
                          '\$${pkg.price.toStringAsFixed(2)}',
                          style: const TextStyle(
                            fontSize: 20,
                            fontWeight: FontWeight.bold,
                            color: Color(0xFF6366F1),
                          ),
                        ),
                        const SizedBox(height: 12),
                        SizedBox(
                          width: 100,
                          child: ElevatedButton(
                            onPressed: paymentProvider.isPurchasing
                                ? null
                                : () => _handlePurchase(context, pkg),
                            style: ElevatedButton.styleFrom(
                              padding: const EdgeInsets.symmetric(vertical: 10),
                            ),
                            child: paymentProvider.isPurchasing
                                ? const SizedBox(
                                    height: 18,
                                    width: 18,
                                    child: CircularProgressIndicator(
                                      strokeWidth: 2,
                                      color: Colors.white,
                                    ),
                                  )
                                : Text(loc.translate('purchase')),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
                if (pkg.description.isNotEmpty) ...[
                  const SizedBox(height: 12),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                    decoration: BoxDecoration(
                      color: AppColors.info.withOpacity(0.1),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Text(
                      pkg.description,
                      style: const TextStyle(
                        fontSize: 12,
                        color: AppColors.info,
                      ),
                    ),
                  ),
                ],
              ],
            ),
          ),
          if (pkg.isRecommended)
            Positioned(
              top: 0,
              right: 0,
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                decoration: const BoxDecoration(
                  color: AppColors.primary,
                  borderRadius: BorderRadius.only(
                    topRight: Radius.circular(14),
                    bottomLeft: Radius.circular(12),
                  ),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Icon(Icons.star, size: 12, color: Colors.white),
                    const SizedBox(width: 4),
                    Text(
                      loc.translate('recommended'),
                      style: const TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                        color: Colors.white,
                      ),
                    ),
                  ],
                ),
              ),
            ),
        ],
      ),
    );
  }

  Widget _buildRestoreButton(BuildContext context) {
    final loc = context.loc;
    return Center(
      child: TextButton(
        onPressed: () async {
          try {
            await context.read<PaymentProvider>().loadPackages();
            await context.read<AuthProvider>().loadProfile();
            await AppleIAPService().restorePurchases();
            if (mounted) {
              ScaffoldMessenger.of(context).showSnackBar(
                SnackBar(
                  content: Text(loc.translate('purchase_restored')),
                  backgroundColor: AppColors.success,
                ),
              );
            }
          } catch (e) {
            if (mounted) {
              ScaffoldMessenger.of(context).showSnackBar(
                SnackBar(
                  content: Text(loc.translate('restore_failed')),
                  backgroundColor: AppColors.error,
                ),
              );
            }
          }
        },
        child: Text(loc.translate('restore_purchases')),
      ),
    );
  }

  Future<void> _handlePurchase(BuildContext context, PaymentPackageModel pkg) async {
    final loc = context.loc;
    final paymentProvider = context.read<PaymentProvider>();

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(loc.translate('confirm_purchase')),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(pkg.displayName),
            const SizedBox(height: 12),
            Row(
              children: [
                Image.asset(
                  'assets/icons/jifen.webp',
                  width: 24,
                  height: 24,
                ),
                const SizedBox(width: 8),
                Text(
                  '${pkg.points}',
                  style: const TextStyle(
                    fontSize: 24,
                    fontWeight: FontWeight.bold,
                    color: AppColors.primary,
                  ),
                ),
                const SizedBox(width: 4),
                Text(
                  loc.translate('credits'),
                  style: TextStyle(
                    fontSize: 14,
                    color: Theme.of(context).colorScheme.onSurface.withOpacity(0.6),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Text(
              '\$${pkg.price.toStringAsFixed(2)}',
              style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w600),
            ),
            if (pkg.description.isNotEmpty) ...[
              const SizedBox(height: 12),
              Text(
                pkg.description,
                style: TextStyle(
                  fontSize: 14,
                  color: Theme.of(context).colorScheme.onSurface.withOpacity(0.6),
                ),
              ),
            ],
          ],
        ),
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

    if (confirmed == true && mounted) {
      await paymentProvider.startApplePurchase(pkg);
    }
  }
}
