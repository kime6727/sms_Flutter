import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:shared_preferences/shared_preferences.dart';

class AppConstants {
  static const String keyApiKey = 'api_key';
  static const String keyToken = 'token';
  static const String keyUserId = 'user_id';
  static const String keyDeviceId = 'device_id';
  static const String keyUsername = 'username';
  static const String keyLocale = 'locale';
  static const String keyThemeMode = 'theme_mode';
  static const String keyHasShownCredentials = 'has_shown_credentials';
  static const String keyFirstLaunch = 'first_launch';
}

class StorageService {
  static late SharedPreferences _prefs;
  static const _secure = FlutterSecureStorage(
    aOptions: AndroidOptions(encryptedSharedPreferences: true),
    iOptions: IOSOptions(
      accessibility: KeychainAccessibility.first_unlock_this_device,
    ),
  );

  static const _secureKeys = {
    AppConstants.keyToken,
    AppConstants.keyApiKey,
    AppConstants.keyUserId,
  };

  static void init(SharedPreferences prefs) {
    _prefs = prefs;
  }

  static Future<bool> _isSecure(String key) => Future.value(_secureKeys.contains(key));

  static Future<void> setString(String key, String value) async {
    if (_secureKeys.contains(key)) {
      await _secure.write(key: key, value: value);
    } else {
      await _prefs.setString(key, value);
    }
  }

  static Future<String> getStringAsync(String key, {String defaultValue = ''}) async {
    if (_secureKeys.contains(key)) {
      return await _secure.read(key: key) ?? defaultValue;
    }
    return _prefs.getString(key) ?? defaultValue;
  }

  static String getString(String key, {String defaultValue = ''}) {
    if (_secureKeys.contains(key)) {
      throw StateError(
        'StorageService.getString(\'$key\') 是安全字段, 必须使用 getStringAsync 异步读取',
      );
    }
    return _prefs.getString(key) ?? defaultValue;
  }

  static Future<bool> setBool(String key, bool value) => _prefs.setBool(key, value);

  static bool getBool(String key, {bool defaultValue = false}) =>
      _prefs.getBool(key) ?? defaultValue;

  static Future<bool> setInt(String key, int value) => _prefs.setInt(key, value);

  static int getInt(String key, {int defaultValue = 0}) =>
      _prefs.getInt(key) ?? defaultValue;

  static Future<bool> remove(String key) async {
    if (_secureKeys.contains(key)) {
      await _secure.delete(key: key);
    }
    return _prefs.remove(key);
  }

  static Future<bool> clear() async {
    for (final k in _secureKeys) {
      await _secure.delete(key: k);
    }
    return _prefs.clear();
  }
}
