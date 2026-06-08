import 'dart:async';
import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../../providers/sos_provider.dart';
import '../../services/api_service.dart';
import '../../services/sound_service.dart';
import 'user_home_screen.dart';
import 'user_map_screen.dart';
import 'user_proof_screen.dart';
import 'user_community_rescue_screen.dart';
import 'user_online_responders_screen.dart';
import '../../providers/auth_provider.dart';
import '../../components/app_drawer.dart';
import '../../components/notification_banner.dart';
import '../../utils/translator.dart';

class UserShell extends StatefulWidget {
  static final GlobalKey<ScaffoldState> scaffoldKey = GlobalKey<ScaffoldState>();
  /// Use this to switch tabs from any child screen without a GlobalKey.
  static final ValueNotifier<int> tabNotifier = ValueNotifier<int>(0);

  const UserShell({super.key});

  @override
  State<UserShell> createState() => _UserShellState();
}

class _UserShellState extends State<UserShell> with WidgetsBindingObserver {
  int _currentIndex = 0;

  void setPage(int index) {
    UserShell.tabNotifier.value = index;
  }

  void _onTabChanged() {
    if (mounted) {
      setState(() {
        _currentIndex = UserShell.tabNotifier.value;
      });
    }
  }
  SosProvider? _sosProvider;
  bool _isCompletionPopupShowing = false;
  
  Timer? _notificationPollTimer;
  bool _isFirstNotifFetch = true;
  final Set<int> _alertedNotificationIds = {};
  
  late final List<Widget> _screens;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _screens = [
      const UserHomeScreen(),
      const UserMapScreen(),
      const UserProofScreen(),
      const UserCommunityRescueScreen(),
      const UserOnlineRespondersScreen(),
    ];

    // Listen to tab changes triggered by child screens
    UserShell.tabNotifier.addListener(_onTabChanged);

    WidgetsBinding.instance.addPostFrameCallback((_) {
      final auth = Provider.of<AuthProvider>(context, listen: false);
      if (auth.user != null) {
        _sosProvider = Provider.of<SosProvider>(context, listen: false);
        _sosProvider!.init(auth.user!.id.toString());
        _sosProvider!.addListener(_sosListener);
      }
    });
    
