import 'package:flutter/material.dart';

enum ButtonType { primary, secondary, outline, text }

class CustomButton extends StatelessWidget {
  final String label;
  final VoidCallback? onPressed;
  final ButtonType type;
  final bool isLoading;
  final IconData? icon;
  final Color? color;
  final Color? textColor;
  final double? width;
  final EdgeInsets? padding;
  final double borderRadius;
  final double elevation;

  const CustomButton({
    super.key,
    required this.label,
    this.onPressed,
    this.type = ButtonType.primary,
    this.isLoading = false,
    this.icon,
    this.color,
    this.textColor,
    this.width,
    this.padding,
    this.borderRadius = 14.0,
    this.elevation = 0.0,
  });

  @override
  Widget build(BuildContext context) {
    final primaryColor = color ?? const Color(0xFF2563EB); // Default premium blue
    
    // Determine colors based on button type
    Color getBackgroundColor() {
      if (onPressed == null && type != ButtonType.outline && type != ButtonType.text) {
        return Colors.grey.shade300;
      }
      switch (type) {
        case ButtonType.primary:
          return primaryColor;
        case ButtonType.secondary:
          return primaryColor.withValues(alpha: 0.1);
        case ButtonType.outline:
        case ButtonType.text:
          return Colors.transparent;
      }
    }

    Color getForegroundColor() {
      if (onPressed == null) {
        return Colors.grey.shade500;
      }
      if (textColor != null) return textColor!;
      
      switch (type) {
        case ButtonType.primary:
          return Colors.white;
        case ButtonType.secondary:
        case ButtonType.outline:
        case ButtonType.text:
          return primaryColor;
      }
    }

    BorderSide? getBorder() {
      if (type == ButtonType.outline) {
        return BorderSide(
          color: onPressed == null ? Colors.grey.shade300 : primaryColor,
          width: 1.5,
        );
      }
      return null;
    }

    final btnStyle = ElevatedButton.styleFrom(
      backgroundColor: getBackgroundColor(),
      foregroundColor: getForegroundColor(),
      elevation: type == ButtonType.primary ? elevation : 0,
      padding: padding ?? const EdgeInsets.symmetric(vertical: 16, horizontal: 24),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(borderRadius),
        side: getBorder() ?? BorderSide.none,
      ),
    );

    Widget buttonChild = Row(
      mainAxisSize: MainAxisSize.min,
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        if (isLoading) ...[
          SizedBox(
            width: 20,
            height: 20,
            child: CircularProgressIndicator(
              strokeWidth: 2.5,
              valueColor: AlwaysStoppedAnimation<Color>(getForegroundColor()),
            ),
          ),
          const SizedBox(width: 12),
        ] else if (icon != null) ...[
          Icon(icon, size: 20),
          const SizedBox(width: 8),
        ],
        Text(
          label,
          style: TextStyle(
            fontSize: 15,
            fontWeight: FontWeight.w700,
            letterSpacing: 0.3,
            color: getForegroundColor(),
          ),
        ),
      ],
    );

    Widget button;
    switch (type) {
      case ButtonType.text:
        button = TextButton(
          onPressed: isLoading ? null : onPressed,
          style: TextButton.styleFrom(
            foregroundColor: getForegroundColor(),
            padding: padding ?? const EdgeInsets.symmetric(vertical: 16, horizontal: 24),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(borderRadius),
            ),
          ),
          child: buttonChild,
        );
        break;
      case ButtonType.outline:
        button = OutlinedButton(
          onPressed: isLoading ? null : onPressed,
          style: btnStyle,
          child: buttonChild,
        );
        break;
      default:
        button = ElevatedButton(
          onPressed: isLoading ? null : onPressed,
          style: btnStyle,
          child: buttonChild,
        );
    }

    return SizedBox(
      width: width ?? double.infinity,
      child: button,
    );
  }
}
