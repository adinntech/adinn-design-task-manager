import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:package_info_plus/package_info_plus.dart';

import 'auth.dart';

/// What the server says about the newest app release (config/app_update.php on the server).
class AppUpdateInfo {
  const AppUpdateInfo({this.minBuild = 0, this.latestBuild = 0, this.latestVersion, this.url, this.notes});

  final int minBuild;
  final int latestBuild;
  final String? latestVersion;
  final String? url;
  final String? notes;

  factory AppUpdateInfo.fromJson(Map<String, dynamic> j) => AppUpdateInfo(
        minBuild: int.tryParse('${j['min_build'] ?? 0}') ?? 0,
        latestBuild: int.tryParse('${j['latest_build'] ?? 0}') ?? 0,
        latestVersion: j['latest_version'] as String?,
        url: j['url'] as String?,
        notes: j['notes'] as String?,
      );

  /// This install is too old to be used: the app is frozen behind the Update screen.
  bool mustUpdate(int build) => minBuild > 0 && build < minBuild;

  /// A newer release exists but the old one still works.
  bool canUpdate(int build) => !mustUpdate(build) && latestBuild > build;
}

/// This install's build number (the number after "+" in pubspec.yaml). Overridden in tests.
final appBuildProvider = FutureProvider<int>((ref) async {
  final info = await PackageInfo.fromPlatform();
  return int.tryParse(info.buildNumber) ?? 0;
});

/// Remembers the last build this phone ran, so the first launch after an update can say what changed.
abstract class SeenBuildStore {
  Future<int?> read();
  Future<void> write(int build);
}

class SecureSeenBuildStore implements SeenBuildStore {
  const SecureSeenBuildStore([this._storage = const FlutterSecureStorage()]);

  final FlutterSecureStorage _storage;
  static const _key = 'last_seen_build';

  @override
  Future<int?> read() async {
    try {
      return int.tryParse(await _storage.read(key: _key) ?? '');
    } catch (_) {
      return null;
    }
  }

  @override
  Future<void> write(int build) async {
    try {
      await _storage.write(key: _key, value: '$build');
    } catch (_) {}
  }
}

final seenBuildStoreProvider = Provider<SeenBuildStore>((ref) => const SecureSeenBuildStore());

/// "What's new" for the release this phone just updated to.
class WhatsNew {
  const WhatsNew({required this.version, required this.notes});

  final String? version;
  final String notes;
}

/// Non-null exactly once: on the first launch of a build newer than the last one this phone ran, when the server
/// has release notes. A brand-new install (nothing remembered yet) just records the build and says nothing.
final whatsNewProvider = FutureProvider<WhatsNew?>((ref) async {
  final build = await ref.watch(appBuildProvider.future);
  final info = await ref.watch(appUpdateProvider.future);
  if (info == null) return null; // can't read the notes right now: don't mark this build as seen yet

  final store = ref.read(seenBuildStoreProvider);
  final seen = await store.read();
  if (seen == build) return null;
  await store.write(build);

  final notes = info.notes?.trim() ?? '';
  if (seen == null || seen > build || notes.isEmpty) return null;
  return WhatsNew(version: info.latestVersion, notes: notes);
});

/// The server's release info, or null when it can't be read. Offline or a server error must never lock
/// people out, so the gate only ever blocks on a definite "too old" answer.
final appUpdateProvider = FutureProvider.autoDispose<AppUpdateInfo?>((ref) async {
  try {
    final json = await ref.watch(apiClientProvider).get('/app-update', query: {'platform': 'android'});
    return AppUpdateInfo.fromJson(json);
  } catch (_) {
    return null;
  }
});
