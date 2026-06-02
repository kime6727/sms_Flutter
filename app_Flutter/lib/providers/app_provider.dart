import 'package:flutter/material.dart';
import '../core/utils/storage_service.dart';

class AppProvider extends ChangeNotifier {
  ThemeMode _themeMode = ThemeMode.system;
  Locale? _locale;
  bool _isDark = false;

  ThemeMode get themeMode => _themeMode;
  bool get isDark => _isDark;
  Locale? get locale => _locale;

  AppProvider() {
    _loadSettings();
  }

  void _loadSettings() {
    final savedTheme = StorageService.getString('theme_mode');
    if (savedTheme == 'dark') {
      _themeMode = ThemeMode.dark;
      _isDark = true;
    } else if (savedTheme == 'light') {
      _themeMode = ThemeMode.light;
      _isDark = false;
    }

    final savedLocale = StorageService.getString('locale');
    if (savedLocale.isNotEmpty) {
      _locale = Locale(savedLocale);
    }
  }

  void toggleTheme() {
    _isDark = !_isDark;
    _themeMode = _isDark ? ThemeMode.dark : ThemeMode.light;
    StorageService.setString('theme_mode', _isDark ? 'dark' : 'light');
    notifyListeners();
  }

  void setThemeMode(ThemeMode mode) {
    _themeMode = mode;
    _isDark = mode == ThemeMode.dark;
    StorageService.setString('theme_mode', mode.toString().split('.').last);
    notifyListeners();
  }

  void setLocale(Locale? locale) {
    _locale = locale;
    if (locale != null) {
      StorageService.setString('locale', locale.languageCode);
    } else {
      StorageService.remove('locale');
    }
    notifyListeners();
  }

  static const List<Locale> supportedLocales = [
    Locale('en', 'US'),
    Locale('zh', 'CN'),
  ];

  String getLocaleName(Locale? locale) {
    if (locale == null) return 'System Default';
    switch (locale.languageCode) {
      case 'zh':
        return '中文';
      case 'en':
        return 'English';
      default:
        return locale.languageCode;
    }
  }
}
