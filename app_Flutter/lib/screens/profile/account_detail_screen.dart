import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:go_router/go_router.dart';
import '../../core/i18n/app_localizations.dart';
import '../../core/theme/app_theme.dart';
import '../../providers/auth_provider.dart';

class AccountDetailScreen extends StatefulWidget {
  const AccountDetailScreen({super.key});

  @override
  State<AccountDetailScreen> createState() => _AccountDetailScreenState();
}

class _AccountDetailScreenState extends State<AccountDetailScreen> {
  void _showSetPasswordDialog(BuildContext context) {
    final loc = context.loc;
    final newPasswordController = TextEditingController();
    final confirmPasswordController = TextEditingController();
    bool obscureNew = true;
    bool obscureConfirm = true;

    showDialog(
      context: context,
      builder: (context) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: Text(loc.translate('set_password')),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  loc.translate('set_password_desc'),
                  style: TextStyle(
                    fontSize: 13,
                    color: Theme.of(context).colorScheme.onSurface.withOpacity(0.6),
                  ),
                ),
                const SizedBox(height: 16),
                TextField(
                  controller: newPasswordController,
                  decoration: InputDecoration(
                    labelText: loc.translate('new_password'),
                    border: const OutlineInputBorder(),
                    suffixIcon: IconButton(
                      icon: Icon(obscureNew ? Icons.visibility_off : Icons.visibility),
                      onPressed: () => setDialogState(() => obscureNew = !obscureNew),
                    ),
                  ),
                  obscureText: obscureNew,
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: confirmPasswordController,
                  decoration: InputDecoration(
                    labelText: loc.translate('confirm_password'),
                    border: const OutlineInputBorder(),
                    suffixIcon: IconButton(
                      icon: Icon(obscureConfirm ? Icons.visibility_off : Icons.visibility),
                      onPressed: () => setDialogState(() => obscureConfirm = !obscureConfirm),
                    ),
                  ),
                  obscureText: obscureConfirm,
                ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context),
              child: Text(loc.translate('cancel')),
            ),
            ElevatedButton(
              onPressed: () async {
                final newPwd = newPasswordController.text;
                final confirmPwd = confirmPasswordController.text;

                if (newPwd.isEmpty || confirmPwd.isEmpty) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(
                      content: Text(loc.translate('password_required')),
                      backgroundColor: AppColors.error,
                    ),
                  );
                  return;
                }

