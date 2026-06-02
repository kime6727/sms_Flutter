class AppConfig {
  static const String appName = 'Simu';
  static const String appVersion = '1.0.0';

  // 远程生产环境域名
  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'https://smsapi2.niceapp.eu.cc',
  );

  // API Key 必须在构建时通过 --dart-define=API_KEY=... 注入
  // 严禁在源码中保留默认值，详见 docs/security.md
  static const String apiKey = String.fromEnvironment('API_KEY');

  static const String apiVersion = '/api';

  static String get fullApiUrl => '$apiBaseUrl$apiVersion';

  static const Duration apiTimeout = Duration(seconds: 30);
  static const int maxRetryCount = 3;

  static const String defaultLocale = 'en';
  static const List<String> supportedLocales = ['en', 'zh'];

  static const int pendingOrderExpireHours = 72;
  static const int orderTimeoutMinutes = 20;

  static const int maxBatchOrderCount = 10;

  // 官方页面地址
  static const String privacyPolicyUrl = 'https://page.niceapp.eu.cc/index.php/archives/4.html';
  static const String termsOfServiceUrl = 'https://page.niceapp.eu.cc/index.php/archives/5.html';
  static const String paymentPolicyUrl = 'https://page.niceapp.eu.cc/index.php/archives/6.html';
  static const String contactEmail = '';
  static const String helpUrl = 'https://page.niceapp.eu.cc/index.php/archives/sms-chat.html';
}
