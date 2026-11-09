import 'dart:async';
import 'dart:io';

import 'package:audioplayers/audioplayers.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:path_provider/path_provider.dart';
import 'package:record/record.dart';

import 'uploads.dart';

/// The text a voice note is sent with (the API needs a non-empty comment); the chat hides it behind the player.
const voiceNoteText = 'Voice message';

/// Records the microphone to a file. Abstract so tests don't need a microphone.
abstract class VoiceRecorder {
  /// Starts recording; false when the microphone permission was refused.
  Future<bool> start();

  /// Stops and returns the recorded file's path (null when nothing was captured).
  Future<String?> stop();

  /// Stops and throws the recording away.
  Future<void> cancel();
}

class DeviceVoiceRecorder implements VoiceRecorder {
  final AudioRecorder _recorder = AudioRecorder();

  @override
  Future<bool> start() async {
    if (!await _recorder.hasPermission()) return false;

    final dir = await getTemporaryDirectory();
    final path = '${dir.path}${Platform.pathSeparator}voice_${DateTime.now().millisecondsSinceEpoch}.m4a';
    await _recorder.start(const RecordConfig(encoder: AudioEncoder.aacLc, bitRate: 64000, sampleRate: 44100, numChannels: 1), path: path);

    return true;
  }

  @override
  Future<String?> stop() => _recorder.stop();

  @override
  Future<void> cancel() async {
    await _recorder.cancel();
  }
}

/// Plays one remote audio file. Abstract for the same reason as the recorder.
abstract class AudioPlaying {
  Stream<Duration> get onPosition;
  Stream<Duration> get onDuration;
  Stream<bool> get onPlaying;
  Stream<void> get onComplete;

  Future<void> play(String url);
  Future<void> resume();
  Future<void> pause();
  Future<void> seek(Duration position);
  Future<void> dispose();
}

class DeviceAudioPlaying implements AudioPlaying {
  final AudioPlayer _player = AudioPlayer();

  @override
  Stream<Duration> get onPosition => _player.onPositionChanged;

  @override
  Stream<Duration> get onDuration => _player.onDurationChanged;

  @override
  Stream<bool> get onPlaying => _player.onPlayerStateChanged.map((s) => s == PlayerState.playing);

  @override
  Stream<void> get onComplete => _player.onPlayerComplete;

  @override
  Future<void> play(String url) => _player.play(UrlSource(url));

  @override
  Future<void> resume() => _player.resume();

  @override
  Future<void> pause() => _player.pause();

  @override
  Future<void> seek(Duration position) => _player.seek(position);

  @override
  Future<void> dispose() => _player.dispose();
}

/// A recorded file on the phone, streamed to the multipart upload like any picked file.
class FileUploadSource implements UploadSource {
  FileUploadSource(this.file);

  final File file;

  @override
  String get name => file.uri.pathSegments.last;

  @override
  Future<int> length() => file.length();

  @override
  Stream<List<int>> read(int start, int end) => file.openRead(start, end);
}

final voiceRecorderProvider = Provider<VoiceRecorder>((ref) => DeviceVoiceRecorder());

/// A factory, because every audio bubble needs its own player.
final audioPlayingFactoryProvider = Provider<AudioPlaying Function()>((ref) => DeviceAudioPlaying.new);
