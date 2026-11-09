import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:open_filex/open_filex.dart';
import 'package:path_provider/path_provider.dart';

/// Downloads a file to the phone and opens it in whatever app handles that type. Abstract so tests need no device.
abstract class FileAccess {
  /// Saves [url] under a stable name and returns the local path; a file already downloaded is reused.
  Future<String> download(String url, String name);

  Future<void> open(String path);
}

class FileAccessException implements Exception {
  FileAccessException(this.message);

  final String message;

  @override
  String toString() => message;
}

class DeviceFileAccess implements FileAccess {
  DeviceFileAccess([Dio? dio]) : _dio = dio ?? Dio(BaseOptions(connectTimeout: const Duration(seconds: 20)));

  // A plain client: the file URLs are public storage links, and the API bearer token must never go to that host.
  final Dio _dio;

  @override
  Future<String> download(String url, String name) async {
    final root = await getApplicationDocumentsDirectory();
    final dir = Directory('${root.path}${Platform.pathSeparator}downloads');
    await dir.create(recursive: true);

    final file = File('${dir.path}${Platform.pathSeparator}${url.hashCode.toUnsigned(24)}_${safeName(name)}');
    if (await file.exists() && await file.length() > 0) return file.path;

    final part = File('${file.path}.part');
    try {
      await _dio.download(url, part.path);
      await part.rename(file.path);
    } on DioException {
      if (await part.exists()) await part.delete();
      throw FileAccessException('Could not download the file. Check your connection and try again.');
    }

    return file.path;
  }

  @override
  Future<void> open(String path) async {
    final result = await OpenFilex.open(path);
    if (result.type == ResultType.noAppToOpen) {
      throw FileAccessException('No app on this phone can open that type of file. It was saved to: $path');
    }
    if (result.type == ResultType.fileNotFound || result.type == ResultType.error) {
      throw FileAccessException('Could not open the file.');
    }
  }
}

/// Keeps letters, digits, dot, dash and underscore so a server-supplied name can never escape the downloads folder.
String safeName(String name) {
  final cleaned = name.replaceAll(RegExp(r'[^A-Za-z0-9._-]+'), '_');
  return cleaned.isEmpty || cleaned.replaceAll('.', '').isEmpty ? 'file' : cleaned;
}

final fileAccessProvider = Provider<FileAccess>((ref) => DeviceFileAccess());
