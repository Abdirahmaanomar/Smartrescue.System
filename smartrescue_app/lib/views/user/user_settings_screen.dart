import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../providers/auth_provider.dart';
import '../../services/api_service.dart';
import '../../services/sound_service.dart';
import '../../utils/helpers.dart';
import '../../components/app_drawer.dart';
import 'package:font_awesome_flutter/font_awesome_flutter.dart';
import 'user_notifications_screen.dart';
import '../../utils/translator.dart';
import '../../utils/responsive.dart';

class UserSettingsScreen extends StatefulWidget {
  const UserSettingsScreen({super.key});

  @override
  State<UserSettingsScreen> createState() => _UserSettingsScreenState();
}

class _UserSettingsScreenState extends State<UserSettingsScreen> {
  String _t(String key) {
    return AppTranslator.t(context, key);
  }

  bool _isSavingDarkMode = false;
  bool _isSavingGps = false;
  final Map<String, bool> _updatingStates = {};

  // Notification states
  bool _enableNotifications = true;
  bool _soundAlerts = true;
  bool _vibration = true;
  bool _emergencyAlerts = true;

  // Location states
  bool _shareLiveLocation = true;
  bool _saveLocationHistory = false;

  // Language & Region states
  String _selectedLanguage = '🇬🇧 English';
  String _selectedDateFormat = 'DD/MM/YYYY';
  String _selectedTimeFormat = '12-hour (AM/PM)';

  Future<void> _loadSoundSetting() async {
    final enabled = await SoundService.isSoundEnabled();
    if (mounted) {
      setState(() {
        _soundAlerts = enabled;
      });
    }
  }

