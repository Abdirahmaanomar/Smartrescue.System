import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import '../../providers/auth_provider.dart';
import '../../providers/driver_provider.dart';
import '../../utils/translator.dart';
import 'driver_dashboard_screen.dart';
import 'driver_map_screen.dart';
import 'driver_history_screen.dart';
import 'driver_profile_screen.dart';
import 'driver_settings_screen.dart';

class DriverShell extends StatefulWidget {
  const DriverShell({super.key});

  @override
  State<DriverShell> createState() => _DriverShellState();
}

class _DriverShellState extends State<DriverShell> {
  int _currentIndex = 0;
  late List<Widget> _screens;
  bool _isSosDialogShowing = false;

  @override
  void initState() {
    super.initState();
    _screens = [
      const DriverDashboardScreen(),
      const DriverMapScreen(),
      const DriverHistoryScreen(),
      const DriverProfileScreen(),
      const DriverSettingsScreen(),
    ];

    // Initialize Driver state and start polling / live location pushes
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final auth = Provider.of<AuthProvider>(context, listen: false);
      if (auth.user != null) {
        Provider.of<DriverProvider>(context, listen: false).init(auth.user!.id.toString());
      }
    });
  }

  @override
  void dispose() {
    // Stop timers and tracking when shell is disposed
    Future.delayed(Duration.zero, () {
      if (mounted) {
        Provider.of<DriverProvider>(context, listen: false).stop();
      }
    });
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final driver = Provider.of<DriverProvider>(context);
    final auth = Provider.of<AuthProvider>(context, listen: false);

    if (driver.isSessionExpired) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        // Capture context-dependent objects before async gap
        final messenger = ScaffoldMessenger.of(context);
        final navigator = Navigator.of(context);
        // Set offline in DB before logging out
        driver.updateUnitAvailability(false, forceOffline: true).then((_) {
          auth.logout();
          messenger.showSnackBar(
            const SnackBar(
              content: Text('Session expired. Please log in again.'),
              backgroundColor: Colors.redAccent,
            ),
          );
          navigator.pushNamedAndRemoveUntil('/login', (route) => false);
        });
      });
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }

    // ── SOS Dispatch alert — fires on ANY tab ─────────────────────────────────
    // Reset flag if overlay was cleared externally (e.g. dismiss from another path)
    if (!driver.isSosOverlayShowing && _isSosDialogShowing) {
      _isSosDialogShowing = false;
    }
    if (driver.isSosOverlayShowing && driver.incomingSos != null && !_isSosDialogShowing) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (!_isSosDialogShowing && driver.isSosOverlayShowing && mounted) {
          _isSosDialogShowing = true;
          _showSosDispatchDialog(context, driver);
        }
      });
    }

    final isDark = Theme.of(context).brightness == Brightness.dark;
    
    // Online = available OR busy (active mission) OR hasActiveJob
    final isOnline = driver.unitStatus == 'available' || 
                     driver.unitStatus == 'busy' || 
                     driver.hasActiveJob;

    // Label shown in toggle area
    final String toggleLabel = driver.hasActiveJob 
        ? 'On Mission' 
        : (isOnline ? 'Online' : 'Offline');

    // Tab configs
    final tabItems = [
      (icon: Icons.dashboard_rounded, label: 'Dashboard'),
      (icon: Icons.map_rounded, label: 'Map'),
      (icon: Icons.history_rounded, label: 'History'),
      (icon: Icons.person_rounded, label: 'My Account'),
      (icon: Icons.settings_rounded, label: 'Settings'),
    ];

    const Color activeColor = Color(0xFF6366F1);
    final Color navBg = isDark ? const Color(0xFF0A1628) : Colors.white;
    final Color navBorder = isDark
        ? Colors.white.withValues(alpha: 0.07)
        : Colors.grey.shade100;

    return Scaffold(
      backgroundColor: isDark ? const Color(0xFF060B18) : const Color(0xFFF0F4FF),
      appBar: PreferredSize(
        preferredSize: const Size.fromHeight(0),
        child: AppBar(elevation: 0, toolbarHeight: 0),
      ),
      body: Column(
        children: [
          Expanded(
            child: IndexedStack(
              index: _currentIndex,
              children: _screens,
            ),
          ),
        ],
      ),
      bottomNavigationBar: Container(
        decoration: BoxDecoration(
          color: navBg,
          border: Border(
            top: BorderSide(color: navBorder, width: 1),
          ),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: isDark ? 0.25 : 0.06),
              blurRadius: 24,
              offset: const Offset(0, -6),
            ),
          ],
        ),
        child: SafeArea(
          top: false,
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 8),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceAround,
              children: List.generate(tabItems.length, (i) {
                final item = tabItems[i];
                final isActive = _currentIndex == i;
                return _NavItem(
                  icon: item.icon,
                  label: AppTranslator.t(context, item.label),
                  isActive: isActive,
                  activeColor: activeColor,
                  isDark: isDark,
                  onTap: () {
                    HapticFeedback.selectionClick();
                    setState(() => _currentIndex = i);
                  },
                  // special: show online badge on Dashboard tab
                  badge: i == 0 && driver.hasActiveJob ? '!' : null,
                );
              }),
            ),
          ),
        ),
      ),
      // ── Floating Online/Offline toggle bar ─────────────────────────────────
      floatingActionButton: _currentIndex == 0
          ? Padding(
              padding: const EdgeInsets.only(bottom: 0),
              child: _OnlineToggleFab(
                isOnline: isOnline,
                isBusy: driver.hasActiveJob,
                isToggling: driver.isTogglingStatus,
                label: toggleLabel,
                isDark: isDark,
                onToggle: (driver.hasActiveJob || driver.isTogglingStatus)
                    ? null
                    : (val) {
                        debugPrint(
                            '[DriverShell] Toggling availability to: $val');
                        driver.updateUnitAvailability(val);
                      },
              ),
            )
          : null,
      floatingActionButtonLocation: FloatingActionButtonLocation.endFloat,
    );
  }

  // ─── SOS Dispatch Alert Dialog ────────────────────────────────────────────────
  void _showSosDispatchDialog(BuildContext context, DriverProvider driver) {
    final sos = driver.incomingSos!;
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final int id = int.tryParse(sos['id']?.toString() ?? '0') ?? 0;

    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (ctx) => PopScope(
        canPop: false,
        child: Dialog(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
          insetPadding: const EdgeInsets.symmetric(horizontal: 20, vertical: 40),
          child: SingleChildScrollView(
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  // Pulsing alert icon
                  Container(
                    width: 72,
                    height: 72,
                    decoration: BoxDecoration(
                      color: Colors.red.withValues(alpha: 0.12),
                      shape: BoxShape.circle,
                    ),
                    child: const Icon(Icons.emergency_rounded, color: Colors.red, size: 40),
                  ),
                  const SizedBox(height: 16),
                  const Text(
                    '🚨 NEW DISPATCH',
                    style: TextStyle(
                      color: Colors.red,
                      fontWeight: FontWeight.w900,
                      fontSize: 13,
                      letterSpacing: 2,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    '${sos['emergency_type'] ?? 'Emergency'} Request',
                    textAlign: TextAlign.center,
                    style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 20, letterSpacing: -0.5),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    'A new emergency has been assigned to your unit.',
                    textAlign: TextAlign.center,
                    style: TextStyle(color: Colors.grey.shade500, fontSize: 13),
                  ),
                  const SizedBox(height: 20),

                  // Details box
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      color: isDark ? const Color(0xFF1E293B) : Colors.grey.withValues(alpha: 0.07),
                      borderRadius: BorderRadius.circular(14),
                    ),
                    child: Column(
                      children: [
                        _sosRow('Patient', sos['patient_name'] ?? sos['fullname'] ?? 'Unknown', isDark),
                        const Divider(height: 14),
                        _sosRow('Type', sos['emergency_type'] ?? 'Unknown', isDark),
                        if ((sos['patient_phone'] ?? sos['phone'] ?? '').toString().isNotEmpty) ...[
                          const Divider(height: 14),
                          _sosRow('Contact', sos['patient_phone'] ?? sos['phone'] ?? '', isDark, valColor: Colors.orange),
                        ],
                        if ((sos['description'] ?? '').toString().isNotEmpty) ...[
                          const Divider(height: 14),
                          _sosRow('Info', sos['description'], isDark),
                        ],
                      ],
                    ),
                  ),
                  const SizedBox(height: 24),

                  // Action buttons
                  Column(
                    children: [
                      // Accept button — full width, prominent
                      SizedBox(
                        width: double.infinity,
                        child: ElevatedButton.icon(
                          onPressed: () async {
                            Navigator.of(ctx).pop();
                            await driver.acceptMission(id);
                          },
                          icon: const Icon(Icons.check_circle_rounded, size: 18),
                          label: const Text('Accept & Respond', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 15)),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: const Color(0xFF10B981),
                            foregroundColor: Colors.white,
                            padding: const EdgeInsets.symmetric(vertical: 15),
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                          ),
                        ),
                      ),
                      const SizedBox(height: 10),
                      // Reject and Dismiss row
                      Row(
                        children: [
                          Expanded(
                            child: OutlinedButton.icon(
                              onPressed: () async {
                                Navigator.of(ctx).pop();
                                await driver.rejectMission(id);
                              },
                              icon: const Icon(Icons.cancel_rounded, size: 16),
                              label: const Text('Reject', style: TextStyle(fontWeight: FontWeight.bold)),
                              style: OutlinedButton.styleFrom(
                                foregroundColor: Colors.red,
                                side: const BorderSide(color: Colors.red),
                                padding: const EdgeInsets.symmetric(vertical: 12),
                                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                              ),
                            ),
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: OutlinedButton(
                              onPressed: () {
                                driver.dismissSosOverlay();
                                Navigator.of(ctx).pop();
                              },
                              style: OutlinedButton.styleFrom(
                                padding: const EdgeInsets.symmetric(vertical: 12),
                                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                              ),
                              child: const Text('Dismiss', style: TextStyle(fontWeight: FontWeight.bold)),
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    ).then((_) {
      _isSosDialogShowing = false;
      driver.dismissSosOverlay();
    });
  }

  Widget _sosRow(String label, String value, bool isDark, {Color? valColor}) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(label, style: TextStyle(color: Colors.grey.shade500, fontSize: 12, fontWeight: FontWeight.bold)),
        Flexible(
          child: Text(
            value,
            textAlign: TextAlign.end,
            style: TextStyle(
              color: valColor ?? (isDark ? Colors.white : Colors.black87),
              fontSize: 13,
              fontWeight: FontWeight.w800,
            ),
          ),
        ),
      ],
    );
  }
}