    // Start notification polling IMMEDIATELY in initState — independent of auth state.
    // This guarantees trusted contact SOS alerts always appear, even after Hot Restart.
    _notificationPollTimer?.cancel();
    _notificationPollTimer = Timer.periodic(const Duration(seconds: 3), (_) {
      if (mounted) _pollNotifications();
    });
    // Also fire once immediately after first frame
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) _pollNotifications();
    });
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    UserShell.tabNotifier.removeListener(_onTabChanged);
    _sosProvider?.removeListener(_sosListener);
    _notificationPollTimer?.cancel();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      if (mounted) {
        final auth = Provider.of<AuthProvider>(context, listen: false);
        auth.refreshFromServer();
      }
    }
  }

  void _sosListener() {
    if (_sosProvider != null && _sosProvider!.popupMessage != null) {
      final msgType = _sosProvider!.popupMessage!;
      final req = _sosProvider!.activeRequest;
      final driverName = req?.driverName ?? '';
      final unitName = req?.unitName ?? '';
      final plateNumber = req?.plateNumber ?? '';
      
      _sosProvider!.clearPopupMessage(); // Clear immediately
      
      WidgetsBinding.instance.addPostFrameCallback((_) {
        // Show WhatsApp-style notification banner
        _showNotificationBanner(msgType, driverName, unitName, plateNumber);
        
        // Show completion popup when responder finishes the job
        if (msgType == 'INCIDENT_COMPLETED' && !_isCompletionPopupShowing) {
          _showCompletionPopup();
        }
      });
    }
  }

  void _showNotificationBanner(String type, String driverName, String unitName, String plateNumber) {
    String title = '';
    String message = '';
    IconData icon = Icons.notifications_rounded;
    Color iconColor = const Color(0xFFE11D48);

    switch (type) {
      case 'SOS_SENT':
        title = 'SOS Signal Broadcasted';
        message = 'Your emergency SOS has been sent. Rescue team is being dispatched.';
        icon = Icons.sensors_rounded;
        iconColor = const Color(0xFFE11D48); // Rose red
        break;
      case 'DRIVER_ASSIGNED':
        title = 'Responder Assigned';
        message = 'Responder ${driverName.isNotEmpty ? driverName : 'Operator'} has been assigned to your request.';
        icon = Icons.assignment_ind_rounded;
        iconColor = const Color(0xFF2563EB); // Deep blue
        break;
      case 'DRIVER_ON_THE_WAY':
        title = 'Driver On The Way';
        message = 'Rescue driver ${driverName.isNotEmpty ? driverName : 'Operator'} ($unitName) is heading to your location.';
        icon = Icons.airport_shuttle_rounded;
        iconColor = const Color(0xFFF59E0B); // Amber orange
        break;
      case 'TRIP_STARTED':
        title = 'Rescue Trip Started';
        message = 'The rescue team is en route! Track them live on the map.';
        icon = Icons.navigation_rounded;
        iconColor = const Color(0xFF10B981); // Emerald green
        break;
      case 'INCIDENT_COMPLETED':
        title = '✅ Emergency Resolved';
        message = 'The responder has successfully completed your emergency. Stay safe!';
        icon = Icons.verified_rounded;
        iconColor = const Color(0xFF10B981); // Emerald green
        break;
    }

    if (title.isNotEmpty) {
      NotificationBanner.show(
        context,
        title: title,
        message: message,
        icon: icon,
        iconColor: iconColor,
        iconBgColor: iconColor.withValues(alpha: 0.1),
      );
    }
  }

  void _showCompletionPopup() {
    if (_isCompletionPopupShowing) return;
    _isCompletionPopupShowing = true;
    
    final isDark = Theme.of(context).brightness == Brightness.dark;
    bool dismissed = false;

    showDialog(
      context: context,
      barrierDismissible: true,
      builder: (BuildContext ctx) {
        // Auto-close dialog after 5s if user hasn't tapped Done yet
        Future.delayed(const Duration(seconds: 5), () {
          if (dismissed) return; // already handled by user
          dismissed = true;
          if (ctx.mounted) Navigator.of(ctx).pop();
          if (mounted) {
            setState(() => _currentIndex = 0);
            UserShell.tabNotifier.value = 0;
          }
        });
        return Dialog(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(28)),
          elevation: 24,

          backgroundColor: isDark ? const Color(0xFF1E293B) : Colors.white,
          child: Padding(
            padding: const EdgeInsets.all(32.0),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                // Success Icon with green glow
                Container(
                  padding: const EdgeInsets.all(22),
                  decoration: BoxDecoration(
                    color: const Color(0xFF10B981).withValues(alpha: 0.12),
                    shape: BoxShape.circle,
                    boxShadow: [
                      BoxShadow(
                        color: const Color(0xFF10B981).withValues(alpha: 0.25),
                        blurRadius: 30,
                        spreadRadius: 5,
                      ),
                    ],
                  ),
                  child: const Icon(
                    Icons.check_circle_rounded,
                    color: Color(0xFF10B981),
                    size: 60,
                  ),
                ),
                const SizedBox(height: 24),
                Text(
                  'EMERGENCY RESOLVED!',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontSize: 20,
                    fontWeight: FontWeight.w900,
                    letterSpacing: 0.5,
                    color: isDark ? Colors.white : const Color(0xFF1E293B),
                  ),
                ),
                const SizedBox(height: 12),
                Text(
                  'The responder has successfully completed your emergency request. You are now safe.',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontSize: 14,
                    color: isDark ? Colors.grey.shade400 : Colors.grey.shade600,
                    height: 1.5,
                  ),
                ),
                const SizedBox(height: 8),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                  decoration: BoxDecoration(
                    color: const Color(0xFF10B981).withValues(alpha: 0.08),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: const Color(0xFF10B981).withValues(alpha: 0.25)),
                  ),
                  child: const Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(Icons.shield_rounded, color: Color(0xFF10B981), size: 16),
                      SizedBox(width: 8),
                      Text(
                        'Incident has been closed',
                        style: TextStyle(
                          color: Color(0xFF10B981),
                          fontWeight: FontWeight.w700,
                          fontSize: 13,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 28),
                SizedBox(
                  width: double.infinity,
                  height: 52,
                  child: ElevatedButton(
                    onPressed: () {
                      if (dismissed) return;
                      dismissed = true;
                      Navigator.of(ctx).pop();
                      // Stay on Home/SOS tab
                      if (mounted) {
                        setState(() => _currentIndex = 0);
                        UserShell.tabNotifier.value = 0;
                      }
                    },
                    style: ElevatedButton.styleFrom(
                      backgroundColor: const Color(0xFF10B981),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                      elevation: 0,
                    ),
                    child: const Text(
                      'Done — I\'m Safe',
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 16,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
        );
      },
    ).then((_) {
      _isCompletionPopupShowing = false;
      if (mounted) {
        Provider.of<SosProvider>(context, listen: false).clearCompletedRequest();
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      key: UserShell.scaffoldKey,
      drawer: AppDrawer(
        currentIndex: _currentIndex,
        onTabSelected: (index) {
          setState(() {
            _currentIndex = index;
          });
        },
      ),
      body: IndexedStack(
        index: _currentIndex,
        children: _screens,
      ),
      bottomNavigationBar: Container(
        decoration: BoxDecoration(
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.05),
              blurRadius: 20,
              offset: const Offset(0, -5),
            )
          ],
        ),
        child: BottomNavigationBar(
          currentIndex: _currentIndex,
          onTap: (index) => setState(() => _currentIndex = index),
          type: BottomNavigationBarType.fixed,
          backgroundColor: Theme.of(context).cardTheme.color,
          selectedItemColor: Theme.of(context).colorScheme.primary,
          unselectedItemColor: Colors.grey.shade400,
          showSelectedLabels: true,
          showUnselectedLabels: true,
          selectedLabelStyle: const TextStyle(fontWeight: FontWeight.w700, fontSize: 12),
          unselectedLabelStyle: const TextStyle(fontWeight: FontWeight.w500, fontSize: 12),
          items: [
            BottomNavigationBarItem(
              icon: const Padding(padding: EdgeInsets.only(bottom: 4), child: Icon(Icons.home_rounded)),
              label: AppTranslator.t(context, 'SOS'),
            ),
            BottomNavigationBarItem(
              icon: const Padding(padding: EdgeInsets.only(bottom: 4), child: Icon(Icons.map_rounded)),
              label: AppTranslator.t(context, 'Map'),
            ),
            BottomNavigationBarItem(
              icon: const Padding(padding: EdgeInsets.only(bottom: 4), child: Icon(Icons.shield_rounded)),
              label: AppTranslator.t(context, 'Proof'),
            ),
            BottomNavigationBarItem(
              icon: const Padding(padding: EdgeInsets.only(bottom: 4), child: Icon(Icons.volunteer_activism_rounded)),
              label: AppTranslator.t(context, 'Community'),
            ),
            BottomNavigationBarItem(
              icon: const Padding(padding: EdgeInsets.only(bottom: 4), child: Icon(Icons.more_horiz_rounded)),
              label: AppTranslator.t(context, 'More'),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _pollNotifications() async {
    if (!mounted) return;
    
    try {
      // Read user_id directly from SharedPreferences — does NOT depend on Provider or context.
      // This ensures the timer works for BOTH users independently even after hot restarts.
      final prefs = await SharedPreferences.getInstance();
      String? userId;
      try {
        final userDataStr = prefs.getString('user_data');
        if (userDataStr != null) {
          final userData = jsonDecode(userDataStr) as Map<String, dynamic>;
          userId = userData['id']?.toString();
        }
        userId ??= prefs.getString('user_id');
      } catch (_) {}

      if (userId == null || userId.isEmpty || userId == '0') {
        return; // Not logged in yet, skip
      }

      final notifs = await ApiService.getNotifications(userId);
      debugPrint('[Notif] user=$userId polled ${notifs.length} notifs');

      if (!mounted) return;
      if (notifs.isEmpty) {
        _isFirstNotifFetch = false;
        return;
      }

      bool playedSound = false;

      for (final notif in notifs) {
        final dynamic rawId = notif['id'];
        if (rawId == null) continue;
        final int id = int.tryParse(rawId.toString()) ?? 0;
        if (id == 0) continue;

        // Skip already-seen notifications
        if (_alertedNotificationIds.contains(id)) continue;
        _alertedNotificationIds.add(id);

        // Check if the notification is recent (within last 60 seconds)
        final String? rawTime = notif['created_at'] as String?;
        bool isRecent = false;
        if (rawTime != null) {
          try {
            final DateTime createdTime = DateTime.parse(rawTime);
            isRecent = DateTime.now().difference(createdTime).inSeconds.abs() <= 60;
          } catch (_) {}
        }

        // On first fetch at app start, skip old notifications
        if (_isFirstNotifFetch && !isRecent) continue;

        // Only show banners for SOS / emergency / trusted contact notifications
        final title = (notif['title'] as String? ?? '').toLowerCase();
        final message = notif['message'] as String? ?? '';

        if (title.contains('gurmad') || title.contains('sos') ||
            title.contains('emergency') || title.contains('ehelka')) {
          if (!mounted) break;
          IconData icon = Icons.emergency_rounded;
          Color iconColor = const Color(0xFFEF4444);
          if (title.contains('ogaysiiyay') || title.contains('alerted') || title.contains('✅')) {
            icon = Icons.verified_rounded;
            iconColor = const Color(0xFF10B981);
          }

          NotificationBanner.show(
            context,
            title: notif['title'] as String? ?? '🚨 SOS Alert',
            message: message,
            icon: icon,
            iconColor: iconColor,
            iconBgColor: iconColor.withValues(alpha: 0.1),
          );

          if (!playedSound) {
            SoundService.playNotificationBeep();
            playedSound = true;
          }
        }
      }
      _isFirstNotifFetch = false;
    } catch (e) {
      debugPrint('[Notif] Error: $e');
    }
  }
}
