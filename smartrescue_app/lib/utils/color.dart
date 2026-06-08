import 'package:flutter/material.dart';

class AppColors {
  // ─── BRAND COLORS ─────────────────────────────────────────────────────────
  static const Color primary = Color(0xFF3B82F6); // Professional Blue
  static const Color primaryDark = Color(0xFF1D4ED8);
  static const Color primaryLight = Color(0xFFEFF6FF);

  static const Color secondary = Color(0xFF0F172A); // Deep Slate Navy
  static const Color accent = Color(0xFF06B6D4); // Cyan
  
  // ─── EMERGENCY / SOS COLORS ───────────────────────────────────────────────
  static const Color sosRed = Color(0xFFEF4444); // Vibrant Red
  static const Color sosRedDark = Color(0xFFB91C1C);
  static const Color sosRedLight = Color(0xFFFEF2F2);
  
  static const Color medical = Color(0xFFEF4444); // Red
  static const Color police = Color(0xFF3B82F6); // Blue
  static const Color fire = Color(0xFFF97316); // Orange

  // ─── STATUS COLORS ────────────────────────────────────────────────────────
  static const Color success = Color(0xFF22C55E); // Green
  static const Color warning = Color(0xFFF59E0B); // Amber
  static const Color error = Color(0xFFEF4444); // Red
  
  // ─── BACKGROUNDS & SURFACES ───────────────────────────────────────────────
  static const Color background = Color(0xFFF8FAFC); // Light grayish blue
  static const Color backgroundDark = Color(0xFF0F172A);
  
  static const Color surface = Colors.white;
  static const Color surfaceDark = Color(0xFF1E293B);

  // ─── TYPOGRAPHY ───────────────────────────────────────────────────────────
  static const Color textPrimary = Color(0xFF1E293B);
  static const Color textSecondary = Color(0xFF64748B);
  static const Color textHint = Color(0xFF94A3B8);

  static const Color textPrimaryDark = Color(0xFFF1F5F9);
  static const Color textSecondaryDark = Color(0xFF94A3B8);

  // ─── BORDERS & DIVIDERS ───────────────────────────────────────────────────
  static const Color border = Color(0xFFE2E8F0);
  static const Color borderDark = Color(0xFF334155);

  // ─── PREMIUM GRADIENTS ────────────────────────────────────────────────────
  static const LinearGradient primaryGradient = LinearGradient(
    colors: [Color(0xFF60A5FA), Color(0xFF2563EB)],
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
  );

  static const LinearGradient sosGradient = LinearGradient(
    colors: [Color(0xFFF87171), Color(0xFFDC2626)],
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
  );

  static const LinearGradient successGradient = LinearGradient(
    colors: [Color(0xFF4ADE80), Color(0xFF16A34A)],
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
  );
}
