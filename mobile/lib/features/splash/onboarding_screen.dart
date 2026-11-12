import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../../core/theme.dart';
import '../kanban/stage3d/designer_character.dart';
import '../kanban/stage3d/held_card.dart';
import '../kanban/stage3d/stage_meta.dart';

/// The welcome tour shown on first launch: three pages, each with a small animated illustration, a title, a line of text,
/// page dots, Continue and Skip ("Get started" on the last page). Everything is drawn in code, no video.
class OnboardingScreen extends StatefulWidget {
  const OnboardingScreen({super.key, required this.onDone});

  final VoidCallback onDone;

  @override
  State<OnboardingScreen> createState() => _OnboardingScreenState();
}

class _Page {
  const _Page(this.title, this.body, this.art);

  final String title;
  final String body;
  final Widget Function(Animation<double> t) art;
}

class _OnboardingScreenState extends State<OnboardingScreen> with SingleTickerProviderStateMixin {
  final _pages = PageController();
  // One slow clock drives every illustration's floating, swinging and sliding.
  late final AnimationController _loop = AnimationController(vsync: this, duration: const Duration(seconds: 6))..repeat();
  int _index = 0;

  static final _content = <_Page>[
    _Page('Welcome to Adinn\nDesign Tasks', 'Request, follow and approve creative work with the design team, all in one place.', (t) => _WelcomeArt(t: t)),
    _Page('Create tasks in seconds', 'Pick the vertical, add the brief and files, set the due date. Your designer is notified at once.', (t) => _TasksArt(t: t)),
    _Page('Always in the loop', 'Get a nudge when work moves, a comment arrives or something needs your approval.', (t) => _AlertsArt(t: t)),
  ];

  bool get _last => _index == _content.length - 1;

  void _next() {
    if (_last) {
      widget.onDone();
    } else {
      _pages.nextPage(duration: const Duration(milliseconds: 420), curve: Curves.easeInOutCubic);
    }
  }

  @override
  void dispose() {
    _pages.dispose();
    _loop.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final bottom = MediaQuery.paddingOf(context).bottom;
    return Material(
      color: Colors.white,
      child: Column(
        children: [
          Expanded(
            child: PageView.builder(
              controller: _pages,
              // Exactly the three pages: no pulling past the first or last one into empty space.
              physics: const PageScrollPhysics(parent: ClampingScrollPhysics()),
              itemCount: _content.length,
              onPageChanged: (i) => setState(() => _index = i),
              itemBuilder: (context, i) => _PageView(page: _content[i], loop: _loop, pages: _pages, index: i),
            ),
          ),
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              for (var i = 0; i < _content.length; i++)
                AnimatedContainer(
                  duration: const Duration(milliseconds: 250),
                  margin: const EdgeInsets.symmetric(horizontal: 4),
                  width: i == _index ? 22 : 8,
                  height: 8,
                  decoration: BoxDecoration(color: i == _index ? adinnRed : const Color(0xFFD9DCE3), borderRadius: BorderRadius.circular(4)),
                ),
            ],
          ),
          const SizedBox(height: 28),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 28),
            child: SizedBox(
              width: double.infinity,
              height: 52,
              child: FilledButton(
                key: const Key('onboarding-continue'),
                style: FilledButton.styleFrom(backgroundColor: adinnRed, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14))),
                onPressed: _next,
                child: Text(_last ? 'Get started' : 'Continue', style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600)),
              ),
            ),
          ),
          SizedBox(
            height: 52,
            child: AnimatedOpacity(
              duration: const Duration(milliseconds: 200),
              opacity: _last ? 0 : 1,
              child: TextButton(key: const Key('onboarding-skip'), onPressed: _last ? null : widget.onDone, child: const Text('Skip', style: TextStyle(color: adinnRed, fontWeight: FontWeight.w600))),
            ),
          ),
          SizedBox(height: 8 + bottom),
        ],
      ),
    );
  }
}

