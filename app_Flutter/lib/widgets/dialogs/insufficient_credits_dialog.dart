import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:go_router/go_router.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/i18n/app_localizations.dart';
import '../../../providers/payment_provider.dart';
import '../../../models/payment_package_model.dart';

class InsufficientCreditsDialog extends StatefulWidget {
  final double requiredPoints;

  const InsufficientCreditsDialog({
    super.key,
    required this.requiredPoints,
  });

  @override
  State<InsufficientCreditsDialog> createState() => _InsufficientCreditsDialogState();
}

class _InsufficientCreditsDialogState extends State<InsufficientCreditsDialog> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<PaymentProvider>().loadPackages();
    });
  }

  @override
  Widget build(BuildContext context) {
    final loc = context.loc;
    final paymentProvider = context.watch<PaymentProvider>();
    final packages = paymentProvider.packages;

    return Dialog(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
      child: Container(
        padding: const EdgeInsets.all(20),
        constraints: const BoxConstraints(maxWidth: 400),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: AppColors.error.withOpacity(0.1),
                shape: BoxShape.circle,
              ),
              child: const Icon(
                Icons.account_balance_wallet_outlined,
                color: AppColors.error,
                size: 40,
              ),
            ),
            const SizedBox(height: 16),
            Text(
              loc.translate('insufficient_balance'),
              style: const TextStyle(
                fontSize: 20,
                fontWeight: FontWeight.bold,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              loc.translate('required_points').replaceAll('{points}', '${widget.requiredPoints.toInt()}'),
              style: TextStyle(
                fontSize: 14,
                color: Theme.of(context).colorScheme.onSurface.withOpacity(0.6),
              ),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 20),
            if (packages.isEmpty && paymentProvider.isLoading)
              const Center(child: CircularProgressIndicator())
            else if (packages.isNotEmpty)
              SizedBox(
                height: 120,
                child: ListView.builder(
                  scrollDirection: Axis.horizontal,
                  itemCount: packages.length > 3 ? 3 : packages.length,
                  itemBuilder: (context, index) {
                    final pkg = packages[index];
                    return GestureDetector(
                      onTap: () {
                        Navigator.pop(context);
                        context.push('/payment');
                      },
                      child: Container(
                        width: 110,
                        margin: EdgeInsets.only(
                          left: index == 0 ? 0 : 8,
                          right: index == (packages.length > 3 ? 2 : packages.length - 1) ? 0 : 8,
                        ),
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          gradient: pkg.isRecommended
                              ? const LinearGradient(
                                  colors: [AppColors.primary, Color(0xFF8B5CF6)],
                                  begin: Alignment.topLeft,
                                  end: Alignment.bottomRight,
                                )
                              : null,
                          color: pkg.isRecommended ? null : Theme.of(context).colorScheme.surfaceVariant,
                          borderRadius: BorderRadius.circular(12),
                          border: pkg.isRecommended ? null : Border.all(
                            color: Theme.of(context).colorScheme.outline.withOpacity(0.3),
                          ),
                        ),
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Text(
                              '${pkg.points}',
                              style: TextStyle(
                                fontSize: 20,
                                fontWeight: FontWeight.bold,
                                color: pkg.isRecommended ? Colors.white : AppColors.primary,
                              ),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              loc.translate('credits'),
                              style: TextStyle(
                                fontSize: 12,
                                color: pkg.isRecommended ? Colors.white70 : Theme.of(context).colorScheme.onSurface.withOpacity(0.6),
                              ),
                            ),
                            const SizedBox(height: 8),
                            Text(
                              '\$${pkg.price.toStringAsFixed(2)}',
                              style: TextStyle(
                                fontSize: 14,
                                fontWeight: FontWeight.w600,
                                color: pkg.isRecommended ? Colors.white : const Color(0xFF6366F1),
                              ),
                            ),
                            if (pkg.isRecommended) ...[
                              const SizedBox(height: 4),
                              Container(
                                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                decoration: BoxDecoration(
                                  color: Colors.white.withOpacity(0.2),
                                  borderRadius: BorderRadius.circular(4),
                                ),
                                child: Text(
                                  loc.translate('recommended'),
                                  style: const TextStyle(
                                    fontSize: 10,
                                    fontWeight: FontWeight.w600,
                                    color: Colors.white,
                                  ),
                                ),
                              ),
                            ],
                          ],
                        ),
                      ),
                    );
                  },
                ),
              ),
            const SizedBox(height: 20),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    onPressed: () => Navigator.pop(context),
                    style: OutlinedButton.styleFrom(
                      padding: const EdgeInsets.symmetric(vertical: 12),
                    ),
                    child: Text(loc.translate('cancel')),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: ElevatedButton.icon(
                    onPressed: () {
                      Navigator.pop(context);
                      context.push('/payment');
                    },
                    icon: const Icon(Icons.add, size: 18),
                    label: Text(loc.translate('top_up')),
                    style: ElevatedButton.styleFrom(
                      padding: const EdgeInsets.symmetric(vertical: 12),
                    ),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

void showInsufficientCreditsDialog(BuildContext context, double requiredPoints) {
  showDialog(
    context: context,
    builder: (context) => InsufficientCreditsDialog(requiredPoints: requiredPoints),
  );
}
