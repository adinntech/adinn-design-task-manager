// Export tool, not a real test: renders the splash reveal frame by frame to PNGs (for the video and the GIF).
//   $env:SPLASH_OUT = 'C:\some\folder'; flutter test tool/splash_export/render_frames_test.dart
import 'dart:io';
import 'dart:ui' as ui;

import 'package:adinn_tasks/features/splash/splash_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';

final _key = GlobalKey();

Future<void> _font(String family, String path) async {
  final loader = FontLoader(family)..addFont(Future.value(ByteData.sublistView(File(path).readAsBytesSync())));
  await loader.load();
}

void main() {
  testWidgets('render splash frames', (tester) async {
    final out = Platform.environment['SPLASH_OUT'];
    if (out == null) return;
    const fonts = 'C:/flutter/bin/cache/artifacts/material_fonts';
    await _font('Roboto', '$fonts/roboto-regular.ttf');
    await _font('Roboto', '$fonts/roboto-bold.ttf');

    tester.view.physicalSize = const Size(1080, 1920);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(MaterialApp(
      debugShowCheckedModeBanner: false,
      home: RepaintBoundary(key: _key, child: SplashScreen(onDone: () {})),
    ));
    // Let the artwork decode while the fake clock stands still, so the animation starts from its first frame.
    await tester.runAsync(() => Future<void>.delayed(const Duration(milliseconds: 1500)));
    await tester.pump();

    const fps = 30;
    const seconds = 5.0; // the 4.5 s reveal and a short hold on the finished logo
    Directory(out).createSync(recursive: true);
    for (var i = 0; i <= seconds * fps; i++) {
      if (i > 0) await tester.pump(const Duration(microseconds: 1000000 ~/ fps));
      await tester.runAsync(() async {
        final boundary = _key.currentContext!.findRenderObject() as RenderRepaintBoundary;
        final image = await boundary.toImage(pixelRatio: 3);
        final bytes = await image.toByteData(format: ui.ImageByteFormat.png);
        File('$out/f_${i.toString().padLeft(4, '0')}.png').writeAsBytesSync(bytes!.buffer.asUint8List());
      });
    }
  }, timeout: const Timeout(Duration(minutes: 10)));
}