                if (newPwd.length < 6) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(
                      content: Text(loc.translate('password_too_short')),
                      backgroundColor: AppColors.error,
                    ),
                  );
                  return;
                }

                if (newPwd != confirmPwd) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(
                      content: Text(loc.translate('password_not_match')),
                      backgroundColor: AppColors.error,
                    ),
                  );
                  return;
                }

                Navigator.pop(context);

                final authProvider = context.read<AuthProvider>();
                final response = await authProvider.updateProfile(
                  setPassword: newPwd,
                );

                if (context.mounted) {
                  if (response['success'] == true) {
                    ScaffoldMessenger.of(context).showSnackBar(
                      SnackBar(
                        content: Text(loc.translate('password_set_success')),
                        backgroundColor: AppColors.success,
                      ),
                    );
                  } else {
                    ScaffoldMessenger.of(context).showSnackBar(
                      SnackBar(
                        content: Text(response['message'] ?? loc.translate('profile_update_failed')),
                        backgroundColor: AppColors.error,
                      ),
                    );
                  }
                }
              },
              child: Text(loc.translate('confirm')),
            ),
          ],
        ),
      ),
    );
  }

  void _showChangePasswordDialog(BuildContext context) {
    final loc = context.loc;
    final oldPasswordController = TextEditingController();
    final newPasswordController = TextEditingController();
    final confirmPasswordController = TextEditingController();
    bool obscureOld = true;
    bool obscureNew = true;
    bool obscureConfirm = true;

    showDialog(
      context: context,
      builder: (context) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: Text(loc.translate('change_password')),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                TextField(
                  controller: oldPasswordController,
                  decoration: InputDecoration(
                    labelText: loc.translate('old_password'),
                    border: const OutlineInputBorder(),
                    suffixIcon: IconButton(
                      icon: Icon(obscureOld ? Icons.visibility_off : Icons.visibility),
                      onPressed: () => setDialogState(() => obscureOld = !obscureOld),
                    ),
                  ),
                  obscureText: obscureOld,
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: newPasswordController,
                  decoration: InputDecoration(
                    labelText: loc.translate('new_password'),
                    border: const OutlineInputBorder(),
                    suffixIcon: IconButton(
                      icon: Icon(obscureNew ? Icons.visibility_off : Icons.visibility),
                      onPressed: () => setDialogState(() => obscureNew = !obscureNew),
                    ),
                  ),
                  obscureText: obscureNew,
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: confirmPasswordController,
                  decoration: InputDecoration(
                    labelText: loc.translate('confirm_password'),
                    border: const OutlineInputBorder(),
                    suffixIcon: IconButton(
                      icon: Icon(obscureConfirm ? Icons.visibility_off : Icons.visibility),
                      onPressed: () => setDialogState(() => obscureConfirm = !obscureConfirm),
                    ),
                  ),
                  obscureText: obscureConfirm,
                ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context),
              child: Text(loc.translate('cancel')),
            ),
            ElevatedButton(
              onPressed: () async {
                final oldPwd = oldPasswordController.text;
                final newPwd = newPasswordController.text;
                final confirmPwd = confirmPasswordController.text;

                if (oldPwd.isEmpty || newPwd.isEmpty || confirmPwd.isEmpty) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(
                      content: Text(loc.translate('password_required')),
                      backgroundColor: AppColors.error,
                    ),
                  );
                  return;
                }

                if (newPwd.length < 6) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(
                      content: Text(loc.translate('password_too_short')),
                      backgroundColor: AppColors.error,
                    ),
                  );
                  return;
                }

                if (newPwd != confirmPwd) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(
                      content: Text(loc.translate('password_not_match')),
                      backgroundColor: AppColors.error,
                    ),
                  );
                  return;
                }

                Navigator.pop(context);

                final authProvider = context.read<AuthProvider>();
                final response = await authProvider.updateProfile(
                  oldPassword: oldPwd,
                  newPassword: newPwd,
                );

                if (context.mounted) {
                  if (response['success'] == true) {
                    ScaffoldMessenger.of(context).showSnackBar(
                      SnackBar(
                        content: Text(loc.translate('password_changed_success')),
                        backgroundColor: AppColors.success,
                      ),
                    );
                  } else {
                    ScaffoldMessenger.of(context).showSnackBar(
                      SnackBar(
                        content: Text(response['message'] ?? loc.translate('profile_update_failed')),
                        backgroundColor: AppColors.error,
                      ),
                    );
                  }
                }
              },
              child: Text(loc.translate('confirm')),
            ),
          ],
        ),
      ),
    );
  }

  void _showEditNicknameDialog(BuildContext context) {
    final loc = context.loc;
    final authProvider = context.read<AuthProvider>();
    final controller = TextEditingController(text: authProvider.user?.username ?? '');

    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(loc.translate('edit_nickname')),
        content: TextField(
          controller: controller,
          decoration: InputDecoration(
            labelText: loc.translate('nickname'),
            border: const OutlineInputBorder(),
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: Text(loc.translate('cancel')),
          ),
          ElevatedButton(
            onPressed: () async {
              final nickname = controller.text.trim();
              if (nickname.isEmpty) {
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(
                    content: Text(loc.translate('username_required')),
                    backgroundColor: AppColors.error,
                  ),
                );
                return;
              }

              Navigator.pop(context);

              final response = await authProvider.updateProfile(nickname: nickname);

              if (context.mounted) {
                if (response['success'] == true) {
                  await authProvider.loadProfile();
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(
                      content: Text(loc.translate('profile_updated')),
                      backgroundColor: AppColors.success,
                    ),
                  );
                } else {
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(
                      content: Text(response['message'] ?? loc.translate('profile_update_failed')),
                      backgroundColor: AppColors.error,
                    ),
                  );
                }
              }
            },
            child: Text(loc.translate('save')),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final loc = context.loc;
    final authProvider = context.watch<AuthProvider>();
    final user = authProvider.user;

    if (user == null) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }

    return Scaffold(
      appBar: AppBar(
        title: Text(loc.translate('account_info')),
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
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
                              'ID: ${user.id}',
                              style: TextStyle(
                                fontSize: 14,
                                color: Theme.of(context).colorScheme.onSurface.withOpacity(0.5),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 16),
          Card(
            child: Column(
              children: [
                _buildInfoTile(
                  context,
                  icon: Icons.email_outlined,
                  label: loc.translate('email'),
                  value: user.email,
                ),
                const Divider(height: 1, indent: 16, endIndent: 16),
                _buildInfoTile(
                  context,
                  icon: Icons.person_outline,
                  label: loc.translate('nickname'),
                  value: user.username,
                  editable: true,
                ),
                const Divider(height: 1, indent: 16, endIndent: 16),
                _buildInfoTile(
                  context,
                  icon: Icons.fingerprint_outlined,
                  label: 'User ID',
                  value: user.id,
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          Card(
            child: Column(
              children: [
                ListTile(
                  leading: const Icon(Icons.lock_reset_outlined, color: AppColors.primary),
                  title: Text(loc.translate('set_password')),
                  subtitle: Text(loc.translate('set_password_desc'), style: TextStyle(fontSize: 12, color: Theme.of(context).colorScheme.onSurface.withOpacity(0.5))),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: () => _showSetPasswordDialog(context),
                ),
                const Divider(height: 1, indent: 16, endIndent: 16),
                ListTile(
                  leading: const Icon(Icons.lock_outline, color: AppColors.primary),
                  title: Text(loc.translate('change_password')),
                  subtitle: Text(loc.translate('change_password_requires_old'), style: TextStyle(fontSize: 12, color: Theme.of(context).colorScheme.onSurface.withOpacity(0.5))),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: () => _showChangePasswordDialog(context),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          Card(
            child: Column(
              children: [
                _buildInfoTile(
                  context,
                  icon: Icons.event_outlined,
                  label: loc.translate('created_at'),
                  value: user.createdAt,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildInfoTile(
    BuildContext context, {
    required IconData icon,
    required String label,
    required String value,
    bool editable = false,
  }) {
    return ListTile(
      leading: Icon(icon, color: AppColors.primary),
      title: Text(label),
      subtitle: Text(
        value,
        style: TextStyle(
          fontSize: 14,
          color: Theme.of(context).colorScheme.onSurface.withOpacity(0.7),
        ),
      ),
      trailing: editable
          ? IconButton(
              icon: const Icon(Icons.edit_outlined),
              onPressed: () => _showEditNicknameDialog(context),
            )
          : null,
    );
  }
}
