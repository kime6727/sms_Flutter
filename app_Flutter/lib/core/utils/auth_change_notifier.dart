import 'package:flutter/foundation.dart';

/// 用于通知路由认证状态变化的辅助类（单例，AuthProvider 在状态变化时调用 notify）
///
/// AppRouter 的 GoRouter.refreshListenable 持有本类的单例，
/// AuthProvider 通过 override notifyListeners() 同时通知 Provider 监听者
/// 和 GoRouter。
class AuthChangeNotifier extends ChangeNotifier {
  AuthChangeNotifier._();
  static final AuthChangeNotifier instance = AuthChangeNotifier._();
}
