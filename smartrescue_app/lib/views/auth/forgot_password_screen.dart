import 'dart:math' as math;
import 'package:flutter/material.dart';
import '../../services/api_service.dart';

class ForgotPasswordScreen extends StatefulWidget {
  const ForgotPasswordScreen({super.key});

  @override
  State<ForgotPasswordScreen> createState() => _ForgotPasswordScreenState();
}

class _ForgotPasswordScreenState extends State<ForgotPasswordScreen>
    with TickerProviderStateMixin {
  // Step 1 = enter email/phone, Step 2 = enter new password
  int _step = 1;

  final _identifierController = TextEditingController();
  final _newPassController = TextEditingController();
  final _confirmPassController = TextEditingController();

  final _identifierFocus = FocusNode();
  final _newPassFocus = FocusNode();
  final _confirmPassFocus = FocusNode();

  bool _identifierFocused = false;
  bool _newPassFocused = false;
  bool _confirmPassFocused = false;
  bool _newPassObscured = true;
  bool _confirmPassObscured = true;

  bool _isLoading = false;
  String? _errorMessage;
  String? _successMessage;

  late AnimationController _blobController;
  late AnimationController _cardController;
  late Animation<double> _cardFadeAnim;
  late Animation<Offset> _cardSlideAnim;

  @override
  void initState() {
    super.initState();
    _blobController = AnimationController(
      vsync: this,
      duration: const Duration(seconds: 8),
    )..repeat(reverse: true);

    _cardController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 600),
    );
    _cardFadeAnim =
        CurvedAnimation(parent: _cardController, curve: Curves.easeOut);
    _cardSlideAnim = Tween<Offset>(
      begin: const Offset(0, 0.07),
      end: Offset.zero,
    ).animate(CurvedAnimation(parent: _cardController, curve: Curves.easeOutCubic));
    _cardController.forward();

    _identifierFocus.addListener(
        () => setState(() => _identifierFocused = _identifierFocus.hasFocus));
    _newPassFocus.addListener(
        () => setState(() => _newPassFocused = _newPassFocus.hasFocus));
    _confirmPassFocus.addListener(
        () => setState(() => _confirmPassFocused = _confirmPassFocus.hasFocus));
  }

  @override
  void dispose() {
    _identifierController.dispose();
    _newPassController.dispose();
    _confirmPassController.dispose();
    _identifierFocus.dispose();
    _newPassFocus.dispose();
    _confirmPassFocus.dispose();
    _blobController.dispose();
    _cardController.dispose();
    super.dispose();
  }

  // ─── Step 1: Verify account ──────────────────────────────────────────────────
  Future<void> _verifyAccount() async {
    final identifier = _identifierController.text.trim();
    if (identifier.isEmpty) {
      setState(() => _errorMessage = 'Please enter your email or phone number.');
      return;
    }
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    final result = await ApiService.forgotPasswordVerify(identifier);

    if (!mounted) return;
    setState(() => _isLoading = false);

    if (result['status'] == 'success') {
      // Animate to step 2
      await _cardController.reverse();
      setState(() {
        _step = 2;
        _errorMessage = null;
        _successMessage = result['message'] ?? 'Account found! Set your new password.';
      });
      _cardController.forward(from: 0);
    } else {
      setState(() => _errorMessage = result['message'] ?? 'Account not found.');
    }
  }

  // ─── Step 2: Reset password ──────────────────────────────────────────────────
  Future<void> _resetPassword() async {
    final newPass = _newPassController.text;
    final confirmPass = _confirmPassController.text;

    if (newPass.isEmpty || confirmPass.isEmpty) {
      setState(() => _errorMessage = 'Please fill in both password fields.');
      return;
    }
    if (newPass.length < 6) {
      setState(() => _errorMessage = 'Password must be at least 6 characters.');
      return;
    }
    if (newPass != confirmPass) {
      setState(() => _errorMessage = 'Passwords do not match.');
      return;
    }

    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    final result = await ApiService.forgotPasswordReset(
      identifier: _identifierController.text.trim(),
      newPassword: newPass,
    );

    if (!mounted) return;
    setState(() => _isLoading = false);

    if (result['status'] == 'success') {
      setState(() {
        _successMessage = 'Password reset successfully!';
        _errorMessage = null;
      });
      // Go back to login after delay
      await Future.delayed(const Duration(seconds: 2));
      if (mounted) Navigator.pop(context);
    } else {
      setState(() => _errorMessage = result['message'] ?? 'Failed to reset password.');
    }
  }

  @override
  Widget build(BuildContext context) {
    final size = MediaQuery.of(context).size;

    return Scaffold(
      resizeToAvoidBottomInset: true,
      backgroundColor: const Color(0xFFEFF6FF),
      body: Stack(
        children: [
          _AnimatedBlobBackground(controller: _blobController, size: size),
          SafeArea(
            child: Column(
              children: [
                // Back button top-left
                Align(
                  alignment: Alignment.centerLeft,
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                    child: _buildBackButton(),
                  ),
                ),
                Expanded(
                  child: Center(
                    child: SingleChildScrollView(
                      physics: const ClampingScrollPhysics(),
                      padding: const EdgeInsets.symmetric(horizontal: 24),
                      child: FadeTransition(
                        opacity: _cardFadeAnim,
                        child: SlideTransition(
                          position: _cardSlideAnim,
                          child: ConstrainedBox(
                            constraints: const BoxConstraints(maxWidth: 460),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.stretch,
                              children: [
                                // ── Icon ──────────────────────────────────
                                Center(child: _buildIconBadge()),
                                const SizedBox(height: 24),

                                // ── Title ─────────────────────────────────
                                Text(
                                  _step == 1 ? 'Forgot Password?' : 'Set New Password',
                                  textAlign: TextAlign.center,
                                  style: const TextStyle(
                                    fontSize: 28,
                                    fontWeight: FontWeight.w900,
                                    letterSpacing: -0.6,
                                    color: Color(0xFF0F172A),
                                    height: 1.15,
                                  ),
                                ),
                                const SizedBox(height: 8),
                                Text(
                                  _step == 1
                                      ? 'Enter your email or phone number and we\'ll help you reset your password.'
                                      : 'Choose a strong new password for your account.',
                                  textAlign: TextAlign.center,
                                  style: TextStyle(
                                    fontSize: 14,
                                    color: const Color(0xFF64748B).withValues(alpha: 0.9),
                                    fontWeight: FontWeight.w500,
                                    height: 1.5,
                                  ),
                                ),

                                const SizedBox(height: 30),

                                // ── Step indicator ────────────────────────
                                _buildStepIndicator(),

                                const SizedBox(height: 24),

                                // ── Card ──────────────────────────────────
                                _buildCard(),

                                const SizedBox(height: 32),
                              ],
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildBackButton() {
    return GestureDetector(
      onTap: () {
        if (_step == 2) {
          _cardController.reverse().then((_) {
            setState(() {
              _step = 1;
              _errorMessage = null;
              _successMessage = null;
            });
            _cardController.forward(from: 0);
          });
        } else {
          Navigator.pop(context);
        }
      },
      child: Container(
        width: 40,
        height: 40,
        decoration: BoxDecoration(
          color: Colors.white.withValues(alpha: 0.85),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: const Color(0xFFE2E8F0), width: 1.2),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.05),
              blurRadius: 8,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        child: const Icon(
          Icons.arrow_back_rounded,
          color: Color(0xFF334155),
          size: 20,
        ),
      ),
    );
  }

  Widget _buildIconBadge() {
    return Container(
      width: 72,
      height: 72,
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFF3B82F6), Color(0xFF1D4ED8)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(22),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF2563EB).withValues(alpha: 0.35),
            blurRadius: 24,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: Icon(
        _step == 1 ? Icons.lock_reset_rounded : Icons.lock_outline_rounded,
        color: Colors.white,
        size: 34,
      ),
    );
  }

  Widget _buildStepIndicator() {
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        _stepDot(1, 'Verify'),
        Container(
          width: 40,
          height: 2,
          margin: const EdgeInsets.symmetric(horizontal: 6),
          decoration: BoxDecoration(
            color: _step >= 2
                ? const Color(0xFF2563EB)
                : const Color(0xFFE2E8F0),
            borderRadius: BorderRadius.circular(2),
          ),
        ),
        _stepDot(2, 'Reset'),
      ],
    );
  }

  Widget _stepDot(int step, String label) {
    final isActive = _step == step;
    final isDone = _step > step;
    return Column(
      children: [
        AnimatedContainer(
          duration: const Duration(milliseconds: 300),
          width: 30,
          height: 30,
          decoration: BoxDecoration(
            color: isDone || isActive
                ? const Color(0xFF2563EB)
                : const Color(0xFFE2E8F0),
            shape: BoxShape.circle,
          ),
          child: Center(
            child: isDone
                ? const Icon(Icons.check_rounded, color: Colors.white, size: 16)
                : Text(
                    '$step',
                    style: TextStyle(
                      color: isActive ? Colors.white : const Color(0xFF94A3B8),
                      fontWeight: FontWeight.w700,
                      fontSize: 13,
                    ),
                  ),
          ),
        ),
        const SizedBox(height: 4),
        Text(
          label,
          style: TextStyle(
            fontSize: 11,
            fontWeight: FontWeight.w600,
            color: isActive || isDone
                ? const Color(0xFF2563EB)
                : const Color(0xFF94A3B8),
          ),
        ),
      ],
    );
  }

  Widget _buildCard() {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.88),
        borderRadius: BorderRadius.circular(28),
        border: Border.all(
          color: Colors.white.withValues(alpha: 0.9),
          width: 1.5,
        ),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF2563EB).withValues(alpha: 0.09),
            blurRadius: 40,
            offset: const Offset(0, 16),
          ),
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.05),
            blurRadius: 10,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 28),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          // Success message
          if (_successMessage != null) ...[
            _buildBanner(
              message: _successMessage!,
              isError: false,
            ),
            const SizedBox(height: 16),
          ],
          // Error message
          if (_errorMessage != null) ...[
            _buildBanner(
              message: _errorMessage!,
              isError: true,
            ),
            const SizedBox(height: 16),
          ],

          if (_step == 1) ...[
            _buildField(
              controller: _identifierController,
              focusNode: _identifierFocus,
              isFocused: _identifierFocused,
              label: 'Email or Phone Number',
              hint: 'Enter your email or phone',
              icon: Icons.person_outline_rounded,
              keyboardType: TextInputType.emailAddress,
            ),
            const SizedBox(height: 20),
            _buildActionButton(
              label: 'Verify Account',
              icon: Icons.search_rounded,
              onTap: _isLoading ? null : _verifyAccount,
              isLoading: _isLoading,
            ),
          ] else ...[
            _buildField(
              controller: _newPassController,
              focusNode: _newPassFocus,
              isFocused: _newPassFocused,
              label: 'New Password',
              hint: 'Minimum 6 characters',
              icon: Icons.lock_outline_rounded,
              obscure: true,
              obscured: _newPassObscured,
              onToggleObscure: () =>
                  setState(() => _newPassObscured = !_newPassObscured),
            ),
            const SizedBox(height: 14),
            _buildField(
              controller: _confirmPassController,
              focusNode: _confirmPassFocus,
              isFocused: _confirmPassFocused,
              label: 'Confirm New Password',
              hint: 'Re-enter your new password',
              icon: Icons.lock_outline_rounded,
              obscure: true,
              obscured: _confirmPassObscured,
              onToggleObscure: () =>
                  setState(() => _confirmPassObscured = !_confirmPassObscured),
            ),
            const SizedBox(height: 20),
            _buildActionButton(
              label: 'Reset Password',
              icon: Icons.check_circle_outline_rounded,
              onTap: _isLoading ? null : _resetPassword,
              isLoading: _isLoading,
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildBanner({required String message, required bool isError}) {
    return AnimatedContainer(
      duration: const Duration(milliseconds: 250),
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(
        color: isError
            ? const Color(0xFFFEF2F2)
            : const Color(0xFFF0FDF4),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(
          color: isError
              ? const Color(0xFFFECACA)
              : const Color(0xFFBBF7D0),
          width: 1.2,
        ),
      ),
      child: Row(
        children: [
          Icon(
            isError ? Icons.error_outline_rounded : Icons.check_circle_outline_rounded,
            color: isError ? const Color(0xFFEF4444) : const Color(0xFF22C55E),
            size: 18,
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              message,
              style: TextStyle(
                fontSize: 13,
                color: isError ? const Color(0xFFDC2626) : const Color(0xFF16A34A),
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildField({
    required TextEditingController controller,
    required FocusNode focusNode,
    required bool isFocused,
    required String label,
    String? hint,
    required IconData icon,
    bool obscure = false,
    bool obscured = true,
    VoidCallback? onToggleObscure,
    TextInputType keyboardType = TextInputType.text,
  }) {
    const primaryBlue = Color(0xFF2563EB);
    return AnimatedContainer(
      duration: const Duration(milliseconds: 200),
      decoration: BoxDecoration(
        color: isFocused ? const Color(0xFFEEF4FF) : const Color(0xFFF8FAFC),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: isFocused
              ? primaryBlue.withValues(alpha: 0.7)
              : const Color(0xFFE2E8F0),
          width: isFocused ? 1.8 : 1.2,
        ),
        boxShadow: isFocused
            ? [
                BoxShadow(
                  color: primaryBlue.withValues(alpha: 0.12),
                  blurRadius: 14,
                  offset: const Offset(0, 4),
                )
              ]
            : [
                BoxShadow(
                  color: Colors.black.withValues(alpha: 0.03),
                  blurRadius: 6,
                  offset: const Offset(0, 2),
                )
              ],
      ),
      child: TextField(
        controller: controller,
        focusNode: focusNode,
        obscureText: obscure ? obscured : false,
        keyboardType: keyboardType,
        style: const TextStyle(
          fontSize: 15,
          fontWeight: FontWeight.w600,
          color: Color(0xFF0F172A),
        ),
        decoration: InputDecoration(
          labelText: label,
          hintText: hint,
          labelStyle: TextStyle(
            color: isFocused ? primaryBlue : const Color(0xFF94A3B8),
            fontSize: 14,
            fontWeight: FontWeight.w500,
          ),
          hintStyle: const TextStyle(
            color: Color(0xFFCBD5E1),
            fontSize: 13,
          ),
          prefixIcon: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
            child: Icon(
              icon,
              color: isFocused ? primaryBlue : const Color(0xFF94A3B8),
              size: 20,
            ),
          ),
          suffixIcon: obscure && onToggleObscure != null
              ? IconButton(
                  icon: Icon(
                    obscured
                        ? Icons.visibility_off_outlined
                        : Icons.visibility_outlined,
                    color: isFocused ? primaryBlue : const Color(0xFF94A3B8),
                    size: 20,
                  ),
                  onPressed: onToggleObscure,
                )
              : null,
          border: InputBorder.none,
          enabledBorder: InputBorder.none,
          focusedBorder: InputBorder.none,
          contentPadding:
              const EdgeInsets.symmetric(horizontal: 0, vertical: 16),
        ),
      ),
    );
  }

  Widget _buildActionButton({
    required String label,
    required IconData icon,
    required VoidCallback? onTap,
    required bool isLoading,
  }) {
    return GestureDetector(
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 180),
        height: 54,
        decoration: BoxDecoration(
          gradient: isLoading
              ? const LinearGradient(
                  colors: [Color(0xFF93C5FD), Color(0xFF60A5FA)])
              : const LinearGradient(
                  colors: [Color(0xFF3B82F6), Color(0xFF1D4ED8)],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
          borderRadius: BorderRadius.circular(16),
          boxShadow: isLoading
              ? []
              : [
                  BoxShadow(
                    color: const Color(0xFF2563EB).withValues(alpha: 0.35),
                    blurRadius: 20,
                    offset: const Offset(0, 8),
                  ),
                ],
        ),
        child: Center(
          child: isLoading
              ? const SizedBox(
                  width: 24,
                  height: 24,
                  child: CircularProgressIndicator(
                      color: Colors.white, strokeWidth: 2.5))
              : Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Text(
                      label,
                      style: const TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.w700,
                        fontSize: 16,
                        letterSpacing: 0.2,
                      ),
                    ),
                    const SizedBox(width: 8),
                    Icon(icon, color: Colors.white, size: 20),
                  ],
                ),
        ),
      ),
    );
  }
}

// ─── Animated Background ─────────────────────────────────────────────────────
class _AnimatedBlobBackground extends StatelessWidget {
  final AnimationController controller;
  final Size size;
  const _AnimatedBlobBackground(
      {required this.controller, required this.size});

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: controller,
      builder: (_, __) =>
          CustomPaint(size: size, painter: _BlobPainter(controller.value)),
    );
  }
}

class _BlobPainter extends CustomPainter {
  final double t;
  _BlobPainter(this.t);

  @override
  void paint(Canvas canvas, Size size) {
    final bgPaint = Paint()
      ..shader = const LinearGradient(
        colors: [Color(0xFFEFF6FF), Color(0xFFDBEAFE), Color(0xFFF0F4FF)],
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
      ).createShader(Rect.fromLTWH(0, 0, size.width, size.height));
    canvas.drawRect(Rect.fromLTWH(0, 0, size.width, size.height), bgPaint);

    _blob(canvas,
        Offset(size.width * 0.85 + math.sin(t * math.pi * 2) * 18,
            size.height * 0.12 + math.cos(t * math.pi * 2) * 14),
        size.width * 0.5,
        const Color(0xFF3B82F6).withValues(alpha: 0.08));
    _blob(canvas,
        Offset(size.width * 0.12 + math.cos(t * math.pi * 2) * 12,
            size.height * 0.8 + math.sin(t * math.pi * 2) * 14),
        size.width * 0.4,
        const Color(0xFF1D4ED8).withValues(alpha: 0.06));
  }

  void _blob(Canvas canvas, Offset center, double radius, Color color) {
    canvas.drawCircle(
      center,
      radius,
      Paint()
        ..color = color
        ..maskFilter = const MaskFilter.blur(BlurStyle.normal, 55),
    );
  }

  @override
  bool shouldRepaint(_BlobPainter old) => old.t != t;
}
