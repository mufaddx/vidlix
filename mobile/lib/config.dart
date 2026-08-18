import 'package:flutter/foundation.dart';

class Config {
  static String get apiBase {
    const override = String.fromEnvironment('API_BASE');
    if (override.isNotEmpty) return override;
    if (kIsWeb) return 'http://127.0.0.1:8000';
    switch (defaultTargetPlatform) {
      case TargetPlatform.android:
        return 'http://10.0.2.2:8000';
      default:
        return 'http://127.0.0.1:8000';
    }
  }
}
