import 'dart:math';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:font_awesome_flutter/font_awesome_flutter.dart';
import '../../providers/auth_provider.dart';
import '../../providers/driver_provider.dart';
import '../../constants/api_constants.dart';

class DriverDashboardScreen extends StatefulWidget {
  const DriverDashboardScreen({super.key});

  @override
  State<DriverDashboardScreen> createState() => _DriverDashboardScreenState();
}

class _DriverDashboardScreenState extends State<DriverDashboardScreen>
    with TickerProviderStateMixin {
  // Pulse animation for standby radar
  late AnimationController _pulseController;
  late Animation<double> _pulseAnim1;
  late Animation<double> _pulseAnim2;
  late Animation<double> _pulseAnim3;

  // Count-up animation for KPI numbers
  late AnimationController _countController;
  late Animation<double> _savesAnim;
  late Animation<double> _missionsAnim;

  // Entrance animation
  late AnimationController _entranceController;
  late Animation<double> _entranceFade;
  late Animation<Offset> _entranceSlide;

  int _lastSaves = 0;
  int _lastMissions = 0;

  @override
  void initState() {
    super.initState();

    // Radar pulse — 3 staggered expanding rings
    _pulseController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 2400),
    )..repeat();

    _pulseAnim1 = Tween<double>(begin: 0.0, end: 1.0).animate(
      CurvedAnimation(
        parent: _pulseController,
        curve: const Interval(0.0, 0.7, curve: Curves.easeOut),
      ),
    );
    _pulseAnim2 = Tween<double>(begin: 0.0, end: 1.0).animate(
      CurvedAnimation(
        parent: _pulseController,
        curve: const Interval(0.2, 0.9, curve: Curves.easeOut),
      ),
    );
    _pulseAnim3 = Tween<double>(begin: 0.0, end: 1.0).animate(
      CurvedAnimation(
        parent: _pulseController,
        curve: const Interval(0.4, 1.0, curve: Curves.easeOut),
      ),
    );

    // KPI count-up
    _countController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 900),
    );
    _savesAnim = Tween<double>(begin: 0, end: 0).animate(
      CurvedAnimation(parent: _countController, curve: Curves.easeOut),
    );
    _missionsAnim = Tween<double>(begin: 0, end: 0).animate(
      CurvedAnimation(parent: _countController, curve: Curves.easeOut),
    );

    // Entrance animation
    _entranceController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 700),
    );
    _entranceFade = CurvedAnimation(parent: _entranceController, curve: Curves.easeOut);
    _entranceSlide = Tween<Offset>(begin: const Offset(0, 0.06), end: Offset.zero).animate(
      CurvedAnimation(parent: _entranceController, curve: Curves.easeOut),
    );
    _entranceController.forward();
  }

  @override
  void dispose() {
    _pulseController.dispose();
    _countController.dispose();
    _entranceController.dispose();
    super.dispose();
  }

  void _triggerCountUp(int saves, int missions) {
    if (saves != _lastSaves || missions != _lastMissions) {
      _lastSaves = saves;
      _lastMissions = missions;
      _savesAnim = Tween<double>(begin: 0, end: saves.toDouble()).animate(
        CurvedAnimation(parent: _countController, curve: Curves.easeOut),
      );
      _missionsAnim = Tween<double>(begin: 0, end: missions.toDouble()).animate(
        CurvedAnimation(parent: _countController, curve: Curves.easeOut),
      );
      _countController.forward(from: 0);
    }
  }

  double _haversineDistance(double lat1, double lon1, double lat2, double lon2) {
    const double r = 6371.0;
    final double dLat = _toRadians(lat2 - lat1);
    final double dLon = _toRadians(lon2 - lon1);
    final double a = sin(dLat / 2) * sin(dLat / 2) +
        cos(_toRadians(lat1)) * cos(_toRadians(lat2)) *
        sin(dLon / 2) * sin(dLon / 2);
    final double c = 2 * atan2(sqrt(a), sqrt(1 - a));
    return r * c;
  }

  double _toRadians(double degree) => degree * pi / 180.0;

  void _callNumber(String phone) async {
    final Uri url = Uri.parse('tel:$phone');
    if (await canLaunchUrl(url)) await launchUrl(url);
  }

  @override
  Widget build(BuildContext context) {
    final driver = Provider.of<DriverProvider>(context);
    final auth = Provider.of<AuthProvider>(context);
    final isDark = Theme.of(context).brightness == Brightness.dark;

    _triggerCountUp(driver.saves, driver.history.length);

    return Scaffold(
      backgroundColor: isDark ? const Color(0xFF060B18) : const Color(0xFFF0F4FF),
      body: driver.initialLoading
          ? _buildSkeleton(isDark)
          : RefreshIndicator(
              color: const Color(0xFF6366F1),
              backgroundColor: isDark ? const Color(0xFF1A2235) : Colors.white,
              onRefresh: () => driver.fetchDriverData(),
              child: FadeTransition(
                opacity: _entranceFade,
                child: SlideTransition(
                  position: _entranceSlide,
                  child: SingleChildScrollView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        // ── Hero header with gradient bg ────────────────────
                        _buildHeroHeader(auth, driver, isDark),

                        // ── KPI Grid raised up over Hero Header ─────────────
                        Padding(
                          padding: const EdgeInsets.fromLTRB(16, 0, 16, 0),
                          child: _buildKpiGrid(driver, isDark),
                        ),

                        if (driver.hasActiveJob) ...[
                          const SizedBox(height: 8),
                          Padding(
                            padding: const EdgeInsets.fromLTRB(14, 0, 14, 16),
                            child: AnimatedSize(
                              duration: const Duration(milliseconds: 300),
                              curve: Curves.easeInOut,
                              child: _buildActiveMissionCard(driver, isDark),
                            ),
                          ),
                          const SizedBox(height: 70),
                        ] else ...[
                          const SizedBox(height: 12),
                          Padding(
                            padding: const EdgeInsets.fromLTRB(16, 0, 16, 32),
                            child: AnimatedSize(
                              duration: const Duration(milliseconds: 300),
                              curve: Curves.easeInOut,
                              child: _buildStandbyCard(driver, isDark),
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                ),
              ),
            ),
    );
  }

  // ─── Skeleton Loading ────────────────────────────────────────────────────────
  Widget _buildSkeleton(bool isDark) {
    final shimmer = isDark ? const Color(0xFF1A2235) : Colors.grey.shade200;
    return Column(
      children: [
        Container(
          height: 220,
          color: shimmer,
        ),
        Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            children: [
              Row(
                children: List.generate(3, (i) => Expanded(
                  child: Padding(
                    padding: EdgeInsets.only(left: i > 0 ? 10 : 0),
                    child: Container(
                      height: 110,
                      decoration: BoxDecoration(
                        color: shimmer,
                        borderRadius: BorderRadius.circular(20),
                      ),
                    ),
                  ),
                )),
              ),
              const SizedBox(height: 16),
              Container(
                height: 220,
                decoration: BoxDecoration(
                  color: shimmer,
                  borderRadius: BorderRadius.circular(24),
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }

  // ─── Hero Header ─────────────────────────────────────────────────────────────
  Widget _buildHeroHeader(AuthProvider auth, DriverProvider driver, bool isDark) {
    final user = auth.user;
    final initials = user != null && user.fullname.isNotEmpty
        ? user.fullname.substring(0, 1).toUpperCase()
        : 'D';

    final isOnline = driver.unitStatus == 'available' || driver.unitStatus == 'busy';
    final isBusy = driver.unitStatus == 'busy' || driver.hasActiveJob;

    Color statusColor;
    String statusLabel;
    IconData statusIcon;

    if (isBusy) {
      statusColor = const Color(0xFFF59E0B);
      statusLabel = 'On Mission';
      statusIcon = Icons.local_fire_department_rounded;
    } else if (isOnline) {
      statusColor = const Color(0xFF10B981);
      statusLabel = 'Online — Ready';
      statusIcon = Icons.wifi_rounded;
    } else {
      statusColor = Colors.grey;
      statusLabel = 'Offline';
      statusIcon = Icons.wifi_off_rounded;
    }

    return Container(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: isDark
              ? [const Color(0xFF0D1B35), const Color(0xFF060B18)]
              : [const Color(0xFF1E3A8A), const Color(0xFF3B82F6)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
      ),
      child: SafeArea(
        bottom: false,
        child: Stack(
          children: [
            // Decorative circles
            Positioned(
              top: -30,
              right: -30,
              child: Container(
                width: 160,
                height: 160,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: Colors.white.withValues(alpha: 0.04),
                ),
              ),
            ),
            Positioned(
              bottom: 10,
              left: -40,
              child: Container(
                width: 120,
                height: 120,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: Colors.white.withValues(alpha: 0.03),
                ),
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 48, 20, 0),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Row: avatar + name + status pill
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.center,
                    children: [
                      // Avatar with animated ring
                      Stack(
                        alignment: Alignment.bottomRight,
                        children: [
                          Container(
                            padding: const EdgeInsets.all(3),
                            decoration: BoxDecoration(
                              shape: BoxShape.circle,
                              gradient: LinearGradient(
                                colors: [
                                  statusColor,
                                  statusColor.withValues(alpha: 0.3),
                                ],
                              ),
                            ),
                            child: CircleAvatar(
                              radius: 30,
                              backgroundColor: const Color(0xFF1E40AF),
                              backgroundImage: user?.profileImage != null &&
                                      user!.profileImage.isNotEmpty
                                  ? NetworkImage(ApiConstants.avatarUrl(user.profileImage))
                                  : null,
                              child: user?.profileImage == null ||
                                      user!.profileImage.isEmpty
                                  ? Text(
                                      initials,
                                      style: const TextStyle(
                                        color: Colors.white,
                                        fontSize: 24,
                                        fontWeight: FontWeight.bold,
                                      ),
                                    )
                                  : null,
                            ),
                          ),
                          if (isOnline) _BlinkingDot(color: statusColor),
                        ],
                      ),
                      const SizedBox(width: 14),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              user?.fullname ?? 'Responder',
                              style: const TextStyle(
                                fontWeight: FontWeight.w900,
                                fontSize: 20,
                                letterSpacing: -0.5,
                                color: Colors.white,
                              ),
                            ),
                            if (driver.unit != null) ...[
                              const SizedBox(height: 3),
                              Row(
                                children: [
                                  FaIcon(
                                    FontAwesomeIcons.truckMedical,
                                    size: 13,
                                    color: Colors.white.withValues(alpha: 0.75),
                                  ),
                                  const SizedBox(width: 4),
                                  Flexible(
                                    child: Text(
                                      '${driver.unit!['unit_name']} · ${driver.unit!['plate_number']}',
                                      style: TextStyle(
                                        color: Colors.white.withValues(alpha: 0.65),
                                        fontSize: 12.5,
                                        fontWeight: FontWeight.w600,
                                      ),
                                      overflow: TextOverflow.ellipsis,
                                    ),
                                  ),
                                ],
                              ),
                            ],
                          ],
                        ),
                      ),
                      // Status pill
                      AnimatedContainer(
                        duration: const Duration(milliseconds: 400),
                        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 7),
                        decoration: BoxDecoration(
                          color: statusColor.withValues(alpha: 0.18),
                          borderRadius: BorderRadius.circular(20),
                          border: Border.all(
                            color: statusColor.withValues(alpha: 0.45),
                            width: 1.2,
                          ),
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Icon(statusIcon, color: statusColor, size: 13),
                            const SizedBox(width: 5),
                            Text(
                              statusLabel,
                              style: TextStyle(
                                color: statusColor,
                                fontSize: 11.5,
                                fontWeight: FontWeight.w800,
                                letterSpacing: 0.2,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),

                  const SizedBox(height: 24),

                  // KPI cards overlap here via negative margin trick
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  // ─── KPI Grid ───────────────────────────────────────────────────────────────
  Widget _buildKpiGrid(DriverProvider driver, bool isDark) {
    // Cards lift up over hero header
    return Transform.translate(
      offset: const Offset(0, -16),
      child: AnimatedBuilder(
        animation: _countController,
        builder: (context, _) {
          final completedCount = driver.history
              .where((m) => m['status'] == 'completed')
              .length;
          final total = driver.history.length;
          final rate = total > 0 ? ((completedCount / total) * 100).round() : 0;

          return Row(
            children: [
              Expanded(
                child: _GlassKpiCard(
                  isDark: isDark,
                  icon: Icons.favorite_rounded,
                  iconGradient: const [Color(0xFFFF6B6B), Color(0xFFEF4444)],
                  title: 'Saved',
                  value: _savesAnim.value.toInt().toString(),
                  subtitle: 'Lives rescued',
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _GlassKpiCard(
                  isDark: isDark,
                  icon: Icons.assignment_rounded,
                  iconGradient: const [Color(0xFF818CF8), Color(0xFF6366F1)],
                  title: 'Missions',
                  value: _missionsAnim.value.toInt().toString(),
                  subtitle: 'All time',
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _GlassKpiCard(
                  isDark: isDark,
                  icon: Icons.insights_rounded,
                  iconGradient: const [Color(0xFF34D399), Color(0xFF10B981)],
                  title: 'Success',
                  value: '$rate%',
                  subtitle: 'Completion',
                ),
              ),
            ],
          );
        },
      ),
    );
  }

  // ─── Standby Card with Pulsing Radar ────────────────────────────────────────
  Widget _buildStandbyCard(DriverProvider driver, bool isDark) {
    final isOnline = driver.unitStatus == 'available';

    return Container(
      decoration: BoxDecoration(
        color: isDark ? const Color(0xFF0F1C30) : Colors.white,
        borderRadius: BorderRadius.circular(28),
        border: Border.all(
          color: isDark
              ? Colors.white.withValues(alpha: 0.07)
              : Colors.grey.shade100,
          width: 1.2,
        ),
        boxShadow: [
          BoxShadow(
            color: isDark
                ? Colors.black.withValues(alpha: 0.35)
                : Colors.blue.withValues(alpha: 0.06),
            blurRadius: 24,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      padding: const EdgeInsets.symmetric(vertical: 40, horizontal: 24),
      child: Column(
        children: [
          SizedBox(
            width: 140,
            height: 140,
            child: AnimatedBuilder(
              animation: _pulseController,
              builder: (context, child) {
                final Color pulseColor = isOnline
                    ? const Color(0xFF6366F1)
                    : Colors.grey.shade400;
                return Stack(
                  alignment: Alignment.center,
                  children: [
                    if (isOnline) ...[
                      Opacity(
                        opacity: (1.0 - _pulseAnim3.value).clamp(0.0, 1.0),
                        child: Transform.scale(
                          scale: 0.55 + (_pulseAnim3.value * 0.85),
                          child: Container(
                            decoration: BoxDecoration(
                              shape: BoxShape.circle,
                              border: Border.all(
                                  color: pulseColor.withValues(alpha: 0.08),
                                  width: 1.5),
                            ),
                          ),
                        ),
                      ),
                      Opacity(
                        opacity: (1.0 - _pulseAnim2.value).clamp(0.0, 1.0),
                        child: Transform.scale(
                          scale: 0.45 + (_pulseAnim2.value * 0.65),
                          child: Container(
                            decoration: BoxDecoration(
                              shape: BoxShape.circle,
                              border: Border.all(
                                  color: pulseColor.withValues(alpha: 0.15),
                                  width: 1.5),
                            ),
                          ),
                        ),
                      ),
                      Opacity(
                        opacity: (1.0 - _pulseAnim1.value).clamp(0.0, 1.0),
                        child: Transform.scale(
                          scale: 0.35 + (_pulseAnim1.value * 0.45),
                          child: Container(
                            decoration: BoxDecoration(
                              shape: BoxShape.circle,
                              border: Border.all(
                                  color: pulseColor.withValues(alpha: 0.28),
                                  width: 1.5),
                            ),
                          ),
                        ),
                      ),
                    ],
                    Container(
                      width: 72,
                      height: 72,
                      decoration: BoxDecoration(
                        gradient: isOnline
                            ? const LinearGradient(
                                colors: [Color(0xFF4F46E5), Color(0xFF818CF8)],
                                begin: Alignment.topLeft,
                                end: Alignment.bottomRight,
                              )
                            : null,
                        color: isOnline
                            ? null
                            : (isDark
                                ? const Color(0xFF1E293B)
                                : Colors.grey.shade200),
                        shape: BoxShape.circle,
                        boxShadow: isOnline
                            ? [
                                BoxShadow(
                                  color: const Color(0xFF6366F1).withValues(alpha: 0.40),
                                  blurRadius: 24,
                                  spreadRadius: 2,
                                )
                              ]
                            : null,
                      ),
                      child: Icon(
                        Icons.sensors_rounded,
                        color: isOnline
                            ? Colors.white
                            : (isDark
                                ? Colors.grey.shade500
                                : Colors.grey.shade400),
                        size: 32,
                      ),
                    ),
                  ],
                );
              },
            ),
          ),
          const SizedBox(height: 22),
          Text(
            isOnline ? 'Scanning for Dispatch…' : 'You are Offline',
            style: TextStyle(
              fontWeight: FontWeight.w900,
              fontSize: 20,
              letterSpacing: -0.5,
              color: isDark ? Colors.white : const Color(0xFF0F172A),
            ),
          ),
          const SizedBox(height: 10),
          Text(
            isOnline
                ? 'System is actively monitoring for new emergency assignments.\nStay alert — you will be notified immediately.'
                : 'Toggle the switch in the top bar to go online\nand receive emergency dispatches.',
            textAlign: TextAlign.center,
            style: TextStyle(
              color: isDark ? Colors.grey.shade500 : Colors.grey.shade400,
              fontSize: 13,
              height: 1.6,
              fontWeight: FontWeight.w500,
            ),
          ),
          if (driver.currentPosition != null) ...[
            const SizedBox(height: 22),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
              decoration: BoxDecoration(
                color: const Color(0xFF10B981).withValues(alpha: 0.08),
                borderRadius: BorderRadius.circular(14),
                border: Border.all(
                    color: const Color(0xFF10B981).withValues(alpha: 0.20)),
              ),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Icon(Icons.gps_fixed_rounded,
                      color: Color(0xFF10B981), size: 14),
                  const SizedBox(width: 8),
                  Text(
                    'GPS Active · ${driver.currentPosition!.latitude.toStringAsFixed(4)}, ${driver.currentPosition!.longitude.toStringAsFixed(4)}',
                    style: const TextStyle(
                      color: Color(0xFF10B981),
                      fontSize: 11.5,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }

  // ─── Active Mission Card ─────────────────────────────────────────────────────
  Widget _buildActiveMissionCard(DriverProvider driver, bool isDark) {
    final job = driver.activeJob!;
    final status = job['status'] ?? 'pending';

    final steps = ['pending', 'accepted', 'en_route', 'arrived', 'complete'];
    final stepLabels = ['Assigned', 'Accepted', 'En Route', 'Arrived', 'Done'];
    final stepIcons = [
      Icons.notification_important_rounded,
      Icons.check_circle_rounded,
      Icons.medical_services_rounded,
      Icons.location_on_rounded,
      Icons.verified_rounded,
    ];
    final currentStep = steps.indexOf(status).clamp(0, 4);

    String distanceStr = 'Calculating…';
    String etaStr = 'ETA: …';
    if (driver.currentPosition != null &&
        job['lat'] != null &&
        job['lng'] != null) {
      try {
        final double vLat = double.tryParse(job['lat'].toString()) ?? 0.0;
        final double vLng = double.tryParse(job['lng'].toString()) ?? 0.0;
        if (vLat != 0.0 && vLng != 0.0) {
          final dist = _haversineDistance(
              driver.currentPosition!.latitude,
              driver.currentPosition!.longitude,
              vLat,
              vLng);
          final etaMin = (dist / 40.0 * 60.0).ceil();
          distanceStr = '${dist.toStringAsFixed(1)} km';
          etaStr = 'ETA: $etaMin min';
        }
      } catch (_) {}
    }

    return Container(
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFF1E1B4B), Color(0xFF312E81), Color(0xFF1E40AF)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(22),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF4F46E5).withValues(alpha: 0.35),
            blurRadius: 20,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Stack(
        children: [
          // Decorative top-right circle
          Positioned(
            top: -20,
            right: -20,
            child: Container(
              width: 100,
              height: 100,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: Colors.white.withValues(alpha: 0.04),
              ),
            ),
          ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              // Header
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 14, 16, 0),
                child: Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.all(8),
                      decoration: BoxDecoration(
                        color: Colors.white.withValues(alpha: 0.12),
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: const Icon(Icons.emergency_rounded,
                          color: Colors.white, size: 16),
                    ),
                    const SizedBox(width: 10),
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'ACTIVE MISSION',
                          style: TextStyle(
                            color: Colors.white60,
                            fontSize: 9,
                            fontWeight: FontWeight.w800,
                            letterSpacing: 1.2,
                          ),
                        ),
                        Text(
                          job['emergency_type'] ?? 'Emergency',
                          style: const TextStyle(
                            color: Colors.white,
                            fontWeight: FontWeight.w900,
                            fontSize: 15,
                            letterSpacing: -0.3,
                          ),
                        ),
                      ],
                    ),
                    const Spacer(),
                    if (driver.loading)
                      const SizedBox(
                        width: 14,
                        height: 14,
                        child: CircularProgressIndicator(
                            strokeWidth: 2, color: Colors.white60),
                      ),
                  ],
                ),
              ),

              // Progress Stepper
              Padding(
                padding: const EdgeInsets.fromLTRB(14, 10, 14, 0),
                child: Container(
                  padding: const EdgeInsets.symmetric(
                      horizontal: 8, vertical: 8),
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.07),
                    borderRadius: BorderRadius.circular(14),
                    border: Border.all(
                        color: Colors.white.withValues(alpha: 0.10)),
                  ),
                  child: Row(
                    children: List.generate(steps.length, (i) {
                      final isDone = i <= currentStep;
                      final isActive = i == currentStep;
                      return Expanded(
                        child: Row(
                          children: [
                            Column(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                AnimatedContainer(
                                  duration: const Duration(milliseconds: 400),
                                  width: isActive ? 24 : 18,
                                  height: isActive ? 24 : 18,
                                  decoration: BoxDecoration(
                                    gradient: isDone
                                        ? const LinearGradient(
                                            colors: [
                                              Colors.white,
                                              Color(0xFFE0E7FF)
                                            ],
                                          )
                                        : null,
                                    color: isDone
                                        ? null
                                        : Colors.white.withValues(alpha: 0.15),
                                    shape: BoxShape.circle,
                                    boxShadow: isActive
                                        ? [
                                            BoxShadow(
                                              color: Colors.white
                                                  .withValues(alpha: 0.50),
                                              blurRadius: 8,
                                              spreadRadius: 1,
                                            )
                                          ]
                                        : null,
                                  ),
                                  child: Icon(
                                    stepIcons[i],
                                    size: isActive ? 13 : 9,
                                    color: isDone
                                        ? const Color(0xFF312E81)
                                        : Colors.white.withValues(alpha: 0.4),
                                  ),
                                ),
                                const SizedBox(height: 3),
                                Text(
                                  stepLabels[i],
                                  style: TextStyle(
                                    color: isDone
                                        ? Colors.white
                                        : Colors.white.withValues(alpha: 0.30),
                                    fontSize: 7.5,
                                    fontWeight: isActive
                                        ? FontWeight.w900
                                        : FontWeight.w500,
                                  ),
                                ),
                              ],
                            ),
                            if (i < steps.length - 1)
                              Expanded(
                                child: Container(
                                  height: 1.5,
                                  margin: const EdgeInsets.only(bottom: 12),
                                  decoration: BoxDecoration(
                                    borderRadius: BorderRadius.circular(2),
                                    color: i < currentStep
                                        ? Colors.white.withValues(alpha: 0.7)
                                        : Colors.white.withValues(alpha: 0.12),
                                  ),
                                ),
                              ),
                          ],
                        ),
                      );
                    }),
                  ),
                ),
              ),

              const SizedBox(height: 8),

              // Job details
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 14),
                child: Container(
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.08),
                    borderRadius: BorderRadius.circular(14),
                    border:
                        Border.all(color: Colors.white.withValues(alpha: 0.12)),
                  ),
                  child: Column(
                    children: [
                      _buildMissionInfoRow('Patient',
                          job['patient_name'] ?? 'Unknown'),
                      const Divider(color: Colors.white10, height: 6),
                      _buildMissionInfoRow('Emergency',
                          job['emergency_type'] ?? 'Unknown'),
                      const Divider(color: Colors.white10, height: 6),
                      _buildMissionInfoRow('Contact',
                          job['patient_phone'] ?? 'N/A',
                          valColor: const Color(0xFFFBBF24)),
                      if (job['neighborhood'] != null &&
                          job['neighborhood'].toString().isNotEmpty) ...[
                        const Divider(color: Colors.white10, height: 6),
                        _buildMissionInfoRow(
                            'Neighborhood', job['neighborhood']),
                      ],
                      if (job['description'] != null &&
                          job['description'].toString().isNotEmpty) ...[
                        const Divider(color: Colors.white10, height: 6),
                        _buildMissionInfoRow('Info', job['description'],
                            isLongText: true),
                      ],
                    ],
                  ),
                ),
              ),

              const SizedBox(height: 8),

              // Distance / ETA chips
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 14),
                child: Row(
                  children: [
                    Expanded(
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                            vertical: 8, horizontal: 10),
                        decoration: BoxDecoration(
                          color: Colors.white.withValues(alpha: 0.07),
                          borderRadius: BorderRadius.circular(10),
                          border: Border.all(
                              color: Colors.white.withValues(alpha: 0.10)),
                        ),
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            const Icon(Icons.route_rounded,
                                color: Color(0xFF93C5FD), size: 14),
                            const SizedBox(width: 6),
                            Text(
                              distanceStr,
                              style: const TextStyle(
                                color: Colors.white,
                                fontWeight: FontWeight.w800,
                                fontSize: 12,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                            vertical: 8, horizontal: 10),
                        decoration: BoxDecoration(
                          color: Colors.white.withValues(alpha: 0.07),
                          borderRadius: BorderRadius.circular(10),
                          border: Border.all(
                              color: Colors.white.withValues(alpha: 0.10)),
                        ),
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            const Icon(Icons.access_time_filled_rounded,
                                color: Color(0xFFFBBF24), size: 14),
                            const SizedBox(width: 6),
                            Text(
                              etaStr,
                              style: const TextStyle(
                                color: Colors.white,
                                fontWeight: FontWeight.w800,
                                fontSize: 12,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 10),

              // Action buttons
              Padding(
                padding: const EdgeInsets.fromLTRB(14, 0, 14, 14),
                child: Row(
                  children: [
                    _GlassButton(
                      icon: Icons.phone_in_talk_rounded,
                      label: 'Call',
                      onTap: () {
                        HapticFeedback.lightImpact();
                        _callNumber(job['patient_phone'] ?? '');
                      },
                    ),
                    const SizedBox(width: 10),
                    if (status == 'pending')
                      Expanded(
                        child: _ActionButton(
                          label: 'Accept Mission',
                          icon: Icons.check_circle_rounded,
                          gradient: const [Color(0xFF10B981), Color(0xFF059669)],
                          onTap: () => driver.acceptMission(
                              int.tryParse(job['id'].toString()) ?? 0),
                        ),
                      )
                    else if (status == 'accepted')
                      Expanded(
                        child: _ActionButton(
                          label: 'Start Trip',
                          icon: Icons.medical_services_rounded,
                          gradient: const [Color(0xFFF59E0B), Color(0xFFD97706)],
                          onTap: () => driver.updateMissionStatus('en_route'),
                        ),
                      )
                    else if (status == 'en_route')
                      Expanded(
                        child: _ActionButton(
                          label: 'Mark Arrived',
                          icon: Icons.location_on_rounded,
                          gradient: const [Color(0xFF3B82F6), Color(0xFF2563EB)],
                          onTap: () => driver.updateMissionStatus('arrived'),
                        ),
                      )
                    else if (status == 'arrived')
                      Expanded(
                        child: _ActionButton(
                          label: 'Complete',
                          icon: Icons.verified_rounded,
                          gradient: const [Color(0xFF10B981), Color(0xFF059669)],
                          onTap: () => driver.updateMissionStatus('complete'),
                        ),
                      ),
                  ],
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildMissionInfoRow(String label, String value,
      {Color valColor = Colors.white, bool isLongText = false}) {
    return isLongText
        ? Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(label.toUpperCase(),
                style: TextStyle(
                    color: Colors.white.withValues(alpha: 0.45),
                    fontSize: 9,
                    fontWeight: FontWeight.w800,
                    letterSpacing: 0.8)),
            const SizedBox(height: 2),
            Text(
              value,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                  color: valColor, fontSize: 11.5, fontWeight: FontWeight.w600),
            ),
          ])
        : Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
            Text(label.toUpperCase(),
                style: TextStyle(
                    color: Colors.white.withValues(alpha: 0.45),
                    fontSize: 9,
                    fontWeight: FontWeight.w800,
                    letterSpacing: 0.8)),
            Flexible(
                child: Text(value,
                    textAlign: TextAlign.end,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                        color: valColor,
                        fontSize: 12,
                        fontWeight: FontWeight.w900))),
          ]);
  }
}

// ─── Sub-Widgets ──────────────────────────────────────────────────────────────

/// Glass-style KPI card that lifts from the hero section
class _GlassKpiCard extends StatelessWidget {
  final bool isDark;
  final IconData icon;
  final List<Color> iconGradient;
  final String title;
  final String value;
  final String subtitle;

  const _GlassKpiCard({
    required this.isDark,
    required this.icon,
    required this.iconGradient,
    required this.title,
    required this.value,
    required this.subtitle,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 16),
      decoration: BoxDecoration(
        color: isDark ? const Color(0xFF0F1C30) : Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(
          color: isDark
              ? Colors.white.withValues(alpha: 0.07)
              : Colors.grey.shade100,
          width: 1.2,
        ),
        boxShadow: [
          BoxShadow(
            color: isDark
                ? Colors.black.withValues(alpha: 0.30)
                : Colors.blue.withValues(alpha: 0.08),
            blurRadius: 18,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 36,
            height: 36,
            decoration: BoxDecoration(
              gradient: LinearGradient(
                colors: iconGradient,
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
              borderRadius: BorderRadius.circular(10),
              boxShadow: [
                BoxShadow(
                  color: iconGradient.last.withValues(alpha: 0.35),
                  blurRadius: 8,
                  offset: const Offset(0, 3),
                ),
              ],
            ),
            child: Icon(icon, color: Colors.white, size: 18),
          ),
          const SizedBox(height: 12),
          Text(
            value,
            style: TextStyle(
              fontWeight: FontWeight.w900,
              fontSize: 22,
              color: isDark ? Colors.white : const Color(0xFF0F172A),
              letterSpacing: -0.5,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            title,
            style: TextStyle(
              color: isDark ? Colors.grey.shade400 : Colors.grey.shade500,
              fontSize: 10,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 1),
          Text(
            subtitle,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              color: isDark ? Colors.grey.shade600 : Colors.grey.shade400,
              fontSize: 9,
              fontWeight: FontWeight.w500,
            ),
          ),
        ],
      ),
    );
  }
}

/// Glass-style outlined button (Call button)
class _GlassButton extends StatefulWidget {
  final IconData icon;
  final String label;
  final VoidCallback onTap;

  const _GlassButton({
    required this.icon,
    required this.label,
    required this.onTap,
  });

  @override
  State<_GlassButton> createState() => _GlassButtonState();
}

class _GlassButtonState extends State<_GlassButton>
    with SingleTickerProviderStateMixin {
  late AnimationController _ctrl;
  late Animation<double> _scale;

  @override
  void initState() {
    super.initState();
    _ctrl = AnimationController(
        vsync: this, duration: const Duration(milliseconds: 100));
    _scale = Tween<double>(begin: 1.0, end: 0.92)
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
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 14),
          decoration: BoxDecoration(
            color: Colors.white.withValues(alpha: 0.12),
            borderRadius: BorderRadius.circular(14),
            border:
                Border.all(color: Colors.white.withValues(alpha: 0.25), width: 1.2),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(widget.icon, color: Colors.white, size: 17),
              const SizedBox(width: 6),
              Text(
                widget.label,
                style: const TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w800,
                  fontSize: 13,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Gradient action button with press animation
class _ActionButton extends StatefulWidget {
  final String label;
  final IconData icon;
  final List<Color> gradient;
  final VoidCallback onTap;

  const _ActionButton({
    required this.label,
    required this.icon,
    required this.gradient,
    required this.onTap,
  });

  @override
  State<_ActionButton> createState() => _ActionButtonState();
}

class _ActionButtonState extends State<_ActionButton>
    with SingleTickerProviderStateMixin {
  late AnimationController _ctrl;
  late Animation<double> _scale;

  @override
  void initState() {
    super.initState();
    _ctrl = AnimationController(
        vsync: this, duration: const Duration(milliseconds: 100));
    _scale = Tween<double>(begin: 1.0, end: 0.95)
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
      onTapDown: (_) {
        HapticFeedback.lightImpact();
        _ctrl.forward();
      },
      onTapUp: (_) {
        _ctrl.reverse();
        widget.onTap();
      },
      onTapCancel: () => _ctrl.reverse(),
      child: ScaleTransition(
        scale: _scale,
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 14),
          decoration: BoxDecoration(
            gradient: LinearGradient(
              colors: widget.gradient,
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
            ),
            borderRadius: BorderRadius.circular(14),
            boxShadow: [
              BoxShadow(
                color: widget.gradient.last.withValues(alpha: 0.40),
                blurRadius: 14,
                offset: const Offset(0, 5),
              ),
            ],
          ),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(widget.icon, color: Colors.white, size: 17),
              const SizedBox(width: 8),
              Text(
                widget.label,
                style: const TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w900,
                  fontSize: 13.5,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

// ─── Blinking status dot ─────────────────────────────────────────────────────
class _BlinkingDot extends StatefulWidget {
  final Color color;
  const _BlinkingDot({required this.color});

  @override
  State<_BlinkingDot> createState() => _BlinkingDotState();
}

class _BlinkingDotState extends State<_BlinkingDot>
    with SingleTickerProviderStateMixin {
  late AnimationController _ctrl;
  late Animation<double> _anim;

  @override
  void initState() {
    super.initState();
    _ctrl = AnimationController(
        vsync: this, duration: const Duration(milliseconds: 900))
      ..repeat(reverse: true);
    _anim = Tween<double>(begin: 0.3, end: 1.0).animate(_ctrl);
  }

  @override
  void dispose() {
    _ctrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return FadeTransition(
      opacity: _anim,
      child: Container(
        width: 14,
        height: 14,
        decoration: BoxDecoration(
          color: widget.color,
          shape: BoxShape.circle,
          border: Border.all(color: Colors.white, width: 2),
          boxShadow: [
            BoxShadow(
              color: widget.color.withValues(alpha: 0.50),
              blurRadius: 6,
              spreadRadius: 1,
            ),
          ],
        ),
      ),
    );
  }
}