class _PageView extends StatelessWidget {
  const _PageView({required this.page, required this.loop, required this.pages, required this.index});

  final _Page page;
  final Animation<double> loop;
  final PageController pages;
  final int index;

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: pages,
      builder: (context, _) {
        // Parallax: the art drifts a little against the swipe, the text follows the finger.
        final p = pages.hasClients && pages.position.haveDimensions ? (pages.page ?? index.toDouble()) - index : 0.0;
        final w = MediaQuery.sizeOf(context).width;
        return Column(
          children: [
            Expanded(
              flex: 6,
              child: ClipPath(
                clipper: const _BlobClipper(),
                child: DecoratedBox(
                  // The pink background stays put; only the illustration drifts, so no white gap opens up beside it.
                  decoration: const BoxDecoration(gradient: LinearGradient(begin: Alignment.topCenter, end: Alignment.bottomCenter, colors: [Color(0xFFFFE8EA), Color(0xFFFFF6F6)])),
                  child: SizedBox.expand(
                    child: ClipRect(child: Transform.translate(offset: Offset(-p * w * 0.12, 0), child: page.art(loop))),
                  ),
                ),
              ),
            ),
            Expanded(
              flex: 4,
              child: Transform.translate(
                offset: Offset(-p * w * 0.05, 0),
                child: Opacity(
                  opacity: (1 - p.abs() * 1.6).clamp(0.0, 1.0),
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(32, 28, 32, 0),
                    // Scales down on very short screens instead of overflowing.
                    child: FittedBox(
                      fit: BoxFit.scaleDown,
                      alignment: Alignment.topCenter,
                      child: SizedBox(
                        width: w - 64,
                        child: Column(
                          children: [
                            Text(page.title, textAlign: TextAlign.center, style: const TextStyle(fontSize: 26, height: 1.2, fontWeight: FontWeight.w800, color: Color(0xFF16161A))),
                            const SizedBox(height: 14),
                            Text(page.body, textAlign: TextAlign.center, style: const TextStyle(fontSize: 15, height: 1.45, color: Color(0xFF6B6F7A))),
                          ],
                        ),
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ],
        );
      },
    );
  }
}

/// The curved lower edge under the illustration.
class _BlobClipper extends CustomClipper<Path> {
  const _BlobClipper();

  @override
  Path getClip(Size s) => Path()
    ..lineTo(0, s.height - 46)
    ..quadraticBezierTo(s.width / 2, s.height + 30, s.width, s.height - 46)
    ..lineTo(s.width, 0)
    ..close();

  @override
  bool shouldReclip(_BlobClipper old) => false;
}

double _wave(Animation<double> t, {double phase = 0, double speed = 1}) => math.sin((t.value * speed + phase) * 2 * math.pi);

/// Page 1: the Adinn representative, floating chips around him (brush, pencil, picture).
class _WelcomeArt extends StatelessWidget {
  const _WelcomeArt({required this.t});

  final Animation<double> t;

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: t,
      builder: (context, _) {
        return LayoutBuilder(builder: (context, box) {
          final w = box.maxWidth, h = box.maxHeight;
          Widget chip(IconData icon, double x, double y, double phase, Color color) => Positioned(
                left: x * w - 24,
                top: y * h + _wave(t, phase: phase) * 8 - 24,
                child: Container(
                  width: 48,
                  height: 48,
                  decoration: BoxDecoration(color: Colors.white, shape: BoxShape.circle, boxShadow: [BoxShadow(color: color.withValues(alpha: 0.25), blurRadius: 14, offset: const Offset(0, 6))]),
                  child: Icon(icon, color: color, size: 24),
                ),
              );
          return Stack(
            clipBehavior: Clip.none,
            children: [
              Positioned(left: w * 0.12, top: h * 0.12, child: _Dot(size: 14, color: adinnRed.withValues(alpha: 0.25), dy: _wave(t, phase: 0.3) * 6)),
              Positioned(right: w * 0.14, top: h * 0.3, child: _Dot(size: 10, color: adinnRed.withValues(alpha: 0.35), dy: _wave(t, phase: 0.7) * 6)),
              // The representative holding the stack of cards, exactly as on the My Tasks screen.
              Align(
                alignment: Alignment.bottomCenter,
                child: Transform.translate(
                  offset: Offset(0, _wave(t) * 3),
                  child: _HeldStack(width: w, height: h - 52),
                ),
              ),
              chip(Icons.brush_rounded, 0.09, 0.34, 0.0, adinnRed),
              chip(Icons.edit_rounded, 0.91, 0.30, 0.35, const Color(0xFF1B1B1F)),
              chip(Icons.image_rounded, 0.10, 0.62, 0.6, const Color(0xFF2E7DF6)),
            ],
          );
        });
      },
    );
  }
}

class _Dot extends StatelessWidget {
  const _Dot({required this.size, required this.color, required this.dy});

