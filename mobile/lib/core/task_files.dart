/// Files shown on a task's Overview: what the BD attached when creating it (grouped by upload field) and what the
/// designer handed in. The API sends these under `files` in every role's task detail.
library;

class TaskFile {
  const TaskFile({required this.name, required this.extension, required this.kind, required this.url});

  final String name;
  final String extension;

  /// image | audio | video | file
  final String kind;
  final String url;

  bool get isImage => kind == 'image';
  bool get isAudio => kind == 'audio';

  factory TaskFile.fromJson(Map<String, dynamic> j) => TaskFile(
        name: (j['name'] ?? 'file') as String,
        extension: (j['extension'] ?? 'FILE') as String,
        kind: (j['kind'] ?? 'file') as String,
        url: (j['url'] ?? '') as String,
      );

  /// For chat attachments, which carry a name and a mime type but no `kind`.
  factory TaskFile.fromNameAndMime(String name, String url, String? mime) {
    final dot = name.lastIndexOf('.');
    final ext = dot < 0 ? '' : name.substring(dot + 1).toLowerCase();
    final m = (mime ?? '').toLowerCase();
    final kind = m.startsWith('image/') || const {'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'heic'}.contains(ext)
        ? 'image'
        : m.startsWith('audio/') || const {'mp3', 'm4a', 'aac', 'wav', 'ogg', 'opus', 'amr'}.contains(ext)
            ? 'audio'
            : m.startsWith('video/') || const {'mp4', 'mov', 'webm', 'mkv'}.contains(ext)
                ? 'video'
                : 'file';

    return TaskFile(name: name, extension: ext.isEmpty ? 'FILE' : ext.toUpperCase(), kind: kind, url: url);
  }
}

class FileGroup {
  const FileGroup({required this.label, required this.files});

  final String label;
  final List<TaskFile> files;
}

class Submission {
  const Submission({required this.submittedAt, required this.completed, required this.files});

  final DateTime? submittedAt;
  final int completed;
  final List<TaskFile> files;
}

class TaskFiles {
  const TaskFiles({this.groups = const [], this.submissions = const []});

  final List<FileGroup> groups;
  final List<Submission> submissions;

  bool get isEmpty => groups.isEmpty && submissions.isEmpty;

  /// Reads `files` from a task-detail response. A server that predates it simply yields nothing.
  factory TaskFiles.fromDetail(Map<String, dynamic> detail) {
    final raw = detail['files'];
    if (raw is! Map) return const TaskFiles();

    List<TaskFile> files(Object? l) => [for (final f in (l as List? ?? const [])) TaskFile.fromJson((f as Map).cast<String, dynamic>())];

    return TaskFiles(
      groups: [
        for (final g in (raw['requirement_files'] as List? ?? const []))
          FileGroup(label: ((g as Map)['label'] ?? '') as String, files: files(g['files'])),
      ],
      submissions: [
        for (final s in (raw['submissions'] as List? ?? const []))
          Submission(
            submittedAt: (s as Map)['submitted_at'] == null ? null : DateTime.tryParse(s['submitted_at'] as String)?.toLocal(),
            completed: ((s['completed_count'] ?? 0) as num).toInt(),
            files: files(s['files']),
          ),
      ],
    );
  }
}
