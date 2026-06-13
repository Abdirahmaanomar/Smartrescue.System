import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../providers/sos_provider.dart';
import '../../providers/auth_provider.dart';
import '../../models/rescue_request_model.dart';
import '../../utils/translator.dart';
import '../../utils/responsive.dart';

class UserLiveAlertsScreen extends StatefulWidget {
  final VoidCallback? onGoToSos;
  const UserLiveAlertsScreen({super.key, this.onGoToSos});

  @override
  State<UserLiveAlertsScreen> createState() => _UserLiveAlertsScreenState();
}

class _UserLiveAlertsScreenState extends State<UserLiveAlertsScreen>
    with TickerProviderStateMixin {
  late AnimationController _pulseController;
  late AnimationController _fadeController;
  late Animation<double> _pulseAnimation;
  late Animation<double> _fadeAnimation;

  @override
  void initState() {
    super.initState();
    _pulseController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1500),
    )..repeat(reverse: true);

    _fadeController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 600),
    )..forward();

    _pulseAnimation = Tween<double>(begin: 0.85, end: 1.0).animate(
      CurvedAnimation(parent: _pulseController, curve: Curves.easeInOut),
    );
    _fadeAnimation = Tween<double>(begin: 0.0, end: 1.0).animate(
      CurvedAnimation(parent: _fadeController, curve: Curves.easeOut),
    );
  }

  @override
  void dispose() {
    _pulseController.dispose();
    _fadeController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Scaffold(
      backgroundColor: isDark ? const Color(0xFF0F172A) : const Color(0xFFF1F5F9),
      body: SafeArea(
        child: FadeTransition(
          opacity: _fadeAnimation,
          child: Consumer2<SosProvider, AuthProvider>(
            builder: (context, sos, auth, _) {
              final request = sos.activeRequest;
              final hasActive = sos.hasActiveRequest;

              return Responsive(context).wrapWidescreen(
                CustomScrollView(
                  slivers: [
                    // ── Header ──────────────────────────────────────────────
                    SliverToBoxAdapter(
                      child: _buildHeader(context, isDark, hasActive),
                    ),

                    // ── Live Status Card ─────────────────────────────────────
                    if (hasActive && request != null)
                      SliverToBoxAdapter(
                        child: _buildLiveStatusCard(context, isDark, request),
                      ),

                    // ── Alert Timeline ───────────────────────────────────────
                    if (hasActive && request != null)
                      SliverToBoxAdapter(
                        child: _buildAlertTimeline(context, isDark, request),
                      ),

                    // ── Responder Info ───────────────────────────────────────
                    if (hasActive && request != null && (request.driverAssigned || request.unitName.isNotEmpty))
                      SliverToBoxAdapter(
                        child: _buildResponderCard(context, isDark, request),
                      ),

                    // ── No Active SOS ────────────────────────────────────────
                    if (!hasActive)
                      SliverFillRemaining(
                        child: _buildNoActiveState(context, isDark),
                      ),

                    const SliverToBoxAdapter(child: SizedBox(height: 32)),
                  ],
                ),
              );
            },
          ),
        ),
      ),
    );
  }

  // ── Header ──────────────────────────────────────────────────────────────────
  Widget _buildHeader(BuildContext context, bool isDark, bool hasActive) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 20, 20, 8),
      child: Row(
        children: [
          // Title
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  AppTranslator.t(context, 'Live Alerts'),
                  style: TextStyle(
                    fontSize: 28,
                    fontWeight: FontWeight.w900,
                    color: isDark ? Colors.white : const Color(0xFF0F172A),
                    letterSpacing: -0.5,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  hasActive
                      ? AppTranslator.t(context, 'Your emergency is being tracked')
                      : AppTranslator.t(context, 'No active emergencies'),
                  style: TextStyle(
                    fontSize: 13,
                    color: isDark ? Colors.grey.shade400 : Colors.grey.shade600,
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ],
            ),
          ),
          // Live indicator
          if (hasActive)
            AnimatedBuilder(
              animation: _pulseAnimation,
              builder: (context, child) {
                return Transform.scale(
                  scale: _pulseAnimation.value,
                  child: Container(
                    padding:
                        const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                    decoration: BoxDecoration(
                      color: const Color(0xFFE11D48).withValues(alpha: 0.12),
                      borderRadius: BorderRadius.circular(30),
                      border: Border.all(
                        color: const Color(0xFFE11D48).withValues(alpha: 0.4),
                      ),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const Icon(Icons.circle, color: Color(0xFFE11D48), size: 8),
                        const SizedBox(width: 6),
                        Text(
                          AppTranslator.t(context, 'LIVE'),
                          style: const TextStyle(
                            color: Color(0xFFE11D48),
                            fontSize: 12,
                            fontWeight: FontWeight.w900,
                            letterSpacing: 1.2,
                          ),
                        ),
                      ],
                    ),
                  ),
                );
              },
            ),
        ],
      ),
    );
  }

  // ── Live Status Card ─────────────────────────────────────────────────────────
  Widget _buildLiveStatusCard(BuildContext context, bool isDark, RescueRequestModel req) {
    final statusConfig = _getStatusConfig(context, req.status);

    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
      child: AnimatedBuilder(
        animation: _pulseAnimation,
        builder: (context, child) {
          return Container(
            decoration: BoxDecoration(
              gradient: LinearGradient(
                colors: [
                  statusConfig['color'] as Color,
                  (statusConfig['color'] as Color).withValues(alpha: 0.75),
                ],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
              borderRadius: BorderRadius.circular(24),
              boxShadow: [
                BoxShadow(
                  color: (statusConfig['color'] as Color)
                      .withValues(alpha: 0.35 * _pulseAnimation.value),
                  blurRadius: 24,
                  offset: const Offset(0, 8),
                ),
              ],
            ),
            padding: const EdgeInsets.all(22),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: Colors.white.withValues(alpha: 0.2),
                        shape: BoxShape.circle,
                      ),
                      child: Icon(
                        statusConfig['icon'] as IconData,
                        color: Colors.white,
                        size: 26,
                      ),
                    ),
                    const SizedBox(width: 14),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            statusConfig['label'] as String,
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 18,
                              fontWeight: FontWeight.w900,
                              letterSpacing: 0.2,
                            ),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            statusConfig['subtitle'] as String,
                            style: TextStyle(
                              color: Colors.white.withValues(alpha: 0.85),
                              fontSize: 13,
                              fontWeight: FontWeight.w500,
                            ),
                          ),
                        ],
                      ),
                    ),
                    // Emergency type badge
                    Container(
                      padding: const EdgeInsets.symmetric(
                          horizontal: 12, vertical: 6),
                      decoration: BoxDecoration(
                        color: Colors.white.withValues(alpha: 0.2),
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: Text(
                        AppTranslator.t(context, req.emergencyType).toUpperCase(),
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 10,
                          fontWeight: FontWeight.w800,
                          letterSpacing: 0.8,
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 18),
                Wrap(
                  spacing: 10,
                  runSpacing: 8,
                  children: [
                    _infoChip(Icons.tag_rounded, '#${req.id}'),
                    _infoChip(Icons.schedule_rounded, _formatTime(req.createdAt)),
                    _infoChip(
                      Icons.location_on_rounded,
                      '${req.lat.toStringAsFixed(4)}, ${req.lng.toStringAsFixed(4)}',
                    ),
                  ],
                ),
              ],
            ),
          );
        },
      ),
    );
  }

  Widget _infoChip(IconData icon, String label) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.18),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, color: Colors.white70, size: 12),
          const SizedBox(width: 4),
          Text(
            label,
            style: const TextStyle(
              color: Colors.white,
              fontSize: 11,
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }

  // ── Alert Timeline ───────────────────────────────────────────────────────────
  Widget _buildAlertTimeline(BuildContext context, bool isDark, RescueRequestModel req) {
    final steps = _getTimelineSteps(context, req);

    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 4, 16, 8),
      child: Container(
        decoration: BoxDecoration(
          color: isDark ? const Color(0xFF1E293B) : Colors.white,
          borderRadius: BorderRadius.circular(20),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: isDark ? 0.3 : 0.06),
              blurRadius: 16,
              offset: const Offset(0, 4),
            ),
          ],
        ),
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Section title
            Row(
              children: [
                Icon(
                  Icons.notifications_active_rounded,
                  color: const Color(0xFFE11D48),
                  size: 20,
                ),
                const SizedBox(width: 8),
                Text(
                  AppTranslator.t(context, 'Alert Progress'),
                  style: TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w800,
                    color: isDark ? Colors.white : const Color(0xFF0F172A),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 20),
            // Steps
            ...steps.asMap().entries.map((entry) {
              final i = entry.key;
              final step = entry.value;
              final isLast = i == steps.length - 1;
              return _buildTimelineStep(isDark, step, isLast);
            }),
          ],
        ),
      ),
    );
  }

  Widget _buildTimelineStep(bool isDark, Map<String, dynamic> step, bool isLast) {
    final isActive = step['active'] as bool;
    final isCompleted = step['completed'] as bool;
    final Color color = isCompleted || isActive
        ? (step['color'] as Color)
        : (isDark ? Colors.grey.shade700 : Colors.grey.shade300);

    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // Indicator column
        Column(
          children: [
            AnimatedBuilder(
              animation: _pulseAnimation,
              builder: (context, child) {
                return Container(
                  width: 38,
                  height: 38,
                  decoration: BoxDecoration(
                    color: isActive
                        ? color.withValues(alpha: 0.15 + 0.05 * _pulseAnimation.value)
                        : isCompleted
                            ? color.withValues(alpha: 0.12)
                            : Colors.transparent,
                    shape: BoxShape.circle,
                    border: Border.all(
                      color: isActive || isCompleted
                          ? color
                          : (isDark ? Colors.grey.shade700 : Colors.grey.shade300),
                      width: isActive ? 2.5 : 1.5,
                    ),
                  ),
                  child: Icon(
                    isCompleted && !isActive
                        ? Icons.check_rounded
                        : (step['icon'] as IconData),
                    color: isActive || isCompleted
                        ? color
                        : (isDark ? Colors.grey.shade600 : Colors.grey.shade400),
                    size: 18,
                  ),
                );
              },
            ),
            if (!isLast)
              Container(
                width: 2,
                height: 32,
                margin: const EdgeInsets.symmetric(vertical: 3),
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    colors: isCompleted
                        ? [color, color.withValues(alpha: 0.3)]
                        : [
                            isDark ? Colors.grey.shade700 : Colors.grey.shade300,
                            isDark ? Colors.grey.shade700 : Colors.grey.shade300,
                          ],
                    begin: Alignment.topCenter,
                    end: Alignment.bottomCenter,
                  ),
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
          ],
        ),
        const SizedBox(width: 14),
        // Content
        Expanded(
          child: Padding(
            padding: EdgeInsets.only(bottom: isLast ? 0 : 36, top: 6),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        step['title'] as String,
                        style: TextStyle(
                          fontSize: 14,
                          fontWeight: isActive || isCompleted
                              ? FontWeight.w800
                              : FontWeight.w500,
                          color: isActive || isCompleted
                              ? (isDark ? Colors.white : const Color(0xFF0F172A))
                              : (isDark
                                  ? Colors.grey.shade500
                                  : Colors.grey.shade400),
                        ),
                      ),
                    ),
                    if (isActive)
                      Container(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 8, vertical: 3),
                        decoration: BoxDecoration(
                          color: color.withValues(alpha: 0.12),
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: Text(
                          AppTranslator.t(context, 'NOW'),
                          style: TextStyle(
                            color: color,
                            fontSize: 10,
                            fontWeight: FontWeight.w900,
                            letterSpacing: 0.8,
                          ),
                        ),
                      ),
                  ],
                ),
                const SizedBox(height: 3),
                Text(
                  step['subtitle'] as String,
                  style: TextStyle(
                    fontSize: 12,
                    color: isDark ? Colors.grey.shade500 : Colors.grey.shade500,
                    fontWeight: FontWeight.w400,
                    height: 1.4,
                  ),
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }

  // ── Responder Info ───────────────────────────────────────────────────────────
  Widget _buildResponderCard(BuildContext context, bool isDark, RescueRequestModel req) {
    final typeColor = _getUnitTypeColor(req.unitType);

    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 4, 16, 8),
      child: Container(
        decoration: BoxDecoration(
          color: isDark ? const Color(0xFF1E293B) : Colors.white,
          borderRadius: BorderRadius.circular(20),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: isDark ? 0.3 : 0.06),
              blurRadius: 16,
              offset: const Offset(0, 4),
            ),
          ],
        ),
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(Icons.person_pin_rounded, color: typeColor, size: 20),
                const SizedBox(width: 8),
                Text(
                  AppTranslator.t(context, 'Assigned Responder'),
                  style: TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w800,
                    color: isDark ? Colors.white : const Color(0xFF0F172A),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),
            Row(
              children: [
                // Avatar
                Container(
                  width: 54,
                  height: 54,
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      colors: [typeColor, typeColor.withValues(alpha: 0.7)],
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                    ),
                    shape: BoxShape.circle,
                  ),
                  child: const Icon(Icons.person, color: Colors.white, size: 28),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        req.driverName.isNotEmpty
                            ? req.driverName
                            : AppTranslator.t(context, 'Rescue Operator'),
                        style: TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.w800,
                          color:
                              isDark ? Colors.white : const Color(0xFF0F172A),
                        ),
                      ),
                      const SizedBox(height: 4),
                      Row(
                        children: [
                          _responderBadge(
                            req.unitName.isNotEmpty ? req.unitName : '—',
                            typeColor,
                          ),
                          if (req.plateNumber.isNotEmpty) ...[
                            const SizedBox(width: 6),
                            _responderBadge(req.plateNumber, Colors.grey),
                          ],
                        ],
                      ),
                    ],
                  ),
                ),
                // Call button
                if (req.driverPhone.isNotEmpty)
                  Container(
                    decoration: BoxDecoration(
                      color: const Color(0xFF10B981).withValues(alpha: 0.12),
                      shape: BoxShape.circle,
                    ),
                    child: IconButton(
                      onPressed: () {},
                      icon: const Icon(
                        Icons.phone_rounded,
                        color: Color(0xFF10B981),
                      ),
                    ),
                  ),
              ],
            ),
            if (req.driverPhone.isNotEmpty) ...[
              const SizedBox(height: 14),
              Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: isDark
                      ? const Color(0xFF0F172A)
                      : const Color(0xFFF8FAFC),
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(
                    color: isDark
                        ? const Color(0xFF334155)
                        : Colors.grey.shade200,
                  ),
                ),
                child: Row(
                  children: [
                    Icon(Icons.phone_android_rounded,
                        color: const Color(0xFF10B981), size: 18),
                    const SizedBox(width: 10),
                    Text(
                      req.driverPhone,
                      style: TextStyle(
                        fontSize: 15,
                        fontWeight: FontWeight.w700,
                        color: isDark ? Colors.white : const Color(0xFF0F172A),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _responderBadge(String label, Color color) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: color,
          fontSize: 11,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }

  // ── No Active State ──────────────────────────────────────────────────────────
  Widget _buildNoActiveState(BuildContext context, bool isDark) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(40),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Container(
              padding: const EdgeInsets.all(28),
              decoration: BoxDecoration(
                color: isDark
                    ? const Color(0xFF1E293B)
                    : const Color(0xFFE2E8F0),
                shape: BoxShape.circle,
              ),
              child: Icon(
                Icons.notifications_off_rounded,
                size: 56,
                color: isDark ? Colors.grey.shade600 : Colors.grey.shade400,
              ),
            ),
            const SizedBox(height: 28),
            Text(
              AppTranslator.t(context, 'No Active Alerts'),
              style: TextStyle(
                fontSize: 22,
                fontWeight: FontWeight.w900,
                color: isDark ? Colors.white : const Color(0xFF0F172A),
              ),
            ),
            const SizedBox(height: 10),
            Text(
              AppTranslator.t(context, 'When you send an SOS, live updates will appear here in real time.'),
              textAlign: TextAlign.center,
              style: TextStyle(
                fontSize: 14,
                color: isDark ? Colors.grey.shade500 : Colors.grey.shade500,
                height: 1.6,
              ),
            ),
            const SizedBox(height: 32),
            GestureDetector(
              onTap: widget.onGoToSos,
              child: Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
                decoration: BoxDecoration(
                  color: const Color(0xFFE11D48).withValues(alpha: 0.08),
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(
                    color: const Color(0xFFE11D48).withValues(alpha: 0.2),
                  ),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Icon(
                      Icons.touch_app_rounded,
                      color: Color(0xFFE11D48),
                      size: 18,
                    ),
                    const SizedBox(width: 8),
                    Text(
                      AppTranslator.t(context, 'Go to SOS tab to activate'),
                      style: const TextStyle(
                        color: Color(0xFFE11D48),
                        fontSize: 13,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  // ── Helpers ──────────────────────────────────────────────────────────────────
  Map<String, dynamic> _getStatusConfig(BuildContext context, String status) {
    switch (status) {
      case 'pending':
        return {
          'color': const Color(0xFFF59E0B),
          'icon': Icons.hourglass_top_rounded,
          'label': AppTranslator.t(context, 'Awaiting Response'),
          'subtitle': AppTranslator.t(context, 'Your SOS has been broadcasted. Dispatching team...'),
        };
      case 'accepted':
      case 'dispatched':
        return {
          'color': const Color(0xFF3B82F6),
          'icon': Icons.assignment_ind_rounded,
          'label': AppTranslator.t(context, 'Responder Assigned'),
          'subtitle': AppTranslator.t(context, 'A rescue unit has accepted your emergency request.'),
        };
      case 'en_route':
        return {
          'color': const Color(0xFF8B5CF6),
          'icon': Icons.airport_shuttle_rounded,
          'label': AppTranslator.t(context, 'Rescue En Route'),
          'subtitle': AppTranslator.t(context, 'Rescue team is heading to your location right now.'),
        };
      case 'arrived':
        return {
          'color': const Color(0xFF10B981),
          'icon': Icons.place_rounded,
          'label': AppTranslator.t(context, 'Rescuer Arrived'),
          'subtitle': AppTranslator.t(context, 'The rescue team has arrived at your location.'),
        };
      case 'completed':
        return {
          'color': const Color(0xFF10B981),
          'icon': Icons.check_circle_rounded,
          'label': AppTranslator.t(context, 'Request Completed'),
          'subtitle': AppTranslator.t(context, 'Your emergency request has been successfully resolved.'),
        };
      default:
        return {
          'color': const Color(0xFFE11D48),
          'icon': Icons.sensors_rounded,
          'label': AppTranslator.t(context, 'SOS Active'),
          'subtitle': AppTranslator.t(context, 'Emergency signal is active.'),
        };
    }
  }

  List<Map<String, dynamic>> _getTimelineSteps(BuildContext context, RescueRequestModel req) {
    final step = req.timelineStep;
    return [
      {
        'title': AppTranslator.t(context, 'SOS Broadcasted'),
        'subtitle': AppTranslator.t(context, 'Emergency signal sent to rescue center'),
        'icon': Icons.sensors_rounded,
        'color': const Color(0xFFE11D48),
        'completed': step >= 0,
        'active': step == 0,
      },
      {
        'title': AppTranslator.t(context, 'Request Accepted'),
        'subtitle': AppTranslator.t(context, 'Rescue operator acknowledged your request'),
        'icon': Icons.task_alt_rounded,
        'color': const Color(0xFF3B82F6),
        'completed': step >= 1,
        'active': step == 1,
      },
      {
        'title': AppTranslator.t(context, 'Team Assigned'),
        'subtitle': AppTranslator.t(context, 'A rescue unit has been assigned to you'),
        'icon': Icons.assignment_ind_rounded,
        'color': const Color(0xFF6366F1),
        'completed': step >= 2,
        'active': step == 2,
      },
      {
        'title': AppTranslator.t(context, 'Rescue En Route'),
        'subtitle': AppTranslator.t(context, 'Ambulance is on the way to your location'),
        'icon': Icons.airport_shuttle_rounded,
        'color': const Color(0xFF8B5CF6),
        'completed': step >= 3,
        'active': step == 3,
      },
      {
        'title': AppTranslator.t(context, 'Rescuer Arrived'),
        'subtitle': AppTranslator.t(context, 'Emergency team has reached your location'),
        'icon': Icons.place_rounded,
        'color': const Color(0xFF10B981),
        'completed': step >= 4,
        'active': step == 4,
      },
      {
        'title': AppTranslator.t(context, 'Request Completed'),
        'subtitle': AppTranslator.t(context, 'Emergency successfully resolved'),
        'icon': Icons.verified_rounded,
        'color': const Color(0xFF10B981),
        'completed': step >= 5,
        'active': step == 5,
      },
    ];
  }

  Color _getUnitTypeColor(String type) {
    switch (type.toLowerCase()) {
      case 'medical':
        return const Color(0xFFE11D48);
      case 'fire':
        return const Color(0xFFF59E0B);
      case 'police':
        return const Color(0xFF2563EB);
      default:
        return const Color(0xFF8B5CF6);
    }
  }

  String _formatTime(String createdAt) {
    if (createdAt.isEmpty) return '—';
    try {
      final dt = DateTime.parse(createdAt);
      final h = dt.hour.toString().padLeft(2, '0');
      final m = dt.minute.toString().padLeft(2, '0');
      return '$h:$m';
    } catch (_) {
      return createdAt;
    }
  }
}
