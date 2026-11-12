import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:open_filex/open_filex.dart';
import 'package:path_provider/path_provider.dart';

/// Why an in-app update did not reach the installer.
class InstallException implements Exception {
  InstallException(this.message, {this.needsPermission = false});

  final String message;

  /// Android is waiting for "Allow from this source" — the user must switch it on, then tap Update again.
  final bool needsPermission;

  @override
  String toString() => message;
}

/// Downloads the new APK inside the app and hands it to Android's installer, so nobody has to go through Chrome.
/// Abstract so tests need no device.
abstract class AppInstaller {
  /// Reports progress 0..1 while downloading; completes once the installer screen has been opened.
  Future<void> downloadAndInstall(String url, {required int build, void Function(double progress)? onProgress});
}

class DeviceAppInstaller implements AppInstaller {
  DeviceAppInstaller([Dio? dio]) : _dio = dio ?? Dio(BaseOptions(connectTimeout: const Duration(seconds: 20)));

  // A plain client: the APK is a public link, and the API bearer token must never go to that host.
  final Dio _dio;

  @override
  Future<void> downloadAndInstall(String url, {required int build, void Function(double progress)? onProgress}) async {
    final root = await getTemporaryDirectory();
    final dir = Directory('${root.path}${Platform.pathSeparator}update');
    await dir.create(recursive: true);

    final name = 'adinn-design-tasks-$build.apk';
    final file = File('${dir.path}${Platform.pathSeparator}$name');

    // Old update files are of no use any more.
    await for (final f in dir.list()) {
      if (f is File && !f.path.endsWith(name)) await f.delete();
    }

    if (!(await file.exists() && await file.length() > 0)) {
      final part = File('${file.path}.part');
      try {
        await _dio.download(
          url,
          part.path,
          onReceiveProgress: (got, total) {
            if (total > 0) onProgress?.call((got / total).clamp(0, 1).toDouble());
          },
        );
        await part.rename(file.path);
      } on DioException {
        if (await part.exists()) await part.delete();
        throw InstallException('Could not download the update. Check your connection and try again.');
      }
    }
    onProgress?.call(1);

    final result = await OpenFilex.open(file.path, type: 'application/vnd.android.package-archive');
    switch (result.type) {
      case ResultType.done:
        return;
      case ResultType.permissionDenied:
        throw InstallException('Allow installs from this app in the screen that opened, then tap Update again.', needsPermission: true);
      default:
        throw InstallException('Could not open the installer. Please try again.');
    }
  }
}

final appInstallerProvider = Provider<AppInstaller>((ref) => DeviceAppInstaller());
