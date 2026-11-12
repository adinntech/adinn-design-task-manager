import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../core/file_access.dart';
import '../../core/task_files.dart';
import '../../core/voice.dart';

const _muted = Color(0xFF667085);
const _line = Color(0xFFEAECF0);

/// Downloads [file] to the phone and opens it in an app that can show it; tells the user when that fails.
Future<void> downloadAndOpen(BuildContext context, WidgetRef ref, TaskFile file) async {
  final messenger = ScaffoldMessenger.of(context);
  final access = ref.read(fileAccessProvider);

  try {
    messenger.showSnackBar(SnackBar(content: Text('Downloading ${file.name}…'), duration: const Duration(seconds: 2)));
    final path = await access.download(file.url, file.name);
    await access.open(path);
  } on FileAccessException catch (e) {
    messenger.showSnackBar(SnackBar(content: Text(e.message)));
  } catch (_) {
    messenger.showSnackBar(const SnackBar(content: Text('Could not open the file.')));
  }
}

/// A grid thumbnail for an image and a labelled chip for everything else; audio gets an inline player.
/// [gallery] is every image shown next to this one, so the viewer can swipe through them.
class FileTile extends ConsumerStatefulWidget {
  const FileTile({super.key, required this.file, this.gallery = const []});

  final TaskFile file;
  final List<TaskFile> gallery;

  @override
  ConsumerState<FileTile> createState() => _FileTileState();
}

class _FileTileState extends ConsumerState<FileTile> {
  bool _busy = false;

  Future<void> _download() async {
    setState(() => _busy = true);
    await downloadAndOpen(context, ref, widget.file);
    if (mounted) setState(() => _busy = false);
  }

  @override
  Widget build(BuildContext context) {
    final file = widget.file;
    final primary = Theme.of(context).colorScheme.primary;

    if (file.isImage) {
      final images = widget.gallery.isEmpty ? [file] : widget.gallery;

      return InkWell(
        key: Key('file-${file.name}'),
        borderRadius: BorderRadius.circular(12),
        onTap: () => Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => ImageViewerScreen(images: images, initial: images.indexOf(file).clamp(0, images.length - 1)))),
        child: ClipRRect(
          borderRadius: BorderRadius.circular(12),
          child: SizedBox(width: 78, height: 78, child: NetworkThumb(url: file.url, fit: BoxFit.cover)),
        ),
      );
    }

    if (file.isAudio) {
      return SizedBox(key: Key('file-${file.name}'), width: 260, child: AudioNote(url: file.url, label: file.name));
    }

    return InkWell(
      key: Key('file-${file.name}'),
      borderRadius: BorderRadius.circular(12),
      onTap: _busy ? null : _download,
      child: Container(
        constraints: const BoxConstraints(maxWidth: 220),
        padding: const EdgeInsets.fromLTRB(10, 8, 12, 8),
        decoration: BoxDecoration(color: const Color(0xFFF9FAFB), borderRadius: BorderRadius.circular(12), border: Border.all(color: _line)),
        child: Row(mainAxisSize: MainAxisSize.min, children: [
          _busy
              ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2))
              : Icon(file.kind == 'video' ? Icons.movie_outlined : Icons.insert_drive_file_outlined, size: 22, color: primary),
          const SizedBox(width: 8),
          Flexible(
            child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(file.name, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 12.5, fontWeight: FontWeight.w700)),
              Text('${file.extension} · tap to download', style: const TextStyle(fontSize: 10.5, color: _muted)),
            ]),
          ),
        ]),
      ),
    );
  }
}

/// A picture from the network that shows an icon instead of throwing when it cannot load.
class NetworkThumb extends StatelessWidget {
  const NetworkThumb({super.key, required this.url, this.fit = BoxFit.contain});

  final String url;
  final BoxFit fit;

