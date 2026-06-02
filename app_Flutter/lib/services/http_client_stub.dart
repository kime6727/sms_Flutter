import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;

/// Creates an HTTP client for web platforms.
///
/// On web, we use the standard browser fetch API. SSL verification is
/// handled by the browser so no special handling is needed.
http.Client createClient() {
  debugPrint('🌐 [WEB] Creating standard browser HTTP client');
  return http.Client();
}
