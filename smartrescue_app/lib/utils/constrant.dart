import 'package:flutter/material.dart';

class AppConstants {
  // ─── PADDING & MARGIN ──────────────────────────────────────────────────────
  static const double paddingXS = 4.0;
  static const double paddingS = 8.0;
  static const double paddingM = 16.0;
  static const double paddingL = 24.0;
  static const double paddingXL = 32.0;

  static const double defaultPadding = 16.0;
  static const EdgeInsets defaultPaddingAll = EdgeInsets.all(defaultPadding);
  static const EdgeInsets defaultPaddingHorizontal = EdgeInsets.symmetric(horizontal: defaultPadding);

  // ─── BORDER RADIUS ─────────────────────────────────────────────────────────
  static const double radiusS = 8.0;
  static const double radiusM = 12.0;
  static const double radiusL = 16.0;
  static const double radiusXL = 20.0;
  static const double radiusXXL = 24.0;
  static const double radiusCircular = 100.0;

  static const BorderRadius defaultBorderRadius = BorderRadius.all(Radius.circular(radiusM));
  static const BorderRadius cardBorderRadius = BorderRadius.all(Radius.circular(radiusXL));

  // ─── ANIMATION DURATIONS ───────────────────────────────────────────────────
  static const Duration durationFast = Duration(milliseconds: 200);
  static const Duration durationNormal = Duration(milliseconds: 300);
  static const Duration durationSlow = Duration(milliseconds: 500);

  // ─── SIZING ────────────────────────────────────────────────────────────────
  static const double buttonHeight = 56.0;
  static const double inputHeight = 56.0;
  static const double iconSizeSmall = 16.0;
  static const double iconSizeMedium = 24.0;
  static const double iconSizeLarge = 32.0;

  // ─── SHADOWS ───────────────────────────────────────────────────────────────
  static final List<BoxShadow> defaultShadow = [
    BoxShadow(
      color: Colors.black.withValues(alpha: 0.04),
      blurRadius: 16,
      offset: const Offset(0, 4),
    ),
  ];

  static final List<BoxShadow> heavyShadow = [
    BoxShadow(
      color: Colors.black.withValues(alpha: 0.08),
      blurRadius: 24,
      offset: const Offset(0, 8),
    ),
  ];
}