  @override
  Widget build(BuildContext context) => Image.network(
        url,
        fit: fit,
        loadingBuilder: (_, child, progress) => progress == null ? child : const Center(child: SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2))),
        errorBuilder: (_, _, _) => Container(color: const Color(0xFFF2F4F7), alignment: Alignment.center, child: const Icon(Icons.broken_image_outlined, color: Color(0xFF98A2B3))),
      );
}

/// Full-screen pictures: pinch to zoom, swipe for the next one, download to keep a copy.
class ImageViewerScreen extends ConsumerStatefulWidget {
  const ImageViewerScreen({super.key, required this.images, this.initial = 0});

  final List<TaskFile> images;
  final int initial;

  @override
  ConsumerState<ImageViewerScreen> createState() => _ImageViewerScreenState();
}

class _ImageViewerScreenState extends ConsumerState<ImageViewerScreen> {
  late final PageController _pages = PageController(initialPage: widget.initial);
  late int _index = widget.initial;

  @override
  void dispose() {
    _pages.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final current = widget.images[_index];

    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        backgroundColor: Colors.black,
        foregroundColor: Colors.white,
        title: Text(widget.images.length > 1 ? '${_index + 1} of ${widget.images.length}' : current.name, style: const TextStyle(fontSize: 15)),
        actions: [
          IconButton(
            key: const Key('image-download'),
            tooltip: 'Download',
            icon: const Icon(Icons.download_rounded),
            onPressed: () => downloadAndOpen(context, ref, current),
          ),
        ],
      ),
      body: PageView.builder(
        controller: _pages,
        itemCount: widget.images.length,
        onPageChanged: (i) => setState(() => _index = i),
        itemBuilder: (_, i) => InteractiveViewer(
          minScale: 1,
          maxScale: 5,
          child: Center(child: NetworkThumb(url: widget.images[i].url)),
        ),
      ),
    );
  }
}

/// A play / pause bar for one audio file: a voice note in the chat, or a client's call recording on the Overview.
class AudioNote extends ConsumerStatefulWidget {
  const AudioNote({super.key, required this.url, this.label, this.onDark = false});

  final String url;

  /// A caption under the bar (the file name); a chat voice note has none.
  final String? label;

  /// True on the red "my message" bubble, where the bar is drawn in white.
  final bool onDark;

  @override
  ConsumerState<AudioNote> createState() => _AudioNoteState();
}

class _AudioNoteState extends ConsumerState<AudioNote> {
  // Only one recording plays at a time: starting another pauses this.
  static _AudioNoteState? _active;

  AudioPlaying? _player;
  final List<StreamSubscription<Object?>> _subs = [];
  bool _playing = false;
  bool _loaded = false;
  Duration _position = Duration.zero;
  Duration _total = Duration.zero;

  AudioPlaying _ensurePlayer() {
    final existing = _player;
    if (existing != null) return existing;

    final player = ref.read(audioPlayingFactoryProvider)();
    _player = player;
    _subs
      ..add(player.onPosition.listen((p) => mounted ? setState(() => _position = p) : null))
      ..add(player.onDuration.listen((d) => mounted ? setState(() => _total = d) : null))
      ..add(player.onPlaying.listen((p) => mounted ? setState(() => _playing = p) : null))
      ..add(player.onComplete.listen((_) => mounted ? setState(() {
            _playing = false;
            _position = Duration.zero;
            _loaded = false;
          }) : null));

    return player;
  }

