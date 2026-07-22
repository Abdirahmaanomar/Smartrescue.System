import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../providers/auth_provider.dart';
import '../providers/sos_provider.dart';
import '../constants/api_constants.dart';
import '../views/user/user_medical_screen.dart';
import '../views/user/user_contacts_screen.dart';
import '../views/user/user_settings_screen.dart';
import '../views/user/user_history_screen.dart';
import '../views/user/user_profile_screen.dart';
import '../utils/translator.dart';
import './app_logo.dart';

class AppDrawer extends StatelessWidget {
  final int currentIndex;
  final ValueChanged<int> onTabSelected;
  final bool isSubScreen;

  const AppDrawer({
    super.key,
    required this.currentIndex,
    required this.onTabSelected,
    this.isSubScreen = false,
  });

  @override
  Widget build(BuildContext context) {
    final auth = Provider.of<AuthProvider>(context);
    final user = auth.user;
    if (user == null) return const SizedBox.shrink();

    final initials = user.fullname.isNotEmpty ? user.fullname[0].toUpperCase() : '?';

    return Drawer(
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.only(
          topRight: Radius.circular(24),
          bottomRight: Radius.circular(24),
        ),
      ),
      child: Container(
        color: const Color(0xFF080F1E), // Deep Premium Dark Background
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Styled SmartRescue Logo Header
            Padding(
              padding: const EdgeInsets.fromLTRB(24, 60, 24, 30),
              child: Row(
                children: [
                  const AppLogo(
                    size: 44,
                    borderRadius: 12,
                    showBorder: false,
                    showShadow: true,
                  ),
                  const SizedBox(width: 14),
                  RichText(
                    text: const TextSpan(
                      style: TextStyle(
                        fontSize: 22,
                        fontWeight: FontWeight.w900,
                        letterSpacing: -0.8,
                        fontFamily: 'Inter',
                      ),
                      children: [
                        TextSpan(
                          text: 'Smart',
                          style: TextStyle(color: Colors.white),
                        ),
                        TextSpan(
                          text: 'Rescue',
                          style: TextStyle(color: Color(0xFF60A5FA)), // Lighter blue accent
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),

            // Navigation Menu Options
            Expanded(
              child: ListView(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                children: [
                  _buildSectionLabel(AppTranslator.t(context, 'MAIN')),
                  _buildDrawerItem(
                    context: context,
                    icon: Icons.speed_rounded,
                    title: AppTranslator.t(context, 'Dashboard'),
                    isActive: currentIndex == 0,
                    onTap: () {
                      Navigator.pop(context);
                      if (isSubScreen) {
                        Navigator.pop(context, 0);
                      } else {
                        onTabSelected(0);
                      }
                    },
                  ),
                  _buildDrawerItem(
                    context: context,
                    icon: Icons.monitor_heart_rounded,
                    title: AppTranslator.t(context, 'Medical ID'),
                    isActive: currentIndex == 10,
                    onTap: () {
                      Navigator.pop(context);
                      if (currentIndex == 10) return;
                      if (isSubScreen) {
                        Navigator.pushReplacement(
                          context,
                          MaterialPageRoute(
                            builder: (context) => const UserMedicalScreen(),
                          ),
                        );
                      } else {
                        Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (context) => const UserMedicalScreen(),
                          ),
                        ).then((res) {
                          if (res != null && res is int) {
                            onTabSelected(res);
                          }
                        });
                      }
                    },
                  ),
                  _buildDrawerItem(
                    context: context,
                    icon: Icons.people_alt_rounded,
                    title: AppTranslator.t(context, 'Contacts'),
                    isActive: currentIndex == 11,
                    onTap: () {
                      Navigator.pop(context);
                      if (currentIndex == 11) return;
                      if (isSubScreen) {
                        Navigator.pushReplacement(
                          context,
                          MaterialPageRoute(
                            builder: (context) => const UserContactsScreen(),
                          ),
                        );
                      } else {
                        Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (context) => const UserContactsScreen(),
                          ),
                        ).then((res) {
                          if (res != null && res is int) {
                            onTabSelected(res);
                          }
                        });
                      }
                    },
                  ),
                  const SizedBox(height: 24),
                  _buildSectionLabel(AppTranslator.t(context, 'ACCOUNT')),
                  _buildDrawerItem(
                    context: context,
                    icon: Icons.history_rounded,
                    title: AppTranslator.t(context, 'History'),
                    isActive: currentIndex == 20,
                    onTap: () {
                      Navigator.pop(context);
                      if (currentIndex == 20) return;
                      if (isSubScreen) {
                        Navigator.pushReplacement(
                          context,
                          MaterialPageRoute(
                            builder: (context) => const UserHistoryScreen(),
                          ),
                        );
                      } else {
                        Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (context) => const UserHistoryScreen(),
                          ),
                        ).then((res) {
                          if (res != null && res is int) {
                            onTabSelected(res);
                          }
                        });
                      }
                    },
                  ),
                  _buildDrawerItem(
                    context: context,
                    icon: Icons.account_circle_rounded,
                    title: AppTranslator.t(context, 'My Account'),
                    isActive: currentIndex == 21,
                    onTap: () {
                      Navigator.pop(context);
                      if (currentIndex == 21) return;
                      if (isSubScreen) {
                        Navigator.pushReplacement(
                          context,
                          MaterialPageRoute(
                            builder: (context) => const UserProfileScreen(),
                          ),
                        );
                      } else {
                        Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (context) => const UserProfileScreen(),
                          ),
                        ).then((res) {
                          if (res != null && res is int) {
                            onTabSelected(res);
                          }
                        });
                      }
                    },
                  ),
                  _buildDrawerItem(
                    context: context,
                    icon: Icons.tune_rounded,
                    title: AppTranslator.t(context, 'Settings'),
                    isActive: currentIndex == 12,
                    onTap: () {
                      Navigator.pop(context);
                      if (currentIndex == 12) return;
                      if (isSubScreen) {
                        Navigator.pushReplacement(
                          context,
                          MaterialPageRoute(
                            builder: (context) => const UserSettingsScreen(),
                          ),
                        );
                      } else {
                        Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (context) => const UserSettingsScreen(),
                          ),
                        ).then((res) {
                          if (res != null && res is int) {
                            onTabSelected(res);
                          }
                        });
                      }
                    },
                  ),
                ],
              ),
            ),

            // Profile Card Box
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: Material(
                color: Colors.transparent,
                child: InkWell(
                  onTap: () {
                    Navigator.pop(context);
                    if (currentIndex == 21) return;
                    if (isSubScreen) {
                      Navigator.pushReplacement(
                        context,
                        MaterialPageRoute(
                          builder: (context) => const UserProfileScreen(),
                        ),
                      );
                    } else {
                      Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (context) => const UserProfileScreen(),
                        ),
                      ).then((res) {
                        if (res != null && res is int) {
                          onTabSelected(res);
                        }
                      });
                    }
                  },
                  borderRadius: BorderRadius.circular(16),
                  child: Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: const Color(0xFF0C1528), // Sleek inner dark blue panel
                      borderRadius: BorderRadius.circular(16),
                      border: Border.all(
                        color: Colors.white.withValues(alpha: 0.04),
                      ),
                    ),
                    child: Row(
                      children: [
                        Stack(
                          children: [
                            Container(
                              width: 44,
                              height: 44,
                              decoration: const BoxDecoration(
                                shape: BoxShape.circle,
                                color: Color(0xFF5850EC),
                              ),
                              child: ClipOval(
                                child: user.profileImage.isNotEmpty
                                    ? Image.network(
                                        ApiConstants.avatarUrl(user.profileImage),
                                        key: ValueKey(user.profileImage),
                                        width: 44,
                                        height: 44,
                                        fit: BoxFit.cover,
                                        errorBuilder: (context, error, stackTrace) {
                                          return Center(
                                            child: Text(
                                              initials,
                                              style: const TextStyle(
                                                fontSize: 16,
                                                color: Colors.white,
                                                fontWeight: FontWeight.w900,
                                              ),
                                            ),
                                          );
                                        },
                                      )
                                    : Center(
                                        child: Text(
                                          initials,
                                          style: const TextStyle(
                                            fontSize: 16,
                                            color: Colors.white,
                                            fontWeight: FontWeight.w900,
                                          ),
                                        ),
                                      ),
                              ),
                            ),

                          ],
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                user.fullname,
                                style: const TextStyle(
                                  fontWeight: FontWeight.w800,
                                  fontSize: 14,
                                  color: Colors.white,
                                  letterSpacing: -0.3,
                                ),
                                overflow: TextOverflow.ellipsis,
                              ),
                              const SizedBox(height: 2),
                              Text(
                                AppTranslator.t(context, 'EDIT PROFILE PICTURE'),
                                style: const TextStyle(
                                  fontSize: 9,
                                  color: Color(0xFF60A5FA),
                                  fontWeight: FontWeight.w800,
                                  letterSpacing: 0.5,
                                ),
                              ),
                            ],
                          ),
                        ),
                        const Icon(Icons.chevron_right_rounded, color: Color(0xFF475569), size: 20),
                      ],
                    ),
                  ),
                ),
              ),
            ),

            // Red Styled Sign Out Button
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
              child: SizedBox(
                width: double.infinity,
                child: ElevatedButton.icon(
                  onPressed: () {
                    Navigator.pop(context);
                    Provider.of<SosProvider>(context, listen: false).reset();
                    auth.logout();
                    Navigator.of(context).pushReplacementNamed('/login');
                  },
                  icon: const Icon(
                    Icons.power_settings_new_rounded,
                    color: Colors.white,
                    size: 20,
                  ),
                  label: Text(
                    AppTranslator.t(context, 'Sign Out'),
                    style: const TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w800,
                      fontSize: 14,
                    ),
                  ),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFFEF4444), // Vibrant Red
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    elevation: 0,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14),
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildSectionLabel(String label) {
    return Padding(
      padding: const EdgeInsets.only(left: 12, bottom: 10, top: 6),
      child: Text(
        label,
        style: const TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.w800,
          color: Color(0xFF475569), // Slate section text
          letterSpacing: 1.5,
        ),
      ),
    );
  }

  Widget _buildDrawerItem({
    required BuildContext context,
    required IconData icon,
    required String title,
    required bool isActive,
    required VoidCallback onTap,
  }) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 6),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(14),
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 180),
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(14),
            color: isActive ? const Color(0xFF1D4ED8) : Colors.transparent, // Active blue
          ),
          child: Row(
            children: [
              Icon(
                icon,
                color: isActive ? Colors.white : const Color(0xFF64748B),
                size: 22,
              ),
              const SizedBox(width: 14),
              Text(
                title,
                style: TextStyle(
                  color: isActive ? Colors.white : const Color(0xFF94A3B8),
                  fontWeight: isActive ? FontWeight.w800 : FontWeight.w600,
                  fontSize: 14,
                ),
              ),
              if (isActive) ...[
                const Spacer(),
                Container(
                  width: 6,
                  height: 6,
                  decoration: const BoxDecoration(
                    color: Color(0xFF22C55E), // Glowing neon green dot
                    shape: BoxShape.circle,
                    boxShadow: [
                      BoxShadow(
                        color: Color(0xFF22C55E),
                        blurRadius: 6,
                        spreadRadius: 1,
                      ),
                    ],
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
