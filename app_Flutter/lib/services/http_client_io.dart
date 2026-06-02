import 'dart:io';
import 'package:flutter/foundation.dart';
import 'package:http/io_client.dart';
import 'package:http/http.dart' as http;

/// Creates an HTTP client for mobile platforms (iOS/Android).
///
/// In DEBUG mode: SSL certificate verification is bypassed. This allows
/// development against local servers (e.g. ServBay) that use self-signed
/// or local-CA certificates not trusted by Dart's BoringSSL layer.
///
/// In RELEASE mode: standard SSL verification is enforced. The production
/// server must have a valid CA-signed certificate (e.g. Let's Encrypt).
http.Client createClient() {
  if (kDebugMode) {
    debugPrint('🔓 [MOBILE/DEBUG] Creating IOClient with SSL bypass');
    final ioClient = HttpClient()
      ..badCertificateCallback =
          (X509Certificate cert, String host, int port) {
        debugPrint('🔓 [SSL] Accepting certificate for $host:$port');
        return true;
      };
    return IOClient(ioClient);
  }

  debugPrint('🔒 [MOBILE/RELEASE] Creating IOClient with SSL verification');
  return IOClient(HttpClient());
}