  Future<void> _toggle() async {
    final player = _ensurePlayer();

    if (_playing) {
      await player.pause();
      return;
    }

    if (_active != this) await _active?._player?.pause();
    _active = this;

    try {
      if (_loaded) {
        await player.resume();
      } else {
        await player.play(widget.url);
        _loaded = true;
      }
    } catch (_) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Could not play this audio.')));
    }
  }

  @override
  void dispose() {
    if (_active == this) _active = null;
    for (final s in _subs) {
      s.cancel();
    }
    _player?.dispose();
    super.dispose();
  }

  String _clock(Duration d) => '${d.inMinutes}:${(d.inSeconds % 60).toString().padLeft(2, '0')}';

  @override
  Widget build(BuildContext context) {
    final primary = Theme.of(context).colorScheme.primary;
    final fg = widget.onDark ? Colors.white : primary;
    final track = widget.onDark ? Colors.white30 : const Color(0xFFFEE4E2);
    final value = _total.inMilliseconds == 0 ? 0.0 : (_position.inMilliseconds / _total.inMilliseconds).clamp(0.0, 1.0);

    return Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisSize: MainAxisSize.min, children: [
      Row(children: [
        IconButton(
          key: const Key('audio-toggle'),
          visualDensity: VisualDensity.compact,
          padding: EdgeInsets.zero,
          constraints: const BoxConstraints.tightFor(width: 36, height: 36),
          style: IconButton.styleFrom(backgroundColor: widget.onDark ? Colors.white24 : primary.withValues(alpha: 0.1)),
          icon: Icon(_playing ? Icons.pause_rounded : Icons.play_arrow_rounded, color: fg, size: 24),
          onPressed: _toggle,
        ),
        const SizedBox(width: 10),
        Expanded(child: LinearProgressIndicator(value: value, minHeight: 4, borderRadius: BorderRadius.circular(4), backgroundColor: track, color: fg)),
        const SizedBox(width: 10),
        Text(_clock(_playing || _position > Duration.zero ? _position : _total), style: TextStyle(fontSize: 11.5, fontWeight: FontWeight.w700, color: widget.onDark ? Colors.white70 : _muted)),
      ]),
      if (widget.label != null) Padding(padding: const EdgeInsets.only(top: 2, left: 2), child: Text(widget.label!, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 10.5, color: _muted))),
    ]);
  }
}

/// The Overview's "Reference files" (what the BD attached, per upload field) and "Submitted work" (the designer's
/// deliveries). Draws nothing when the task has neither.
class AttachmentsCard extends StatelessWidget {
  const AttachmentsCard({super.key, required this.files});

  final TaskFiles files;

  @override
  Widget build(BuildContext context) {
    if (files.isEmpty) return const SizedBox.shrink();

    Widget heading(String text) => Padding(
          padding: const EdgeInsets.only(bottom: 6),
          child: Text(text.toUpperCase(), style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w800, letterSpacing: 0.6, color: Color(0xFF98A2B3))),
        );

    Widget wrap(List<TaskFile> list) => Wrap(spacing: 8, runSpacing: 8, children: [for (final f in list) FileTile(file: f, gallery: [for (final i in list) if (i.isImage) i])]);

    return Container(
      key: const Key('attachments-card'),
      padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16), border: Border.all(color: _line)),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        if (files.submissions.isNotEmpty) ...[
          heading('Submitted work'),
          for (var i = 0; i < files.submissions.length; i++) ...[
            if (i > 0) const SizedBox(height: 10),
            Padding(
              padding: const EdgeInsets.only(bottom: 6),
              child: Text(
                [
                  if (files.submissions[i].submittedAt != null) DateFormat('d MMM, h:mm a').format(files.submissions[i].submittedAt!),
                  '${files.submissions[i].completed} creatives done',
                ].join(' · '),
                style: const TextStyle(fontSize: 12.5, fontWeight: FontWeight.w700, color: Color(0xFF344054)),
              ),
            ),
            wrap(files.submissions[i].files),
          ],
        ],
        if (files.submissions.isNotEmpty && files.groups.isNotEmpty) const Padding(padding: EdgeInsets.symmetric(vertical: 12), child: Divider(height: 1, color: _line)),
        if (files.groups.isNotEmpty) ...[
          heading('Reference files'),
          for (var i = 0; i < files.groups.length; i++) ...[
            if (i > 0) const SizedBox(height: 10),
            Padding(padding: const EdgeInsets.only(bottom: 6), child: Text(files.groups[i].label, style: const TextStyle(fontSize: 12.5, fontWeight: FontWeight.w700, color: Color(0xFF344054)))),
            wrap(files.groups[i].files),
          ],
        ],
      ]),
    );
  }
}
