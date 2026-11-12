import 'dart:convert';
import 'dart:typed_data';

import 'package:adinn_tasks/core/api_client.dart';
import 'package:adinn_tasks/core/auth.dart';
import 'package:adinn_tasks/core/models.dart';
import 'package:dio/dio.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_test/flutter_test.dart';

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
      : routes = {
          'GET /realtime': (_) => (status: 200, body: {'enabled': false}),
          // A designer now lands on the Dashboard; tests that aren't about it get an empty one.
          'GET /designer/dashboard': (_) => (status: 200, body: emptyDashboardJson()),
          ...routes,
        };

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


/// A designer lands on the Dashboard; the bottom tab labelled "My Tasks" opens the man holding the cards. Tapping the cards
/// opens All Tickets, so the tickets themselves are on screen, as the old flat board had them.
Future<void> openMyTasks(WidgetTester tester) async {
  // The tab is "My Tasks" for a designer and "Assigned Tasks" for a BD.
  final tab = find.text('My Tasks').evaluate().isNotEmpty ? find.text('My Tasks') : find.text('Assigned Tasks');
  await tester.tap(tab.last); // the bottom bar is last in the tree (the board's own title shares the BD label)
  await tester.pumpAndSettle();

  final cards = find.byKey(const Key('held-card-all'));
  if (cards.evaluate().isEmpty) return; // the board failed to load
  await tester.tap(cards);
  await tester.pumpAndSettle();
}

Map<String, dynamic> emptyDashboardJson({List<Map<String, dynamic>> recent = const [], Map<String, dynamic> stats = const {}}) => {
      'user': {'name': 'Test User', 'last_login_at': null},
      'stats': {
        'total': 0, 'assigned': 0, 'ready_to_start': 0, 'in_progress': 0, 'completed': 0, 'pending_approval': 0, 'waiting_bd_confirmation': 0,
        'waiting_bd_review': 0, 'prepare_printing_file': 0, 'overdue': 0, 'rework': 0, 'rework_creatives': 0,
        ...stats,
      },
      'completion_rate': 0,
      'overall_rating': {'average': null, 'rated': 0, 'total': 0},
      'overall_rework': {'cycles': 0, 'creatives': 0},
      'request_counts': {'swap': 0, 'decline': 0, 'split': 0, 'approved': 0},
      'recent_tasks': recent,
      'trend': {
        'context': 'My Tasks • Nov 2026',
        'cards': [
          {'label': 'Assigned Tasks', 'value': 0, 'color': '#2970ff'},
          {'label': 'In Progress', 'value': 0, 'color': '#7c3aed'},
          {'label': 'Completed', 'value': 0, 'color': '#027a48'},
          {'label': 'Overdue & Completed', 'value': 0, 'color': '#f79009'},
          {'label': 'Declined', 'value': 0, 'color': '#c01048'},
        ],
        'line': [
          for (final m in ['Jun 26', 'Jul 26', 'Aug 26', 'Sep 26', 'Oct 26', 'Nov 26'])
            {'label': m, 'assigned': 0, 'in_progress': 0, 'completed': 0, 'overdue_completed': 0, 'declined': 0},
        ],
      },
      'reviews': [],
      'my_requests': [],
    };

/// `GET /bd/dashboard` with nothing in it: enough for the BD home tab to render.
Map<String, dynamic> emptyBdDashboardJson() => {
      'user': {'name': 'Test BD', 'last_login_at': null},
      'filters': {'designer': null, 'designer_name': null, 'month': '2026-11', 'month_label': 'November 2026', 'designers': [], 'months': []},
      'stats': {},
      'bar': [],
      'trend': {'context': 'All Designers • Nov 2026', 'cards': [], 'line': []},
      'workload': [],
      'recent_tasks': [],
      'overdue': [],
      'rework': [],
      'ratings': [],
      'completions': [],
    };
