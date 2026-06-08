import 'dart:async';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:geolocator/geolocator.dart';
import '../../providers/auth_provider.dart';
import '../../providers/sos_provider.dart';
import '../../services/api_service.dart';
import '../../services/sound_service.dart';
import '../../utils/helpers.dart';
import 'user_shell.dart';
import 'user_response_timeline_screen.dart';
import '../../components/whatsapp_banner_widget.dart';
import '../../components/call_screen.dart';
import '../../utils/translator.dart';

class DashedBorderPainter extends CustomPainter {
  final Color color;
  final double strokeWidth;
  final double gap;
  final double dashLength;
  final double borderRadius;

  DashedBorderPainter({
    required this.color,
    this.strokeWidth = 1.2,
    this.gap = 4.0,
    this.dashLength = 5.0,
    this.borderRadius = 12.0,
  });

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = color
      ..strokeWidth = strokeWidth
      ..style = PaintingStyle.stroke;

    final double w = size.width;
    final double h = size.height;

    // Top edge
    double x = borderRadius;
    while (x < w - borderRadius) {
      canvas.drawLine(Offset(x, 0),
          Offset((x + dashLength).clamp(0, w - borderRadius), 0), paint);
      x += dashLength + gap;
    }

    // Bottom edge
    x = borderRadius;
    while (x < w - borderRadius) {
      canvas.drawLine(Offset(x, h),
          Offset((x + dashLength).clamp(0, w - borderRadius), h), paint);
      x += dashLength + gap;
    }

    // Left edge
    double y = borderRadius;
    while (y < h - borderRadius) {
      canvas.drawLine(Offset(0, y),
          Offset(0, (y + dashLength).clamp(0, h - borderRadius)), paint);
      y += dashLength + gap;
    }

    // Right edge
    y = borderRadius;
    while (y < h - borderRadius) {
      canvas.drawLine(Offset(w, y),
          Offset(w, (y + dashLength).clamp(0, h - borderRadius)), paint);
      y += dashLength + gap;
    }

    // Draw solid rounded corners for clean aesthetic
    final rrect = RRect.fromRectAndRadius(
      Rect.fromLTWH(0, 0, w, h),
      Radius.circular(borderRadius),
    );
    canvas.drawRRect(rrect, paint);
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}

class UserHomeScreen extends StatefulWidget {
  const UserHomeScreen({super.key});

  @override
  State<UserHomeScreen> createState() => _UserHomeScreenState();
}

class _UserHomeScreenState extends State<UserHomeScreen> {
  final TextEditingController _descController = TextEditingController();

  // Dynamic dashboard stats fields
  int _totalSosCount = 0;
  String _lastEmergencyTime = 'Never';
  String _gpsAccuracy = '±74m';

  // Digital clock properties
  late String _timeString;
  Timer? _clockTimer;

  @override
  void initState() {
    super.initState();
    _timeString = _formatDateTime(DateTime.now());
    _clockTimer = Timer.periodic(const Duration(seconds: 1), (Timer t) => _getTime());
    _loadStats();
    _loadGpsAccuracy();
    _descController.addListener(() {
      if (mounted) {
        setState(() {});
        final sos = Provider.of<SosProvider>(context, listen: false);
        if (sos.description != _descController.text) {
          sos.setDescription(_descController.text);
        }
      }
    });
  }

  void _getTime() {
    final DateTime now = DateTime.now();
    final String formattedDateTime = _formatDateTime(now);
    if (mounted) {
      setState(() {
        _timeString = formattedDateTime;
      });
    }
  }

  String _formatDateTime(DateTime dateTime) {
    return "${dateTime.hour.toString().padLeft(2, '0')}:${dateTime.minute.toString().padLeft(2, '0')}:${dateTime.second.toString().padLeft(2, '0')}";
  }

  Future<void> _loadStats() async {
    try {
      final result = await ApiService.getHistory();
      final totalCount = result['total_count'] as int? ?? 0;
      final history = result['history'] as List? ?? [];
      if (mounted) {
        setState(() {
          _totalSosCount = totalCount;
          if (history.isNotEmpty) {
            final lastItem = history.first;
            final createdAt = lastItem['created_at'];
            if (createdAt != null) {
              _lastEmergencyTime = AppHelpers.formatDate(createdAt);
            }
          }
        });
      }
    } catch (_) {}
  }

