import 'dart:convert';
import 'dart:typed_data';

import 'package:adinn_tasks/core/api_client.dart';
import 'package:adinn_tasks/core/auth.dart';
import 'package:adinn_tasks/core/models.dart';
import 'package:dio/dio.dart';

/// In-memory session store.
class MemoryStore implements SessionStore {
  MemoryStore({this.token, this.user});

  String? token;
  AppUser? user;

  @override
  Future<({String? token, AppUser? user})> read() async => (token: token, user: user);

  @override
  Future<void> write(String t, AppUser u) async {
    token = t;
    user = u;
  }

  @override
  Future<void> clear() async {
    token = null;
    user = null;
  }
}

typedef Handler = ({int status, Object? body}) Function(RequestOptions options);

/// Dio adapter answering from a route table keyed "METHOD /path"; records requests.
class FakeAdapter implements HttpClientAdapter {
  /// Realtime is off unless a test says otherwise, so app-level tests don't try to open sockets.
  FakeAdapter(Map<String, Handler> routes)
      : routes = {'GET /realtime': (_) => (status: 200, body: {'enabled': false}), ...routes};

  final Map<String, Handler> routes;
  final List<RequestOptions> requests = [];

  @override
  Future<ResponseBody> fetch(RequestOptions options, Stream<Uint8List>? requestStream, Future<void>? cancelFuture) async {
    requests.add(options);
    final handler = routes['${options.method} ${options.path}'];
    final result = handler?.call(options) ?? (status: 404, body: {'message': 'No fake route'});
    return ResponseBody.fromString(jsonEncode(result.body), result.status, headers: {
      Headers.contentTypeHeader: ['application/json'],
    });
  }

  @override
  void close({bool force = false}) {}
}

ApiClient fakeClient(FakeAdapter adapter, {String? Function()? token, void Function()? onUnauthorized}) {
  final dio = Dio(BaseOptions(baseUrl: 'https://test.local/api/v1'))..httpClientAdapter = adapter;
  return ApiClient(dio: dio, tokenProvider: token, onUnauthorized: onUnauthorized);
}

Map<String, dynamic> userJson({String role = 'designer', int id = 1}) =>
    {'id': id, 'name': 'Test User', 'email': 't@example.test', 'role': role};

Map<String, dynamic> taskJson({int id = 1, String status = 'in_progress', String name = 'Banner'}) => {
      'id': id,
      'task_id': 'DT-2026-000$id',
      'task_name': name,
      'display_task_name': name,
      'vertical': 'roadshow',
      'party_name': 'Acme',
      'priority': 'high',
      'status': status,
      'due_at': '2099-01-01T10:00:00+00:00',
      'total_creatives': 3,
    };
