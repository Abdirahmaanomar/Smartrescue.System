import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:url_launcher/url_launcher.dart';


class AppHelpers {
  static String formatDate(String dateStr) {
    try {
      final dt = DateTime.parse(dateStr);
      return DateFormat('MMM dd, HH:mm').format(dt);
    } catch (_) {
      return dateStr;
    }
  }

  static String timeAgo(String dateStr) {
    try {
      final dt = DateTime.parse(dateStr);
      final diff = DateTime.now().difference(dt);
      if (diff.inSeconds < 60) return '${diff.inSeconds}s ago';
      if (diff.inMinutes < 60) return '${diff.inMinutes}m ago';
      if (diff.inHours < 24) return '${diff.inHours}h ago';
      return '${diff.inDays}d ago';
    } catch (_) {
      return '';
    }
  }

  static Color statusColor(String status) {
    switch (status) {
      case 'pending':   return const Color(0xFFF59E0B);
      case 'accepted':  return const Color(0xFF3B82F6);
      case 'en_route':  return const Color(0xFF10B981);
      case 'arrived':   return const Color(0xFF1E40AF);
      case 'completed': return const Color(0xFF10B981);
      default:          return const Color(0xFF64748B);
    }
  }

  static Color emergencyColor(String type) {
    switch (type.toLowerCase()) {
      case 'medical':  return const Color(0xFF2563EB);
      case 'fire':     return const Color(0xFFF59E0B);
      case 'police':   return const Color(0xFF06B6D4);
      case 'accident': return const Color(0xFFEF4444);
      default:         return const Color(0xFF64748B);
    }
  }

  static IconData emergencyIcon(String type) {
    switch (type.toLowerCase()) {
      case 'medical':  return Icons.local_hospital_rounded;
      case 'fire':     return Icons.local_fire_department_rounded;
      case 'police':   return Icons.local_police_rounded;
      case 'accident': return Icons.car_crash_rounded;
      default:         return Icons.warning_rounded;
    }
  }

  static String statusLabel(String status) {
    switch (status) {
      case 'pending':   return 'Awaiting Response';
      case 'accepted':  return 'Accepted';
      case 'en_route':  return 'En Route';
      case 'arrived':   return 'Arrived';
      case 'completed': return 'Completed';
      default:          return status;
    }
  }

  static String initials(String name) {
    if (name.isEmpty) return '?';
    final parts = name.trim().split(' ');
    if (parts.length >= 2) return '${parts[0][0]}${parts[1][0]}'.toUpperCase();
    return name[0].toUpperCase();
  }

  static void showSnack(BuildContext context, String message, {bool isError = false}) {
    String cleanMessage = message;
    if (cleanMessage.contains('TimeoutException') || cleanMessage.contains('Future not completed')) {
      cleanMessage = 'Server response timed out. Please check your network connection or server status.';
    } else if (cleanMessage.contains('SocketException') || cleanMessage.contains('Failed host lookup')) {
      cleanMessage = 'Unable to connect to server. Please check your internet connection.';
    }
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(cleanMessage, style: const TextStyle(fontWeight: FontWeight.w600)),
        backgroundColor: isError ? const Color(0xFFEF4444) : const Color(0xFF10B981),
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        margin: const EdgeInsets.all(16),
      ),
    );
  }

  static Future<void> makePhoneCall(BuildContext context, String phone) async {
    if (phone.isEmpty) {
      showSnack(context, 'Phone number is empty', isError: true);
      return;
    }
    // Remove formatting characters: spaces, dashes, parentheses
    final formattedPhone = phone.replaceAll(RegExp(r'\s+|-|\(|\)'), '');

    showSnack(context, 'Opening dialer for $formattedPhone...');

    final url = Uri.parse('tel:$formattedPhone');
    try {
      final success = await launchUrl(url, mode: LaunchMode.externalApplication);
      if (!success && context.mounted) {
        showSnack(context, 'Could not place call to $formattedPhone. Please dial manually.', isError: true);
      }
    } catch (e) {
      if (context.mounted) {
        showSnack(context, 'Error placing call: $e', isError: true);
      }
    }
  }

  static Future<void> openWhatsApp(BuildContext context, String phone) async {
    if (phone.isEmpty) {
      showSnack(context, 'WhatsApp number is empty', isError: true);
      return;
    }
    // Format number to digits only (e.g. +252 61... -> 25261...)
    final formattedPhone = phone.replaceAll(RegExp(r'\s+|-|\(|\)|\+'), '');
    final url = Uri.parse('https://wa.me/$formattedPhone');
    try {
      final success = await launchUrl(url, mode: LaunchMode.externalApplication);
      if (!success && context.mounted) {
        showSnack(context, 'Could not open WhatsApp', isError: true);
      }
    } catch (e) {
      if (context.mounted) {
        showSnack(context, 'Error opening WhatsApp: $e', isError: true);
      }
    }
  }

  static Future<void> openEmail(BuildContext context, String email) async {
    if (email.isEmpty) {
      showSnack(context, 'Email address is empty', isError: true);
      return;
    }
    final url = Uri.parse('mailto:$email?subject=SmartRescue%20Support');
    try {
      final success = await launchUrl(url);
      if (!success && context.mounted) {
        showSnack(context, 'Could not open mail app', isError: true);
      }
    } catch (e) {
      if (context.mounted) {
        showSnack(context, 'Error opening mail app: $e', isError: true);
      }
    }
  }
}