  Future<void> _loadGpsAccuracy() async {
    try {
      bool serviceEnabled = await Geolocator.isLocationServiceEnabled();
      if (!serviceEnabled) return;

      LocationPermission permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied ||
          permission == LocationPermission.deniedForever) {
        return;
      }

      Position pos = await Geolocator.getCurrentPosition(
        locationSettings:
            const LocationSettings(accuracy: LocationAccuracy.high),
      ).timeout(const Duration(seconds: 4));

      if (mounted) {
        setState(() {
          _gpsAccuracy = '±${pos.accuracy.toStringAsFixed(0)}m';
        });
      }
    } catch (_) {}
  }



  @override
  void dispose() {
    _clockTimer?.cancel();
    _descController.dispose();
    super.dispose();
  }

  void _showSosSuccessDialog() {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    late OverlayEntry overlayEntry;

    overlayEntry = OverlayEntry(
      builder: (context) {
        return WhatsAppBannerWidget(
          isDark: isDark,
          onDismiss: () {
            overlayEntry.remove();
          },
        );
      },
    );

    Overlay.of(context).insert(overlayEntry);
  }

  Future<void> _sendSos(SosProvider sos) async {
    SoundService.playSosSiren();
    sos.setDescription(_descController.text);
    final success = await sos.sendSos();
    if (mounted) {
      if (success) {
        _descController.clear();
        _showSosSuccessDialog();
      } else {
        AppHelpers.showSnack(context, sos.errorMessage ?? 'Failed to send SOS',
            isError: true);
      }
    }
  }



  Future<void> _deleteNotification(String notifId, void Function(void Function()) setModalState) async {
    if (notifId.isEmpty) return;

    final bool? confirm = await showDialog<bool>(
      context: context,
      barrierDismissible: true,
      builder: (ctx) {
        final isDark = Theme.of(ctx).brightness == Brightness.dark;
        return Dialog(
          backgroundColor: Colors.transparent,
          insetPadding: const EdgeInsets.symmetric(horizontal: 24),
          child: Container(
            padding: const EdgeInsets.all(24),
            decoration: BoxDecoration(
              color: isDark ? const Color(0xFF1E293B) : Colors.white,
              borderRadius: BorderRadius.circular(24),
              border: Border.all(
                color: isDark ? const Color(0xFF334155) : Colors.grey.shade100,
                width: 1.5,
              ),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withValues(alpha: isDark ? 0.3 : 0.08),
                  blurRadius: 30,
                  offset: const Offset(0, 10),
                )
              ],
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                // Glowing Warning Icon
                Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: const Color(0xFFEF4444).withValues(alpha: 0.1),
                    shape: BoxShape.circle,
                  ),
                  child: const Icon(
                    Icons.delete_forever_rounded,
                    color: Color(0xFFEF4444),
                    size: 40,
                  ),
                ),
                const SizedBox(height: 20),
                Text(
                  AppTranslator.t(ctx, 'Delete Notification'),
                  style: TextStyle(
                    fontSize: 20,
                    fontWeight: FontWeight.w900,
                    color: isDark ? Colors.white : const Color(0xFF1E293B),
                  ),
                ),
                const SizedBox(height: 12),
                Text(
                  AppTranslator.t(ctx, 'Are you sure you want to delete this notification?'),
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontSize: 13,
                    height: 1.5,
                    color: isDark ? Colors.grey.shade400 : Colors.grey.shade600,
                    fontWeight: FontWeight.w500,
                  ),
                ),
                const SizedBox(height: 24),
                Row(
                  children: [
                    Expanded(
                      child: TextButton(
                        style: TextButton.styleFrom(
                          padding: const EdgeInsets.symmetric(vertical: 14),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(14),
                          ),
                        ),
                        onPressed: () => Navigator.pop(ctx, false),
                        child: Text(
                          AppTranslator.t(ctx, 'Cancel'),
                          style: TextStyle(
                            fontWeight: FontWeight.bold,
                            color: isDark ? Colors.grey.shade400 : Colors.grey.shade600,
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Container(
                        decoration: BoxDecoration(
                          borderRadius: BorderRadius.circular(14),
                          boxShadow: [
                            BoxShadow(
                              color: const Color(0xFFEF4444).withValues(alpha: 0.25),
                              blurRadius: 10,
                              offset: const Offset(0, 4),
                            )
                          ],
                        ),
                        child: ElevatedButton(
                          style: ElevatedButton.styleFrom(
                            backgroundColor: const Color(0xFFEF4444),
                            foregroundColor: Colors.white,
                            padding: const EdgeInsets.symmetric(vertical: 14),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(14),
                            ),
                            elevation: 0,
                          ),
                          onPressed: () => Navigator.pop(ctx, true),
                          child: Text(
                            AppTranslator.t(ctx, 'Delete'),
                            style: const TextStyle(fontWeight: FontWeight.w800),
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        );
      },
    );

    if (confirm != true) return;

    try {
      final res = await ApiService.deleteNotification(notifId);
      if (res['success'] == true) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(res['message'] ?? AppTranslator.t(context, 'Notification deleted successfully!')),
              backgroundColor: const Color(0xFF10B981),
              behavior: SnackBarBehavior.floating,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
            ),
          );
          setModalState(() {}); // Force reload of notifications in the bottom sheet
        }
      } else {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(res['message'] ?? AppTranslator.t(context, 'Failed to delete notification')),
              backgroundColor: const Color(0xFFEF4444),
              behavior: SnackBarBehavior.floating,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
            ),
          );
        }
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('${AppTranslator.t(context, 'Error')}: $e'),
            backgroundColor: const Color(0xFFEF4444),
            behavior: SnackBarBehavior.floating,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
          ),
        );
      }
    }
  }

  Future<void> _showNotificationsDialog() async {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.transparent,
      isScrollControlled: true,
      builder: (context) {
        return StatefulBuilder(
          builder: (context, setModalState) {
            return Container(
              height: MediaQuery.of(context).size.height * 0.7,
              decoration: BoxDecoration(
                color: isDark ? const Color(0xFF0F172A) : Colors.white,
                borderRadius:
                    const BorderRadius.vertical(top: Radius.circular(30)),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.15),
                    blurRadius: 30,
                    offset: const Offset(0, -5),
                  )
                ],
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  // Handle Bar
                  Center(
                    child: Container(
                      margin: const EdgeInsets.only(top: 12, bottom: 20),
                      width: 40,
                      height: 5,
                      decoration: BoxDecoration(
                        color: isDark
                            ? const Color(0xFF334155)
                            : Colors.grey.shade300,
                        borderRadius: BorderRadius.circular(10),
                      ),
                    ),
                  ),

                  // Header
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 24),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Row(
                          children: [
                            Container(
                              padding: const EdgeInsets.all(10),
                              decoration: BoxDecoration(
                                color: const Color(0xFF2563EB)
                                    .withValues(alpha: 0.1),
                                shape: BoxShape.circle,
                              ),
                              child: const Icon(
                                Icons.notifications_active_rounded,
                                color: Color(0xFF2563EB),
                                size: 22,
                              ),
                            ),
                            const SizedBox(width: 12),
                            Text(
                              AppTranslator.t(context, 'Notifications'),
                              style: TextStyle(
                                fontSize: 20,
                                fontWeight: FontWeight.w900,
                                color: isDark
                                    ? Colors.white
                                    : const Color(0xFF1E293B),
                              ),
                            ),
                          ],
                        ),
                        IconButton(
                          icon: const Icon(Icons.close_rounded),
                          onPressed: () => Navigator.pop(context),
                          style: IconButton.styleFrom(
                            backgroundColor: isDark
                                ? const Color(0xFF1E293B)
                                : Colors.grey.shade100,
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 20),

                  // Notifications list
                  Expanded(
                    child: FutureBuilder<List<dynamic>>(
                      future: ApiService.getNotifications(),
                      builder: (context, snapshot) {
                        if (snapshot.connectionState ==
                            ConnectionState.waiting) {
                          return const Center(
                            child: CircularProgressIndicator(
                              valueColor: AlwaysStoppedAnimation<Color>(
                                  Color(0xFF2563EB)),
                            ),
                          );
                        }
                        if (snapshot.hasError ||
                            snapshot.data == null ||
                            snapshot.data!.isEmpty) {
                          return Center(
                            child: Column(
                              mainAxisAlignment: MainAxisAlignment.center,
                              children: [
                                Icon(
                                  Icons.notifications_none_rounded,
                                  size: 64,
                                  color: Colors.grey.shade400,
                                ),
                                const SizedBox(height: 16),
                                Text(
                                  AppTranslator.t(context, 'No notifications now!'),
                                  style: TextStyle(
                                    fontSize: 16,
                                    fontWeight: FontWeight.bold,
                                    color: Colors.grey.shade500,
                                  ),
                                ),
                                const SizedBox(height: 4),
                                Text(
                                  AppTranslator.t(context, 'Any updates will be shown here.'),
                                  style: TextStyle(
                                    fontSize: 12,
                                    color: Colors.grey.shade400,
                                  ),
                                ),
                              ],
                            ),
                          );
                        }

                        final list = snapshot.data!;
                        return ListView.separated(
                          padding: const EdgeInsets.symmetric(
                              horizontal: 24, vertical: 10),
                          itemCount: list.length,
                          separatorBuilder: (context, index) =>
                              const SizedBox(height: 14),
                          itemBuilder: (context, index) {
                            final item = list[index];
                            final title = item['title'] ?? 'System Update';
                            final msg = item['message'] ?? '';
                            final time = item['created_at'] ?? '';
                            final isRead = item['is_read'] == true;

                            IconData icon;
                            Color iconColor;
                            List<Color> gradientColors;

                            if (title.toLowerCase().contains('sos') ||
                                title.toLowerCase().contains('emergency') ||
                                title.toLowerCase().contains('gurmad') ||
                                title.toLowerCase().contains('ehelka') ||
                                title.toLowerCase().contains('ehelkaaga')) {
                              icon = Icons.emergency_rounded;
                              iconColor = const Color(0xFFEF4444);
                              gradientColors = [const Color(0xFFEF4444), const Color(0xFFF97316)];
                            } else if (title.toLowerCase().contains('gps') ||
                                title.toLowerCase().contains('location') ||
                                title.toLowerCase().contains('live')) {
                              icon = Icons.location_on_rounded;
                              iconColor = const Color(0xFF10B981);
                              gradientColors = [const Color(0xFF10B981), const Color(0xFF06B6D4)];
                            } else if (title.toLowerCase().contains('driver') ||
                                title.toLowerCase().contains('assigned') ||
                                title.toLowerCase().contains('responder')) {
                              icon = Icons.airport_shuttle_rounded;
                              iconColor = const Color(0xFFF59E0B);
                              gradientColors = [const Color(0xFFF59E0B), const Color(0xFFEF4444)];
                            } else {
                              icon = Icons.notifications_rounded;
                              iconColor = const Color(0xFF2563EB);
                              gradientColors = [const Color(0xFF2563EB), const Color(0xFF7C3AED)];
                            }

                            return Container(
                              padding: const EdgeInsets.all(18),
                              decoration: BoxDecoration(
                                color: isDark
                                    ? const Color(0xFF1E293B)
                                    : Colors.white,
                                borderRadius: BorderRadius.circular(20),
                                border: Border.all(
                                  color: isRead
                                      ? (isDark ? const Color(0xFF334155) : Colors.grey.shade100)
                                      : iconColor.withValues(alpha: 0.25),
                                  width: isRead ? 1.5 : 2,
                                ),
                                boxShadow: [
                                  BoxShadow(
                                    color: isRead
                                        ? Colors.black.withValues(alpha: isDark ? 0.15 : 0.03)
                                        : iconColor.withValues(alpha: 0.08),
                                    blurRadius: 16,
                                    offset: const Offset(0, 4),
                                  )
                                ],
                              ),
                              child: Row(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Container(
                                    padding: const EdgeInsets.all(10),
                                    decoration: BoxDecoration(
                                      gradient: isRead
                                          ? null
                                          : LinearGradient(
                                              colors: gradientColors,
                                              begin: Alignment.topLeft,
                                              end: Alignment.bottomRight,
                                            ),
                                      color: isRead ? Colors.grey.withValues(alpha: 0.1) : null,
                                      borderRadius: BorderRadius.circular(14),
                                      boxShadow: isRead
                                          ? []
                                          : [
                                              BoxShadow(
                                                color: iconColor.withValues(alpha: 0.3),
                                                blurRadius: 10,
                                                offset: const Offset(0, 3),
                                              )
                                            ],
                                    ),
                                    child: Icon(
                                      icon,
                                      color: isRead ? Colors.grey.shade400 : Colors.white,
                                      size: 18,
                                    ),
                                  ),
                                  const SizedBox(width: 14),
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment:
                                          CrossAxisAlignment.start,
                                      children: [
                                        Text(
                                          title,
                                          style: TextStyle(
                                            fontWeight: FontWeight.bold,
                                            fontSize: 14,
                                            color: isDark
                                                ? Colors.white
                                                : const Color(0xFF1E293B),
                                          ),
                                        ),
                                        const SizedBox(height: 6),
                                        Text(
                                          msg,
                                          style: TextStyle(
                                            fontSize: 12,
                                            height: 1.4,
                                            color: isDark
                                                ? Colors.grey.shade400
                                                : Colors.grey.shade600,
                                            fontWeight: FontWeight.w500,
                                          ),
                                        ),
                                        const SizedBox(height: 8),
                                        Text(
                                          time,
                                          style: TextStyle(
                                            fontSize: 10,
                                            color: Colors.grey.shade400,
                                            fontWeight: FontWeight.w600,
                                          ),
                                        ),
                                      ],
                                    ),
                                  ),
                                  const SizedBox(width: 8),
                                  IconButton(
                                    icon: Icon(
                                      Icons.delete_outline_rounded,
                                      color: isDark ? Colors.red.shade400 : Colors.red.shade600,
                                      size: 18,
                                    ),
                                    tooltip: AppTranslator.t(context, 'Delete'),
                                    onPressed: () => _deleteNotification(item['id']?.toString() ?? '', setModalState),
                                    constraints: const BoxConstraints(),
                                    padding: EdgeInsets.zero,
                                    splashRadius: 18,
                                  ),
                                ],
                              ),
                            );
                          },
                        );
                      },
                    ),
                  ),
                ],
              ),
            );
          },
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final auth = Provider.of<AuthProvider>(context);
    final sos = Provider.of<SosProvider>(context);
    
    // Sync description text with SosProvider
    if (sos.description != null && sos.description != _descController.text) {
      _descController.text = sos.description!;
    } else if (sos.description == null && _descController.text.isNotEmpty) {
      _descController.clear();
    }

    final isDark = Theme.of(context).brightness == Brightness.dark;
    final isWide = MediaQuery.of(context).size.width > 800;

    String actionGuideText = '';
    switch (sos.selectedType) {
      case 'Medical':
        actionGuideText =
            '🚑 Stay calm. Check for breathing. If bleeding, apply firm pressure. Do not move if head/neck injury suspected.';
        break;
      case 'Fire':
        actionGuideText =
            '🚒 Evacuate immediately. Stay low under smoke. Do not open hot doors. Dial emergency services immediately.';
        break;
      case 'Police':
        actionGuideText =
            '👮 Seek immediate shelter. Lock doors and windows. Stay quiet and turn off lights. Wait for police arrival.';
        break;
      case 'Accident':
        actionGuideText =
            '🚗 Pull over to a safe area. Turn on hazard lights. Check for injuries. Do not move severely injured persons unless necessary.';
        break;
      default:
        actionGuideText =
            '🚨 Stay calm. Check for safety. Report location immediately to emergency responders.';
    }

    return Scaffold(
      backgroundColor:
          isDark ? const Color(0xFF0F172A) : const Color(0xFFF8FAFC),
      appBar: AppBar(
        automaticallyImplyLeading: false,
        backgroundColor: Colors.transparent,
        elevation: 0,
        leading: Builder(
          builder: (context) => IconButton(
            icon: Icon(Icons.menu_rounded,
                color: isDark ? Colors.white : Colors.black87),
            onPressed: () => UserShell.scaffoldKey.currentState?.openDrawer(),
          ),
        ),
        title: Text(
          'SmartRescue',
          style: TextStyle(
            fontWeight: FontWeight.w900,
            fontSize: 22,
            color: isDark ? Colors.white : const Color(0xFF0F172A),
            letterSpacing: -0.5,
          ),
        ),
        centerTitle: false,
        actions: [
          Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              if (MediaQuery.of(context).size.width >= 500) ...[
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                  decoration: BoxDecoration(
                    color: isDark ? const Color(0xFF064E3B).withValues(alpha: 0.2) : const Color(0xFFE6F7F0),
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(
                      color: isDark ? const Color(0xFF047857).withValues(alpha: 0.4) : const Color(0xFFA3E2C9),
                      width: 1,
                    ),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Container(
                        width: 7,
                        height: 7,
                        decoration: const BoxDecoration(
                          color: Color(0xFF10B981),
                          shape: BoxShape.circle,
                        ),
                      ),
                      const SizedBox(width: 5),
                      Text(
                        AppTranslator.t(context, 'Online'),
                        style: const TextStyle(
                          color: Color(0xFF10B981),
                          fontWeight: FontWeight.w800,
                          fontSize: 11,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 8),
              ],
              if (MediaQuery.of(context).size.width >= 600) ...[
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                  decoration: BoxDecoration(
                    color: isDark ? const Color(0xFF1E293B) : Colors.white,
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(
                      color: isDark ? const Color(0xFF334155) : const Color(0xFFE2E8F0),
                      width: 1,
                    ),
                  ),
                  child: Text(
                    _timeString,
                    style: TextStyle(
                      color: isDark ? Colors.white : const Color(0xFF475569),
                      fontWeight: FontWeight.w800,
                      fontSize: 11,
                      letterSpacing: 0.5,
                    ),
                  ),
                ),
                const SizedBox(width: 8),
              ],
              GestureDetector(
                onTap: () async {
                  if (auth.user == null) return;
                  final user = auth.user!;
                  final newVal = !user.darkMode;
                  
                  await auth.updateUser(user.copyWith(darkMode: newVal));
                  
                  try {
                    await ApiService.togglePreference(user.id.toString(), 'dark_mode', newVal ? 1 : 0);
                  } catch (_) {}
                },
                child: Container(
                  padding: const EdgeInsets.all(7),
                  decoration: BoxDecoration(
                    color: isDark ? const Color(0xFF1E293B) : Colors.white,
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(
                      color: isDark ? const Color(0xFF334155) : const Color(0xFFE2E8F0),
                      width: 1,
                    ),
                  ),
                  child: Icon(
                    isDark ? Icons.wb_sunny_rounded : Icons.nightlight_round,
                    color: isDark ? Colors.amber : const Color(0xFF475569),
                    size: 15,
                  ),
                ),
              ),
              const SizedBox(width: 8),
              GestureDetector(
                onTap: () => _showNotificationsDialog(),
                child: Container(
                  padding: const EdgeInsets.all(7),
                  decoration: BoxDecoration(
                    color: isDark ? const Color(0xFF1E293B) : Colors.white,
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(
                      color: isDark ? const Color(0xFF334155) : const Color(0xFFE2E8F0),
                      width: 1,
                    ),
                  ),
                  child: Icon(
                    Icons.notifications_none_rounded,
                    color: isDark ? Colors.white : const Color(0xFF475569),
                    size: 15,
                  ),
                ),
              ),
              const SizedBox(width: 16),
            ],
          ),
        ],
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 10),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // Emergency Command Center Header Part
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SizedBox(height: 8),
                Text(
                  AppTranslator.t(context, 'EMERGENCY COMMAND CENTER'),
                  style: TextStyle(
                    fontSize: 10,
                    fontWeight: FontWeight.w900,
                    color: Colors.grey.shade500,
                    letterSpacing: 0.8,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  '${AppTranslator.t(context, 'Hello')}, ${auth.user?.fullname.split(' ')[0] ?? AppTranslator.t(context, 'User')} 👋',
                  style: TextStyle(
                    fontSize: 26,
                    fontWeight: FontWeight.bold,
                    color: isDark ? Colors.white : Colors.black87,
                    fontFamily: 'Inter',
                  ),
                ),
              ],
            ),
            const SizedBox(height: 24),

            if (sos.hasActiveRequest) ...[
              const SizedBox(height: 16),
              _buildActiveTracker(sos),
              const SizedBox(height: 24),
              Row(
                children: [
                  Expanded(
                    child: ElevatedButton.icon(
                      onPressed: () {
                        // Switch to map tab (index 1 in shell navigation)
                        UserShell.tabNotifier.value = 1;
                      },
                      icon: const Icon(Icons.map_rounded, color: Colors.white),
                      label: Text(
                        AppTranslator.t(context, 'TRACK DRIVER ON MAP'),
                        style: const TextStyle(
                          fontWeight: FontWeight.w900,
                          color: Colors.white,
                          fontSize: 12,
                          letterSpacing: 0.5,
                        ),
                      ),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: const Color(0xFF2563EB),
                        padding: const EdgeInsets.symmetric(vertical: 16),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(16),
                        ),
                        elevation: 0,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: () async {
                        final confirm = await showDialog<bool>(
                          context: context,
                          builder: (context) => AlertDialog(
                            title: Text(AppTranslator.t(context, 'Cancel Emergency?')),
                            content: Text(AppTranslator.t(context, 'Are you sure you want to cancel this emergency request?')),
                            actions: [
                              TextButton(
                                onPressed: () => Navigator.pop(context, false),
                                child: Text(AppTranslator.t(context, 'NO, STAY ACTIVE')),
                              ),
                              TextButton(
                                onPressed: () => Navigator.pop(context, true),
                                child: Text(AppTranslator.t(context, 'YES, CANCEL'), style: const TextStyle(color: Colors.red)),
                              ),
                            ],
                          ),
                        );
                        if (confirm == true) {
                          await sos.cancelActiveRequest();
                        }
                      },
                      icon: const Icon(Icons.cancel_outlined, color: Colors.redAccent),
                      label: Text(
                        AppTranslator.t(context, 'CANCEL EMERGENCY'),
                        style: const TextStyle(
                          fontWeight: FontWeight.w900,
                          color: Colors.redAccent,
                          fontSize: 12,
                          letterSpacing: 0.5,
                        ),
                      ),
                      style: OutlinedButton.styleFrom(
                        side: const BorderSide(color: Colors.redAccent, width: 1.5),
                        padding: const EdgeInsets.symmetric(vertical: 16),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(16),
                        ),
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 30),
            ] else ...[
              // Stats Cards Row
              SingleChildScrollView(
                scrollDirection: Axis.horizontal,
                physics: const BouncingScrollPhysics(),
                child: Row(
                  children: [
                    _buildStatCard(
                      title: AppTranslator.t(context, 'TOTAL SOS SENT'),
                      value: '$_totalSosCount',
                      subtitle: '📈 ${AppTranslator.t(context, 'All time')}',
                      icon: Icons.sensors_rounded,
                      gradient: const [Color(0xFFE11D48), Color(0xFFBE123C)],
                    ),
                    const SizedBox(width: 12),
                    _buildStatCard(
                      title: AppTranslator.t(context, 'LAST EMERGENCY'),
                      value: _lastEmergencyTime,
                      subtitle: '📅 ${AppTranslator.t(context, 'Most recent')}',
                      icon: Icons.history_rounded,
                      gradient: const [Color(0xFF2563EB), Color(0xFF1D4ED8)],
                    ),
                    const SizedBox(width: 12),
                    _buildStatCard(
                      title: AppTranslator.t(context, 'EMERGENCY CONTACTS'),
                      value: '${(() { final raw = auth.user?.emergencyContacts; if (raw == null || raw.trim().isEmpty) return 0; return raw.split('\n').where((l) => l.trim().isNotEmpty).length; })()}',
                      subtitle: '👥 ${AppTranslator.t(context, 'Guardian network')}',
                      icon: Icons.people_alt_rounded,
                      gradient: const [Color(0xFF059669), Color(0xFF047857)],
                    ),
                    const SizedBox(width: 12),
                    _buildStatCard(
                      title: AppTranslator.t(context, 'GPS ACCURACY'),
                      value: _gpsAccuracy,
                      subtitle: AppTranslator.t(context, '📡 Good signal'),
                      icon: Icons.satellite_alt_rounded,
                      gradient: const [Color(0xFFF59E0B), Color(0xFFD97706)],
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 30),

              // Standard layout: full-width grid
              Row(
                children: [
                  Icon(Icons.warning_rounded,
                      color: Colors.orange.shade700, size: 18),
                  const SizedBox(width: 8),
                  Flexible(
                    child: Text(
                      AppTranslator.t(context, 'SELECT EMERGENCY TYPE'),
                      style: TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w900,
                        color: Colors.grey.shade600,
                        letterSpacing: 0.5,
                      ),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Divider(
                      color: Colors.grey.withValues(alpha: 0.15),
                      thickness: 1.5,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              GridView.count(
                shrinkWrap: true,
                physics: const NeverScrollableScrollPhysics(),
                crossAxisCount: isWide ? 4 : 2,
                mainAxisSpacing: 12,
                crossAxisSpacing: 12,
                childAspectRatio: isWide ? 2.2 : 1.25,
                children: [
                  _buildTypeCard('Medical', sos),
                  _buildTypeCard('Fire', sos),
                  _buildTypeCard('Police', sos),
                  _buildTypeCard('Accident', sos),
                ],
              ),
              const SizedBox(height: 24),

              // Action Guide Card
              Container(
                decoration: BoxDecoration(
                  color: isDark ? const Color(0xFF1E293B) : Colors.white,
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(
                    color:
                        isDark ? const Color(0xFF334155) : Colors.grey.shade100,
                    width: 1,
                  ),
                ),
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(16),
                  child: Row(
                    children: [
                      Container(
                        width: 4,
                        height: 70,
                        color: const Color(0xFF2563EB),
                      ),
                      Expanded(
                        child: Padding(
                          padding: const EdgeInsets.symmetric(
                              vertical: 16, horizontal: 16),
                          child: Row(
                            children: [
                              Container(
                                padding: const EdgeInsets.all(8),
                                decoration: BoxDecoration(
                                  color: isDark
                                      ? const Color(0xFF0F172A)
                                      : const Color(0xFFEFF6FF),
                                  shape: BoxShape.circle,
                                ),
                                child: const Icon(
                                  Icons.lightbulb_outline_rounded,
                                  color: Color(0xFF2563EB),
                                  size: 20,
                                ),
                              ),
                              const SizedBox(width: 14),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      AppTranslator.t(context, 'ACTION GUIDE'),
                                      style: const TextStyle(
                                        fontSize: 10,
                                        fontWeight: FontWeight.w800,
                                        color: Color(0xFF2563EB),
                                        letterSpacing: 0.8,
                                      ),
                                    ),
                                    const SizedBox(height: 4),
                                    Text(
                                      AppTranslator.t(context, actionGuideText),
                                      style: TextStyle(
                                        fontSize: 11,
                                        fontWeight: FontWeight.w600,
                                        color: isDark
                                            ? Colors.grey.shade300
                                            : const Color(0xFF334155),
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 24),

              // SOS Button
              _buildSosButtonBlock(isDark, sos),
            ],
          ],
        ),
      ),
    );
  }

  // ─── SOS Button Block ────────────────────────────────────────────────────────
  Widget _buildSosButtonBlock(bool isDark, SosProvider sos) {
    return Column(
      children: [
        // Status Pills
        Center(
          child: Container(
            padding: const EdgeInsets.all(4),
            decoration: BoxDecoration(
              color: isDark ? const Color(0xFF1E293B) : Colors.white,
              borderRadius: BorderRadius.circular(30),
              border: Border.all(
                color: isDark ? const Color(0xFF334155) : Colors.grey.shade100,
                width: 1.5,
              ),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withValues(alpha: 0.05),
                  blurRadius: 10,
                  offset: const Offset(0, 4),
                ),
              ],
            ),
            child: Wrap(
              alignment: WrapAlignment.center,
              crossAxisAlignment: WrapCrossAlignment.center,
              spacing: 8,
              runSpacing: 6,
              children: [
                Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                  decoration: BoxDecoration(
                    color: const Color(0xFFE6FDF5),
                    borderRadius: BorderRadius.circular(30),
                    border:
                        Border.all(color: const Color(0xFFA7F3D0), width: 1),
                  ),
                  child: Text(
                    AppTranslator.t(context, 'Safety: Good'),
                    style: const TextStyle(
                      color: Color(0xFF047857),
                      fontSize: 11,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ),
                Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                  decoration: BoxDecoration(
                    color: isDark ? const Color(0xFF0F172A) : Colors.white,
                    borderRadius: BorderRadius.circular(30),
                    border: Border.all(
                      color: isDark
                          ? const Color(0xFF334155)
                          : Colors.grey.shade100,
                      width: 1,
                    ),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Icon(Icons.near_me_rounded,
                          color: Color(0xFFF59E0B), size: 14),
                      const SizedBox(width: 6),
                      Text(
                        '${AppTranslator.t(context, 'GPS Good')} ($_gpsAccuracy)',
                        style: TextStyle(
                          color:
                              isDark ? Colors.white70 : const Color(0xFF334155),
                          fontSize: 11,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 24),

        // Main SOS Button
        SosPulseButton(
          sos: sos,
          onTap: () {
            if (sos.hasActiveRequest || sos.sosState == SosState.sending) return;
            _sendSos(sos);
          },
        ),
        const SizedBox(height: 14),

        // Caption
        Center(
          child: RichText(
            text: TextSpan(
              children: [
                const TextSpan(
                  text: '| ',
                  style: TextStyle(
                    color: Color(0xFF94A3B8),
                    fontWeight: FontWeight.w900,
                    fontSize: 10,
                  ),
                ),
                TextSpan(
                  text: sos.hasActiveRequest
                      ? AppTranslator.t(context, 'EMERGENCY BROADCAST ACTIVE')
                      : AppTranslator.t(context, 'TAP BUTTON TO TRIGGER SOS'),
                  style: TextStyle(
                    color: sos.hasActiveRequest
                        ? const Color(0xFF10B981)
                        : const Color(0xFF64748B),
                    fontWeight: FontWeight.w900,
                    fontSize: 10,
                    letterSpacing: 0.8,
                  ),
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }



  Widget _buildTimelineRow({
    required IconData icon,
    required String title,
    required String subtitle,
    required TimelineStepState state,
    required bool isLineActive,
    required bool isLast,
    required bool isDark,
  }) {
    final activeColor = const Color(0xFF2563EB); // Deep premium blue
    final currentColor = const Color(0xFFEF4444); // Urgent red

    Widget circleWidget;
    if (state == TimelineStepState.current) {
      circleWidget = Container(
        width: 44,
        height: 44,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: currentColor.withValues(alpha: 0.15),
          shape: BoxShape.circle,
        ),
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 300),
          width: 32,
          height: 32,
          decoration: BoxDecoration(
            color: Colors.white,
            shape: BoxShape.circle,
            border: Border.all(
              color: currentColor,
              width: 2.5,
            ),
          ),
          child: Icon(
            icon,
            color: currentColor,
            size: 15,
          ),
        ),
      );
    } else if (state == TimelineStepState.completed) {
      circleWidget = AnimatedContainer(
        duration: const Duration(milliseconds: 300),
        width: 34,
        height: 34,
        decoration: BoxDecoration(
          color: activeColor.withValues(alpha: 0.1),
          shape: BoxShape.circle,
          border: Border.all(
            color: activeColor,
            width: 2,
          ),
        ),
        child: Icon(
          icon,
          color: activeColor,
          size: 15,
        ),
      );
    } else {
      circleWidget = AnimatedContainer(
        duration: const Duration(milliseconds: 300),
        width: 34,
        height: 34,
        decoration: BoxDecoration(
          color: isDark ? const Color(0xFF0F172A) : Colors.grey.shade50,
          shape: BoxShape.circle,
          border: Border.all(
            color: isDark ? const Color(0xFF334155) : Colors.grey.shade300,
            width: 1.5,
          ),
        ),
        child: Icon(
          icon,
          color: Colors.grey.shade400,
          size: 15,
        ),
      );
    }

    Color titleColor;
    if (state == TimelineStepState.completed) {
      titleColor = activeColor;
    } else if (state == TimelineStepState.current) {
      titleColor = currentColor;
    } else {
      titleColor = isDark ? Colors.white70 : const Color(0xFF1E293B);
    }

    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Column(
          children: [
            SizedBox(
              width: 44,
              height: 44,
              child: Center(child: circleWidget),
            ),
            if (!isLast)
              AnimatedContainer(
                duration: const Duration(milliseconds: 300),
                width: 2,
                height: 24,
                color: isLineActive
                    ? activeColor
                    : (isDark ? const Color(0xFF1E293B) : Colors.grey.shade200),
              ),
          ],
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Padding(
            padding: EdgeInsets.only(top: 10, bottom: isLast ? 0 : 14),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  AppTranslator.t(context, title),
                  style: TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.bold,
                    color: titleColor,
                  ),
                ),
                const SizedBox(height: 1),
                Text(
                  AppTranslator.t(context, subtitle),
                  style: TextStyle(
                    fontSize: 10,
                    color: state == TimelineStepState.current
                        ? Colors.grey.shade500
                        : Colors.grey.shade400,
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildTypeCard(String type, SosProvider sos) {
    final isSelected = sos.selectedType == type;
    final isDark = Theme.of(context).brightness == Brightness.dark;

    // Map internal type to display text, icon, and colors
    String title = '';
    String subtitle = '';
    IconData icon = Icons.help_rounded;
    Color primaryColor = Colors.blue;
    Color cardBgColor = isDark ? const Color(0xFF1E293B) : Colors.white;

    switch (type) {
      case 'Medical':
        title = 'AMBULANCE';
        subtitle = 'Medical Emergency';
        icon = Icons.airport_shuttle_rounded;
        primaryColor = Colors.blue.shade600;
        cardBgColor = isSelected
            ? (isDark
                ? const Color(0xFF1E3A8A)
                : Colors.blue.shade50.withValues(alpha: 0.4))
            : (isDark ? const Color(0xFF1E293B) : Colors.white);
        break;
      case 'Fire':
        title = 'FIRE RESCUE';
        subtitle = 'Fire & Burns';
        icon = Icons.local_fire_department_rounded;
        primaryColor = Colors.orange.shade600;
        cardBgColor = isSelected
            ? (isDark
                ? const Color(0xFF7C2D12)
                : Colors.orange.shade50.withValues(alpha: 0.4))
            : (isDark ? const Color(0xFF1E293B) : Colors.white);
        break;
      case 'Police':
        title = 'POLICE';
        subtitle = 'Security Help';
        icon = Icons.shield_rounded;
        primaryColor = Colors.cyan.shade600;
        cardBgColor = isSelected
            ? (isDark
                ? const Color(0xFF164E63)
                : Colors.cyan.shade50.withValues(alpha: 0.4))
            : (isDark ? const Color(0xFF1E293B) : Colors.white);
        break;
      case 'Accident':
        title = 'ACCIDENT';
        subtitle = 'Vehicle Crash';
        icon = Icons.car_crash_rounded;
        primaryColor = Colors.red.shade600;
        cardBgColor = isSelected
            ? (isDark
                ? const Color(0xFF7F1D1D)
                : Colors.red.shade50.withValues(alpha: 0.4))
            : (isDark ? const Color(0xFF1E293B) : Colors.white);
        break;
    }

    return GestureDetector(
      onTap: () => sos.selectType(type),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        decoration: BoxDecoration(
          color: cardBgColor,
          borderRadius: BorderRadius.circular(20),
          border: Border.all(
            color: isSelected
                ? primaryColor
                : (isDark
                    ? const Color(0xFF334155)
                    : Colors.grey.withValues(alpha: 0.15)),
            width: isSelected ? 2.5 : 1,
          ),
          boxShadow: [
            BoxShadow(
              color: isSelected
                  ? primaryColor.withValues(alpha: 0.08)
                  : Colors.black.withValues(alpha: 0.02),
              blurRadius: 15,
              offset: const Offset(0, 4),
            )
          ],
        ),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            // Icon Container
            AnimatedContainer(
              duration: const Duration(milliseconds: 200),
              width: 48,
              height: 48,
              decoration: BoxDecoration(
                color:
                    isSelected ? primaryColor : primaryColor.withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(14),
              ),
              child: Icon(
                icon,
                color: isSelected ? Colors.white : primaryColor,
                size: 24,
              ),
            ),
            const SizedBox(height: 10),
            // Title
            Text(
              AppTranslator.t(context, title),
              style: TextStyle(
                fontWeight: FontWeight.w900,
                fontSize: 12,
                color: isDark ? Colors.white : Colors.black87,
                letterSpacing: 0.2,
              ),
            ),
            const SizedBox(height: 2),
            // Subtitle
            Text(
              AppTranslator.t(context, subtitle),
              style: TextStyle(
                fontWeight: FontWeight.w600,
                fontSize: 10,
                color: Colors.grey.shade500,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildStatCard({
    required String title,
    required String value,
    required String subtitle,
    required IconData icon,
    required List<Color> gradient,
  }) {
    return Container(
      width: 175,
      height: 85,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: gradient,
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(20),
        boxShadow: [
          BoxShadow(
            color: gradient.first.withValues(alpha: 0.3),
            blurRadius: 10,
            offset: const Offset(0, 4),
          )
        ],
      ),
      child: Row(
        children: [
          // Glassmorphic Icon Box
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.15),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(icon, color: Colors.white, size: 20),
          ),
          const SizedBox(width: 10),
          // Text Details
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text(
                  value,
                  style: const TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.w900,
                    fontSize: 16,
                    height: 1.1,
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
                const SizedBox(height: 2),
                Text(
                  title,
                  style: const TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.w800,
                    fontSize: 8,
                    letterSpacing: 0.2,
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
                const SizedBox(height: 2),
                Text(
                  subtitle,
                  style: TextStyle(
                    color: Colors.white.withValues(alpha: 0.7),
                    fontWeight: FontWeight.w600,
                    fontSize: 8,
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }



  Widget _buildActiveTracker(SosProvider sos) {
    final req = sos.activeRequest;
    final isDark = Theme.of(context).brightness == Brightness.dark;

    // Use the unified timelineStep from the model
    final int currentIdx = req != null ? req.timelineStep : -1;

    TimelineStepState getStepState(int index) {
      if (index < currentIdx) return TimelineStepState.completed;
      if (index == currentIdx) return TimelineStepState.current;
      return TimelineStepState.upcoming;
    }

    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: isDark ? const Color(0xFF1E293B) : Colors.white,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(
          color: isDark ? const Color(0xFF334155) : Colors.grey.shade100,
          width: 1.5,
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: isDark ? 0.2 : 0.04),
            blurRadius: 20,
            offset: const Offset(0, 8),
          )
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Header Row
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(8),
                    decoration: BoxDecoration(
                      color: const Color(0xFF2563EB).withValues(alpha: 0.1),
                      shape: BoxShape.circle,
                    ),
                    child: const Icon(
                      Icons.alt_route_rounded,
                      color: Color(0xFF2563EB),
                      size: 18,
                    ),
                  ),
                  const SizedBox(width: 10),
                  Text(
                    'RESPONSE TIMELINE',
                    style: TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w900,
                      color: isDark
                          ? Colors.grey.shade300
                          : const Color(0xFF475569),
                      letterSpacing: 0.5,
                    ),
                  ),
                ],
              ),
              Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: const Color(0xFFEFF6FF),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: const Text(
                  'LIVE',
                  style: TextStyle(
                    color: Color(0xFF2563EB),
                    fontSize: 10,
                    fontWeight: FontWeight.w900,
                    letterSpacing: 0.5,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 24),

          // Timeline items — 6 steps (0–5)
          _buildTimelineRow(
            icon: Icons.sensors_rounded,
            title: 'SOS Sent',
            subtitle: 'Emergency signal broadcast',
            state: getStepState(0),
            isLineActive: currentIdx > 0,
            isLast: false,
            isDark: isDark,
          ),
          _buildTimelineRow(
            icon: Icons.support_agent_rounded,
            title: 'Dispatched',
            subtitle: 'Dispatch center notified',
            state: getStepState(1),
            isLineActive: currentIdx > 1,
            isLast: false,
            isDark: isDark,
          ),
          _buildTimelineRow(
            icon: Icons.assignment_ind_rounded,
            title: 'Team Assigned',
            subtitle: 'Rescue unit selected',
            state: getStepState(2),
            isLineActive: currentIdx > 2,
            isLast: false,
            isDark: isDark,
          ),
          _buildTimelineRow(
            icon: Icons.airport_shuttle_rounded,
            title: 'On The Way',
            subtitle: 'Team heading to location',
            state: getStepState(3),
            isLineActive: currentIdx > 3,
            isLast: false,
            isDark: isDark,
          ),
          _buildTimelineRow(
            icon: Icons.location_on_rounded,
            title: 'Arrived',
            subtitle: 'Team on scene',
            state: getStepState(4),
            isLineActive: currentIdx > 4,
            isLast: false,
            isDark: isDark,
          ),
          _buildTimelineRow(
            icon: Icons.verified_rounded,
            title: 'Completed',
            subtitle: 'Emergency successfully resolved',
            state: getStepState(5),
            isLineActive: false,
            isLast: true,
            isDark: isDark,
          ),

          // Driver Details (if assigned)
          if (req != null && (req.driverAssigned || req.unitName.isNotEmpty)) ...[
            const Padding(
              padding: EdgeInsets.only(top: 20, bottom: 16),
              child: Divider(height: 1),
            ),
            Row(
              children: [
                Container(
                  width: 48,
                  height: 48,
                  decoration: BoxDecoration(
                    color: const Color(0xFF2563EB).withValues(alpha: 0.1),
                    shape: BoxShape.circle,
                  ),
                  child: Center(
                    child: Text(
                      AppHelpers.initials(req.driverName),
                      style: const TextStyle(
                        color: Color(0xFF2563EB),
                        fontWeight: FontWeight.w800,
                        fontSize: 16,
                      ),
                    ),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        req.driverName,
                        style: TextStyle(
                          fontWeight: FontWeight.w800,
                          fontSize: 15,
                          color:
                              isDark ? Colors.white : const Color(0xFF1E293B),
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        '${req.unitName} • ${req.plateNumber}',
                        style: TextStyle(
                          color: Colors.grey.shade500,
                          fontSize: 11,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                      if (req.driverPhone.isNotEmpty) ...[
                        const SizedBox(height: 2),
                        Text(
                          req.driverPhone,
                          style: const TextStyle(
                            color: Color(0xFF10B981),
                            fontSize: 12,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
                IconButton(
                  onPressed: () => CallScreen.show(
                    context,
                    name: req.driverName,
                    phone: req.driverPhone,
                  ),
                  icon: const Icon(Icons.phone_rounded, color: Colors.green),
                  style: IconButton.styleFrom(
                    backgroundColor: Colors.green.withValues(alpha: 0.1),
                  ),
                )
              ],
            )
          ]
        ],
      ),
    );
  }
}

class SosPulseButton extends StatefulWidget {
  final SosProvider sos;
  final VoidCallback onTap;

  const SosPulseButton({
    super.key,
    required this.sos,
    required this.onTap,
  });

  @override
  State<SosPulseButton> createState() => _SosPulseButtonState();
}

class _SosPulseButtonState extends State<SosPulseButton> with TickerProviderStateMixin {
  late AnimationController _pulseController;
  late AnimationController _shakeController;

  @override
  void initState() {
    super.initState();
    _pulseController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1500),
    )..repeat(reverse: true); // 1.5s one way, 3s total

    _shakeController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 2500),
    )..repeat();
  }

  @override
  void dispose() {
    _pulseController.dispose();
    _shakeController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: Listenable.merge([_pulseController, _shakeController]),
      builder: (context, child) {
        final isActive = widget.sos.hasActiveRequest;
        
        final pulseVal = _pulseController.value;
        final blurRadius = isActive ? 32.0 : 32.0 + (8.0 * pulseVal);
        final spreadRadius = isActive ? 0.0 : 0.0 + (14.0 * pulseVal);
        final mainShadowOpacity = isActive ? 0.45 : 0.45 + (0.15 * pulseVal);
        final ringOpacity = isActive ? 0.0 : 0.3 * (1.0 - pulseVal);

        final shakeVal = _shakeController.value;
        double angle = 0.0;
        if (!isActive) {
          if (shakeVal > 0.92 && shakeVal <= 0.94) {
            angle = -0.1396 * ((shakeVal - 0.92) / 0.02);
          } else if (shakeVal > 0.94 && shakeVal <= 0.98) {
            angle = -0.1396 + (0.2792 * ((shakeVal - 0.94) / 0.04));
          } else if (shakeVal > 0.98 && shakeVal <= 1.0) {
            angle = 0.1396 - (0.1396 * ((shakeVal - 0.98) / 0.02));
          }
        }

        return GestureDetector(
          onTap: widget.onTap,
          child: Container(
            width: double.infinity,
            constraints: const BoxConstraints(maxWidth: 380),
            clipBehavior: Clip.antiAlias,
            decoration: BoxDecoration(
              gradient: LinearGradient(
                colors: isActive
                    ? [const Color(0xFF34D399), const Color(0xFF10B981)]
                    : [const Color(0xFFFF4444), const Color(0xFFEF4444), const Color(0xFFC41E3A)],
                stops: isActive ? null : [0.0, 0.4, 1.0],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
              borderRadius: BorderRadius.circular(16),
              boxShadow: isActive 
                ? [
                    BoxShadow(
                      color: const Color(0xFF10B981).withValues(alpha: 0.45),
                      blurRadius: 32,
                      offset: const Offset(0, 8),
                    )
                  ]
                : [
                    BoxShadow(
                      color: const Color(0xFFEF4444).withValues(alpha: mainShadowOpacity),
                      blurRadius: blurRadius,
                      offset: const Offset(0, 8),
                    ),
                    BoxShadow(
                      color: const Color(0xFFEF4444).withValues(alpha: ringOpacity),
                      spreadRadius: spreadRadius,
                      blurRadius: 0,
                    ),
                  ],
            ),
            child: Stack(
              children: [
                Positioned.fill(
                  child: Container(
                    decoration: const BoxDecoration(
                      gradient: LinearGradient(
                        colors: [Color(0x26FFFFFF), Colors.transparent],
                        stops: [0.0, 0.6],
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                      ),
                    ),
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 32, vertical: 22),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Transform.rotate(
                        angle: angle,
                        child: Icon(
                          isActive
                              ? Icons.check_circle_rounded
                              : Icons.warning_amber_rounded,
                          color: Colors.white,
                          size: 32,
                        ),
                      ),
                      const SizedBox(width: 16),
                      Flexible(
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          crossAxisAlignment: CrossAxisAlignment.start,
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Text(
                              isActive ? 'SOS SENT' : 'ACTIVATE SOS',
                              style: const TextStyle(
                                color: Colors.white,
                                fontWeight: FontWeight.w900,
                                fontSize: 24,
                                letterSpacing: 2.0,
                                height: 1.0,
                              ),
                              overflow: TextOverflow.ellipsis,
                            ),
                            const SizedBox(height: 3),
                            Text(
                              isActive
                                  ? 'RESCUE IS ON THE WAY'
                                  : 'TAP ONCE TO SEND ALERT',
                              style: const TextStyle(
                                color: Colors.white70,
                                fontWeight: FontWeight.w700,
                                fontSize: 10.88,
                                letterSpacing: 2.0,
                              ),
                              overflow: TextOverflow.ellipsis,
                            ),
                          ],
                        ),
                      ),
                    ],
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
