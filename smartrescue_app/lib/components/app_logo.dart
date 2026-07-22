import 'package:flutter/material.dart';
import 'package:font_awesome_flutter/font_awesome_flutter.dart';

class AppLogo extends StatelessWidget {
  final double size;
  final double borderRadius;
  final bool showBorder;
  final bool showShadow;

  const AppLogo({
    super.key,
    this.size = 80,
    this.borderRadius = 22,
    this.showBorder = true,
    this.showShadow = true,
  });

  @override
  Widget build(BuildContext context) {
    final double iconSize = size * 0.46;
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFF3B82F6), Color(0xFF1D4ED8)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(borderRadius),
        border: showBorder
            ? Border.all(
                color: Colors.white.withValues(alpha: 0.30),
                width: 2.0,
              )
            : null,
        boxShadow: showShadow
            ? [
                BoxShadow(
                  color: const Color(0xFF2563EB).withValues(alpha: 0.40),
                  blurRadius: 20,
                  offset: const Offset(0, 8),
                ),
              ]
            : null,
      ),
      child: Center(
        child: FaIcon(
          FontAwesomeIcons.suitcaseMedical,
          size: iconSize,
          color: Colors.white,
        ),
      ),
    );
  }
}
