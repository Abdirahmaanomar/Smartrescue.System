import 'package:flutter/material.dart';
import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:provider/provider.dart';
import 'dart:io';
import 'package:image_picker/image_picker.dart';
import 'package:geolocator/geolocator.dart';
import '../../providers/auth_provider.dart';
import '../../services/api_service.dart';
import '../../constants/api_constants.dart';
import '../../utils/helpers.dart';
import '../../components/app_drawer.dart';
import 'user_notifications_screen.dart';
import '../../utils/translator.dart';
import '../../utils/responsive.dart';

class UserProfileScreen extends StatefulWidget {
  const UserProfileScreen({super.key});

  @override
  State<UserProfileScreen> createState() => _UserProfileScreenState();
}

class _UserProfileScreenState extends State<UserProfileScreen> {
  // Edit Profile Controllers
  late TextEditingController _nameController;
  late TextEditingController _phoneController;
  late TextEditingController _emailController;

  XFile? _selectedAvatar;
  int _avatarVersion = 0; // bumped on every successful save to bust cache

  // Security Controllers
  final _oldPasswordController = TextEditingController();
  final _newPasswordController = TextEditingController();
  final _confirmPasswordController = TextEditingController();

  bool _obscureOld = true;
  bool _obscureNew = true;
  bool _obscureConfirm = true;

  bool _isSavingProfile = false;
  bool _isUpdatingPassword = false;

  // Local GPS/Location switches
  bool _enableGpsAccess = true;
  bool _liveLocationDuringSos = true;
  String _gpsCoordinates = '2.033771, 45.20700';

