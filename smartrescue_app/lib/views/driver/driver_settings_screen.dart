import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import '../../providers/auth_provider.dart';
import '../../providers/driver_provider.dart';
import '../../services/api_service.dart';
import '../../services/sound_service.dart';
import '../../utils/helpers.dart';

class DriverSettingsScreen extends StatefulWidget {
  const DriverSettingsScreen({super.key});

  @override
  State<DriverSettingsScreen> createState() => _DriverSettingsScreenState();
}

class _DriverSettingsScreenState extends State<DriverSettingsScreen> {
  bool _soundAlerts = true;
  bool _loadingNotif = false;
  bool _loadingVibration = false;
  bool _loadingDark = false;
  bool _loadingLang = false;

  @override
  void initState() {
    super.initState();
    _loadSoundPref();
  }

  Future<void> _loadSoundPref() async {
    final soundEnabled = await SoundService.isSoundEnabled();
    if (mounted) setState(() => _soundAlerts = soundEnabled);
  }

  // ── Toggle a DB boolean preference ────────────────────────────────────────
  Future<void> _togglePref(String prefKey, bool newVal, void Function() setLoading, void Function() clearLoading) async {
    final auth = Provider.of<AuthProvider>(context, listen: false);
    final user = auth.user;
    if (user == null) return;

    HapticFeedback.mediumImpact();
    setLoading();
    try {
      final res = await ApiService.togglePreference(user.id.toString(), prefKey, newVal ? 1 : 0);
      if (res['status'] == 'success') {
        if (prefKey == 'notifications_enabled') {
          auth.updateUser(user.copyWith(notificationsEnabled: newVal));
        } else if (prefKey == 'vibration_enabled') {
          auth.updateUser(user.copyWith(vibrationEnabled: newVal));
        } else if (prefKey == 'dark_mode') {
          auth.updateUser(user.copyWith(darkMode: newVal));
        }
        if (mounted) AppHelpers.showSnack(context, 'Setting updated!');
      } else {
        if (mounted) AppHelpers.showSnack(context, 'Failed to update setting', isError: true);
      }
    } catch (_) {
      if (mounted) AppHelpers.showSnack(context, 'Network error', isError: true);
    }
    clearLoading();
  }

  Future<void> _toggleSoundAlerts(bool newVal) async {
    HapticFeedback.mediumImpact();
    await SoundService.setSoundEnabled(newVal);
    if (mounted) setState(() => _soundAlerts = newVal);
    if (mounted) AppHelpers.showSnack(context, 'Sound alerts ${newVal ? 'enabled' : 'disabled'}');
  }

  Future<void> _changeLanguage(String langCode, String langLabel) async {
    final auth = Provider.of<AuthProvider>(context, listen: false);
    final user = auth.user;
    if (user == null) return;

    HapticFeedback.selectionClick();
    setState(() => _loadingLang = true);
    try {
      final res = await ApiService.togglePreference(user.id.toString(), 'language', langCode);
      if (res['status'] == 'success') {
        auth.updateUser(user.copyWith(language: langCode));
        if (mounted) AppHelpers.showSnack(context, 'Language updated to $langLabel');
      }
    } catch (_) {}
    if (mounted) setState(() => _loadingLang = false);
  }

  String _langLabel(String code) {
    if (code == 'so') return '🇸🇴 Somali';
    if (code == 'ar') return '🇸🇦 Arabic';
    return '🇬🇧 English';
  }

  String _langCode(String label) {
    if (label == '🇸🇴 Somali') return 'so';
    if (label == '🇸🇦 Arabic') return 'ar';
    return 'en';
  }

