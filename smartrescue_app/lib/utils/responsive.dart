import 'package:flutter/material.dart';

/// Lightweight responsive helper.
/// Usage: final r = Responsive(context);  then r.sp(16) / r.wp(0.9) / r.hp(0.1)
class Responsive {
  final BuildContext context;

  const Responsive(this.context);

  /// Screen width
  double get screenWidth => MediaQuery.of(context).size.width;

  /// Screen height
  double get screenHeight => MediaQuery.of(context).size.height;

  /// Proportion of screen width  (0.0 – 1.0)
  double wp(double fraction) => screenWidth * fraction;

  /// Proportion of screen height (0.0 – 1.0)
  double hp(double fraction) => screenHeight * fraction;

  /// Scaled font size based on screen width relative to 390px (iPhone 14 base)
  double sp(double size) => size * (screenWidth / 390).clamp(0.75, 1.4);

  /// Scaled size (for icons, boxes) based on screen width
  double dp(double size) => size * (screenWidth / 390).clamp(0.75, 1.35);

  /// Adaptive horizontal padding (tighter on small phones, larger on wide screens)
  double get hPad {
    if (screenWidth < 360) return 14;
    if (screenWidth < 400) return 18;
    return 24;
  }

  /// True when the device width is tablet-like (>= 600)
  bool get isTablet => screenWidth >= 600;

  /// True when we're on a compact phone (< 360px)
  bool get isCompact => screenWidth < 360;

  /// Grid cross-axis count for emergency type cards
  int get typeCardColumns => screenWidth >= 800 ? 4 : 2;

  /// Aspect ratio for type-card grid cells
  double get typeCardAspectRatio {
    if (screenWidth >= 800) return 2.2;
    if (screenWidth < 360) return 1.15;
    return 1.3;
  }

  /// Wrap screen contents to restrict max width and center it on large screens (tablets/laptops)
  Widget wrapWidescreen(Widget child, {double maxWidth = 900, Alignment alignment = Alignment.topCenter}) {
    return Align(
      alignment: alignment,
      child: ConstrainedBox(
        constraints: BoxConstraints(maxWidth: maxWidth),
        child: child,
      ),
    );
  }
}