  @override
  void initState() {
    super.initState();
    _loadSoundSetting();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final auth = Provider.of<AuthProvider>(context, listen: false);
      final user = auth.user;
      if (user != null) {
        setState(() {
          _enableNotifications = user.notificationsEnabled;
          _vibration = user.vibrationEnabled;
          _shareLiveLocation = user.shareLiveLocation;
          _saveLocationHistory = user.locationHistory;
          if (user.language == 'so') {
            _selectedLanguage = '🇸🇴 Somali';
          } else if (user.language == 'ar') {
            _selectedLanguage = '🇸🇦 Arabic';
          } else {
            _selectedLanguage = '🇬🇧 English';
          }
        });
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final auth = Provider.of<AuthProvider>(context);
    final user = auth.user;
    if (user == null) return const SizedBox.shrink();

    final scheme = Theme.of(context).colorScheme;
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final textColor = isDark ? Colors.white : Colors.black87;
    final subColor = isDark ? Colors.grey.shade400 : Colors.grey;

    return Scaffold(
      drawer: AppDrawer(
        currentIndex: 12,
        onTabSelected: (index) {},
        isSubScreen: true,
      ),
      appBar: AppBar(
        automaticallyImplyLeading: false,
        leading: Builder(
          builder: (context) => IconButton(
            icon: const Icon(Icons.menu_rounded),
            onPressed: () => Scaffold.of(context).openDrawer(),
          ),
        ),
        title: Text(
          'SmartRescue',
          style: TextStyle(
            fontWeight: FontWeight.bold,
            fontSize: 20,
            color: textColor,
          ),
        ),
        centerTitle: true,
        actions: [
          IconButton(
            icon: const Icon(Icons.notifications_none_rounded),
            onPressed: () {
              Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (context) => const UserNotificationsScreen(),
                ),
              );
            },
          ),
          const SizedBox(width: 8),
        ],
      ),
      body: Responsive(context).wrapWidescreen(
        SingleChildScrollView(
          padding: EdgeInsets.symmetric(horizontal: Responsive(context).hPad, vertical: 20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // Header Section
            Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: const Color(0xFFE11D48),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: const Icon(
                    Icons.settings_suggest_rounded,
                    color: Colors.white,
                    size: 26,
                  ),
                ),
                const SizedBox(width: 16),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        _t('App Settings'),
                        style: TextStyle(
                          fontSize: 24,
                          fontWeight: FontWeight.bold,
                          color: textColor,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        _t('Customize your SmartRescue experience and preferences.'),
                        style: TextStyle(
                          fontSize: 12,
                          color: subColor,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 32),

            // Responsive Layout Builder
            LayoutBuilder(
              builder: (context, constraints) {
                final isWide = constraints.maxWidth >= 900;
                if (isWide) {
                  return Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          // Left Column
                          Expanded(
                            child: Column(
                              children: [
                                _buildDisplayCard(user, scheme),
                                const SizedBox(height: 24),
                                _buildLanguageCard(user),
                              ],
                            ),
                          ),
                          const SizedBox(width: 24),
                          // Right Column
                          Expanded(
                            child: Column(
                              children: [
                                _buildHelpCard(),
                                const SizedBox(height: 24),
                                _buildNotificationCard(user),
                              ],
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 24),
                      _buildLocationSettingsCard(user, scheme, isWide: true),
                    ],
                  );
                } else {
                  // Mobile view stacked
                  return Column(
                    children: [
                      _buildDisplayCard(user, scheme),
                      const SizedBox(height: 24),
                      _buildHelpCard(),
                      const SizedBox(height: 24),
                      _buildLanguageCard(user),
                      const SizedBox(height: 24),
                      _buildNotificationCard(user),
                      const SizedBox(height: 24),
                      _buildLocationSettingsCard(user, scheme, isWide: false),
                    ],
                  );
                }
              },
            ),
          ],
        ),
      ),
      ),
    );
  }

  // --- CARD BUILDERS ---

  Widget _buildCard({
    required IconData headerIcon,
    required Color headerIconColor,
    required String title,
    required String subtitle,
    required List<Widget> children,
  }) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final textColor = isDark ? Colors.white : Colors.black87;
    final subColor = isDark ? Colors.grey.shade400 : Colors.grey.shade500;
    final cardBgColor = isDark ? const Color(0xFF0F1937) : Colors.white;
    final borderColor = isDark ? const Color(0xFF1E293B) : Colors.grey.shade100;

    return Container(
      decoration: BoxDecoration(
        color: cardBgColor,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: borderColor, width: 1.5),
        boxShadow: [
          BoxShadow(
            color: isDark
                ? Colors.black.withValues(alpha: 0.3)
                : Colors.grey.shade200.withValues(alpha: 0.3),
            blurRadius: 10,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          // Header Row
          Padding(
            padding: const EdgeInsets.all(20),
            child: Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: headerIconColor.withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Icon(headerIcon, color: headerIconColor, size: 22),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        _t(title),
                        style: TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.bold,
                          color: textColor,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        _t(subtitle),
                        style: TextStyle(
                          fontSize: 12,
                          color: subColor,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          Divider(
              height: 1,
              color:
                  isDark ? const Color(0xFF1E293B) : const Color(0xFFF1F5F9)),
          Padding(
            padding: const EdgeInsets.all(20),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: children,
            ),
          ),
        ],
      ),
    );
  }

  // 1. Display Card
  Widget _buildDisplayCard(dynamic user, ColorScheme scheme) {
    return _buildCard(
      headerIcon: Icons.monitor_rounded,
      headerIconColor: Colors.purple,
      title: 'Display',
      subtitle: 'Theme and visual preferences',
      children: [
        _buildSwitchTile(
          icon: Icons.dark_mode_rounded,
          iconBgColor: Colors.purple,
          title: 'Dark Mode',
          subtitle: 'Optimize for low-light environments',
          value: user.darkMode,
          isLoading: _isSavingDarkMode,
          onChanged: (val) async {
            setState(() => _isSavingDarkMode = true);
            final result = await ApiService.togglePreference(user.id.toString(), 'dark_mode', val);
            if (mounted) {
              setState(() => _isSavingDarkMode = false);
              if (result['status'] == 'success') {
                Provider.of<AuthProvider>(context, listen: false)
                    .updateUser(user.copyWith(darkMode: val));
                AppHelpers.showSnack(
                  context,
                  _t(val ? 'Dark Mode Enabled' : 'Dark Mode Disabled'),
                );
              } else {
                AppHelpers.showSnack(
                  context,
                  _t('Failed to toggle Dark Mode'),
                  isError: true,
                );
              }
            }
          },
        ),
      ],
    );
  }

  // 2. Help & Support Card
  Widget _buildHelpCard() {
    return _buildCard(
      headerIcon: Icons.help_outline_rounded,
      headerIconColor: Colors.blue.shade600,
      title: 'Help & Support',
      subtitle: 'Get help and report issues',
      children: [
        _buildActionTile(
          icon: Icons.chat_bubble_outline_rounded,
          iconBgColor: Colors.blue.shade600,
          title: 'Contact Support',
          subtitle: 'Chat with our support team',
          onTap: () {
            _showContactSupportDialog();
          },
        ),
        const SizedBox(height: 14),
        _buildActionTile(
          icon: Icons.question_mark_rounded,
          iconBgColor: Colors.green.shade600,
          title: 'FAQ',
          subtitle: 'Browse common questions and answers',
          onTap: () {
            _showFaqDialog();
          },
        ),
        const SizedBox(height: 14),
        _buildActionTile(
          icon: Icons.star_outline_rounded,
          iconBgColor: Colors.red.shade600,
          title: 'Report a Problem',
          subtitle: 'Submit a bug or technical issue',
          onTap: () {
            _showReportProblemDialog();
          },
        ),
      ],
    );
  }

  // 4. Notification Settings Card
  Widget _buildNotificationCard(dynamic user) {
    return _buildCard(
      headerIcon: Icons.notifications_active_rounded,
      headerIconColor: Colors.red.shade600,
      title: 'Notification Settings',
      subtitle: 'Manage alerts and sound preferences',
      children: [
        _buildSwitchTile(
          icon: Icons.notifications_none_rounded,
          iconBgColor: Colors.blue.shade600,
          title: 'Enable Notifications',
          subtitle: 'Important updates and status alerts',
          value: _enableNotifications,
          isLoading: _updatingStates['notifications_enabled'] ?? false,
          onChanged: (val) async {
            setState(() => _updatingStates['notifications_enabled'] = true);
            final result =
                await ApiService.togglePreference(user.id.toString(), 'notifications_enabled', val);
            if (mounted) {
              setState(() {
                _updatingStates['notifications_enabled'] = false;
                if (result['status'] == 'success') {
                  _enableNotifications = val;
                  Provider.of<AuthProvider>(context, listen: false)
                      .updateUser(user.copyWith(notificationsEnabled: val));
                  AppHelpers.showSnack(
                    context,
                    _t(val ? 'Notifications Enabled' : 'Notifications Disabled'),
                  );
                } else {
                  AppHelpers.showSnack(
                    context,
                    _t('Failed to toggle notifications'),
                    isError: true,
                  );
                }
              });
            }
          },
        ),
        const SizedBox(height: 14),
        _buildSwitchTile(
          icon: Icons.volume_up_outlined,
          iconBgColor: Colors.green.shade600,
          title: 'Sound Alerts',
          subtitle: 'Audible dispatch notifications',
          value: _soundAlerts,
          onChanged: (val) async {
            setState(() => _soundAlerts = val);
            await SoundService.setSoundEnabled(val);
            if (val) {
              await SoundService.playNotificationBeep();
            }
          },
        ),
        const SizedBox(height: 14),
        _buildSwitchTile(
          icon: Icons.vibration_rounded,
          iconBgColor: Colors.orange.shade700,
          title: 'Vibration',
          subtitle: 'Haptic feedback on alerts',
          value: _vibration,
          isLoading: _updatingStates['vibration_enabled'] ?? false,
          onChanged: (val) async {
            setState(() => _updatingStates['vibration_enabled'] = true);
            final result =
                await ApiService.togglePreference(user.id.toString(), 'vibration_enabled', val);
            if (mounted) {
              setState(() {
                _updatingStates['vibration_enabled'] = false;
                if (result['status'] == 'success') {
                  _vibration = val;
                  Provider.of<AuthProvider>(context, listen: false)
                      .updateUser(user.copyWith(vibrationEnabled: val));
                  AppHelpers.showSnack(
                    context,
                    _t(val ? 'Vibration Enabled' : 'Vibration Disabled'),
                  );
                } else {
                  AppHelpers.showSnack(
                    context,
                    _t('Failed to toggle vibration'),
                    isError: true,
                  );
                }
              });
            }
          },
        ),
        const SizedBox(height: 14),
        _buildSwitchTile(
          icon: Icons.campaign_rounded,
          iconBgColor: Colors.red.shade600,
          title: 'Emergency Alerts',
          tagText: 'HIGH PRIORITY',
          subtitle: 'Critical fire and rescue alerts - always on',
          value: _emergencyAlerts,
          onChanged: (val) => setState(() => _emergencyAlerts = val),
        ),
      ],
    );
  }

  // 5. Location Settings Card
  Widget _buildLocationSettingsCard(dynamic user, ColorScheme scheme,
      {required bool isWide}) {
    final childrenWidgets = [
      _buildSwitchTile(
        icon: Icons.gps_fixed_rounded,
        iconBgColor: Colors.green.shade600,
        title: 'Enable GPS Location',
        subtitle: 'Core feature for emergency dispatch',
        value: user.gpsEnabled,
        isLoading: _isSavingGps,
        onChanged: (val) async {
          setState(() => _isSavingGps = true);
          final result = await ApiService.togglePreference(user.id.toString(), 'gps_enabled', val);
          if (mounted) {
            setState(() => _isSavingGps = false);
            if (result['status'] == 'success') {
              Provider.of<AuthProvider>(context, listen: false)
                  .updateUser(user.copyWith(gpsEnabled: val));
              AppHelpers.showSnack(
                context,
                _t(val ? 'GPS Tracking Enabled' : 'GPS Tracking Disabled'),
              );
            } else {
              AppHelpers.showSnack(
                context,
                _t('Failed to toggle GPS Tracking'),
                isError: true,
              );
            }
          }
        },
      ),
      const SizedBox(height: 14),
      _buildSwitchTile(
        icon: Icons.navigation_rounded,
        iconBgColor: Colors.red.shade600,
        title: 'Share Live Location During SOS',
        subtitle: 'Stream real-time assets to rescuers',
        value: _shareLiveLocation,
        isLoading: _updatingStates['share_live_location'] ?? false,
        onChanged: (val) async {
          setState(() => _updatingStates['share_live_location'] = true);
          final result =
              await ApiService.togglePreference(user.id.toString(), 'share_live_location', val);
          if (mounted) {
            setState(() {
              _updatingStates['share_live_location'] = false;
              if (result['status'] == 'success') {
                _shareLiveLocation = val;
                Provider.of<AuthProvider>(context, listen: false)
                    .updateUser(user.copyWith(shareLiveLocation: val));
                AppHelpers.showSnack(
                  context,
                  _t(val ? 'Live Location Sharing Enabled' : 'Live Location Sharing Disabled'),
                );
              } else {
                AppHelpers.showSnack(
                  context,
                  _t('Failed to toggle live location sharing'),
                  isError: true,
                );
              }
            });
          }
        },
      ),
    ];

    final childrenWidgetsRight = [
      _buildManageTile(
        icon: Icons.person_pin_circle_rounded,
        iconBgColor: Colors.blue.shade600,
        title: 'Location Permission Control',
        subtitle: 'Manage browser GPS permissions',
        btnText: 'Manage',
        onTap: () {},
      ),
      const SizedBox(height: 14),
      _buildSwitchTile(
        icon: Icons.history_rounded,
        iconBgColor: Colors.orange.shade700,
        title: 'Save Location History',
        subtitle: 'Store location data for SOS reports',
        value: _saveLocationHistory,
        isLoading: _updatingStates['location_history'] ?? false,
        onChanged: (val) async {
          setState(() => _updatingStates['location_history'] = true);
          final result =
              await ApiService.togglePreference(user.id.toString(), 'location_history', val);
          if (mounted) {
            setState(() {
              _updatingStates['location_history'] = false;
              if (result['status'] == 'success') {
                _saveLocationHistory = val;
                Provider.of<AuthProvider>(context, listen: false)
                    .updateUser(user.copyWith(locationHistory: val));
                AppHelpers.showSnack(
                  context,
                  _t(val ? 'Location History Saved' : 'Location History Disabled'),
                );
              } else {
                AppHelpers.showSnack(
                  context,
                  _t('Failed to toggle location history'),
                  isError: true,
                );
              }
            });
          }
        },
      ),
    ];

    return _buildCard(
      headerIcon: Icons.location_on_rounded,
      headerIconColor: Colors.teal.shade600,
      title: 'Location Settings',
      subtitle: 'GPS, live tracking, and location permissions',
      children: isWide
          ? [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: Column(
                      children: childrenWidgets,
                    ),
                  ),
                  const SizedBox(width: 24),
                  Expanded(
                    child: Column(
                      children: childrenWidgetsRight,
                    ),
                  ),
                ],
              ),
            ]
          : [
              ...childrenWidgets,
              const SizedBox(height: 14),
              ...childrenWidgetsRight,
            ],
    );
  }

  // --- SUB COMPONENT BUILDERS ---

  Widget _buildSwitchTile({
    required IconData icon,
    required Color iconBgColor,
    required String title,
    required String subtitle,
    required bool value,
    bool isLoading = false,
    String? tagText,
    ValueChanged<bool>? onChanged,
  }) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final textColor = isDark ? Colors.white : Colors.black87;
    final subColor = isDark ? Colors.grey.shade400 : Colors.grey.shade500;

    return Row(
      children: [
        Container(
          width: 38,
          height: 38,
          decoration: BoxDecoration(
            color: iconBgColor.withValues(alpha: 0.1),
            shape: BoxShape.circle,
          ),
          child: Icon(icon, color: iconBgColor, size: 18),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Flexible(
                    child: Text(
                      _t(title),
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.bold,
                        color: textColor,
                      ),
                    ),
                  ),
                  if (tagText != null) ...[
                    const SizedBox(width: 8),
                    Container(
                      padding: const EdgeInsets.symmetric(
                          horizontal: 6, vertical: 2),
                      decoration: BoxDecoration(
                        color: Colors.red.shade50,
                        borderRadius: BorderRadius.circular(4),
                        border:
                            Border.all(color: Colors.red.shade200, width: 0.5),
                      ),
                      child: Text(
                        tagText,
                        style: TextStyle(
                          color: Colors.red.shade700,
                          fontSize: 8,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ),
                  ],
                ],
              ),
              const SizedBox(height: 2),
              Text(
                _t(subtitle),
                style: TextStyle(
                  fontSize: 11,
                  color: subColor,
                  fontWeight: FontWeight.w500,
                ),
              ),
            ],
          ),
        ),
        const SizedBox(width: 12),
        if (isLoading)
          const SizedBox(
            height: 20,
            width: 20,
            child: CircularProgressIndicator(strokeWidth: 2),
          )
        else
          Switch(
            value: value,
            activeThumbColor: const Color(0xFFE11D48),
            activeTrackColor: const Color(0xFFE11D48).withValues(alpha: 0.5),
            onChanged: onChanged,
          ),
      ],
    );
  }

  Widget _buildActionTile({
    required IconData icon,
    required Color iconBgColor,
    required String title,
    required String subtitle,
    required VoidCallback onTap,
  }) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final textColor = isDark ? Colors.white : Colors.black87;
    final subColor = isDark ? Colors.grey.shade400 : Colors.grey.shade500;

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 4),
        child: Row(
          children: [
            Container(
              width: 38,
              height: 38,
              decoration: BoxDecoration(
                color: iconBgColor.withValues(alpha: 0.1),
                shape: BoxShape.circle,
              ),
              child: Icon(icon, color: iconBgColor, size: 18),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    _t(title),
                    style: TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.bold,
                      color: textColor,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    _t(subtitle),
                    style: TextStyle(
                      fontSize: 11,
                      color: subColor,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 12),
            Icon(Icons.chevron_right_rounded,
                color: Colors.grey.shade400, size: 20),
          ],
        ),
      ),
    );
  }

  Widget _buildManageTile({
    required IconData icon,
    required Color iconBgColor,
    required String title,
    required String subtitle,
    required String btnText,
    required VoidCallback onTap,
  }) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final textColor = isDark ? Colors.white : Colors.black87;
    final subColor = isDark ? Colors.grey.shade400 : Colors.grey.shade500;

    return Row(
      children: [
        Container(
          width: 38,
          height: 38,
          decoration: BoxDecoration(
            color: iconBgColor.withValues(alpha: 0.1),
            shape: BoxShape.circle,
          ),
          child: Icon(icon, color: iconBgColor, size: 18),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                _t(title),
                style: TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.bold,
                  color: textColor,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                _t(subtitle),
                style: TextStyle(
                  fontSize: 11,
                  color: subColor,
                  fontWeight: FontWeight.w500,
                ),
              ),
            ],
          ),
        ),
        const SizedBox(width: 12),
        ElevatedButton(
          onPressed: onTap,
          style: ElevatedButton.styleFrom(
            backgroundColor: isDark ? const Color(0xFF1A2540) : Colors.white,
            foregroundColor: textColor,
            elevation: 0,
            side: BorderSide(
              color: isDark ? const Color(0xFF1E293B) : Colors.grey.shade200,
              width: 1.5,
            ),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(8),
            ),
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
            minimumSize: Size.zero,
            tapTargetSize: MaterialTapTargetSize.shrinkWrap,
          ),
          child: Text(
            _t(btnText),
            style: const TextStyle(
              fontSize: 11,
              fontWeight: FontWeight.w800,
            ),
          ),
        ),
      ],
    );
  }

  void _showContactSupportDialog() {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final dialogBg = isDark ? const Color(0xFF0F1937) : Colors.white;
    final titleColor = isDark ? Colors.white : const Color(0xFF1E293B);
    final subtitleColor =
        isDark ? Colors.grey.shade400 : const Color(0xFF64748B);

    showDialog(
      context: context,
      builder: (context) {
        return Dialog(
          backgroundColor: dialogBg,
          shape:
              RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
          child: Container(
            constraints: const BoxConstraints(maxWidth: 360),
            padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 32),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                // Top Circular Icon Container
                Container(
                  padding: const EdgeInsets.all(18),
                  decoration: BoxDecoration(
                    color: const Color(0xFFE0E7FF),
                    shape: BoxShape.circle,
                    border: Border.all(color: Colors.white, width: 4),
                  ),
                  child: const Icon(
                    Icons.support_agent_rounded,
                    size: 40,
                    color: Color(0xFF2563EB),
                  ),
                ),
                const SizedBox(height: 20),

                // Title
                Text(
                  _t('Contact Support'),
                  style: TextStyle(
                    fontSize: 22,
                    fontWeight: FontWeight.w800,
                    color: titleColor,
                  ),
                ),
                const SizedBox(height: 10),

                // Subtitle
                Text(
                  _t('Our response team is available 24/7. Choose an option below to reach us.'),
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontSize: 13,
                    color: subtitleColor,
                    height: 1.5,
                  ),
                ),
                const SizedBox(height: 28),

                // Call Hotline Button
                SizedBox(
                  width: double.infinity,
                  height: 48,
                  child: ElevatedButton.icon(
                    onPressed: () {
                      AppHelpers.makePhoneCall(context, '+252610000000');
                    },
                    icon: const Icon(Icons.phone_rounded,
                        color: Colors.white, size: 18),
                    label: Text(
                      _t('Call Hotline'),
                      style: const TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.bold,
                        color: Colors.white,
                      ),
                    ),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: const Color(0xFF2563EB),
                      elevation: 0,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(14),
                      ),
                    ),
                  ),
                ),
                const SizedBox(height: 12),

                // WhatsApp Us Button
                SizedBox(
                  width: double.infinity,
                  height: 48,
                  child: ElevatedButton.icon(
                    onPressed: () {
                      AppHelpers.openWhatsApp(context, '+252610000000');
                    },
                    icon: const FaIcon(FontAwesomeIcons.whatsapp,
                        color: Colors.white, size: 18),
                    label: Text(
                      _t('WhatsApp Us'),
                      style: const TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.bold,
                        color: Colors.white,
                      ),
                    ),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: const Color(0xFF0F9F5F),
                      elevation: 0,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(14),
                      ),
                    ),
                  ),
                ),
                const SizedBox(height: 20),

                // Email Support
                InkWell(
                  onTap: () {
                    AppHelpers.openEmail(context, 'support@smartrescue.com');
                  },
                  borderRadius: BorderRadius.circular(8),
                  child: Padding(
                    padding:
                        const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(Icons.email_rounded, size: 16, color: titleColor),
                        const SizedBox(width: 8),
                        Text(
                          _t('Email Support'),
                          style: TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.bold,
                            color: titleColor,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 20),

                // Close Button
                TextButton(
                  onPressed: () => Navigator.pop(context),
                  style: TextButton.styleFrom(
                    padding: const EdgeInsets.symmetric(
                        horizontal: 20, vertical: 10),
                  ),
                  child: Text(
                    _t('Close'),
                    style: TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.bold,
                      color: titleColor,
                    ),
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  void _showFaqDialog() {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final dialogBg = isDark ? const Color(0xFF0F1937) : Colors.white;
    final titleColor = isDark ? Colors.white : const Color(0xFF1E293B);
    final questionColor = isDark ? Colors.white : const Color(0xFF0F172A);
    final answerColor = isDark ? Colors.grey.shade400 : const Color(0xFF475569);

    showDialog(
      context: context,
      builder: (context) {
        return Dialog(
          backgroundColor: dialogBg,
          shape:
              RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
          child: Container(
            constraints: const BoxConstraints(maxWidth: 420),
            padding: const EdgeInsets.all(24),
            child: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Title Row
                  Row(
                    children: [
                      Container(
                        padding: const EdgeInsets.all(8),
                        decoration: const BoxDecoration(
                          color: Color(0xFF10B981),
                          shape: BoxShape.circle,
                        ),
                        child: const Icon(
                          Icons.question_mark_rounded,
                          size: 20,
                          color: Colors.white,
                        ),
                      ),
                      const SizedBox(width: 12),
                      Text(
                        'FAQ',
                        style: TextStyle(
                          fontSize: 22,
                          fontWeight: FontWeight.w800,
                          color: titleColor,
                        ),
                      ),
                      const Spacer(),
                      IconButton(
                        onPressed: () => Navigator.pop(context),
                        icon: Icon(Icons.close_rounded,
                            color: isDark
                                ? Colors.grey.shade400
                                : Colors.grey.shade600),
                        padding: EdgeInsets.zero,
                        constraints: const BoxConstraints(),
                      ),
                    ],
                  ),
                  const SizedBox(height: 24),

                  // FAQ Content
                  _buildFaqItem(
                    'How do I trigger an SOS?',
                    'Just press and hold the giant SOS button on your dashboard for 3 seconds, or tap it once to see options.',
                    questionColor,
                    answerColor,
                  ),
                  const SizedBox(height: 20),

                  _buildFaqItem(
                    'Is my location shared instantly?',
                    'Yes, the moment you activate SOS, your GPS coordinates are sent directly to the nearest dispatch unit.',
                    questionColor,
                    answerColor,
                  ),
                  const SizedBox(height: 20),

                  _buildFaqItem(
                    'Can I cancel a false alarm?',
                    'Yes. If you trigger it by accident, press the "Cancel SOS" button on the tracking screen within 15 seconds.',
                    questionColor,
                    answerColor,
                  ),
                  const SizedBox(height: 20),

                  _buildFaqItem(
                    'How does Dark Mode help?',
                    'Dark mode reduces screen glare in low-light environments, keeping you covert and saving your device battery during an emergency.',
                    questionColor,
                    answerColor,
                  ),
                ],
              ),
            ),
          ),
        );
      },
    );
  }

  Widget _buildFaqItem(
      String question, String answer, Color qColor, Color aColor) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          _t(question),
          style: TextStyle(
            fontSize: 15,
            fontWeight: FontWeight.w800,
            color: qColor,
            height: 1.3,
          ),
        ),
        const SizedBox(height: 6),
        Text(
          _t(answer),
          style: TextStyle(
            fontSize: 13,
            color: aColor,
            height: 1.4,
          ),
        ),
      ],
    );
  }

  void _showReportProblemDialog() {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final dialogBg = isDark ? const Color(0xFF0F1937) : Colors.white;
    final titleColor = isDark ? Colors.white : const Color(0xFF1E293B);
    final subtitleColor =
        isDark ? Colors.grey.shade400 : const Color(0xFF64748B);
    final labelColor = isDark ? Colors.grey.shade400 : const Color(0xFF64748B);
    final dropdownBg = isDark ? const Color(0xFF1A2540) : Colors.white;
    final textFieldBg =
        isDark ? const Color(0xFF1E293B) : const Color(0xFFF8FAFC);

    final descriptionController = TextEditingController();
    String selectedCategory = 'Select category...';
    final categories = [
      'Select category...',
      'GPS/Location not working',
      'App crashed/froze',
      'Display/Layout issue',
      'Other technical issue',
    ];

    showDialog(
      context: context,
      builder: (context) {
        return StatefulBuilder(
          builder: (context, setDialogState) {
            return Dialog(
              backgroundColor: dialogBg,
              shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(20)),
              child: Container(
                constraints: const BoxConstraints(maxWidth: 380),
                padding: const EdgeInsets.all(24),
                child: SingleChildScrollView(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      // Title Row
                      Row(
                        children: [
                          const Icon(
                            Icons.bug_report_rounded,
                            size: 28,
                            color: Color(0xFFF59E0B), // Orange color
                          ),
                          const SizedBox(width: 10),
                          Text(
                            _t('Report Problem'),
                            style: TextStyle(
                              fontSize: 20,
                              fontWeight: FontWeight.w800,
                              color: titleColor,
                            ),
                          ),
                          const Spacer(),
                          IconButton(
                            onPressed: () => Navigator.pop(context),
                            icon: Icon(Icons.close_rounded,
                                color: isDark
                                    ? Colors.grey.shade400
                                    : Colors.grey.shade600),
                            padding: EdgeInsets.zero,
                            constraints: const BoxConstraints(),
                          ),
                        ],
                      ),
                      const SizedBox(height: 16),

                      // Subtitle
                      Text(
                        _t('Experiencing a technical issue or bug? Let us know so we can fix it.'),
                        style: TextStyle(
                          fontSize: 13,
                          color: subtitleColor,
                          height: 1.5,
                        ),
                      ),
                      const SizedBox(height: 24),

                      // Issue Type Label
                      Text(
                        _t('ISSUE TYPE'),
                        style: TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.bold,
                          color: labelColor,
                          letterSpacing: 0.8,
                        ),
                      ),
                      const SizedBox(height: 8),

                      // Dropdown Container
                      Container(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 14, vertical: 4),
                        decoration: BoxDecoration(
                          color: dropdownBg,
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(
                              color: const Color(0xFFFCA5A5),
                              width:
                                  1.5), // Subtle red/orange border like mockup
                        ),
                        child: DropdownButtonHideUnderline(
                          child: DropdownButton<String>(
                            value: selectedCategory,
                            isExpanded: true,
                            dropdownColor: dropdownBg,
                            icon: Icon(Icons.keyboard_arrow_down_rounded,
                                color: Colors.grey.shade600, size: 20),
                            onChanged: (val) {
                              if (val != null) {
                                setDialogState(() {
                                  selectedCategory = val;
                                });
                              }
                            },
                            items: categories
                                .map<DropdownMenuItem<String>>((String val) {
                              return DropdownMenuItem<String>(
                                value: val,
                                child: Text(
                                  _t(val),
                                  style: TextStyle(
                                    fontSize: 13,
                                    fontWeight: FontWeight.w600,
                                    color: val == 'Select category...'
                                        ? Colors.grey.shade500
                                        : (isDark
                                            ? Colors.white
                                            : Colors.black87),
                                  ),
                                ),
                              );
                            }).toList(),
                          ),
                        ),
                      ),
                      const SizedBox(height: 20),

                      // Description Label
                      Text(
                        _t('DESCRIPTION'),
                        style: TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.bold,
                          color: labelColor,
                          letterSpacing: 0.8,
                        ),
                      ),
                      const SizedBox(height: 8),

                      // Description TextField
                      TextField(
                        controller: descriptionController,
                        maxLines: 4,
                        style: TextStyle(
                          fontSize: 13,
                          color: isDark ? Colors.white : Colors.black87,
                        ),
                        decoration: InputDecoration(
                          hintText:
                              _t('Please describe the problem in detail...'),
                          hintStyle: TextStyle(
                              fontSize: 13, color: Colors.grey.shade500),
                          filled: true,
                          fillColor: textFieldBg,
                          contentPadding: const EdgeInsets.all(14),
                          border: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(12),
                            borderSide: BorderSide.none,
                          ),
                        ),
                      ),
                      const SizedBox(height: 28),

                      // Bottom Row Buttons
                      Row(
                        children: [
                          // Submit Button
                          Expanded(
                            child: SizedBox(
                              height: 48,
                              child: ElevatedButton(
                                onPressed: () {
                                  if (selectedCategory ==
                                      'Select category...') {
                                    AppHelpers.showSnack(context,
                                        _t('Please select a category first'),
                                        isError: true);
                                    return;
                                  }
                                  if (descriptionController.text
                                      .trim()
                                      .isEmpty) {
                                    AppHelpers.showSnack(context,
                                        _t('Please enter a description of the problem'),
                                        isError: true);
                                    return;
                                  }
                                  Navigator.pop(context);
                                  AppHelpers.showSnack(context,
                                      _t('Report submitted successfully! Thank you.'));
                                },
                                style: ElevatedButton.styleFrom(
                                  backgroundColor: const Color(0xFF2563EB),
                                  shape: RoundedRectangleBorder(
                                      borderRadius: BorderRadius.circular(16)),
                                  elevation: 0,
                                ),
                                child: Text(
                                  _t('Submit Report'),
                                  style: const TextStyle(
                                    fontSize: 14,
                                    fontWeight: FontWeight.bold,
                                    color: Colors.white,
                                  ),
                                ),
                              ),
                            ),
                          ),
                          const SizedBox(width: 12),
                          // Cancel Button
                          TextButton(
                            onPressed: () => Navigator.pop(context),
                            style: TextButton.styleFrom(
                              padding: const EdgeInsets.symmetric(
                                  horizontal: 20, vertical: 14),
                            ),
                            child: Text(
                              _t('Cancel'),
                              style: TextStyle(
                                fontSize: 14,
                                fontWeight: FontWeight.bold,
                                color: titleColor,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
            );
          },
        );
      },
    );
  }

  Widget _buildDropdownLabel(String label, IconData icon) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final labelColor = isDark ? Colors.grey.shade400 : const Color(0xFF64748B);
    return Row(
      children: [
        Icon(icon, size: 14, color: Colors.orange.shade700),
        const SizedBox(width: 6),
        Text(
          _t(label),
          style: TextStyle(
            fontSize: 10,
            fontWeight: FontWeight.bold,
            color: labelColor,
            letterSpacing: 0.8,
          ),
        ),
      ],
    );
  }

  Widget _buildDropdownButton({
    required IconData icon,
    required String value,
    required List<String> items,
    required void Function(String?) onChanged,
  }) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final dropdownBg =
        isDark ? const Color(0xFF1E293B) : const Color(0xFFF8FAFC);
    final borderColor = isDark ? const Color(0xFF334155) : Colors.grey.shade200;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 4),
      decoration: BoxDecoration(
        color: dropdownBg,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: borderColor, width: 1.5),
      ),
      child: DropdownButtonHideUnderline(
        child: DropdownButton<String>(
          value: value,
          isExpanded: true,
          dropdownColor: isDark ? const Color(0xFF0F1937) : Colors.white,
          icon: Icon(Icons.keyboard_arrow_down_rounded,
              color: Colors.grey.shade500, size: 20),
          onChanged: onChanged,
          items: items.map<DropdownMenuItem<String>>((String val) {
            return DropdownMenuItem<String>(
              value: val,
              child: Row(
                children: [
                  Icon(icon, size: 16, color: Colors.orange.shade700),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      _t(val),
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                        color: isDark ? Colors.white : Colors.black87,
                      ),
                    ),
                  ),
                ],
              ),
            );
          }).toList(),
        ),
      ),
    );
  }

  Widget _buildLanguageCard(dynamic user) {
    return _buildCard(
      headerIcon: Icons.language_rounded,
      headerIconColor: Colors.orange.shade700,
      title: 'Language & Region',
      subtitle: 'Locale, date, and time preferences',
      children: [
        _buildDropdownLabel('LANGUAGE', Icons.translate_rounded),
        const SizedBox(height: 8),
        _buildDropdownButton(
          icon: Icons.language_rounded,
          value: _selectedLanguage,
          items: ['🇬🇧 English', '🇸🇴 Somali', '🇸🇦 Arabic'],
          onChanged: (val) async {
            if (val != null) {
              setState(() => _selectedLanguage = val);
              String langCode = 'en';
              if (val.contains('Somali')) langCode = 'so';
              if (val.contains('Arabic')) langCode = 'ar';
              final result =
                  await ApiService.togglePreference(user.id.toString(), 'language', langCode);
              if (mounted) {
                if (result['status'] == 'success') {
                  Provider.of<AuthProvider>(context, listen: false)
                      .updateUser(user.copyWith(language: langCode));
                  AppHelpers.showSnack(context, _t('Language updated to $val'));
                } else {
                  AppHelpers.showSnack(context, _t('Failed to update language'),
                      isError: true);
                }
              }
            }
          },
        ),
        const SizedBox(height: 16),
        _buildDropdownLabel('DATE FORMAT', Icons.calendar_today_rounded),
        const SizedBox(height: 8),
        _buildDropdownButton(
          icon: Icons.calendar_today_rounded,
          value: _selectedDateFormat,
          items: ['DD/MM/YYYY', 'MM/DD/YYYY', 'YYYY-MM-DD'],
          onChanged: (val) {
            if (val != null) setState(() => _selectedDateFormat = val);
          },
        ),
        const SizedBox(height: 16),
        _buildDropdownLabel('TIME FORMAT', Icons.access_time_rounded),
        const SizedBox(height: 8),
        _buildDropdownButton(
          icon: Icons.access_time_rounded,
          value: _selectedTimeFormat,
          items: ['12-hour (AM/PM)', '24-hour'],
          onChanged: (val) {
            if (val != null) setState(() => _selectedTimeFormat = val);
          },
        ),
      ],
    );
  }
}
