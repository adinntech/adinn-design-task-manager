import 'package:flutter/material.dart';

/// The Adinn representative (designer-supplied artwork, processed copy in assets/tasks).
class DesignerCharacterWidget extends StatelessWidget {
  const DesignerCharacterWidget({super.key, required this.height});

  static const asset = 'assets/tasks/adinn_rep.png';

  /// width / height of the artwork.
  static const aspect = 2 / 3;

  final double height;

  @override
  Widget build(BuildContext context) {
    return Image.asset(
      asset,
      key: const Key('designer-character'),
      height: height,
      width: height * aspect,
      fit: BoxFit.contain,
      filterQuality: FilterQuality.medium,
      gaplessPlayback: true,
      semanticLabel: 'Adinn representative',
    );
  }
}

/// One of his two hands, gripping the edge of the cards. The artwork is the designer-supplied pair of hands (a cuff, a
/// thumb on top, fingers curled over the edge); the left one's fingers point right, the right one's point left.
class GrippingHand extends StatelessWidget {
  const GrippingHand({super.key, required this.left, required this.height});

  static const leftAsset = 'assets/tasks/hand_left.png';
  static const rightAsset = 'assets/tasks/hand_right.png';

  /// width / height of the artwork.
  static const aspect = 233 / 275;

  final bool left;
  final double height;

  @override
  Widget build(BuildContext context) => IgnorePointer(
        child: Image.asset(
          left ? leftAsset : rightAsset,
          key: Key(left ? 'designer-hand-left' : 'designer-hand-right'),
          height: height,
          width: height * aspect,
          fit: BoxFit.contain,
          filterQuality: FilterQuality.medium,
          gaplessPlayback: true,
          excludeFromSemantics: true,
        ),
      );
}
