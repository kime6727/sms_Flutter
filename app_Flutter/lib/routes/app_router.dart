import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../core/utils/auth_change_notifier.dart';
import '../providers/auth_provider.dart';
import '../screens/auth/login_screen.dart';
import '../screens/auth/register_screen.dart';
import '../screens/auth/forgot_password_screen.dart';
import '../screens/home/home_screen.dart';
import '../screens/orders/orders_screen.dart';
import '../screens/orders/order_detail_screen.dart';
import '../screens/profile/profile_screen.dart';
import '../screens/profile/settings_screen.dart';
import '../screens/profile/edit_profile_screen.dart';
import '../screens/profile/account_detail_screen.dart';
import '../screens/profile/payment_screen.dart';
import '../screens/profile/notifications_screen.dart';
import '../screens/profile/help_screen.dart';
import '../screens/profile/contact_us_screen.dart';
import '../screens/profile/about_screen.dart';
import '../screens/profile/transaction_history_screen.dart';
import '../screens/profile/api_health_check_screen.dart';
import '../screens/onboarding/splash_screen.dart';
import '../screens/onboarding/onboarding_screen.dart';
import '../screens/service/service_countries_screen.dart';
import '../screens/service/purchase_confirm_screen.dart';
import '../widgets/main_shell.dart';

// 旧的 AuthChangeNotifier 已抽到 core/utils/auth_change_notifier.dart

class AppRouter {
  static final GoRouter router = GoRouter(
    initialLocation: '/splash',
    redirect: (context, state) {
      // 使用 read 而不是 watch 避免无限循环
      final authProvider = context.read<AuthProvider>();
      final isAuthenticated = authProvider.isAuthenticated;
      final location = state.matchedLocation;

      final splashRoutes = ['/splash'];
      final onboardingRoutes = ['/onboarding'];
      final authRoutes = ['/login', '/register', '/api-health-check'];

      // 允许访问启动页和引导页
      if (splashRoutes.contains(location) || onboardingRoutes.contains(location)) {
        return null;
      }

      // 未登录用户访问受保护页面，重定向到注册页（新用户优先）
      if (!isAuthenticated && !authRoutes.contains(location)) {
        return '/register';
      }

      // 已登录用户访问登录/注册页，重定向到首页
      if (isAuthenticated && authRoutes.contains(location)) {
        return '/home';
      }

      return null;
    },
    refreshListenable: AuthChangeNotifier.instance,
    routes: [
      GoRoute(
        path: '/splash',
        builder: (context, state) => const SplashScreen(),
      ),
      GoRoute(
        path: '/onboarding',
        builder: (context, state) => const OnboardingScreen(),
      ),
      GoRoute(
        path: '/login',
        builder: (context, state) => const LoginScreen(),
        routes: [
          GoRoute(
            path: 'forgot-password',
            builder: (context, state) => const ForgotPasswordScreen(),
          ),
        ],
      ),
      GoRoute(
        path: '/register',
        builder: (context, state) => const RegisterScreen(),
      ),
      GoRoute(
        path: '/api-health-check',
        builder: (context, state) => const ApiHealthCheckScreen(),
      ),
      ShellRoute(
        builder: (context, state, child) => MainShell(child: child),
        routes: [
          GoRoute(
            path: '/home',
            builder: (context, state) => const HomeScreen(),
            routes: [
              GoRoute(
                path: 'service/:serviceId/countries',
                builder: (context, state) {
                  final serviceId = int.parse(state.pathParameters['serviceId']!);
                  return ServiceCountriesScreen(serviceId: serviceId);
                },
                routes: [
                  GoRoute(
                    path: 'purchase',
                    builder: (context, state) {
                      final serviceId = int.parse(state.pathParameters['serviceId']!);
                      final countryId = int.parse(state.uri.queryParameters['country_id']!);
                      final serviceName = state.uri.queryParameters['service_name'];
                      final countryName = state.uri.queryParameters['country_name'];
                      return PurchaseConfirmScreen(
                        serviceId: serviceId,
                        countryId: countryId,
                        serviceName: serviceName,
                        countryName: countryName,
                      );
                    },
                  ),
                ],
              ),
            ],
          ),
          GoRoute(
            path: '/orders',
            builder: (context, state) => const OrdersScreen(),
            routes: [
              GoRoute(
                path: ':orderId',
                builder: (context, state) {
                  final orderId = state.pathParameters['orderId']!;
                  return OrderDetailScreen(orderId: orderId);
                },
              ),
              GoRoute(
                path: ':orderId/activate',
                builder: (context, state) {
                  final orderId = state.pathParameters['orderId']!;
                  return OrderDetailScreen(orderId: orderId);
                },
              ),
            ],
          ),
          GoRoute(
            path: '/payment',
            builder: (context, state) => const PaymentScreen(),
          ),
          GoRoute(
            path: '/profile',
            builder: (context, state) => const ProfileScreen(),
            routes: [
              GoRoute(
                path: 'account',
                builder: (context, state) => const AccountDetailScreen(),
              ),
              GoRoute(
                path: 'edit',
                builder: (context, state) => const EditProfileScreen(),
              ),
              GoRoute(
                path: 'settings',
                builder: (context, state) => const SettingsScreen(),
              ),
              GoRoute(
                path: 'notifications',
                builder: (context, state) => const NotificationsScreen(),
              ),
              GoRoute(
                path: 'help',
                builder: (context, state) => const HelpScreen(),
              ),
              GoRoute(
                path: 'contact',
                builder: (context, state) => const ContactUsScreen(),
              ),
              GoRoute(
                path: 'about',
                builder: (context, state) => const AboutScreen(),
              ),
              GoRoute(
                path: 'transactions',
                builder: (context, state) => const TransactionHistoryScreen(),
              ),
              GoRoute(
                path: 'api-health-check',
                builder: (context, state) => const ApiHealthCheckScreen(),
              ),
            ],
          ),
        ],
      ),
    ],
  );
}