// ─── Custom Nav Item ──────────────────────────────────────────────────────────
class _NavItem extends StatefulWidget {
  final IconData icon;
  final String label;
  final bool isActive;
  final Color activeColor;
  final bool isDark;
  final VoidCallback onTap;
  final String? badge;

  const _NavItem({
    required this.icon,
    required this.label,
    required this.isActive,
    required this.activeColor,
    required this.isDark,
    required this.onTap,
    this.badge,
  });

  @override
  State<_NavItem> createState() => _NavItemState();
}

class _NavItemState extends State<_NavItem> with SingleTickerProviderStateMixin {
  late AnimationController _ctrl;
  late Animation<double> _scale;

  @override
  void initState() {
    super.initState();
    _ctrl = AnimationController(
        vsync: this, duration: const Duration(milliseconds: 120));
    _scale = Tween<double>(begin: 1.0, end: 0.85)
        .animate(CurvedAnimation(parent: _ctrl, curve: Curves.easeIn));
  }

  @override
  void dispose() {
    _ctrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTapDown: (_) => _ctrl.forward(),
      onTapUp: (_) {
        _ctrl.reverse();
        widget.onTap();
      },
      onTapCancel: () => _ctrl.reverse(),
      child: ScaleTransition(
        scale: _scale,
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 250),
          curve: Curves.easeOut,
          padding: EdgeInsets.symmetric(
            horizontal: widget.isActive ? 16 : 12,
            vertical: 8,
          ),
          decoration: BoxDecoration(
            color: widget.isActive
                ? widget.activeColor.withValues(alpha: 0.12)
                : Colors.transparent,
            borderRadius: BorderRadius.circular(16),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Stack(
                clipBehavior: Clip.none,
                children: [
                  AnimatedContainer(
                    duration: const Duration(milliseconds: 250),
                    child: Icon(
                      widget.icon,
                      size: 22,
                      color: widget.isActive
                          ? widget.activeColor
                          : (widget.isDark
                              ? Colors.grey.shade500
                              : Colors.grey.shade400),
                    ),
                  ),
                  if (widget.badge != null)
                    Positioned(
                      top: -4,
                      right: -4,
                      child: Container(
                        width: 14,
                        height: 14,
                        decoration: const BoxDecoration(
                          color: Color(0xFFEF4444),
                          shape: BoxShape.circle,
                        ),
                        child: Center(
                          child: Text(
                            widget.badge!,
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 8,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                        ),
                      ),
                    ),
                ],
              ),
              if (widget.isActive) ...
                [
                  const SizedBox(width: 6),
                  Text(
                    widget.label,
                    style: TextStyle(
                      color: widget.activeColor,
                      fontSize: 12,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ],
            ],
          ),
        ),
      ),
    );
  }
}

