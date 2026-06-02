import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:go_router/go_router.dart';
import '../../core/i18n/app_localizations.dart';
import '../../providers/app_provider.dart';
import '../../providers/auth_provider.dart';

class SettingsScreen extends StatelessWidget {
  const SettingsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final loc = context.loc;
    final appProvider = context.watch<AppProvider>();

    return Scaffold(
      appBar: AppBar(
        title: Text(loc.translate('settings')),
      ),
      body: ListView(
        children: [
          _buildSection(context, loc.translate('general'), [
            ListTile(
              leading: const Icon(Icons.language),
              title: Text(loc.translate('language')),
              trailing: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    appProvider.getLocaleName(appProvider.locale),
                    style: TextStyle(
                      color: Theme.of(context).colorScheme.onSurface.withOpacity(0.6),
                    ),
                  ),
                  const Icon(Icons.chevron_right),
                ],
              ),
              onTap: () => _showLanguageDialog(context, appProvider),
            ),
            SwitchListTile(
              secondary: const Icon(Icons.dark_mode),
              title: Text(loc.translate('dark_mode')),
              value: appProvider.isDark,
              onChanged: (value) => appProvider.toggleTheme(),
            ),
          ]),
          const Divider(height: 1),
          _buildSection(context, loc.translate('about'), [
            ListTile(
              leading: const Icon(Icons.info_outline),
              title: Text(loc.translate('about')),
              trailing: const Icon(Icons.chevron_right),
              onTap: () => context.push('/profile/about'),
            ),
            ListTile(
              leading: const Icon(Icons.help_outline),
              title: Text(loc.translate('faq')),
              trailing: const Icon(Icons.chevron_right),
              onTap: () => context.push('/profile/help'),
            ),
            ListTile(
              leading: const Icon(Icons.email_outlined),
              title: Text(loc.translate('contact_us')),
              trailing: const Icon(Icons.chevron_right),
              onTap: () => context.push('/profile/contact'),
            ),
          ]),
        ],
      ),
    );
  }

  Widget _buildSection(BuildContext context, String title, List<Widget> children) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
          child: Text(
            title,
            style: TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.w600,
              color: Theme.of(context).colorScheme.primary,
            ),
          ),
        ),
        Card(
          margin: const EdgeInsets.symmetric(horizontal: 16),
          child: Column(children: children),
        ),
      ],
    );
  }

  void _showLanguageDialog(BuildContext context, AppProvider appProvider) {
    final loc = context.loc;
    showDialog(
      context: context,
      builder: (context) => SimpleDialog(
        title: Text(loc.translate('language')),
        children: [
          _buildLanguageOption(context, appProvider, null, 'System Default'),
          _buildLanguageOption(context, appProvider, const Locale('zh'), '中文'),
          _buildLanguageOption(context, appProvider, const Locale('en'), 'English'),
        ],
      ),
    );
  }

  Widget _buildLanguageOption(
    BuildContext context,
    AppProvider appProvider,
    Locale? locale,
    String label,
  ) {
    final isSelected = appProvider.locale?.languageCode == locale?.languageCode;
    return SimpleDialogOption(
      onPressed: () {
        appProvider.setLocale(locale);
        context.pop();
      },
      child: Row(
        children: [
          if (isSelected)
            const Icon(Icons.check, color: Colors.green)
          else
            const SizedBox(width: 24),
          const SizedBox(width: 8),
          Text(label),
        ],
      ),
    );
  }
}