  @override
  void initState() {
    super.initState();
    final auth = Provider.of<AuthProvider>(context, listen: false);
    final user = auth.user;
    _nameController = TextEditingController(text: user?.fullname ?? '');
    _phoneController = TextEditingController(text: user?.phone ?? '');
    _emailController = TextEditingController(text: user?.email ?? '');

    _enableGpsAccess = user?.gpsAccess ?? true;
    _liveLocationDuringSos = user?.liveSosLocation ?? true;
    if (user != null) {
      _gpsCoordinates = '${user.currentLat.toStringAsFixed(6)}, ${user.currentLng.toStringAsFixed(6)}';
    }

    // Retrieve exact position on page load if GPS access is enabled
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      if (_enableGpsAccess && user != null) {
        try {
          LocationPermission permission = await Geolocator.checkPermission();
          if (permission == LocationPermission.always || permission == LocationPermission.whileInUse) {
            // Step 1: Use last known position for instant update
            final lastKnown = await Geolocator.getLastKnownPosition();
            if (lastKnown != null && mounted) {
              setState(() {
                _gpsCoordinates = '${lastKnown.latitude.toStringAsFixed(6)}, ${lastKnown.longitude.toStringAsFixed(6)}';
              });
            }
            
            // Step 2: Get precise current position
            Position pos = await Geolocator.getCurrentPosition(
              locationSettings: const LocationSettings(accuracy: LocationAccuracy.high),
            ).timeout(const Duration(seconds: 5));
            if (mounted) {
              setState(() {
                _gpsCoordinates = '${pos.latitude.toStringAsFixed(6)}, ${pos.longitude.toStringAsFixed(6)}';
              });
            }
          }
        } catch (_) {}
      }
    });
  }

  @override
  void dispose() {
    _nameController.dispose();
    _phoneController.dispose();
    _emailController.dispose();
    _oldPasswordController.dispose();
    _newPasswordController.dispose();
    _confirmPasswordController.dispose();
    super.dispose();
  }

  Future<void> _pickAvatar() async {
    final picker = ImagePicker();
    final pickedFile = await picker.pickImage(source: ImageSource.gallery, imageQuality: 70);
    if (pickedFile != null) {
      // Show preview only — upload happens when Save Changes is tapped
      setState(() {
        _selectedAvatar = pickedFile;
      });
    }
  }

  Future<void> _saveProfile(dynamic user, AuthProvider auth) async {
    if (_nameController.text.trim().isEmpty ||
        _phoneController.text.trim().isEmpty ||
        _emailController.text.trim().isEmpty) {
      AppHelpers.showSnack(context, AppTranslator.t(context, 'All fields are required!'), isError: true);
      return;
    }
    setState(() => _isSavingProfile = true);
    final result = await ApiService.updateProfile(
      userId: user.id.toString(),
      fullname: _nameController.text.trim(),
      phone: _phoneController.text.trim(),
      email: _emailController.text.trim(),
      avatar: _selectedAvatar,
    );
    if (mounted) {
      setState(() => _isSavingProfile = false);
      if (result['status'] == 'success') {
        final savedImage = result['profile_image'] ?? user.profileImage;
        // Update auth state so Drawer + everywhere refreshes immediately
        auth.updateUser(user.copyWith(
          fullname: _nameController.text.trim(),
          phone: _phoneController.text.trim(),
          email: _emailController.text.trim(),
          profileImage: savedImage,
        ));
        // Clear selected file — now using the server image + bust cache
        setState(() {
          _selectedAvatar = null;
          _avatarVersion++; // Forces Image.network to reload fresh
        });
        AppHelpers.showSnack(context, AppTranslator.t(context, '✅ Profile updated successfully!'));
      } else {
        AppHelpers.showSnack(context, result['message'] ?? AppTranslator.t(context, 'Failed to update profile'), isError: true);
      }
    }
  }

  Future<void> _updatePassword() async {
    if (_oldPasswordController.text.isEmpty ||
        _newPasswordController.text.isEmpty ||
        _confirmPasswordController.text.isEmpty) {
      AppHelpers.showSnack(context, AppTranslator.t(context, 'Please fill in all password fields!'), isError: true);
      return;
    }
    if (_newPasswordController.text.length < 8) {
      AppHelpers.showSnack(context, AppTranslator.t(context, 'New password must be at least 8 characters!'), isError: true);
      return;
    }
    if (_newPasswordController.text != _confirmPasswordController.text) {
      AppHelpers.showSnack(context, AppTranslator.t(context, 'New passwords do not match!'), isError: true);
      return;
    }
    final auth = Provider.of<AuthProvider>(context, listen: false);
    final user = auth.user;
    if (user == null) return;
    
    setState(() => _isUpdatingPassword = true);
    final result = await ApiService.changePassword(
      user.id.toString(),
      _oldPasswordController.text,
      _newPasswordController.text,
    );
    if (mounted) {
      setState(() => _isUpdatingPassword = false);
      if (result['status'] == 'success') {
        _oldPasswordController.clear();
        _newPasswordController.clear();
        _confirmPasswordController.clear();
        AppHelpers.showSnack(context, AppTranslator.t(context, 'Password updated successfully!'));
      } else {
        AppHelpers.showSnack(context, result['message'] ?? AppTranslator.t(context, 'Failed to update password'), isError: true);
      }
    }
  }

  Future<void> _showDeleteAccountDialog() async {
    final passwordController = TextEditingController();
    final isDark = Theme.of(context).brightness == Brightness.dark;
    bool isDeleting = false;

    await showDialog(
      context: context,
      barrierDismissible: false,
      builder: (ctx) {
        return StatefulBuilder(
          builder: (ctx, setStateDialog) {
            return AlertDialog(
              backgroundColor: isDark ? const Color(0xFF1E293B) : Colors.white,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
              title: Text(
                AppTranslator.t(ctx, 'Delete Account'),
                style: const TextStyle(color: Color(0xFFE11D48), fontWeight: FontWeight.bold),
              ),
              content: SingleChildScrollView(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      AppTranslator.t(ctx, 'Are you absolutely sure you want to delete your account? This action cannot be undone.'),
                      style: const TextStyle(fontSize: 14),
                    ),
                    const SizedBox(height: 16),
                    TextField(
                      controller: passwordController,
                      obscureText: true,
                      style: TextStyle(color: isDark ? Colors.white : Colors.black87),
                      decoration: InputDecoration(
                        labelText: AppTranslator.t(ctx, 'Confirm Password'),
                        labelStyle: const TextStyle(color: Colors.grey),
                        prefixIcon: const Icon(Icons.lock_rounded, color: Colors.grey),
                        enabledBorder: UnderlineInputBorder(
                          borderSide: BorderSide(color: isDark ? const Color(0xFF334155) : Colors.grey.shade300),
                        ),
                        focusedBorder: const UnderlineInputBorder(
                          borderSide: BorderSide(color: Color(0xFFE11D48)),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              actions: [
                TextButton(
                  onPressed: isDeleting ? null : () => Navigator.pop(ctx),
                  child: Text(AppTranslator.t(ctx, 'Cancel'), style: const TextStyle(color: Colors.grey, fontWeight: FontWeight.bold)),
                ),
                ElevatedButton(
                  onPressed: isDeleting
                      ? null
                      : () async {
                          if (passwordController.text.isEmpty) {
                            AppHelpers.showSnack(ctx, AppTranslator.t(ctx, 'Please enter your password'), isError: true);
                            return;
                          }
                          setStateDialog(() => isDeleting = true);
                          final auth = Provider.of<AuthProvider>(ctx, listen: false);
                          final user = auth.user;
                          if (user == null) {
                            Navigator.pop(ctx);
                            return;
                          }
                          
                          final result = await ApiService.deleteAccount(
                            user.id.toString(),
                            passwordController.text,
                          );
                          
                          if (mounted) {
                            if (result['status'] == 'success') {
                              Navigator.pop(ctx);
                              await auth.logout();
                            } else {
                              setStateDialog(() => isDeleting = false);
                              AppHelpers.showSnack(ctx, result['message'] ?? AppTranslator.t(ctx, 'Failed to delete account'), isError: true);
                            }
                          }
                        },
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFFE11D48),
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                  ),
                  child: isDeleting
                      ? const SizedBox(height: 16, width: 16, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                      : Text(AppTranslator.t(ctx, 'Delete'), style: const TextStyle(fontWeight: FontWeight.bold)),
                ),
              ],
            );
          },
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final auth = Provider.of<AuthProvider>(context);
    final user = auth.user;
    if (user == null) return const SizedBox.shrink();

    final isDark = Theme.of(context).brightness == Brightness.dark;
    final textColor = isDark ? Colors.white : Colors.black87;
    final subColor = isDark ? Colors.grey.shade400 : Colors.grey;
    final initials = user.fullname.isNotEmpty ? user.fullname[0].toUpperCase() : '?';

    return Scaffold(
      drawer: AppDrawer(
        currentIndex: 21,
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
          padding: EdgeInsets.all(Responsive(context).hPad),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // Header Title Section
            Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: const Color(0xFFE11D48),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: const Icon(
                    Icons.account_circle_rounded,
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
                        AppTranslator.t(context, 'My Account'),
                        style: TextStyle(
                          fontSize: 24,
                          fontWeight: FontWeight.bold,
                          color: textColor,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        AppTranslator.t(context, 'Manage your profile, security, and account preferences.'),
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
            const SizedBox(height: 24),

            // Giant Lavender/Pink user profile card
            Container(
              padding: const EdgeInsets.all(24),
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  colors: isDark
                      ? [const Color(0xFF3B0764), const Color(0xFF1E1B4B)]
                      : [const Color(0xFFFCE7F3), const Color(0xFFF3E8FF)], // Pinkish/Lavender gradient
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
                borderRadius: BorderRadius.circular(24),
                boxShadow: [
                  BoxShadow(
                    color: isDark
                        ? Colors.black.withValues(alpha: 0.3)
                        : const Color(0xFFF3E8FF).withValues(alpha: 0.5),
                    blurRadius: 16,
                    offset: const Offset(0, 8),
                  ),
                ],
              ),
              child: Row(
                children: [
                  // CircleAvatar with Initials
                  GestureDetector(
                    onTap: _pickAvatar,
                    child: Stack(
                      children: [
                        Container(
                          width: 72,
                          height: 72,
                          decoration: const BoxDecoration(
                            shape: BoxShape.circle,
                            color: Color(0xFFE11D48),
                          ),
                          child: ClipOval(
                            child: _selectedAvatar != null
                                ? (kIsWeb
                                    ? Image.network(
                                        _selectedAvatar!.path,
                                        width: 72,
                                        height: 72,
                                        fit: BoxFit.cover,
                                      )
                                    : Image.file(
                                        File(_selectedAvatar!.path),
                                        width: 72,
                                        height: 72,
                                        fit: BoxFit.cover,
                                      ))
                                : user.profileImage.isNotEmpty
                                    ? Image.network(
                                        ApiConstants.avatarUrl(user.profileImage),
                                        key: ValueKey('${user.profileImage}_$_avatarVersion'),
                                        width: 72,
                                        height: 72,
                                        fit: BoxFit.cover,
                                        errorBuilder: (context, error, stackTrace) {
                                          return Center(
                                            child: Text(
                                              initials,
                                              style: const TextStyle(
                                                fontSize: 24,
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
                                            fontSize: 24,
                                            color: Colors.white,
                                            fontWeight: FontWeight.w900,
                                          ),
                                        ),
                                      ),
                          ),
                        ),
                        // ✅ Spinner overlay during upload
                        if (_isSavingProfile)
                          Positioned.fill(
                            child: Container(
                              decoration: BoxDecoration(
                                shape: BoxShape.circle,
                                color: Colors.black.withValues(alpha: 0.5),
                              ),
                              child: const Center(
                                child: SizedBox(
                                  width: 28,
                                  height: 28,
                                  child: CircularProgressIndicator(
                                    strokeWidth: 2.5,
                                    color: Colors.white,
                                  ),
                                ),
                              ),
                            ),
                          ),

                      ],
                    ),
                  ),
                  const SizedBox(width: 20),
                  // User Details Column
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        // Verification tag
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                          decoration: BoxDecoration(
                            color: const Color(0xFFFEE2E2),
                            borderRadius: BorderRadius.circular(6),
                            border: Border.all(color: const Color(0xFFFCA5A5), width: 0.5),
                          ),
                          child: Text(
                            AppTranslator.t(context, 'VERIFIED USER'),
                            style: const TextStyle(
                              color: Color(0xFFE11D48),
                              fontSize: 8,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ),
                        const SizedBox(height: 6),
                        // Username lowercase bold
                        Text(
                          user.fullname.toLowerCase(),
                          style: TextStyle(
                            fontSize: 18,
                            fontWeight: FontWeight.w900,
                            color: textColor,
                          ),
                        ),
                        const SizedBox(height: 10),
                        // Three horizontal detail pills in a Wrap
                        Wrap(
                          spacing: 8,
                          runSpacing: 8,
                          children: [
                            _buildInfoPill(Icons.mail_outline_rounded, user.email),
                            _buildInfoPill(Icons.phone_outlined, user.phone),
                            _buildInfoPill(Icons.alternate_email_rounded, user.fullname.replaceAll(' ', '.').toLowerCase()),
                          ],
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 32),

            // Responsive Layout Grid
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
                          Expanded(
                            child: Column(
                              children: [
                                _buildEditProfileCard(user, auth),
                                const SizedBox(height: 24),
                                _buildLocationInfoCard(),
                              ],
                            ),
                          ),
                          const SizedBox(width: 24),
                          Expanded(
                            child: Column(
                              children: [
                                _buildSecurityCard(),
                                const SizedBox(height: 24),
                                _buildDangerZoneCard(auth),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ],
                  );
                } else {
                  return Column(
                    children: [
                      _buildEditProfileCard(user, auth),
                      const SizedBox(height: 24),
                      _buildSecurityCard(),
                      const SizedBox(height: 24),
                      _buildLocationInfoCard(),
                      const SizedBox(height: 24),
                      _buildDangerZoneCard(auth),
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

  // --- INFO PILL BUILDER ---
  Widget _buildInfoPill(IconData icon, String text) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final pillBgColor = isDark ? const Color(0xFF1E2540) : Colors.white;
    final pillBorderColor = isDark ? const Color(0xFF1E293B) : Colors.grey.shade100;
    final pillTextColor = isDark ? Colors.grey.shade300 : Colors.grey.shade600;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: pillBgColor,
        borderRadius: BorderRadius.circular(30),
        border: Border.all(color: pillBorderColor, width: 1),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 12, color: isDark ? Colors.grey.shade400 : Colors.grey.shade500),
          const SizedBox(width: 6),
          Flexible(
            child: Text(
              text,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                fontSize: 10,
                fontWeight: FontWeight.bold,
                color: pillTextColor,
              ),
            ),
          ),
        ],
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
                        title,
                        style: TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.bold,
                          color: textColor,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        subtitle,
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
          Divider(height: 1, color: isDark ? const Color(0xFF1E293B) : const Color(0xFFF1F5F9)),
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

  // 1. Edit Profile Card
  Widget _buildEditProfileCard(dynamic user, AuthProvider auth) {
    return _buildCard(
      headerIcon: Icons.edit_note_rounded,
      headerIconColor: Colors.red.shade600,
      title: AppTranslator.t(context, 'Edit Profile'),
      subtitle: AppTranslator.t(context, 'Update your personal information'),
      children: [
        _buildTextFieldLabel(AppTranslator.t(context, 'Full Name').toUpperCase()),
        const SizedBox(height: 8),
        _buildTextField(controller: _nameController, hint: AppTranslator.t(context, 'Enter full name')),
        const SizedBox(height: 16),
        _buildTextFieldLabel(AppTranslator.t(context, 'Phone Number').toUpperCase()),
        const SizedBox(height: 8),
        _buildTextField(controller: _phoneController, hint: AppTranslator.t(context, 'Enter phone number'), keyboardType: TextInputType.phone),
        const SizedBox(height: 16),
        _buildTextFieldLabel(AppTranslator.t(context, 'Email Address').toUpperCase()),
        const SizedBox(height: 8),
        _buildTextField(controller: _emailController, hint: AppTranslator.t(context, 'Enter email address'), keyboardType: TextInputType.emailAddress),
        const SizedBox(height: 20),
        SizedBox(
          width: double.infinity,
          child: ElevatedButton.icon(
            onPressed: _isSavingProfile ? null : () => _saveProfile(user, auth),
            icon: _isSavingProfile
                ? const SizedBox(height: 16, width: 16, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                : const Icon(Icons.lock_outline_rounded, color: Colors.white, size: 16),
            label: Text(AppTranslator.t(context, 'Save Changes'), style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFFE11D48),
              padding: const EdgeInsets.symmetric(vertical: 14),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
              elevation: 0,
            ),
          ),
        ),
      ],
    );
  }

  // 2. Security Card
  Widget _buildSecurityCard() {
    return _buildCard(
      headerIcon: Icons.lock_outline_rounded,
      headerIconColor: Colors.blue.shade600,
      title: AppTranslator.t(context, 'Security'),
      subtitle: AppTranslator.t(context, 'Change your account password'),
      children: [
        _buildTextFieldLabel(AppTranslator.t(context, 'Current Password').toUpperCase()),
        const SizedBox(height: 8),
        _buildPasswordField(
          controller: _oldPasswordController,
          hint: AppTranslator.t(context, 'Enter current password'),
          obscureText: _obscureOld,
          toggleObscure: () => setState(() => _obscureOld = !_obscureOld),
        ),
        const SizedBox(height: 16),
        _buildTextFieldLabel(AppTranslator.t(context, 'New Password').toUpperCase()),
        const SizedBox(height: 8),
        _buildPasswordField(
          controller: _newPasswordController,
          hint: AppTranslator.t(context, 'Min. 8 characters'),
          obscureText: _obscureNew,
          toggleObscure: () => setState(() => _obscureNew = !_obscureNew),
        ),
        const SizedBox(height: 16),
        _buildTextFieldLabel(AppTranslator.t(context, 'Confirm Password').toUpperCase()),
        const SizedBox(height: 8),
        _buildPasswordField(
          controller: _confirmPasswordController,
          hint: AppTranslator.t(context, 'Repeat new password'),
          obscureText: _obscureConfirm,
          toggleObscure: () => setState(() => _obscureConfirm = !_obscureConfirm),
        ),
        const SizedBox(height: 20),
        SizedBox(
          width: double.infinity,
          child: ElevatedButton.icon(
            onPressed: _isUpdatingPassword ? null : _updatePassword,
            icon: _isUpdatingPassword
                ? const SizedBox(height: 16, width: 16, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                : const Icon(Icons.key_rounded, color: Colors.white, size: 16),
            label: Text(AppTranslator.t(context, 'Update Password'), style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFF0F172A), // Charcoal dark blue button
              padding: const EdgeInsets.symmetric(vertical: 14),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
              elevation: 0,
            ),
          ),
        ),
      ],
    );
  }

  // 3. Location Info Card
  Widget _buildLocationInfoCard() {
    final auth = Provider.of<AuthProvider>(context, listen: false);
    final user = auth.user;

    return _buildCard(
      headerIcon: Icons.location_on_rounded,
      headerIconColor: Colors.teal.shade600,
      title: AppTranslator.t(context, 'Location Info'),
      subtitle: AppTranslator.t(context, 'Your GPS and location access status'),
      children: [
        // Soft red coordinate box
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: const Color(0xFFFFF1F2), // Light red
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: const Color(0xFFFECDD3), width: 1),
          ),
          child: Row(
            children: [
              Container(
                padding: const EdgeInsets.all(8),
                decoration: const BoxDecoration(
                  color: Color(0xFFFCA5A5),
                  shape: BoxShape.circle,
                ),
                child: const Icon(Icons.pin_drop_rounded, color: Color(0xFFBE123C), size: 18),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      AppTranslator.t(context, 'Last Known Location'),
                      style: const TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.bold,
                        color: Color(0xFF9F1239),
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      _gpsCoordinates,
                      style: const TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.bold,
                        color: Color(0xFFE11D48),
                        fontFamily: 'Courier',
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 20),
        _buildSwitchTile(
          icon: Icons.gps_fixed_rounded,
          iconBgColor: Colors.green.shade600,
          title: AppTranslator.t(context, 'Enable GPS Location'),
          subtitle: AppTranslator.t(context, 'Required for SOS emergency dispatch'),
          value: _enableGpsAccess,
          onChanged: (val) async {
            if (user == null) return;
            
            // Instantly update UI and send to database
            setState(() => _enableGpsAccess = val);
            
            final result = await ApiService.togglePreference(user.id.toString(), 'gps_access', val);
            if (result['status'] == 'success') {
              auth.updateUser(user.copyWith(gpsAccess: val));
              if (mounted) {
                AppHelpers.showSnack(context, val ? AppTranslator.t(context, '✅ Location enabled successfully.') : AppTranslator.t(context, 'Location disabled.'));
              }
            } else {
              // Revert only if API fails
              setState(() => _enableGpsAccess = !val);
              if (mounted) {
                AppHelpers.showSnack(context, result['message'] ?? AppTranslator.t(context, 'Failed to update Location'), isError: true);
              }
              return;
            }

            // If turned ON, quietly try to fetch location in background
            if (val) {
              try {
                LocationPermission permission = await Geolocator.checkPermission();
                if (permission == LocationPermission.denied) {
                  permission = await Geolocator.requestPermission();
                }
                
                if (permission == LocationPermission.always || permission == LocationPermission.whileInUse) {
                  Geolocator.getCurrentPosition(
                    locationSettings: const LocationSettings(accuracy: LocationAccuracy.high),
                  ).timeout(const Duration(seconds: 10)).then((pos) {
                    if (mounted) {
                      setState(() {
                        _gpsCoordinates = '${pos.latitude.toStringAsFixed(6)}, ${pos.longitude.toStringAsFixed(6)}';
                      });
                    }
                    ApiService.updateUserLocation(user.id.toString(), pos.latitude, pos.longitude);
                  }).catchError((_) {});
                } else {
                  if (mounted) {
                    AppHelpers.showSnack(context, AppTranslator.t(context, '⚠️ GPS enabled in settings, but browser/device permission is denied.'), isError: true);
                  }
                }
              } catch (e) {
                if (mounted) {
                  AppHelpers.showSnack(context, AppTranslator.t(context, '⚠️ GPS enabled in settings, but location services are unavailable.'), isError: true);
                }
              }
            }
          },
        ),
        const SizedBox(height: 14),
        _buildSwitchTile(
          icon: Icons.navigation_rounded,
          iconBgColor: Colors.red.shade600,
          title: AppTranslator.t(context, 'Share Live Location During SOS'),
          subtitle: AppTranslator.t(context, 'Continuously stream location to rescuers'),
          value: _liveLocationDuringSos,
          onChanged: (val) async {
            if (user == null) return;
            setState(() => _liveLocationDuringSos = val);
            final result = await ApiService.togglePreference(user.id.toString(), 'live_sos_location', val);
            if (result['status'] == 'success') {
              auth.updateUser(user.copyWith(liveSosLocation: val));
              if (mounted) {
                AppHelpers.showSnack(
                  context,
                  val ? AppTranslator.t(context, '✅ Live location streaming enabled during SOS.') : AppTranslator.t(context, 'Live location streaming disabled.'),
                );
              }
            } else {
              setState(() => _liveLocationDuringSos = !val);
              if (mounted) {
                AppHelpers.showSnack(context, result['message'] ?? AppTranslator.t(context, 'Failed to update Live Location preference'), isError: true);
              }
            }
          },
        ),
      ],
    );
  }

  // 4. Danger Zone Card
  Widget _buildDangerZoneCard(AuthProvider auth) {
    return _buildCard(
      headerIcon: Icons.warning_amber_rounded,
      headerIconColor: Colors.orange.shade800,
      title: AppTranslator.t(context, 'Danger Zone'),
      subtitle: AppTranslator.t(context, 'Irreversible account actions'),
      children: [
        // Soft warning box
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: const Color(0xFFFFF1F2), // Light red
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: const Color(0xFFFECDD3), width: 1),
          ),
          child: Row(
            children: [
              Container(
                padding: const EdgeInsets.all(8),
                decoration: const BoxDecoration(
                  color: Color(0xFFFCA5A5),
                  shape: BoxShape.circle,
                ),
                child: const Icon(Icons.person_remove_rounded, color: Color(0xFFBE123C), size: 18),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      AppTranslator.t(context, 'Delete Account'),
                      style: const TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.bold,
                        color: Color(0xFF9F1239),
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      AppTranslator.t(context, 'All your data, history, and records will be permanently removed. This action cannot be undone.'),
                      style: const TextStyle(
                        fontSize: 10,
                        fontWeight: FontWeight.w500,
                        color: Color(0xFFE11D48),
                        height: 1.4,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 20),
        SizedBox(
          width: double.infinity,
          child: OutlinedButton.icon(
            onPressed: _showDeleteAccountDialog,
            icon: const Icon(Icons.delete_forever_rounded, color: Color(0xFFE11D48), size: 18),
            label: Text(AppTranslator.t(context, 'Delete Account'), style: const TextStyle(color: Color(0xFFE11D48), fontWeight: FontWeight.bold)),
            style: OutlinedButton.styleFrom(
              side: const BorderSide(color: Color(0xFFFCA5A5), width: 1.5),
              padding: const EdgeInsets.symmetric(vertical: 14),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
            ),
          ),
        ),
      ],
    );
  }

  // --- SUB COMPONENT BUILDERS ---

  Widget _buildTextFieldLabel(String label) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return Text(
      label,
      style: TextStyle(
        fontSize: 9,
        fontWeight: FontWeight.bold,
        color: isDark ? Colors.grey.shade400 : Colors.grey.shade500,
        letterSpacing: 0.8,
      ),
    );
  }

  Widget _buildTextField({
    required TextEditingController controller,
    required String hint,
    TextInputType keyboardType = TextInputType.text,
  }) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final inputBg = isDark ? const Color(0xFF1A2540) : Colors.grey.shade50;
    final inputBorder = isDark ? const Color(0xFF1E293B) : Colors.grey.shade200;
    final textColor = isDark ? Colors.white : Colors.black87;

    return Container(
      decoration: BoxDecoration(
        color: inputBg,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: inputBorder, width: 1),
      ),
      child: TextField(
        controller: controller,
        keyboardType: keyboardType,
        style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: textColor),
        decoration: InputDecoration(
          hintText: hint,
          hintStyle: TextStyle(color: Colors.grey.shade400, fontSize: 13),
          contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
          border: InputBorder.none,
          isDense: true,
        ),
      ),
    );
  }

  Widget _buildPasswordField({
    required TextEditingController controller,
    required String hint,
    required bool obscureText,
    required VoidCallback toggleObscure,
  }) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final inputBg = isDark ? const Color(0xFF1A2540) : Colors.grey.shade50;
    final inputBorder = isDark ? const Color(0xFF1E293B) : Colors.grey.shade200;
    final textColor = isDark ? Colors.white : Colors.black87;

    return Container(
      decoration: BoxDecoration(
        color: inputBg,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: inputBorder, width: 1),
      ),
      child: Row(
        children: [
          Expanded(
            child: TextField(
              controller: controller,
              obscureText: obscureText,
              style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: textColor),
              decoration: InputDecoration(
                hintText: hint,
                hintStyle: TextStyle(color: Colors.grey.shade400, fontSize: 13),
                contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                border: InputBorder.none,
                isDense: true,
              ),
            ),
          ),
          IconButton(
            icon: Icon(
              obscureText ? Icons.visibility_outlined : Icons.visibility_off_outlined,
              size: 16,
              color: Colors.grey.shade400,
            ),
            onPressed: toggleObscure,
          ),
        ],
      ),
    );
  }

  Widget _buildSwitchTile({
    required IconData icon,
    required Color iconBgColor,
    required String title,
    required String subtitle,
    required bool value,
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
              Text(
                title,
                style: TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.bold,
                  color: textColor,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                subtitle,
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
        Switch(
          value: value,
          activeThumbColor: const Color(0xFFE11D48),
          activeTrackColor: const Color(0xFFE11D48).withValues(alpha: 0.5),
          onChanged: onChanged,
        ),
      ],
    );
  }
}
