import 'dart:io' show Platform;

import 'api.dart';

/// Registering this device so the server can push to it.
///
/// The FCM token itself comes from the platform. Until firebase_messaging is
/// added to the build, [PushRegistration.tokenProvider] returns null and
/// nothing is registered — which is the honest state: no token means no
/// device to push to, and pretending otherwise would put a row in the server's
/// table that can never receive anything.
class PushRegistration {
  PushRegistration(this.api, {this.tokenProvider});

  final Api api;

  /// Supplied by the app once a messaging SDK is wired in.
  final Future<String?> Function()? tokenProvider;

  static String get platform {
    if (Platform.isAndroid) return 'android';
    if (Platform.isIOS) return 'ios';

    return 'web';
  }

  /// Returns what actually happened, so a caller can say so rather than assume.
  Future<String> register({String? appVersion}) async {
    final provider = tokenProvider;

    if (provider == null) {
      return 'no_token_provider';
    }

    final token = await provider();

    if (token == null || token.isEmpty) {
      return 'no_device_token';
    }

    final res = await api.post(
      '/devices',
      {
        'token': token,
        'platform': platform,
        'app_version': ?appVersion,
      },
      auth: true,
    );

    if (res['success'] != true) {
      return 'rejected';
    }

    // The server tells us whether a push provider is configured at all. It is
    // worth surfacing: a registered device with no provider still receives
    // nothing.
    final data = Api.mapOf(res);

    return data['push_provider_configured'] == true ? 'registered' : 'registered_no_provider';
  }
}
