import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import 'package:image_picker/image_picker.dart';
import '../../constants/api_constants.dart';
import '../../providers/auth_provider.dart';
import '../../utils/helpers.dart';
import '../../services/api_service.dart';
import '../../components/app_button.dart';
import '../../components/app_text_field.dart';

class DriverProfileScreen extends StatefulWidget {
  const DriverProfileScreen({super.key});

  @override
  State<DriverProfileScreen> createState() => _DriverProfileScreenState();
}

class _DriverProfileScreenState extends State<DriverProfileScreen> {
  final _profileFormKey = GlobalKey<FormState>();
  final _passwordFormKey = GlobalKey<FormState>();

  final _nameController = TextEditingController();
  final _emailController = TextEditingController();
  final _phoneController = TextEditingController();
  final _birthDateController = TextEditingController();
  
  final _oldPasswordController = TextEditingController();
  final _newPasswordController = TextEditingController();
  final _confirmPasswordController = TextEditingController();

  String? _selectedGender;
  XFile? _selectedAvatar;
  Uint8List? _avatarBytes; // for web-compatible preview
  bool _isSavingProfile = false;
  bool _isUpdatingPassword = false;
  bool _isUploadingAvatar = false; // to track direct photo upload

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final user = Provider.of<AuthProvider>(context, listen: false).user;
      if (user != null) {
        _nameController.text = user.fullname;
        _emailController.text = user.email;
        _phoneController.text = user.phone;
        _birthDateController.text = user.birthDate;
        setState(() {
          _selectedGender = user.gender.isNotEmpty ? user.gender : null;
        });
      }
    });
  }

  @override
  void dispose() {
    _nameController.dispose();
    _emailController.dispose();
    _phoneController.dispose();
    _birthDateController.dispose();
    _oldPasswordController.dispose();
    _newPasswordController.dispose();
    _confirmPasswordController.dispose();
    super.dispose();
  }

  Future<void> _pickAvatar() async {
    // On web, camera source is not supported — always use gallery
    ImageSource? source;
    if (kIsWeb) {
      source = ImageSource.gallery;
    } else {
      // Show bottom sheet to choose source on mobile
      source = await _showImageSourceSheet();
      if (source == null) return;
    }

    try {
      final picker = ImagePicker();
      final pickedFile = await picker.pickImage(
        source: source,
        imageQuality: 75,
        maxWidth: 800,
        maxHeight: 800,
      );
      if (pickedFile != null) {
        final bytes = await pickedFile.readAsBytes();
        setState(() {
          _selectedAvatar = pickedFile;
          _avatarBytes = bytes;
        });
        // Upload immediately for a premium experience
        await _uploadAvatarDirectly(pickedFile);
      }
    } catch (e) {
      if (mounted) {
        AppHelpers.showSnack(
          context,
          'Could not open image picker: $e',
          isError: true,
        );
      }
    }
  }

  Future<void> _uploadAvatarDirectly(XFile file) async {
    final auth = Provider.of<AuthProvider>(context, listen: false);
    final user = auth.user;
    if (user == null) return;

    setState(() => _isUploadingAvatar = true);

    try {
      final result = await ApiService.updateProfile(
        userId: user.id.toString(),
        fullname: _nameController.text.trim().isNotEmpty
            ? _nameController.text.trim()
            : user.fullname,
        phone: _phoneController.text.trim().isNotEmpty
            ? _phoneController.text.trim()
            : user.phone,
        email: _emailController.text.trim().isNotEmpty
            ? _emailController.text.trim()
            : user.email,
        birthDate: _birthDateController.text.trim(),
        gender: _selectedGender,
        avatar: file,
      );

      if (result['status'] == 'success') {
        final savedImage = result['profile_image'] ?? user.profileImage;
        auth.updateUser(user.copyWith(
          profileImage: savedImage,
        ));
        setState(() {
          _selectedAvatar = null;
          _avatarBytes = null;
        });
        if (mounted) {
          AppHelpers.showSnack(context, '✅ Profile photo updated successfully!');
        }
      } else {
        if (mounted) {
          AppHelpers.showSnack(
            context,
            result['message'] ?? 'Failed to update profile photo',
            isError: true,
          );
        }
      }
    } catch (e) {
      if (mounted) {
        AppHelpers.showSnack(context, 'An error occurred: $e', isError: true);
      }
    } finally {
      if (mounted) {
        setState(() => _isUploadingAvatar = false);
      }
    }
  }

  Future<ImageSource?> _showImageSourceSheet() async {
    return showModalBottomSheet<ImageSource>(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (ctx) {
        final isDark = Theme.of(ctx).brightness == Brightness.dark;
        return Container(
          margin: const EdgeInsets.all(16),
          padding: const EdgeInsets.symmetric(vertical: 20, horizontal: 16),
          decoration: BoxDecoration(
            color: isDark ? const Color(0xFF1E293B) : Colors.white,
            borderRadius: BorderRadius.circular(24),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 40,
                height: 4,
                margin: const EdgeInsets.only(bottom: 20),
                decoration: BoxDecoration(
                  color: isDark ? Colors.white24 : Colors.grey.shade300,
                  borderRadius: BorderRadius.circular(4),
                ),
              ),
              Text(
                'Choose Photo Source',
                style: TextStyle(
                  fontWeight: FontWeight.w800,
                  fontSize: 15,
                  color: isDark ? Colors.white : const Color(0xFF0F172A),
                ),
              ),
              const SizedBox(height: 20),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                children: [
                  _SourceOption(
                    icon: Icons.camera_alt_rounded,
                    label: 'Camera',
                    color: const Color(0xFF6366F1),
                    isDark: isDark,
                    onTap: () {
                      HapticFeedback.lightImpact();
                      Navigator.pop(ctx, ImageSource.camera);
                    },
                  ),
                  _SourceOption(
                    icon: Icons.photo_library_rounded,
                    label: 'Gallery',
                    color: const Color(0xFF10B981),
                    isDark: isDark,
                    onTap: () {
                      HapticFeedback.lightImpact();
                      Navigator.pop(ctx, ImageSource.gallery);
                    },
                  ),
                ],
              ),
              const SizedBox(height: 8),
            ],
          ),
        );
      },
    );
  }

  Future<void> _selectBirthDate() async {
    DateTime initialDate = DateTime.now().subtract(const Duration(days: 365 * 25));
    if (_birthDateController.text.isNotEmpty) {
      try {
        initialDate = DateTime.parse(_birthDateController.text);
      } catch (_) {}
    }

    final DateTime? picked = await showDatePicker(
      context: context,
      initialDate: initialDate,
      firstDate: DateTime(1940),
      lastDate: DateTime.now(),
      builder: (context, child) {
        final isDark = Theme.of(context).brightness == Brightness.dark;
        return Theme(
          data: Theme.of(context).copyWith(
            colorScheme: isDark
                ? const ColorScheme.dark(
                    primary: Color(0xFF3B82F6),
                    onPrimary: Colors.white,
                    surface: Color(0xFF1E293B),
                  )
                : const ColorScheme.light(
                    primary: Color(0xFF2563EB),
                    onPrimary: Colors.white,
                    surface: Colors.white,
                  ),
          ),
          child: child!,
        );
      },
    );

    if (picked != null) {
      setState(() {
        _birthDateController.text = "${picked.year}-${picked.month.toString().padLeft(2, '0')}-${picked.day.toString().padLeft(2, '0')}";
      });
    }
  }

  Future<void> _saveProfile() async {
    if (!_profileFormKey.currentState!.validate()) return;

    final auth = Provider.of<AuthProvider>(context, listen: false);
    final user = auth.user;
    if (user == null) return;

    setState(() => _isSavingProfile = true);

    try {
      final result = await ApiService.updateProfile(
        userId: user.id.toString(),
        fullname: _nameController.text.trim(),
        phone: _phoneController.text.trim(),
        email: _emailController.text.trim(),
        birthDate: _birthDateController.text.trim(),
        gender: _selectedGender,
        avatar: _selectedAvatar,
      );

      if (result['status'] == 'success') {
        final savedImage = result['profile_image'] ?? user.profileImage;
        auth.updateUser(user.copyWith(
          fullname: _nameController.text.trim(),
          phone: _phoneController.text.trim(),
          email: _emailController.text.trim(),
          birthDate: _birthDateController.text.trim(),
          gender: _selectedGender ?? '',
          profileImage: savedImage,
        ));
        setState(() {
          _selectedAvatar = null;
          _avatarBytes = null;
        });
        if (mounted) {
          AppHelpers.showSnack(context, '✅ Profile updated successfully!');
        }
      } else {
        if (mounted) {
          AppHelpers.showSnack(context, result['message'] ?? 'Failed to update profile', isError: true);
        }
      }
    } catch (e) {
      if (mounted) {
        AppHelpers.showSnack(context, 'An error occurred: $e', isError: true);
      }
    } finally {
      if (mounted) {
        setState(() => _isSavingProfile = false);
      }
    }
  }

  Future<void> _updatePassword() async {
    if (!_passwordFormKey.currentState!.validate()) return;

    if (_newPasswordController.text.length < 8) {
      AppHelpers.showSnack(context, 'New password must be at least 8 characters!', isError: true);
      return;
    }

    if (_newPasswordController.text != _confirmPasswordController.text) {
      AppHelpers.showSnack(context, 'New passwords do not match!', isError: true);
      return;
    }

    final auth = Provider.of<AuthProvider>(context, listen: false);
    final user = auth.user;
    if (user == null) return;

    setState(() => _isUpdatingPassword = true);

    try {
      final result = await ApiService.changePassword(
        user.id.toString(),
        _oldPasswordController.text,
        _newPasswordController.text,
      );

      if (result['status'] == 'success') {
        _oldPasswordController.clear();
        _newPasswordController.clear();
        _confirmPasswordController.clear();
        if (mounted) {
          AppHelpers.showSnack(context, 'Password updated successfully!');
        }
      } else {
        if (mounted) {
          AppHelpers.showSnack(context, result['message'] ?? 'Failed to update password', isError: true);
        }
      }
    } catch (e) {
      if (mounted) {
        AppHelpers.showSnack(context, 'An error occurred: $e', isError: true);
      }
    } finally {
      if (mounted) {
        setState(() => _isUpdatingPassword = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final user = Provider.of<AuthProvider>(context).user;
    final isDark = Theme.of(context).brightness == Brightness.dark;

    if (user == null) {
      return const Scaffold(body: Center(child: Text('Not Logged In')));
    }

    final initials = user.fullname.isNotEmpty ? user.fullname.substring(0, 1).toUpperCase() : 'D';

    return Scaffold(
      backgroundColor: isDark ? const Color(0xFF0F172A) : const Color(0xFFF8FAFC),
      body: SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // ── Avatar Selector Card ──────────────────────────────────────────
            _buildAvatarCard(user, initials, isDark),
            const SizedBox(height: 20),

            // ── Personal Info Section Header ──────────────────────────────────
            _sectionHeader(Icons.person_outline_rounded, Colors.blue, 'Personal Information', isDark),
            const SizedBox(height: 12),

            // ── Profile Form Form ─────────────────────────────────────────────
            _buildProfileForm(isDark),
            const SizedBox(height: 28),

            // ── Security Section Header ──────────────────────────────────────
            _sectionHeader(Icons.security_rounded, Colors.orange, 'Security & Password', isDark),
            const SizedBox(height: 12),

            // ── Change Password Form ──────────────────────────────────────────
            _buildPasswordForm(isDark),
          ],
        ),
      ),
    );
  }

  // ─── Profile Avatar Panel ──────────────────────────────────────────────────
  Widget _buildAvatarCard(dynamic user, String initials, bool isDark) {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 24, horizontal: 16),
      decoration: BoxDecoration(
        color: isDark ? const Color(0xFF1E293B) : Colors.white,
        borderRadius: BorderRadius.circular(24),
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
      child: Column(
        children: [
          Stack(
            alignment: Alignment.bottomRight,
            children: [
              Container(
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  boxShadow: [
                    BoxShadow(
                      color: Theme.of(context).colorScheme.primary.withValues(alpha: 0.2),
                      blurRadius: 16,
                      spreadRadius: 2,
                    )
                  ],
                ),
                child: CircleAvatar(
                  radius: 50,
                  backgroundColor: Theme.of(context).colorScheme.primary,
                  backgroundImage: _avatarBytes != null
                      ? MemoryImage(_avatarBytes!) as ImageProvider
                      : (user.profileImage.isNotEmpty
                          ? NetworkImage(ApiConstants.avatarUrl(user.profileImage))
                          : null),
                  child: _isUploadingAvatar
                      ? Container(
                          decoration: BoxDecoration(
                            color: Colors.black.withValues(alpha: 0.4),
                            shape: BoxShape.circle,
                          ),
                          child: const Center(
                            child: CircularProgressIndicator(
                              color: Colors.white,
                              strokeWidth: 3,
                            ),
                          ),
                        )
                      : (_avatarBytes == null && user.profileImage.isEmpty
                          ? Text(initials,
                              style: const TextStyle(
                                  fontSize: 36,
                                  fontWeight: FontWeight.bold,
                                  color: Colors.white))
                          : null),
                ),
              ),
              GestureDetector(
                onTap: _isUploadingAvatar ? null : _pickAvatar,
                child: CircleAvatar(
                  radius: 18,
                  backgroundColor: Theme.of(context).colorScheme.primary,
                  child: _isUploadingAvatar
                      ? const SizedBox(
                          width: 14,
                          height: 14,
                          child: CircularProgressIndicator(
                            color: Colors.white,
                            strokeWidth: 2,
                          ),
                        )
                      : const Icon(Icons.camera_alt_rounded, size: 16, color: Colors.white),
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          Text(
            user.fullname,
            style: TextStyle(
              fontWeight: FontWeight.w900,
              fontSize: 18,
              letterSpacing: -0.4,
              color: isDark ? Colors.white : const Color(0xFF0F172A),
            ),
          ),
          const SizedBox(height: 4),
          Text(
            'Emergency Responder Unit',
            style: TextStyle(
              color: isDark ? Colors.grey.shade500 : Colors.grey.shade400,
              fontSize: 12.5,
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }

  // ─── Section Header ────────────────────────────────────────────────────────
  Widget _sectionHeader(IconData icon, Color color, String label, bool isDark) {
    return Row(
      children: [
        Icon(icon, color: color, size: 18),
        const SizedBox(width: 8),
        Text(
          label,
          style: TextStyle(
            fontWeight: FontWeight.w800,
            fontSize: 14.5,
            color: isDark ? Colors.grey.shade300 : const Color(0xFF334155),
          ),
        ),
      ],
    );
  }

  // ─── Profile Form Widget ───────────────────────────────────────────────────
  Widget _buildProfileForm(bool isDark) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: isDark ? const Color(0xFF1E293B) : Colors.white,
        borderRadius: BorderRadius.circular(24),
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
      child: Form(
        key: _profileFormKey,
        child: Column(
          children: [
            AppTextField(
              label: 'Full Name',
              controller: _nameController,
              prefixIcon: Icons.person_outline_rounded,
              validator: (val) => val == null || val.isEmpty ? 'Required' : null,
            ),
            const SizedBox(height: 14),
            AppTextField(
              label: 'Email Address',
              controller: _emailController,
              prefixIcon: Icons.mail_outline_rounded,
              keyboardType: TextInputType.emailAddress,
              validator: (val) => val == null || val.isEmpty ? 'Required' : null,
            ),
            const SizedBox(height: 14),
            AppTextField(
              label: 'Phone Number',
              controller: _phoneController,
              prefixIcon: Icons.phone_outlined,
              keyboardType: TextInputType.phone,
              validator: (val) => val == null || val.isEmpty ? 'Required' : null,
            ),
            const SizedBox(height: 14),
            Row(
              children: [
                Expanded(
                  child: InkWell(
                    onTap: _selectBirthDate,
                    borderRadius: BorderRadius.circular(14),
                    child: IgnorePointer(
                      child: AppTextField(
                        label: 'Birth Date',
                        controller: _birthDateController,
                        prefixIcon: Icons.calendar_today_rounded,
                        hint: 'YYYY-MM-DD',
                      ),
                    ),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: InputDecorator(
                    decoration: InputDecoration(
                      labelText: 'Gender',
                      labelStyle: TextStyle(
                        color: isDark ? Colors.grey.shade400 : Colors.grey.shade500,
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                      ),
                      prefixIcon: const Icon(Icons.wc_rounded, size: 18),
                      contentPadding: const EdgeInsets.symmetric(horizontal: 10, vertical: 2),
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(14),
                      ),
                      enabledBorder: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(14),
                        borderSide: BorderSide(
                          color: isDark ? Colors.white.withValues(alpha: 0.15) : Colors.grey.shade300,
                          width: 1,
                        ),
                      ),
                    ),
                    child: DropdownButtonHideUnderline(
                      child: DropdownButton<String>(
                        value: _selectedGender,
                        isExpanded: true,
                        dropdownColor: isDark ? const Color(0xFF1E293B) : Colors.white,
                        style: TextStyle(
                          color: isDark ? Colors.white : Colors.black87,
                          fontSize: 13,
                          fontWeight: FontWeight.w600,
                        ),
                        items: const [
                          DropdownMenuItem(value: 'Male', child: Text('Male')),
                          DropdownMenuItem(value: 'Female', child: Text('Female')),
                        ],
                        onChanged: (val) => setState(() => _selectedGender = val),
                      ),
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 20),
            SizedBox(
              width: double.infinity,
              child: AppButton(
                label: 'Save Changes',
                onPressed: _saveProfile,
                loading: _isSavingProfile,
              ),
            ),
          ],
        ),
      ),
    );
  }

  // ─── Password Change Form Widget ───────────────────────────────────────────
  Widget _buildPasswordForm(bool isDark) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: isDark ? const Color(0xFF1E293B) : Colors.white,
        borderRadius: BorderRadius.circular(24),
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
      child: Form(
        key: _passwordFormKey,
        child: Column(
          children: [
            AppTextField(
              label: 'Current Password',
              controller: _oldPasswordController,
              obscure: true,
              prefixIcon: Icons.lock_open_rounded,
              validator: (val) => val == null || val.isEmpty ? 'Required' : null,
            ),
            const SizedBox(height: 14),
            AppTextField(
              label: 'New Password',
              controller: _newPasswordController,
              obscure: true,
              prefixIcon: Icons.lock_outline_rounded,
              validator: (val) => val == null || val.isEmpty ? 'Required' : null,
            ),
            const SizedBox(height: 14),
            AppTextField(
              label: 'Confirm New Password',
              controller: _confirmPasswordController,
              obscure: true,
              prefixIcon: Icons.lock_rounded,
              validator: (val) => val == null || val.isEmpty ? 'Required' : null,
            ),
            const SizedBox(height: 20),
            SizedBox(
              width: double.infinity,
              child: AppButton(
                label: 'Update Password',
                onPressed: _updatePassword,
                loading: _isUpdatingPassword,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

// ─── Image Source Option Widget ───────────────────────────────────────────────
class _SourceOption extends StatelessWidget {
  final IconData icon;
  final String label;
  final Color color;
  final bool isDark;
  final VoidCallback onTap;

  const _SourceOption({
    required this.icon,
    required this.label,
    required this.color,
    required this.isDark,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Column(
        children: [
          Container(
            width: 70,
            height: 70,
            decoration: BoxDecoration(
              color: color.withValues(alpha: 0.10),
              borderRadius: BorderRadius.circular(20),
              border: Border.all(
                color: color.withValues(alpha: 0.20),
                width: 1.2,
              ),
            ),
            child: Icon(icon, color: color, size: 30),
          ),
          const SizedBox(height: 10),
          Text(
            label,
            style: TextStyle(
              color: isDark ? Colors.grey.shade300 : Colors.grey.shade700,
              fontSize: 13,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}
