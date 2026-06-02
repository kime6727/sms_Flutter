import 'package:http/http.dart' as http;

// Conditional import: on mobile (dart:io available), use IOClient with SSL bypass.
// On web (no dart:io), fall back to the standard browser HTTP client.
import 'http_client_stub.dart'
    if (dart.library.io) 'http_client_io.dart';

/// Creates a platform-appropriate HTTP client.
///
/// On mobile (iOS/Android): uses `IOClient` wrapping `dart:io` HttpClient with
/// `badCertificateCallback` set to accept all certificates. This is necessary
/// for development servers without a trusted CA-signed certificate.
///
/// On web: uses the standard `http.Client()` backed by the browser's fetch API.
http.Client createPlatformClient() => createClient();
