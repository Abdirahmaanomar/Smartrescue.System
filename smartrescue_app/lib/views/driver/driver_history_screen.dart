import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../providers/driver_provider.dart';

class DriverHistoryScreen extends StatefulWidget {
  const DriverHistoryScreen({super.key});

  @override
  State<DriverHistoryScreen> createState() => _DriverHistoryScreenState();
}

class _DriverHistoryScreenState extends State<DriverHistoryScreen>
    with TickerProviderStateMixin {
  String _filter = 'all';
  int? _expandedIndex;
  late AnimationController _filterAnimController;
  late Animation<double> _filterFadeAnim;



  @override
  void initState() {
    super.initState();
    _filterAnimController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 300),
    );
    _filterFadeAnim = CurvedAnimation(
      parent: _filterAnimController,
      curve: Curves.easeOut,
    );
    _filterAnimController.forward();
  }

  @override
  void dispose() {
    _filterAnimController.dispose();
    super.dispose();
  }

  List<dynamic> _applyFilter(List<dynamic> history) {
    if (_filter == 'all') return history;
    if (_filter == 'completed') {
      return history.where((m) => m['status'] == 'completed').toList();
    }
    if (_filter == 'rejected') {
      return history.where((m) => m['status'] == 'rejected').toList();
    }
    if (_filter == 'active') {
      return history
          .where((m) => ['pending', 'accepted', 'en_route', 'arrived']
              .contains(m['status']))
          .toList();
    }
    return history;
  }

  String _dateGroup(String? rawTime) {
    if (rawTime == null || rawTime.isEmpty) return 'Unknown Date';
    try {
      final dt = DateTime.parse(rawTime);
      final now = DateTime.now();
      final today = DateTime(now.year, now.month, now.day);
      final yesterday = today.subtract(const Duration(days: 1));
      final missionDay = DateTime(dt.year, dt.month, dt.day);
      if (missionDay == today) return 'Today';
      if (missionDay == yesterday) return 'Yesterday';
      final diff = today.difference(missionDay).inDays;
      if (diff <= 7) return 'This Week';
      return '${dt.day}/${dt.month}/${dt.year}';
    } catch (_) {
      return 'Unknown Date';
    }
  }

  String _formatTime(String? rawTime) {
    if (rawTime == null || rawTime.isEmpty) return '';
    try {
      final dt = DateTime.parse(rawTime);
      return '${dt.day}/${dt.month}/${dt.year}  ${dt.hour.toString().padLeft(2, '0')}:${dt.minute.toString().padLeft(2, '0')}';
    } catch (_) {
      return rawTime;
    }
  }

  void _switchFilter(String key) {
    if (_filter == key) return;
    HapticFeedback.selectionClick();
    _filterAnimController.reset();
    setState(() {
      _filter = key;
      _expandedIndex = null;
    });
    _filterAnimController.forward();
  }

  @override
  Widget build(BuildContext context) {
    final driver = Provider.of<DriverProvider>(context);
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final filtered = _applyFilter(driver.history);

    final Map<String, List<MapEntry<int, dynamic>>> grouped = {};
    for (int i = 0; i < filtered.length; i++) {
      final group = _dateGroup(filtered[i]['created_at']);
      grouped.putIfAbsent(group, () => []);
      grouped[group]!.add(MapEntry(i, filtered[i]));
    }

    const groupOrder = ['Today', 'Yesterday', 'This Week'];
    final sortedGroups = [
      ...groupOrder.where((g) => grouped.containsKey(g)),
      ...grouped.keys.where((g) => !groupOrder.contains(g)),
    ];

    return Scaffold(
      backgroundColor:
          isDark ? const Color(0xFF0F172A) : const Color(0xFFF8FAFC),
      body: Column(
        children: [
          // ── Top KPI Stats Cards (Main Filters) ──────────────────────────
          _buildHistoryKpiGrid(driver, isDark),

          // ── List ───────────────────────────────────────────────────────
          Expanded(
            child: filtered.isEmpty && !(driver.hasActiveJob && driver.activeJob != null)
                ? _buildEmpty(isDark)
                : FadeTransition(
                    opacity: _filterFadeAnim,
                    child: RefreshIndicator(
                      onRefresh: () => driver.fetchDriverData(),
                      color: Theme.of(context).colorScheme.primary,
                      backgroundColor:
                          isDark ? const Color(0xFF1E293B) : Colors.white,
                      child: ListView(
                        padding: const EdgeInsets.fromLTRB(0, 4, 0, 32),
                        children: [
                          // ── Active Mission Banner (below kpi) ────────────
                          if (driver.hasActiveJob && driver.activeJob != null)
                            _buildActiveMissionBanner(driver, isDark),

                          if (filtered.isEmpty)
                            _buildEmpty(isDark)
                          else
                            ...List.generate(
                              _countItems(sortedGroups, grouped),
                              (idx) => Padding(
                                padding: const EdgeInsets.symmetric(horizontal: 16),
                                child: _buildItem(idx, sortedGroups, grouped, isDark),
                              ),
                            ),
                        ],
                      ),
                    ),
                  ),
          ),
        ],
      ),
    );
  }


  // ── Active Mission Banner ─────────────────────────────────────────────────
  Widget _buildActiveMissionBanner(DriverProvider driver, bool isDark) {
    final job = driver.activeJob!;
    final patientName = job['patient_name'] ?? job['user_name'] ?? 'Patient';
    final emergencyType = job['emergency_type'] ?? 'Emergency';
    final phone = job['patient_phone'] ?? job['contact'] ?? '';
    final neighborhood = job['neighborhood'] ?? '';
    final medicalId = job['medical_id'] ?? job['description'] ?? '';
    final status = (job['status'] ?? '').toString();

    final stepLabels = {'pending': 'Assigned', 'accepted': 'Accepted', 'en_route': 'En Route', 'arrived': 'Arrived'};
    final stepLabel = stepLabels[status] ?? status.toUpperCase().replaceAll('_', ' ');

    return Container(
      margin: const EdgeInsets.fromLTRB(14, 10, 14, 4),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFF1E1B4B), Color(0xFF312E81), Color(0xFF1E40AF)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(20),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF4F46E5).withValues(alpha: 0.40),
            blurRadius: 18,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 14, 16, 14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Header row
            Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(7),
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.14),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: const Icon(Icons.emergency_rounded, color: Colors.white, size: 15),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
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
                        emergencyType,
                        style: const TextStyle(
                          color: Colors.white,
                          fontWeight: FontWeight.w900,
                          fontSize: 15,
                          letterSpacing: -0.3,
                        ),
                      ),
                    ],
                  ),
                ),
                // Status chip
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.15),
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(color: Colors.white.withValues(alpha: 0.25)),
                  ),
                  child: Text(
                    stepLabel,
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 10,
                      fontWeight: FontWeight.w800,
                      letterSpacing: 0.5,
                    ),
                  ),
                ),
              ],
            ),

            const SizedBox(height: 12),

            // Patient details
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: 0.08),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: Colors.white.withValues(alpha: 0.10)),
              ),
              child: Column(
                children: [
                  _missionRow(Icons.person_rounded, 'PATIENT', patientName),
                  if (neighborhood.isNotEmpty) ...[const SizedBox(height: 6), _missionRow(Icons.location_on_rounded, 'LOCATION', neighborhood)],
                  if (medicalId.isNotEmpty) ...[const SizedBox(height: 6), _missionRow(Icons.medical_information_rounded, 'INFO', medicalId, maxLines: 2)],
                  if (phone.isNotEmpty) ...[const SizedBox(height: 6), _missionRow(Icons.phone_rounded, 'CONTACT', phone)],
                ],
              ),
            ),

            const SizedBox(height: 10),

            // Call button
            if (phone.isNotEmpty)
              SizedBox(
                width: double.infinity,
                child: GestureDetector(
                  onTap: () async {
                    HapticFeedback.mediumImpact();
                    final url = Uri.parse('tel:$phone');
                    if (await canLaunchUrl(url)) await launchUrl(url);
                  },
                  child: Container(
                    padding: const EdgeInsets.symmetric(vertical: 10),
                    decoration: BoxDecoration(
                      color: Colors.white.withValues(alpha: 0.13),
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: Colors.white.withValues(alpha: 0.20)),
                    ),
                    child: const Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(Icons.phone_rounded, color: Colors.white, size: 16),
                        SizedBox(width: 8),
                        Text(
                          'Call Patient',
                          style: TextStyle(
                            color: Colors.white,
                            fontWeight: FontWeight.w800,
                            fontSize: 13,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }

  Widget _missionRow(IconData icon, String label, String value, {int maxLines = 1}) {
    return Row(
      crossAxisAlignment: maxLines > 1 ? CrossAxisAlignment.start : CrossAxisAlignment.center,
      children: [
        Icon(icon, color: Colors.white54, size: 13),
        const SizedBox(width: 6),
        Text(
          '$label  ',
          style: const TextStyle(
            color: Colors.white38,
            fontSize: 10,
            fontWeight: FontWeight.w700,
            letterSpacing: 0.5,
          ),
        ),
        Expanded(
          child: Text(
            value,
            style: const TextStyle(
              color: Colors.white,
              fontSize: 11.5,
              fontWeight: FontWeight.w700,
            ),
            maxLines: maxLines,
            overflow: TextOverflow.ellipsis,
          ),
        ),
      ],
    );
  }

  // ── KPI Stats Grid ────────────────────────────────────────────────────
  Widget _buildHistoryKpiGrid(DriverProvider driver, bool isDark) {
    final completedCount = driver.history.where((m) => m['status'] == 'completed').length;
    final activeCount = driver.history.where((m) => ['pending', 'accepted', 'en_route', 'arrived'].contains(m['status'])).length + (driver.hasActiveJob ? 1 : 0);
    final rejectedCount = driver.history.where((m) => m['status'] == 'rejected' || m['status'] == 'cancelled').length;

    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
      child: Row(
        children: [
          Expanded(
            child: _HistoryKpiCard(
              isDark: isDark,
              isSelected: _filter == 'completed',
              onTap: () => _switchFilter('completed'),
              icon: Icons.check_circle_rounded,
              iconBgColor: const Color(0xFF10B981).withValues(alpha: 0.14),
              iconColor: const Color(0xFF10B981),
              valueColor: const Color(0xFF10B981),
              title: 'Completed',
              value: completedCount.toString(),
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: _HistoryKpiCard(
              isDark: isDark,
              isSelected: _filter == 'active',
              onTap: () => _switchFilter('active'),
              icon: Icons.autorenew_rounded,
              iconBgColor: const Color(0xFFF59E0B).withValues(alpha: 0.14),
              iconColor: const Color(0xFFF59E0B),
              valueColor: const Color(0xFFF59E0B),
              title: 'Active Now',
              value: activeCount.toString(),
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: _HistoryKpiCard(
              isDark: isDark,
              isSelected: _filter == 'rejected',
              onTap: () => _switchFilter('rejected'),
              icon: Icons.block_rounded,
              iconBgColor: const Color(0xFFEF4444).withValues(alpha: 0.14),
              iconColor: const Color(0xFFEF4444),
              valueColor: const Color(0xFFEF4444),
              title: 'Rejected',
              value: rejectedCount.toString(),
            ),
          ),
        ],
      ),
    );
  }


  // ── Item builder helpers ──────────────────────────────────────────────────
  int _countItems(
      List<String> groups, Map<String, List<MapEntry<int, dynamic>>> grouped) {
    int count = 0;
    for (final g in groups) {
      count += 1;
      count += grouped[g]!.length;
    }
    return count;
  }

  Widget _buildItem(int idx, List<String> groups,
      Map<String, List<MapEntry<int, dynamic>>> grouped, bool isDark) {
    int cursor = 0;
    for (final group in groups) {
      if (idx == cursor) return _buildGroupHeader(group, isDark);
      cursor++;
      final items = grouped[group]!;
      if (idx < cursor + items.length) {
        final entry = items[idx - cursor];
        return _buildHistoryCard(entry.key, entry.value, isDark);
      }
      cursor += items.length;
    }
    return const SizedBox.shrink();
  }

  // ── Section header with gradient divider ──────────────────────────────────
  Widget _buildGroupHeader(String label, bool isDark) {
    return Padding(
      padding: const EdgeInsets.only(top: 20, bottom: 8),
      child: Row(
        children: [
          Container(
            padding:
                const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
            decoration: BoxDecoration(
              color: isDark
                  ? Colors.white.withValues(alpha: 0.06)
                  : Colors.grey.shade100,
              borderRadius: BorderRadius.circular(8),
            ),
            child: Text(
              label.toUpperCase(),
              style: TextStyle(
                color: isDark
                    ? Colors.grey.shade400
                    : Colors.grey.shade500,
                fontSize: 10,
                fontWeight: FontWeight.w800,
                letterSpacing: 1.2,
              ),
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Container(
              height: 1,
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  colors: [
                    isDark
                        ? Colors.white.withValues(alpha: 0.08)
                        : Colors.grey.shade200,
                    Colors.transparent,
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  // ── History Card ──────────────────────────────────────────────────────────
  Widget _buildHistoryCard(int index, dynamic mission, bool isDark) {
    final status =
        (mission['status'] ?? 'pending').toString().toLowerCase();
    final isExpanded = _expandedIndex == index;

    Color statusDot;
    Color statusBg;
    Color statusFg;
    String statusLabel;
    IconData statusIcon;

    switch (status) {
      case 'completed':
        statusDot = const Color(0xFF10B981);
        statusBg = const Color(0xFF10B981).withValues(alpha: 0.10);
        statusFg = const Color(0xFF10B981);
        statusLabel = 'COMPLETED';
        statusIcon = Icons.check_circle_rounded;
        break;
      case 'rejected':
        statusDot = const Color(0xFFEF4444);
        statusBg = const Color(0xFFEF4444).withValues(alpha: 0.10);
        statusFg = const Color(0xFFEF4444);
        statusLabel = 'CANCELLED';
        statusIcon = Icons.cancel_rounded;
        break;
      default:
        statusDot = Colors.blue;
        statusBg = Colors.blue.withValues(alpha: 0.10);
        statusFg = Colors.blue;
        statusLabel = status.toUpperCase().replaceAll('_', ' ');
        statusIcon = Icons.timelapse_rounded;
    }

    final bool hasNeighborhood = mission['neighborhood'] != null &&
        mission['neighborhood'].toString().isNotEmpty;
    final bool hasDescription = mission['description'] != null &&
        mission['description'].toString().isNotEmpty;
    final bool hasCoords =
        mission['lat'] != null && mission['lng'] != null;

    // Build inline subtitle: emergency type + neighborhood
    final String emergencyType =
        (mission['emergency_type'] ?? '').toString();
    final String neighborhood =
        hasNeighborhood ? mission['neighborhood'].toString() : '';
    final String subtitle = [
      if (emergencyType.isNotEmpty) emergencyType,
      if (neighborhood.isNotEmpty) neighborhood,
    ].join('  ·  ');

    return _PressableCard(
      onTap: () {
        HapticFeedback.lightImpact();
        setState(
            () => _expandedIndex = isExpanded ? null : index);
      },
      isDark: isDark,
      isExpanded: isExpanded,
      accentColor: statusFg,
      primaryColor: Theme.of(context).colorScheme.primary,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // ── Card header ─────────────────────────────────────────────
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 16),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.center,
              children: [
                // Emergency type icon badge
                Container(
                  width: 46,
                  height: 46,
                  decoration: BoxDecoration(
                    color: statusFg.withValues(alpha: 0.10),
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: Icon(
                    _emergencyIcon(mission['emergency_type']),
                    color: statusFg,
                    size: 22,
                  ),
                ),
                const SizedBox(width: 14),

                // Name + subtitle + timestamp
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        mission['patient_name'] ?? 'Anonymous Patient',
                        style: TextStyle(
                          fontWeight: FontWeight.w800,
                          fontSize: 15,
                          letterSpacing: -0.3,
                          color: isDark
                              ? Colors.white
                              : const Color(0xFF0F172A),
                        ),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                      if (subtitle.isNotEmpty) ...
                        [
                          const SizedBox(height: 3),
                          Text(
                            subtitle,
                            style: TextStyle(
                              color: isDark
                                  ? Colors.grey.shade400
                                  : Colors.grey.shade500,
                              fontSize: 12,
                              fontWeight: FontWeight.w600,
                            ),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ],
                      const SizedBox(height: 5),
                      Row(
                        children: [
                          Icon(
                            Icons.access_time_rounded,
                            size: 11,
                            color: isDark
                                ? Colors.grey.shade600
                                : Colors.grey.shade400,
                          ),
                          const SizedBox(width: 4),
                          Flexible(
                            child: Text(
                              _formatTime(mission['created_at']),
                              style: TextStyle(
                                color: isDark
                                    ? Colors.grey.shade600
                                    : Colors.grey.shade400,
                                fontSize: 11,
                                fontWeight: FontWeight.w500,
                              ),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 8),

                Column(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    _StatusBadge(
                      icon: statusIcon,
                      label: statusLabel,
                      bg: statusBg,
                      fg: statusFg,
                      dot: statusDot,
                    ),
                    const SizedBox(height: 10),
                    AnimatedRotation(
                      turns: isExpanded ? 0.5 : 0,
                      duration: const Duration(milliseconds: 250),
                      curve: Curves.easeInOut,
                      child: Icon(
                        Icons.keyboard_arrow_down_rounded,
                        color: isDark
                            ? Colors.grey.shade600
                            : Colors.grey.shade400,
                        size: 20,
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),

          // ── Expanded detail panel ────────────────────────────────────
          AnimatedCrossFade(
            duration: const Duration(milliseconds: 260),
            crossFadeState: isExpanded
                ? CrossFadeState.showFirst
                : CrossFadeState.showSecond,
            firstChild: Padding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
              child: Container(
                decoration: BoxDecoration(
                  color: isDark
                      ? Colors.black.withValues(alpha: 0.25)
                      : Colors.grey.shade50,
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(
                    color: isDark
                        ? Colors.white.withValues(alpha: 0.05)
                        : Colors.grey.shade100,
                    width: 1,
                  ),
                ),
                child: Column(
                  children: [
                    _DetailTile(
                      icon: Icons.local_fire_department_rounded,
                      iconColor: Colors.orange,
                      label: 'Type',
                      value: emergencyType.isNotEmpty ? emergencyType : '—',
                      isDark: isDark,
                      showDivider: hasNeighborhood ||
                          hasDescription ||
                          hasCoords,
                    ),
                    if (hasNeighborhood)
                      _DetailTile(
                        icon: Icons.location_city_rounded,
                        iconColor: Colors.blue,
                        label: 'Neighborhood',
                        value: mission['neighborhood'],
                        isDark: isDark,
                        showDivider: hasDescription || hasCoords,
                      ),
                    if (hasDescription)
                      _DetailTile(
                        icon: Icons.notes_rounded,
                        iconColor: Colors.grey,
                        label: 'Description',
                        value: mission['description'],
                        isDark: isDark,
                        showDivider: hasCoords,
                      ),
                    if (hasCoords)
                      _DetailTile(
                        icon: Icons.location_on_rounded,
                        iconColor: const Color(0xFFEF4444),
                        label: 'Coordinates',
                        value:
                            '${double.tryParse(mission['lat'].toString())?.toStringAsFixed(5) ?? '—'}, ${double.tryParse(mission['lng'].toString())?.toStringAsFixed(5) ?? '—'}',
                        isDark: isDark,
                        showDivider: false,
                      ),
                  ],
                ),
              ),
            ),
            secondChild: const SizedBox(height: 0),
          ),
        ],
      ),
    );
  }

  IconData _emergencyIcon(dynamic type) {
    final t = (type ?? '').toString().toLowerCase();
    if (t.contains('fire')) {
      return Icons.local_fire_department_rounded;
    }
    if (t.contains('accident')) {
      return Icons.car_crash_rounded;
    }
    if (t.contains('medical') ||
        t.contains('heart') ||
        t.contains('ambulance')) {
      return Icons.favorite_rounded;
    }
    if (t.contains('crime') || t.contains('police')) {
      return Icons.local_police_rounded;
    }
    return Icons.warning_rounded;
  }

  Widget _buildEmpty(bool isDark) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 40),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Container(
              width: 88,
              height: 88,
              decoration: BoxDecoration(
                color: isDark
                    ? Colors.white.withValues(alpha: 0.05)
                    : Colors.grey.shade100,
                shape: BoxShape.circle,
              ),
              child: Icon(
                Icons.folder_open_rounded,
                color: isDark
                    ? Colors.grey.shade600
                    : Colors.grey.shade400,
                size: 40,
              ),
            ),
            const SizedBox(height: 24),
            Text(
              'No Missions Yet',
              style: TextStyle(
                fontWeight: FontWeight.w900,
                fontSize: 20,
                letterSpacing: -0.4,
                color: isDark ? Colors.white : const Color(0xFF0F172A),
              ),
            ),
            const SizedBox(height: 8),
            Text(
              'Completed or assigned rescue missions\nwill appear here.',
              textAlign: TextAlign.center,
              style: TextStyle(
                color: isDark
                    ? Colors.grey.shade500
                    : Colors.grey.shade400,
                fontSize: 14,
                height: 1.6,
                fontWeight: FontWeight.w500,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

// ─── Sub-Widgets ──────────────────────────────────────────────────────────────

/// Animated pill filter chip with press scale
class _FilterChip extends StatefulWidget {
  final String label;
  final bool isSelected;
  final bool isDark;
  final Color primaryColor;
  final VoidCallback onTap;

  const _FilterChip({
    required this.label,
    required this.isSelected,
    required this.isDark,
    required this.primaryColor,
    required this.onTap,
  });

  @override
  State<_FilterChip> createState() => _FilterChipState();
}

class _FilterChipState extends State<_FilterChip>
    with SingleTickerProviderStateMixin {
  late AnimationController _ctrl;
  late Animation<double> _scale;

  @override
  void initState() {
    super.initState();
    _ctrl = AnimationController(
        vsync: this, duration: const Duration(milliseconds: 120));
    _scale = Tween<double>(begin: 1.0, end: 0.93).animate(
        CurvedAnimation(parent: _ctrl, curve: Curves.easeInOut));
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
          duration: const Duration(milliseconds: 220),
          curve: Curves.easeOut,
          padding:
              const EdgeInsets.symmetric(horizontal: 16, vertical: 7),
          decoration: BoxDecoration(
            color: widget.isSelected
                ? widget.primaryColor
                : (widget.isDark
                    ? Colors.white.withValues(alpha: 0.07)
                    : Colors.white),
            borderRadius: BorderRadius.circular(24),
            border: Border.all(
              color: widget.isSelected
                  ? widget.primaryColor
                  : (widget.isDark
                      ? Colors.white.withValues(alpha: 0.10)
                      : Colors.grey.shade200),
              width: 1.2,
            ),
            boxShadow: widget.isSelected
                ? [
                    BoxShadow(
                      color:
                          widget.primaryColor.withValues(alpha: 0.28),
                      blurRadius: 12,
                      offset: const Offset(0, 3),
                    )
                  ]
                : [
                    BoxShadow(
                      color: Colors.black.withValues(
                          alpha: widget.isDark ? 0.12 : 0.04),
                      blurRadius: 6,
                      offset: const Offset(0, 1),
                    )
                  ],
          ),
          child: Text(
            widget.label,
            style: TextStyle(
              color: widget.isSelected
                  ? Colors.white
                  : (widget.isDark
                      ? Colors.grey.shade300
                      : Colors.grey.shade600),
              fontWeight: widget.isSelected
                  ? FontWeight.w800
                  : FontWeight.w600,
              fontSize: 12.5,
              letterSpacing: 0.1,
            ),
          ),
        ),
      ),
    );
  }
}

/// Pressable card with subtle scale + shadow elevation on press
class _PressableCard extends StatefulWidget {
  final Widget child;
  final VoidCallback onTap;
  final bool isDark;
  final bool isExpanded;
  final Color primaryColor;
  final Color accentColor;

  const _PressableCard({
    required this.child,
    required this.onTap,
    required this.isDark,
    required this.isExpanded,
    required this.primaryColor,
    required this.accentColor,
  });

  @override
  State<_PressableCard> createState() => _PressableCardState();
}

class _PressableCardState extends State<_PressableCard>
    with SingleTickerProviderStateMixin {
  late AnimationController _ctrl;
  late Animation<double> _scale;

  @override
  void initState() {
    super.initState();
    _ctrl = AnimationController(
        vsync: this,
        duration: const Duration(milliseconds: 100));
    _scale = Tween<double>(begin: 1.0, end: 0.975).animate(
        CurvedAnimation(parent: _ctrl, curve: Curves.easeIn));
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
          duration: const Duration(milliseconds: 260),
          curve: Curves.easeInOut,
          margin: const EdgeInsets.only(bottom: 12),
          decoration: BoxDecoration(
            color: widget.isDark
                ? const Color(0xFF1E293B)
                : Colors.white,
            borderRadius: BorderRadius.circular(20),
            border: Border.all(
              color: widget.isExpanded
                  ? widget.accentColor.withValues(alpha: 0.40)
                  : (widget.isDark
                      ? Colors.white.withValues(alpha: 0.07)
                      : Colors.grey.shade100),
              width: widget.isExpanded ? 1.5 : 1,
            ),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(
                    alpha: widget.isDark
                        ? (widget.isExpanded ? 0.30 : 0.14)
                        : (widget.isExpanded ? 0.08 : 0.04)),
                blurRadius: widget.isExpanded ? 20 : 8,
                spreadRadius: widget.isExpanded ? 0 : -2,
                offset: const Offset(0, 3),
              ),
            ],
          ),
          child: widget.child,
        ),
      ),
    );
  }
}

/// Compact dot-style status badge
class _StatusBadge extends StatelessWidget {
  final IconData icon;
  final String label;
  final Color bg;
  final Color fg;
  final Color dot;

  const _StatusBadge({
    required this.icon,
    required this.label,
    required this.bg,
    required this.fg,
    required this.dot,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding:
          const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(
            color: fg.withValues(alpha: 0.18), width: 1),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 6,
            height: 6,
            decoration:
                BoxDecoration(color: dot, shape: BoxShape.circle),
          ),
          const SizedBox(width: 5),
          Text(
            label,
            style: TextStyle(
              color: fg,
              fontSize: 10,
              fontWeight: FontWeight.w800,
              letterSpacing: 0.4,
            ),
          ),
        ],
      ),
    );
  }
}

/// Detail row inside the expanded panel with icon container
class _DetailTile extends StatelessWidget {
  final IconData icon;
  final Color iconColor;
  final String label;
  final String value;
  final bool isDark;
  final bool showDivider;

  const _DetailTile({
    required this.icon,
    required this.iconColor,
    required this.label,
    required this.value,
    required this.isDark,
    required this.showDivider,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(
              horizontal: 14, vertical: 12),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 32,
                height: 32,
                decoration: BoxDecoration(
                  color: iconColor.withValues(alpha: 0.10),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Icon(icon, color: iconColor, size: 16),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      label.toUpperCase(),
                      style: TextStyle(
                        color: isDark
                            ? Colors.grey.shade500
                            : Colors.grey.shade400,
                        fontSize: 9.5,
                        fontWeight: FontWeight.w800,
                        letterSpacing: 0.8,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      value,
                      style: TextStyle(
                        fontWeight: FontWeight.w700,
                        fontSize: 13,
                        color: isDark
                            ? Colors.white
                            : const Color(0xFF0F172A),
                        height: 1.4,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
        if (showDivider)
          Divider(
            height: 1,
            indent: 14,
            endIndent: 14,
            color: isDark
                ? Colors.white.withValues(alpha: 0.05)
                : Colors.grey.shade100,
          ),
      ],
    );
  }
}

// ─── History KPI Card ─────────────────────────────────────────────────────────
class _HistoryKpiCard extends StatelessWidget {
  final bool isDark;
  final bool isSelected;
  final VoidCallback onTap;
  final IconData icon;
  final Color iconBgColor;
  final Color iconColor;
  final Color valueColor;
  final String title;
  final String value;

  const _HistoryKpiCard({
    required this.isDark,
    required this.isSelected,
    required this.onTap,
    required this.icon,
    required this.iconBgColor,
    required this.iconColor,
    required this.valueColor,
    required this.title,
    required this.value,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 14),
        decoration: BoxDecoration(
          color: isDark ? const Color(0xFF0F1C30) : Colors.white,
          borderRadius: BorderRadius.circular(20),
          border: Border.all(
            color: isSelected
                ? iconColor
                : (isDark
                    ? Colors.white.withValues(alpha: 0.07)
                    : Colors.grey.shade200),
            width: isSelected ? 2.0 : 1.2,
          ),
          boxShadow: [
            BoxShadow(
              color: isSelected
                  ? iconColor.withValues(alpha: 0.20)
                  : (isDark
                      ? Colors.black.withValues(alpha: 0.30)
                      : Colors.black.withValues(alpha: 0.03)),
              blurRadius: isSelected ? 16 : 10,
              offset: const Offset(0, 4),
            ),
          ],
        ),
        child: Row(
          children: [
            Container(
              width: 34,
              height: 34,
              decoration: BoxDecoration(
                color: iconBgColor,
                borderRadius: BorderRadius.circular(12),
              ),
              child: Icon(icon, color: iconColor, size: 18),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    value,
                    style: TextStyle(
                      fontWeight: FontWeight.w900,
                      fontSize: 19,
                      color: valueColor,
                      letterSpacing: -0.5,
                    ),
                  ),
                  Text(
                    title,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: isDark ? Colors.grey.shade400 : const Color(0xFF475569),
                      fontSize: 10,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
