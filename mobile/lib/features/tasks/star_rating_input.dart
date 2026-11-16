import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

/// Five-star rating input in half-star steps: tap a star (left half = x.5, right half = full) or press and
/// drag across the row. The star under the finger lifts and grows while it is held, and each star fills
/// smoothly instead of jumping.
class StarRatingInput extends StatefulWidget {
  const StarRatingInput({super.key, required this.value, required this.onChanged, this.maxStarSize = 50, this.verticalRoom = 16});

  /// Current rating, 0.5–5 in half steps.
  final double value;
  final ValueChanged<double> onChanged;
  final double maxStarSize;

  /// Space above and below the stars so the lifted star is never clipped; also part of the touch area.
  final double verticalRoom;

  static const double min = 0.5;
  static const double max = 5;

  @override
  State<StarRatingInput> createState() => _StarRatingInputState();
}

class _StarRatingInputState extends State<StarRatingInput> {
  static const _gold = Color(0xFFF5A623);
  static const _empty = Color(0xFFE4E7EC);
  static const _gap = 6.0;
  static const _fillDuration = Duration(milliseconds: 140);

  bool _held = false;

  /// Maps a touch position inside the row to a half-star value.
  double _valueAt(double dx, double starSize, double leftInset) {
    final cell = starSize + _gap;
    final stars = (dx - leftInset) / cell;
    return ((stars * 2).ceil() / 2).clamp(StarRatingInput.min, StarRatingInput.max).toDouble();
  }

  void _set(double next) {
    if (next == widget.value) return;
    HapticFeedback.selectionClick();
    widget.onChanged(next);
  }

  void _hold(bool held) {
    if (_held != held) setState(() => _held = held);
  }

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(builder: (context, box) {
      final available = box.maxWidth.isFinite ? box.maxWidth : 5 * widget.maxStarSize + 4 * _gap;
      final starSize = ((available - 4 * _gap) / 5).clamp(20.0, widget.maxStarSize).toDouble();
      final rowWidth = 5 * starSize + 4 * _gap;
      final leftInset = (available - rowWidth) / 2;
      final activeStar = widget.value.ceil();

      return Semantics(
        slider: true,
        label: 'Rating',
        value: '${widget.value} out of 5 stars',
        increasedValue: '${(widget.value + 0.5).clamp(StarRatingInput.min, StarRatingInput.max)}',
        decreasedValue: '${(widget.value - 0.5).clamp(StarRatingInput.min, StarRatingInput.max)}',
        onIncrease: () => _set((widget.value + 0.5).clamp(StarRatingInput.min, StarRatingInput.max).toDouble()),
        onDecrease: () => _set((widget.value - 0.5).clamp(StarRatingInput.min, StarRatingInput.max).toDouble()),
        child: GestureDetector(
          behavior: HitTestBehavior.opaque,
          onTapDown: (d) {
            _hold(true);
            _set(_valueAt(d.localPosition.dx, starSize, leftInset));
          },
          onTapUp: (_) => _hold(false),
          onTapCancel: () => _hold(false),
          onHorizontalDragStart: (d) {
            _hold(true);
            _set(_valueAt(d.localPosition.dx, starSize, leftInset));
          },
          onHorizontalDragUpdate: (d) => _set(_valueAt(d.localPosition.dx, starSize, leftInset)),
          onHorizontalDragEnd: (_) => _hold(false),
          onHorizontalDragCancel: () => _hold(false),
          child: SizedBox(
            width: double.infinity,
            height: starSize + widget.verticalRoom,
            child: Center(
              child: SizedBox(
                width: rowWidth,
                height: starSize,
                child: Row(
                  children: [
                    for (var i = 1; i <= 5; i++) ...[
                      if (i > 1) const SizedBox(width: _gap),
                      _Star(
                        key: Key('star-$i'),
                        size: starSize,
                        fill: (widget.value - (i - 1)).clamp(0.0, 1.0).toDouble(),
                        lifted: _held && i == activeStar,
                        fillDuration: _fillDuration,
                      ),
                    ],
                  ],
                ),
              ),
            ),
          ),
        ),
      );
    });
  }
}

class _Star extends StatelessWidget {
  const _Star({super.key, required this.size, required this.fill, required this.lifted, required this.fillDuration});

  final double size;

  /// 0 = empty, 0.5 = half, 1 = full.
  final double fill;
  final bool lifted;
  final Duration fillDuration;

  @override
  Widget build(BuildContext context) {
    return AnimatedScale(
      scale: lifted ? 1.3 : 1,
      duration: const Duration(milliseconds: 150),
      curve: Curves.easeOutBack,
      child: AnimatedSlide(
        offset: lifted ? const Offset(0, -0.12) : Offset.zero,
        duration: const Duration(milliseconds: 150),
        curve: Curves.easeOut,
        child: TweenAnimationBuilder<double>(
          tween: Tween<double>(end: fill),
          duration: fillDuration,
          curve: Curves.easeOut,
          builder: (context, f, _) => SizedBox(
            width: size,
            height: size,
            child: Stack(
              children: [
                Icon(Icons.star_rounded, size: size, color: _StarRatingInputState._empty),
                ClipRect(
                  clipper: _FillClipper(f),
                  child: Icon(Icons.star_rounded, size: size, color: _StarRatingInputState._gold),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _FillClipper extends CustomClipper<Rect> {
  const _FillClipper(this.fraction);

  final double fraction;

  @override
  Rect getClip(Size size) => Rect.fromLTWH(0, 0, size.width * fraction, size.height);

  @override
  bool shouldReclip(_FillClipper old) => old.fraction != fraction;
}