  @override
  Widget build(BuildContext context) {
    final auth = Provider.of<AuthProvider>(context);
    final driver = Provider.of<DriverProvider>(context);
    final user = auth.user;
    final isDark = Theme.of(context).brightness == Brightness.dark;

    final isOnline = driver.unitStatus == 'available' ||
        driver.unitStatus == 'busy' ||
        driver.hasActiveJob;

    final currentLangLabel = _langLabel(user?.language ?? 'en');

    return Scaffold(
      backgroundColor: isDark ? const Color(0xFF0F172A) : const Color(0xFFF8FAFC),
      body: SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // ── 1. Unit Status Panel ──────────────────────────────────────────
            _buildUnitPanel(driver, isOnline, isDark),
            const SizedBox(height: 18),

            // ── 2. Visual Theme ───────────────────────────────────────────────
            _buildThemePanel(user, isDark),
            const SizedBox(height: 18),

            // ── 3. Notification Preferences ───────────────────────────────────
            _buildNotificationsPanel(user, isDark),
            const SizedBox(height: 18),

            // ── 4. Language ───────────────────────────────────────────────────
            _buildLanguagePanel(currentLangLabel, isDark),
            const SizedBox(height: 28),

            // ── Sign Out ──────────────────────────────────────────────────────
            ElevatedButton.icon(
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFFEF4444),
                foregroundColor: Colors.white,
                elevation: 2,
                shadowColor: const Color(0xFFEF4444).withValues(alpha: 0.3),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                padding: const EdgeInsets.symmetric(vertical: 15),
              ),
              icon: const Icon(Icons.power_settings_new_rounded, size: 18),
              label: const Text('Sign Out', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 14.5, letterSpacing: 0.2)),
              onPressed: () async {
                HapticFeedback.heavyImpact();
                final driverProv = Provider.of<DriverProvider>(context, listen: false);
                final authProv = Provider.of<AuthProvider>(context, listen: false);
                final navigator = Navigator.of(context);
                // Set driver offline in DB before clearing session
                await driverProv.updateUnitAvailability(false, forceOffline: true);
                driverProv.stop();
                await authProv.logout();
                navigator.pushReplacementNamed('/login');
              },
            ),
          ],
        ),
      ),
    );
  }

  // ─── Unit Status Panel ─────────────────────────────────────────────────────
  Widget _buildUnitPanel(DriverProvider driver, bool isOnline, bool isDark) {
    return _PremiumCard(
      isDark: isDark,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: Colors.blueAccent.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: const Icon(Icons.local_shipping_rounded, color: Colors.blueAccent, size: 18),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      driver.unit != null ? (driver.unit!['unit_name'] ?? 'Assigned Unit') : 'No Assigned Unit',
                      style: TextStyle(
                        fontWeight: FontWeight.w900,
                        fontSize: 15,
                        color: isDark ? Colors.white : const Color(0xFF0F172A),
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      driver.unit != null
                          ? '${driver.unit!['unit_type']} · Plate: ${driver.unit!['plate_number']}'
                          : 'Auto-assignment will happen on next login.',
                      style: TextStyle(
                        color: isDark ? Colors.grey.shade500 : Colors.grey.shade400,
                        fontSize: 11.5,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const Divider(height: 24, thickness: 1),
          _settingRow(
            icon: Icons.radio_button_checked_rounded,
            iconColor: isOnline ? const Color(0xFF10B981) : Colors.grey,
            label: 'Available for Dispatches',
            subtitle: 'Toggle your online readiness',
            isDark: isDark,
            trailing: driver.isTogglingStatus
                ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2))
                : Switch(
                    value: isOnline,
                    activeTrackColor: driver.hasActiveJob ? const Color(0xFFF59E0B) : const Color(0xFF10B981),
                    activeThumbColor: Colors.white,
                    onChanged: (driver.hasActiveJob || driver.isTogglingStatus)
                        ? null
                        : (val) {
                            debugPrint('[DriverSettings] Toggling availability to: $val');
                            driver.updateUnitAvailability(val);
                          },
                  ),
          ),
        ],
      ),
    );
  }

  // ─── Theme Panel ───────────────────────────────────────────────────────────
  Widget _buildThemePanel(dynamic user, bool isDark) {
    return _PremiumCard(
      isDark: isDark,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _sectionHeader(Icons.palette_outlined, Colors.indigoAccent, 'Visual Theme', isDark),
          const SizedBox(height: 14),
          _settingRow(
            icon: isDark ? Icons.dark_mode_rounded : Icons.light_mode_rounded,
            iconColor: Colors.indigoAccent,
            label: 'Dark Mode',
            subtitle: isDark ? 'Dark theme active' : 'Light theme active',
            isDark: isDark,
            trailing: _loadingDark
                ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2))
                : Switch(
                    value: user?.darkMode ?? false,
                    activeTrackColor: Colors.indigoAccent,
                    activeThumbColor: Colors.white,
                    onChanged: (val) => _togglePref(
                      'dark_mode', val,
                      () => setState(() => _loadingDark = true),
                      () => setState(() => _loadingDark = false),
                    ),
                  ),
          ),
        ],
      ),
    );
  }

  // ─── Notifications Panel ───────────────────────────────────────────────────
  Widget _buildNotificationsPanel(dynamic user, bool isDark) {
    return _PremiumCard(
      isDark: isDark,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _sectionHeader(Icons.notifications_active_outlined, Colors.orangeAccent, 'Notification Preferences', isDark),
          const SizedBox(height: 14),
          _settingRow(
            icon: Icons.notifications_none_rounded,
            iconColor: Colors.orangeAccent,
            label: 'System Notifications',
            subtitle: 'Push alerts for new dispatches',
            isDark: isDark,
            trailing: _loadingNotif
                ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2))
                : Switch(
                    value: user?.notificationsEnabled ?? true,
                    activeTrackColor: Colors.orangeAccent,
                    activeThumbColor: Colors.white,
                    onChanged: (val) => _togglePref(
                      'notifications_enabled', val,
                      () => setState(() => _loadingNotif = true),
                      () => setState(() => _loadingNotif = false),
                    ),
                  ),
          ),
          const Divider(height: 24, thickness: 1),
          _settingRow(
            icon: Icons.volume_up_outlined,
            iconColor: const Color(0xFF8B5CF6),
            label: 'Sound Alarm Alerts',
            subtitle: 'Beep on new emergency',
            isDark: isDark,
            trailing: Switch(
              value: _soundAlerts,
              activeTrackColor: const Color(0xFF8B5CF6),
              activeThumbColor: Colors.white,
              onChanged: _toggleSoundAlerts,
            ),
          ),
        ],
      ),
    );
  }

  // ─── Language Panel ────────────────────────────────────────────────────────
  Widget _buildLanguagePanel(String currentLangLabel, bool isDark) {
    return _PremiumCard(
      isDark: isDark,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _sectionHeader(Icons.translate_rounded, const Color(0xFF10B981), 'Language', isDark),
          const SizedBox(height: 14),
          InputDecorator(
            decoration: InputDecoration(
              prefixIcon: _loadingLang
                  ? const Padding(
                      padding: EdgeInsets.all(12),
                      child: SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2)),
                    )
                  : const Icon(Icons.translate_rounded, size: 18),
              contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 2),
              border: OutlineInputBorder(borderRadius: BorderRadius.circular(14)),
              enabledBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(14),
                borderSide: BorderSide(color: isDark ? Colors.white.withValues(alpha: 0.15) : Colors.grey.shade300),
              ),
            ),
            child: DropdownButtonHideUnderline(
              child: DropdownButton<String>(
                value: currentLangLabel,
                isExpanded: true,
                dropdownColor: isDark ? const Color(0xFF1E293B) : Colors.white,
                style: TextStyle(
                  color: isDark ? Colors.white : Colors.black87,
                  fontWeight: FontWeight.w700,
                  fontSize: 13,
                ),
                items: const [
                  DropdownMenuItem(value: '🇬🇧 English', child: Text('🇬🇧 English')),
                  DropdownMenuItem(value: '🇸🇴 Somali', child: Text('🇸🇴 Somali')),
                  DropdownMenuItem(value: '🇸🇦 Arabic', child: Text('🇸🇦 Arabic')),
                ],
                onChanged: _loadingLang
                    ? null
                    : (val) {
                        if (val != null) _changeLanguage(_langCode(val), val);
                      },
              ),
            ),
          ),
        ],
      ),
    );
  }

  // ── Helper Widgets ──────────────────────────────────────────────────────────
  Widget _sectionHeader(IconData icon, Color color, String label, bool isDark) {
    return Row(
      children: [
        Container(
          padding: const EdgeInsets.all(6),
          decoration: BoxDecoration(color: color.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(10)),
          child: Icon(icon, color: color, size: 16),
        ),
        const SizedBox(width: 10),
        Text(
          label,
          style: TextStyle(
            fontWeight: FontWeight.w900,
            fontSize: 14.5,
            color: isDark ? Colors.white : const Color(0xFF0F172A),
          ),
        ),
      ],
    );
  }

  Widget _settingRow({
    required IconData icon,
    required Color iconColor,
    required String label,
    required String subtitle,
    required bool isDark,
    required Widget trailing,
  }) {
    return Row(
      children: [
        Icon(icon, color: iconColor, size: 18),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                label,
                style: TextStyle(
                  fontWeight: FontWeight.w800,
                  fontSize: 13,
                  color: isDark ? Colors.white : const Color(0xFF334155),
                ),
              ),
              const SizedBox(height: 2),
              Text(
                subtitle,
                style: TextStyle(
                  color: isDark ? Colors.grey.shade500 : Colors.grey.shade400,
                  fontSize: 11,
                  fontWeight: FontWeight.w500,
                ),
              ),
            ],
          ),
        ),
        trailing,
      ],
    );
  }
}

/// Modern premium card container
class _PremiumCard extends StatelessWidget {
  final Widget child;
  final bool isDark;

  const _PremiumCard({
    required this.child,
    required this.isDark,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: isDark ? const Color(0xFF1E293B) : Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(
          color: isDark ? Colors.white.withValues(alpha: 0.06) : Colors.grey.shade100,
          width: 1.2,
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: isDark ? 0.18 : 0.04),
            blurRadius: 12,
            offset: const Offset(0, 4),
          )
        ],
      ),
      child: child,
    );
  }
}
