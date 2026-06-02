import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../core/theme/app_theme.dart';
import '../../core/i18n/app_localizations.dart';
import '../../core/config/app_config.dart';

class AboutScreen extends StatelessWidget {
  const AboutScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final loc = context.loc;

    return Scaffold(
      appBar: AppBar(
        title: Text(loc.translate('about')),
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Card(
            child: Column(
              children: [
                ListTile(
                  leading: Container(
                    width: 48,
                    height: 48,
                    decoration: BoxDecoration(
                      color: AppColors.primary,
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: const Icon(
                      Icons.sim_card,
                      color: Colors.white,
                      size: 24,
                    ),
                  ),
                  title: const Text(
                    'Simu',
                    style: TextStyle(
                      fontSize: 20,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  subtitle: Text(loc.translate('app_subtitle')),
                ),
                const Divider(height: 1),
                ListTile(
                  leading: const Icon(Icons.info_outline),
                  title: Text(loc.translate('version')),
                  trailing: const Text('1.0.0'),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          Card(
            child: Column(
              children: [
                ListTile(
                  leading: const Icon(Icons.privacy_tip_outlined),
                  title: Text(loc.translate('privacy_policy')),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: () => _launchUrl(AppConfig.privacyPolicyUrl),
                ),
                const Divider(height: 1),
                ListTile(
                  leading: const Icon(Icons.description_outlined),
                  title: Text(loc.translate('terms_of_service')),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: () => _launchUrl(AppConfig.termsOfServiceUrl),
                ),
                const Divider(height: 1),
                ListTile(
                  leading: const Icon(Icons.payment_outlined),
                  title: Text(loc.translate('payment_policy')),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: () => _launchUrl(AppConfig.paymentPolicyUrl),
                ),
              ],
            ),
          ),
          const SizedBox(height: 32),
          Center(
            child: Text(
              '© 2026 cherish each co ltd. All rights reserved.',
              style: TextStyle(
                color: Theme.of(context).colorScheme.onSurface.withOpacity(0.4),
                fontSize: 12,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _launchUrl(String url) async {
    final uri = Uri.parse(url);
    if (await canLaunchUrl(uri)) {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    } else {
      debugPrint('Cannot launch $url');
    }
  }
}
