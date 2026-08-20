import 'dart:convert';

import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

import 'config.dart';

/// The only way this app reaches Vidlix data. There is no database client on
/// the device: everything goes through HTTPS `/api/v1` with a Sanctum token.
class Api {
  static const _tokenKey = 'vidlix_token';

  Future<String?> token() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString(_tokenKey);
  }

  Future<void> setToken(String? value) async {
    final prefs = await SharedPreferences.getInstance();
    if (value == null) {
      await prefs.remove(_tokenKey);
    } else {
      await prefs.setString(_tokenKey, value);
    }
  }

  Future<Map<String, dynamic>> post(String path, Map<String, dynamic> body, {bool auth = false}) async {
    try {
      final res = await http.post(
        Uri.parse('${Config.apiBase}/api/v1$path'),
        headers: await _headers(auth),
        body: jsonEncode(body),
      );
      return _decode(res);
    } catch (e) {
      return _offline(e);
    }
  }

  Future<Map<String, dynamic>> get(String path, {bool auth = false}) async {
    try {
      final res = await http.get(
        Uri.parse('${Config.apiBase}/api/v1$path'),
        headers: await _headers(auth),
      );
      return _decode(res);
    } catch (e) {
      return _offline(e);
    }
  }

  Future<Map<String, String>> _headers(bool auth) async {
    final headers = {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
    };
    if (auth) {
      final t = await token();
      if (t != null) headers['Authorization'] = 'Bearer $t';
    }
    return headers;
  }

  Map<String, dynamic> _decode(http.Response res) {
    try {
      final decoded = jsonDecode(res.body);
      if (decoded is Map<String, dynamic>) {
        decoded['_http'] = res.statusCode;
        return decoded;
      }
    } catch (_) {
      // fall through to the generic shape below
    }
    return {'_http': res.statusCode, 'success': false, 'message': 'Unexpected response from the server.'};
  }

  Map<String, dynamic> _offline(Object error) {
    return {
      '_http': 0,
      'success': false,
      'code': 'NETWORK_ERROR',
      'message': 'Could not reach ${Config.apiBase}. $error',
    };
  }

  /// Unwraps both plain lists and paginated `{data: {data: []}}` envelopes.
  static List listOf(Map<String, dynamic> res) {
    final data = res['data'];
    if (data is Map && data['data'] is List) return data['data'] as List;
    if (data is List) return data;
    return const [];
  }

  /// The first thing that actually went wrong, in words a person can act on.
  ///
  /// The API answers a rejected form with a map of field errors; showing the
  /// generic message instead ("The given data was invalid") tells somebody
  /// nothing about which field to fix.
  static String firstError(Map<String, dynamic> response, String fallback) {
    final errors = response['errors'];

    if (errors is Map && errors.values.isNotEmpty) {
      final first = errors.values.first;

      if (first is List && first.isNotEmpty) {
        return '${first.first}';
      }

      return '$first';
    }

    final message = response['message'];

    return message == null || '$message'.isEmpty ? fallback : '$message';
  }

  static Map<String, dynamic> mapOf(Map<String, dynamic> res) {
    final data = res['data'];
    return data is Map<String, dynamic> ? data : <String, dynamic>{};
  }

  static String money(Object? minor, [String currency = 'INR']) {
    final value = minor is num ? minor : int.tryParse('$minor') ?? 0;
    final symbol = currency == 'INR' ? '₹' : '$currency ';
    return '$symbol${(value / 100).toStringAsFixed(2)}';
  }
}