  final double size, dy;
  final Color color;

  @override
  Widget build(BuildContext context) => Transform.translate(offset: Offset(0, dy), child: Container(width: size, height: size, decoration: BoxDecoration(color: color, shape: BoxShape.circle)));
}

/// Page 2: task cards that float.
class _TasksArt extends StatelessWidget {
  const _TasksArt({required this.t});

  final Animation<double> t;

  static const _cards = [
    ('Outdoor hoarding, Chennai', 'In progress', Color(0xFF2E7DF6)),
    ('Roadshow vehicle wrap', 'Review', Color(0xFFF5A524)),
    ('Festive pop offset set', 'Completed', Color(0xFF1FA463)),
  ];

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: t,
      builder: (context, _) {
        return Center(
          child: FractionallySizedBox(
            widthFactor: 0.74,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                for (var i = 0; i < _cards.length; i++)
                  Transform.translate(
                    offset: Offset(_wave(t, phase: i * 0.18) * 6 + (i.isEven ? -10 : 10), 0),
                    child: Container(
                      margin: const EdgeInsets.symmetric(vertical: 8),
                      padding: const EdgeInsets.all(14),
                      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16), boxShadow: const [BoxShadow(color: Color(0x1A000000), blurRadius: 16, offset: Offset(0, 6))]),
                      child: Row(
                        children: [
                          Container(width: 5, height: 40, decoration: BoxDecoration(color: _cards[i].$3, borderRadius: BorderRadius.circular(3))),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(_cards[i].$1, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14, color: Color(0xFF16161A))),
                                const SizedBox(height: 6),
                                Container(padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3), decoration: BoxDecoration(color: _cards[i].$3.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(8)), child: Text(_cards[i].$2, style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: _cards[i].$3))),
                              ],
                            ),
                          ),
                          if (i == 2) const Icon(Icons.check_circle_rounded, color: Color(0xFF1FA463)),
                        ],
                      ),
                    ),
                  ),
              ],
            ),
          ),
        );
      },
    );
  }
}

/// Page 3: a swinging bell with a badge, and notices floating around it.
class _AlertsArt extends StatelessWidget {
  const _AlertsArt({required this.t});

