import 'package:dio/dio.dart';

import 'config.dart';

/// A failure from the API, already reduced to something safe to show a user.
class ApiException implements Exception {
  ApiException(this.message, {this.statusCode, this.fieldErrors = const {}});

  final String message;
  final int? statusCode;
  final Map<String, String> fieldErrors;

  bool get isUnauthorized => statusCode == 401;

  @override
  String toString() => message;

  /// Maps a Dio error (Laravel JSON: `{message, errors: {field: [..]}}`) to an [ApiException].
  factory ApiException.from(Object error) {
    if (error is ApiException) return error;
    if (error is DioException) {
      final status = error.response?.statusCode;
      final data = error.response?.data;

      if (data is Map) {
        final errors = <String, String>{};
        final raw = data['errors'];
        if (raw is Map) {
          raw.forEach((k, v) {
            if (v is List && v.isNotEmpty) errors['$k'] = '${v.first}';
          });
        }
        final message = errors.values.isNotEmpty
            ? errors.values.first
            : (data['message'] is String && (data['message'] as String).isNotEmpty)
                ? data['message'] as String
                : _fallback(status);
        return ApiException(message, statusCode: status, fieldErrors: errors);
      }

      if (error.type == DioExceptionType.connectionError ||
          error.type == DioExceptionType.connectionTimeout ||
          error.type == DioExceptionType.receiveTimeout) {
        return ApiException('Cannot reach the server. Check your connection and try again.');
      }
      return ApiException(_fallback(status), statusCode: status);
    }
    return ApiException('Something went wrong. Please try again.');
  }

  static String _fallback(int? status) => switch (status) {
        401 => 'Your session has expired. Please sign in again.',
        403 => 'You do not have access to this.',
        404 => 'That item could not be found.',
        422 => 'Please check the details and try again.',
        429 => 'Too many attempts. Please wait a moment.',
        _ => 'Something went wrong. Please try again.',
      };
}

/// Thin Dio wrapper: bearer token on every call, JSON by default, and a hook
/// that fires when the server says the token is no longer valid.
class ApiClient {
  ApiClient({String? baseUrl, Dio? dio, this.tokenProvider, this.onUnauthorized})
      : _dio = dio ??
            Dio(BaseOptions(
              baseUrl: baseUrl ?? AppConfig.apiBaseUrl,
              connectTimeout: const Duration(seconds: 15),
              receiveTimeout: const Duration(seconds: 30),
              headers: {'Accept': 'application/json'},
            )) {
    _dio.interceptors.add(InterceptorsWrapper(
      onRequest: (options, handler) {
        final token = tokenProvider?.call();
        if (token != null && token.isNotEmpty) {
          options.headers['Authorization'] = 'Bearer $token';
        }
        handler.next(options);
      },
      onError: (error, handler) {
        // A failed login is also a 401-ish validation error (422); only a rejected *token* signs out.
        if (error.response?.statusCode == 401 && (tokenProvider?.call() ?? '').isNotEmpty) {
          onUnauthorized?.call();
        }
        handler.next(error);
      },
    ));
  }

  final Dio _dio;
  final String? Function()? tokenProvider;
  final void Function()? onUnauthorized;

  Future<Map<String, dynamic>> get(String path, {Map<String, dynamic>? query}) =>
      _send(() => _dio.get<Object>(path, queryParameters: query));

  Future<Map<String, dynamic>> post(String path, {Object? data}) =>
      _send(() => _dio.post<Object>(path, data: data));

  Future<Map<String, dynamic>> patch(String path, {Object? data}) =>
      _send(() => _dio.patch<Object>(path, data: data));

  Future<Map<String, dynamic>> _send(Future<Response<Object>> Function() call) async {
    try {
      final response = await call();
      final body = response.data;
      return body is Map<String, dynamic> ? body : <String, dynamic>{};
    } catch (e) {
      throw ApiException.from(e);
    }
  }
}