// ─── Floating Online/Offline Toggle FAB ──────────────────────────────────────
class _OnlineToggleFab extends StatelessWidget {
  final bool isOnline;
  final bool isBusy;
  final bool isToggling;
  final String label;
  final bool isDark;
  final void Function(bool)? onToggle;

  const _OnlineToggleFab({
    required this.isOnline,
    required this.isBusy,
    required this.isToggling,
    required this.label,
    required this.isDark,
    required this.onToggle,
  });

  @override
  Widget build(BuildContext context) {
    final Color statusColor = isBusy
        ? const Color(0xFFF59E0B)
        : isOnline
            ? const Color(0xFF10B981)
            : Colors.grey;

    return AnimatedContainer(
      duration: const Duration(milliseconds: 300),
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
      decoration: BoxDecoration(
        color: isDark ? const Color(0xFF0F1C30) : Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(
          color: statusColor.withValues(alpha: 0.30),
          width: 1.2,
        ),
        boxShadow: [
          BoxShadow(
            color: statusColor.withValues(alpha: 0.25),
            blurRadius: 16,
            offset: const Offset(0, 4),
          ),
          BoxShadow(
            color: Colors.black.withValues(alpha: isDark ? 0.30 : 0.08),
            blurRadius: 20,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (isToggling)
            SizedBox(
              width: 14,
              height: 14,
              child: CircularProgressIndicator(
                strokeWidth: 2,
                color: statusColor,
              ),
            )
          else
            Container(
              width: 8,
              height: 8,
              decoration: BoxDecoration(
                color: statusColor,
                shape: BoxShape.circle,
                boxShadow: [
                  BoxShadow(
                    color: statusColor.withValues(alpha: 0.55),
                    blurRadius: 6,
                    spreadRadius: 1,
                  ),
                ],
              ),
            ),
          const SizedBox(width: 8),
          Text(
            label,
            style: TextStyle(
              color: statusColor,
              fontSize: 12.5,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(width: 8),
          Transform.scale(
            scale: 0.85,
            child: Switch(
              value: isOnline,
              activeTrackColor: statusColor,
              activeThumbColor: Colors.white,
              inactiveTrackColor:
                  isDark ? const Color(0xFF1E293B) : Colors.grey.shade200,
              onChanged: onToggle,
            ),
          ),
        ],
      ),
    );
  }
}
