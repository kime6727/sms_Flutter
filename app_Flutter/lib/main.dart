import 'dart:ui' as ui;
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'core/config/app_config.dart';
import 'core/theme/app_theme.dart';
import 'core/i18n/app_localizations.dart';
import 'providers/auth_provider.dart';
import 'providers/app_provider.dart';
import 'providers/order_provider.dart';
import 'providers/service_provider.dart';
import 'providers/payment_provider.dart';
import 'providers/notification_provider.dart';
import 'providers/banner_provider.dart';
import 'routes/app_router.dart';
import 'services/api_service.dart';
import 'core/utils/storage_service.dart';

import 'services/apple_iap_service.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  await SystemChrome.setPreferredOrientations([
    DeviceOrientation.portraitUp,
    DeviceOrientation.portraitDown,
  ]);

  SystemChrome.setSystemUIOverlayStyle(
    const SystemUiOverlayStyle(
      statusBarColor: Colors.transparent,
      statusBarIconBrightness: Brightness.dark,
    ),
  );

  final prefs = await SharedPreferences.getInstance();
  StorageService.init(prefs);

  final apiService = ApiService();
  await apiService.init();

  await AppleIAPService().init();

  runApp(MyApp(apiService: apiService));
}

class MyApp extends StatelessWidget {
  final ApiService apiService;

  const MyApp({super.key, required this.apiService});

  @override
  Widget build(BuildContext context) {
    return MultiProvider(
      providers: [
        ChangeNotifierProvider(create: (_) => AppProvider()),
        ChangeNotifierProvider(create: (_) => AuthProvider(apiService)),
        ChangeNotifierProvider(create: (_) => ServiceProvider(apiService)),
        ChangeNotifierProvider(create: (_) => OrderProvider(apiService)),
        ChangeNotifierProvider(create: (_) => PaymentProvider(apiService)),
        ChangeNotifierProvider(create: (_) => NotificationProvider(apiService)),
        ChangeNotifierProvider(create: (_) => BannerProvider(apiService)),
      ],
      child: Builder(
        builder: (context) {
          final appProvider = context.watch<AppProvider>();
          final authProvider = context.watch<AuthProvider>();
          return MaterialApp.router(
            title: 'Simu',
            debugShowCheckedModeBanner: false,
            theme: AppTheme.lightTheme,
            darkTheme: AppTheme.darkTheme,
            themeMode: appProvider.themeMode,
            localizationsDelegates: const [
              AppLocalizations.delegate,
              GlobalMaterialLocalizations.delegate,
              GlobalWidgetsLocalizations.delegate,
              GlobalCupertinoLocalizations.delegate,
            ],
            supportedLocales: const [
              Locale('en'),
              Locale('zh'),
            ],
            locale: appProvider.locale ?? _resolveSystemLocale(),
            localeResolutionCallback: (locale, supportedLocales) {
              if (locale == null) return const Locale('en');
              for (var supportedLocale in supportedLocales) {
                if (supportedLocale.languageCode == locale.languageCode) {
                  return supportedLocale;
                }
              }
              return const Locale('en');
            },
            routerConfig: AppRouter.router,
          );
        },
      ),
    );
  }

  Locale _resolveSystemLocale() {
    final systemLocale = ui.window.locale;
    final langCode = systemLocale.languageCode;
    if (langCode == 'zh') return const Locale('zh');
    return const Locale('en');
  }
}