  final Animation<double> t;

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: t,
      builder: (context, _) {
        final swing = _wave(t, speed: 3) * 0.22 * (0.5 + 0.5 * math.max(0, math.sin(t.value * 2 * math.pi)));
        final ring = (t.value * 2) % 1.0;
        Widget note(String text, IconData icon, Color color, double phase) {
          return Transform.translate(
            offset: Offset(0, _wave(t, phase: phase) * 6),
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
              decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(14), boxShadow: const [BoxShadow(color: Color(0x1A000000), blurRadius: 14, offset: Offset(0, 5))]),
              child: Row(mainAxisSize: MainAxisSize.min, children: [Icon(icon, size: 16, color: color), const SizedBox(width: 6), Text(text, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: Color(0xFF16161A)))]),
            ),
          );
        }

        return Stack(
          alignment: Alignment.center,
          children: [
            // Expanding ring behind the bell.
            Container(width: 110 + 90 * ring, height: 110 + 90 * ring, decoration: BoxDecoration(shape: BoxShape.circle, border: Border.all(color: adinnRed.withValues(alpha: 0.35 * (1 - ring)), width: 2))),
            Container(width: 104, height: 104, decoration: const BoxDecoration(color: Colors.white, shape: BoxShape.circle, boxShadow: [BoxShadow(color: Color(0x33E30613), blurRadius: 24, offset: Offset(0, 10))])),
            Transform.rotate(angle: swing, alignment: Alignment.topCenter, child: const Icon(Icons.notifications_rounded, size: 58, color: adinnRed)),
            Align(
              alignment: const Alignment(0.2, -0.2),
              child: Container(width: 26, height: 26, alignment: Alignment.center, decoration: const BoxDecoration(color: Color(0xFF16161A), shape: BoxShape.circle), child: const Text('3', style: TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w800))),
            ),
            Align(alignment: const Alignment(-0.85, -0.7), child: note('Moved to Review', Icons.swap_horiz_rounded, const Color(0xFFF5A524), 0.1)),
            Align(alignment: const Alignment(0.9, 0.62), child: note('New comment', Icons.chat_bubble_rounded, const Color(0xFF2E7DF6), 0.5)),
            Align(alignment: const Alignment(-0.7, 0.8), child: note('Approved', Icons.verified_rounded, const Color(0xFF1FA463), 0.8)),
          ],
        );
      },
    );
  }
}

/// The man from My Tasks holding the big "All Tickets" card with the rest of the stack peeking out behind it and his two
/// gripping hands on the edges. Same proportions as there (all in units of his height); only his top part is shown, fading
/// out at the bottom.
class _HeldStack extends StatelessWidget {
  const _HeldStack({required this.width, required this.height});

  final double width, height;

  /// How much of him is shown (the card ends at about 68% of his height).
  static const _shown = 0.80;

  @override
  Widget build(BuildContext context) {
    final u = math.min(height / _shown, width * 1.95);
    final side = math.min(0.38 * u, width * 0.6);
    final cardTop = 0.30 * u;
    final hh = u * 0.072;
    final hw = hh * GrippingHand.aspect;
    final handTop = u * 0.382 - hh / 2;
    final cardLeft = width / 2 - side / 2;
    final cardRight = width / 2 + side / 2;
    const all = StageInfo(key: allStageKey, label: 'All Tickets', count: 43);
    final edges = [stageAccent('in_progress'), stageAccent('waiting_confirmation'), stageAccent('rework')];

    return SizedBox(
      width: width,
      height: u * _shown,
      child: ShaderMask(
        blendMode: BlendMode.dstIn,
        shaderCallback: (r) => const LinearGradient(begin: Alignment.topCenter, end: Alignment.bottomCenter, colors: [Colors.white, Colors.white, Color(0x00FFFFFF)], stops: [0, 0.9, 1]).createShader(r),
        child: ClipRect(
          child: OverflowBox(
            alignment: Alignment.topLeft,
            minWidth: 0,
            maxWidth: width,
            minHeight: 0,
            maxHeight: u,
            child: SizedBox(
              width: width,
              height: u,
              child: Stack(
                clipBehavior: Clip.none,
                children: [
                  Positioned(left: width / 2 - 0.333 * u, top: 0, child: DesignerCharacterWidget(height: u)),
                  Positioned(left: cardLeft, top: cardTop, child: HeldCardEdges(side: side, colors: edges)),
                  Positioned(left: 0, width: width, top: cardTop - side * 0.05, height: side * 1.1, child: Center(child: HeldStageCard(stage: all, side: side, hero: false))),
                  Positioned(left: cardLeft - hw * 0.46, top: handTop, child: GrippingHand(left: true, height: hh)),
                  Positioned(left: cardRight + 3 * 0.06 * side - hw * 0.54, top: handTop, child: GrippingHand(left: false, height: hh)),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
