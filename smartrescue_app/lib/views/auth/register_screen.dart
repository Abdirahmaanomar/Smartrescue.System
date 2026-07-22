import 'dart:math' as math;
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../providers/auth_provider.dart';
import '../../utils/helpers.dart';
import '../../utils/responsive.dart';
import '../../components/app_logo.dart';

class RegisterScreen extends StatefulWidget {
  const RegisterScreen({super.key});

  @override
  State<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends State<RegisterScreen>
    with TickerProviderStateMixin {
  final _formKey = GlobalKey<FormState>();
  final _nameController = TextEditingController();
  final _phoneController = TextEditingController();
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();

  String _gender = 'Male';
  DateTime? _selectedDate;
  bool _passwordObscured = true;

  // Focus state tracking
  bool _nameFocused = false;
  bool _phoneFocused = false;
  bool _emailFocused = false;
  bool _passwordFocused = false;

  final _nameFocus = FocusNode();
  final _phoneFocus = FocusNode();
  final _emailFocus = FocusNode();
  final _passwordFocus = FocusNode();

  late AnimationController _blobController;
  late AnimationController _cardController;
  late Animation<double> _cardFadeAnim;
  late Animation<Offset> _cardSlideAnim;

  @override
  void initState() {
    super.initState();
    _blobController = AnimationController(
      vsync: this,
      duration: const Duration(seconds: 10),
    )..repeat(reverse: true);

    _cardController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 700),
    );
    _cardFadeAnim =
        CurvedAnimation(parent: _cardController, curve: Curves.easeOut);
    _cardSlideAnim = Tween<Offset>(
      begin: const Offset(0, 0.05),
      end: Offset.zero,
    ).animate(
        CurvedAnimation(parent: _cardController, curve: Curves.easeOutCubic));
    _cardController.forward();

    _nameFocus.addListener(
        () => setState(() => _nameFocused = _nameFocus.hasFocus));
    _phoneFocus.addListener(
        () => setState(() => _phoneFocused = _phoneFocus.hasFocus));
    _emailFocus.addListener(
        () => setState(() => _emailFocused = _emailFocus.hasFocus));
    _passwordFocus.addListener(
        () => setState(() => _passwordFocused = _passwordFocus.hasFocus));
  }

  @override
  void dispose() {
    _nameController.dispose();
    _phoneController.dispose();
    _emailController.dispose();
    _passwordController.dispose();
    _nameFocus.dispose();
    _phoneFocus.dispose();
    _emailFocus.dispose();
    _passwordFocus.dispose();
    _blobController.dispose();
    _cardController.dispose();
    super.dispose();
  }

  Future<void> _pickDate() async {
    final date = await showDatePicker(
      context: context,
      initialDate: DateTime(2000),
      firstDate: DateTime(1900),
      lastDate: DateTime.now(),
      builder: (context, child) {
        return Theme(
          data: Theme.of(context).copyWith(
            colorScheme: const ColorScheme.light(
              primary: Color(0xFF2563EB),
              onPrimary: Colors.white,
              surface: Colors.white,
            ),
          ),
          child: child!,
        );
      },
    );
    if (date != null) setState(() => _selectedDate = date);
  }

  Future<void> _register() async {
    if (!_formKey.currentState!.validate()) return;
    if (_selectedDate == null) {
      AppHelpers.showSnack(context, 'Please select your birth date',
          isError: true);
      return;
    }
    FocusScope.of(context).unfocus();
    final auth = Provider.of<AuthProvider>(context, listen: false);
    final success = await auth.register(
      fullname: _nameController.text,
      phone: _phoneController.text,
      email: _emailController.text,
      password: _passwordController.text,
      role: 'user',
      gender: _gender,
      birthDate:
          "${_selectedDate!.year}-${_selectedDate!.month.toString().padLeft(2, '0')}-${_selectedDate!.day.toString().padLeft(2, '0')}",
    );
    if (!mounted) return;
    if (success) {
      Navigator.pushReplacementNamed(context, '/user');
    } else {
      AppHelpers.showSnack(
          context, auth.errorMessage ?? 'Registration failed',
          isError: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    final auth = Provider.of<AuthProvider>(context);
    final size = MediaQuery.of(context).size;

    return Scaffold(
      resizeToAvoidBottomInset: true,
      backgroundColor: const Color(0xFFF1F5F9),
      body: Stack(
        children: [
          _AnimatedBackground(controller: _blobController, size: size),
          SafeArea(
            child: Center(
              child: Responsive(context).wrapWidescreen(
                Column(
                  children: [
                    // ── ABSOLUTE TOP HEADER ──
                    Padding(
                      padding: const EdgeInsets.only(
                          left: 16, right: 16, top: 10, bottom: 4),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          GestureDetector(
                            onTap: () => Navigator.pop(context),
                            child: Container(
                              width: 38,
                              height: 38,
                              decoration: BoxDecoration(
                                color: Colors.white.withValues(alpha: 0.95),
                                borderRadius: BorderRadius.circular(12),
                                border: Border.all(
                                  color: const Color(0xFFCBD5E1),
                                  width: 1.2,
                                ),
                                boxShadow: [
                                  BoxShadow(
                                    color: Colors.black.withValues(alpha: 0.05),
                                    blurRadius: 8,
                                    offset: const Offset(0, 2),
                                  )
                                ],
                              ),
                              child: const Icon(
                                Icons.arrow_back_ios_new_rounded,
                                color: Color(0xFF2563EB),
                                size: 16,
                              ),
                            ),
                          ),
                          Container(
                            padding: const EdgeInsets.symmetric(
                                horizontal: 10, vertical: 6),
                            decoration: BoxDecoration(
                              color: Colors.white.withValues(alpha: 0.9),
                              borderRadius: BorderRadius.circular(14),
                              border: Border.all(
                                color: const Color(0xFFE2E8F0),
                                width: 1.2,
                              ),
                              boxShadow: [
                                BoxShadow(
                                  color: const Color(0xFF2563EB)
                                      .withValues(alpha: 0.08),
                                  blurRadius: 10,
                                  offset: const Offset(0, 3),
                                ),
                              ],
                            ),
                            child: Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                const Text(
                                  'SmartRescue',
                                  style: TextStyle(
                                    fontSize: 13,
                                    fontWeight: FontWeight.w800,
                                    color: Color(0xFF0F172A),
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),

                    // ── SCROLLABLE BODY ──
                    Expanded(
                      child: SingleChildScrollView(
                        physics: const BouncingScrollPhysics(),
                        padding: EdgeInsets.symmetric(
                          horizontal: Responsive(context).hPad,
                        ),
                        child: FadeTransition(
                          opacity: _cardFadeAnim,
                          child: SlideTransition(
                            position: _cardSlideAnim,
                            child: Form(
                              key: _formKey,
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.center,
                                children: [
                                  const SizedBox(height: 20),

                                  // ── Heading (Centered) ───────────────────
                                  const Text(
                                    'Create Account',
                                    textAlign: TextAlign.center,
                                    style: TextStyle(
                                      fontSize: 34,
                                      fontWeight: FontWeight.w900,
                                      letterSpacing: -1.0,
                                      color: Color(0xFF0F172A),
                                      height: 1.1,
                                    ),
                                  ),
                                  const SizedBox(height: 8),
                                  const Text(
                                    'Join SmartRescue emergency response platform',
                                    textAlign: TextAlign.center,
                                    style: TextStyle(
                                      fontSize: 14.5,
                                      color: Color(0xFF64748B),
                                      fontWeight: FontWeight.w500,
                                    ),
                                  ),

                                  const SizedBox(height: 26),

                                  // ── Form Glass Card ──────────────────────
                                  Container(
                                    decoration: BoxDecoration(
                                      color:
                                          Colors.white.withValues(alpha: 0.94),
                                      borderRadius: BorderRadius.circular(28),
                                      border: Border.all(
                                        color: Colors.white,
                                        width: 2,
                                      ),
                                      boxShadow: [
                                        BoxShadow(
                                          color: const Color(0xFF1E3A8A)
                                              .withValues(alpha: 0.08),
                                          blurRadius: 36,
                                          offset: const Offset(0, 16),
                                          spreadRadius: 2,
                                        ),
                                        BoxShadow(
                                          color: Colors.black
                                              .withValues(alpha: 0.04),
                                          blurRadius: 12,
                                          offset: const Offset(0, 4),
                                        ),
                                      ],
                                    ),
                                    padding: const EdgeInsets.all(24),
                                    child: Column(
                                      crossAxisAlignment:
                                          CrossAxisAlignment.stretch,
                                      children: [
                                        // Full Name
                                        _buildInputField(
                                          controller: _nameController,
                                          focusNode: _nameFocus,
                                          isFocused: _nameFocused,
                                          label: 'Full Name',
                                          hint: 'e.g. Ali Ahmed Mohamed',
                                          icon: Icons.person_outline_rounded,
                                          validator: (val) =>
                                              val == null || val.isEmpty
                                                  ? 'Full name is required'
                                                  : null,
                                        ),
                                        const SizedBox(height: 14),

                                        // Phone
                                        _buildInputField(
                                          controller: _phoneController,
                                          focusNode: _phoneFocus,
                                          isFocused: _phoneFocused,
                                          label: 'Phone Number',
                                          hint: '61XXXXXXX',
                                          icon: Icons.phone_android_rounded,
                                          keyboardType: TextInputType.phone,
                                          validator: (val) =>
                                              val == null || val.isEmpty
                                                  ? 'Phone number is required'
                                                  : null,
                                        ),
                                        const SizedBox(height: 14),

                                        // Email
                                        _buildInputField(
                                          controller: _emailController,
                                          focusNode: _emailFocus,
                                          isFocused: _emailFocused,
                                          label: 'Email Address (Optional)',
                                          hint: 'email@example.com',
                                          icon: Icons.alternate_email_rounded,
                                          keyboardType:
                                              TextInputType.emailAddress,
                                        ),
                                        const SizedBox(height: 14),

                                        // Birth Date + Gender row
                                        Row(
                                          children: [
                                            Expanded(
                                                child: _buildDatePicker()),
                                            const SizedBox(width: 12),
                                            Expanded(
                                                child: _buildGenderDropdown()),
                                          ],
                                        ),
                                        const SizedBox(height: 14),

                                        // Password
                                        _buildInputField(
                                          controller: _passwordController,
                                          focusNode: _passwordFocus,
                                          isFocused: _passwordFocused,
                                          label: 'Password',
                                          hint: '••••••••',
                                          icon: Icons.lock_outline_rounded,
                                          obscure: true,
                                          validator: (val) => val != null &&
                                                  val.length < 6
                                              ? 'Min 6 characters'
                                              : null,
                                        ),
                                        const SizedBox(height: 24),

                                        // Register button
                                        _buildRegisterButton(auth),
                                      ],
                                    ),
                                  ),

                                  const SizedBox(height: 24),

                                  // ── Login link ───────────────────────────
                                  _buildLoginLink(),

                                  const SizedBox(height: 24),
                                ],
                              ),
                            ),
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
                maxWidth: 460,
                alignment: Alignment.center,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildInputField({
    required TextEditingController controller,
    required FocusNode focusNode,
    required bool isFocused,
    required String label,
    String? hint,
    required IconData icon,
    bool obscure = false,
    TextInputType keyboardType = TextInputType.text,
    String? Function(String?)? validator,
  }) {
    const primaryBlue = Color(0xFF2563EB);
    return AnimatedContainer(
      duration: const Duration(milliseconds: 200),
      decoration: BoxDecoration(
        color: isFocused ? const Color(0xFFF0F6FF) : const Color(0xFFF8FAFC),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(
          color: isFocused ? primaryBlue : const Color(0xFFCBD5E1),
          width: isFocused ? 2.0 : 1.2,
        ),
        boxShadow: isFocused
            ? [
                BoxShadow(
                  color: primaryBlue.withValues(alpha: 0.15),
                  blurRadius: 14,
                  offset: const Offset(0, 4),
                )
              ]
            : [],
      ),
      child: TextFormField(
        controller: controller,
        focusNode: focusNode,
        obscureText: obscure ? _passwordObscured : false,
        keyboardType: keyboardType,
        validator: validator,
        style: const TextStyle(
          fontSize: 15,
          fontWeight: FontWeight.w700,
          color: Color(0xFF0F172A),
        ),
        decoration: InputDecoration(
          labelText: label,
          hintText: hint,
          labelStyle: TextStyle(
            color: isFocused ? primaryBlue : const Color(0xFF64748B),
            fontSize: 14,
            fontWeight: FontWeight.w600,
          ),
          hintStyle: const TextStyle(
            color: Color(0xFF94A3B8),
            fontSize: 13.5,
            fontWeight: FontWeight.w400,
          ),
          prefixIcon: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
            child: Container(
              padding: const EdgeInsets.all(6),
              decoration: BoxDecoration(
                color: isFocused
                    ? primaryBlue.withValues(alpha: 0.12)
                    : const Color(0xFFE2E8F0),
                borderRadius: BorderRadius.circular(10),
              ),
              child: Icon(
                icon,
                color: isFocused ? primaryBlue : const Color(0xFF64748B),
                size: 18,
              ),
            ),
          ),
          suffixIcon: obscure
              ? IconButton(
                  icon: Icon(
                    _passwordObscured
                        ? Icons.visibility_off_outlined
                        : Icons.visibility_outlined,
                    color: isFocused ? primaryBlue : const Color(0xFF64748B),
                    size: 20,
                  ),
                  onPressed: () =>
                      setState(() => _passwordObscured = !_passwordObscured),
                )
              : null,
          border: InputBorder.none,
          enabledBorder: InputBorder.none,
          focusedBorder: InputBorder.none,
          errorBorder: InputBorder.none,
          focusedErrorBorder: InputBorder.none,
          contentPadding:
              const EdgeInsets.symmetric(horizontal: 4, vertical: 16),
          errorStyle: const TextStyle(
            fontSize: 11.5,
            color: Color(0xFFEF4444),
            fontWeight: FontWeight.w600,
          ),
        ),
      ),
    );
  }

  Widget _buildDatePicker() {
    final hasBirthDate = _selectedDate != null;
    return GestureDetector(
      onTap: _pickDate,
      child: Container(
        height: 54,
        padding: const EdgeInsets.symmetric(horizontal: 12),
        decoration: BoxDecoration(
          color: hasBirthDate
              ? const Color(0xFFF0F6FF)
              : const Color(0xFFF8FAFC),
          borderRadius: BorderRadius.circular(18),
          border: Border.all(
            color: hasBirthDate
                ? const Color(0xFF2563EB)
                : const Color(0xFFCBD5E1),
            width: hasBirthDate ? 1.8 : 1.2,
          ),
        ),
        child: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(5),
              decoration: BoxDecoration(
                color: hasBirthDate
                    ? const Color(0xFF2563EB).withValues(alpha: 0.12)
                    : const Color(0xFFE2E8F0),
                borderRadius: BorderRadius.circular(8),
              ),
              child: Icon(
                Icons.calendar_month_rounded,
                color: hasBirthDate
                    ? const Color(0xFF2563EB)
                    : const Color(0xFF64748B),
                size: 16,
              ),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                hasBirthDate
                    ? '${_selectedDate!.year}-${_selectedDate!.month.toString().padLeft(2, '0')}-${_selectedDate!.day.toString().padLeft(2, '0')}'
                    : 'Birth Date',
                style: TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w700,
                  color: hasBirthDate
                      ? const Color(0xFF0F172A)
                      : const Color(0xFF64748B),
                ),
                overflow: TextOverflow.ellipsis,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildGenderDropdown() {
    return Container(
      height: 54,
      padding: const EdgeInsets.symmetric(horizontal: 12),
      decoration: BoxDecoration(
        color: const Color(0xFFF8FAFC),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: const Color(0xFFCBD5E1), width: 1.2),
      ),
      child: DropdownButtonHideUnderline(
        child: DropdownButton<String>(
          value: _gender,
          isExpanded: true,
          icon: const Icon(Icons.keyboard_arrow_down_rounded,
              color: Color(0xFF64748B), size: 18),
          style: const TextStyle(
            color: Color(0xFF0F172A),
            fontWeight: FontWeight.w700,
            fontSize: 13,
          ),
          items: ['Male', 'Female'].map((val) {
            return DropdownMenuItem<String>(
              value: val,
              child: Row(
                children: [
                  Icon(
                    val == 'Male' ? Icons.male_rounded : Icons.female_rounded,
                    color: const Color(0xFF2563EB),
                    size: 16,
                  ),
                  const SizedBox(width: 6),
                  Text(val),
                ],
              ),
            );
          }).toList(),
          onChanged: (val) {
            if (val != null) setState(() => _gender = val);
          },
        ),
      ),
    );
  }

  Widget _buildRegisterButton(AuthProvider auth) {
    final isLoading = auth.status == AuthStatus.loading;
    return GestureDetector(
      onTap: isLoading ? null : _register,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 180),
        height: 54,
        decoration: BoxDecoration(
          gradient: isLoading
              ? const LinearGradient(
                  colors: [Color(0xFF93C5FD), Color(0xFF60A5FA)])
              : const LinearGradient(
                  colors: [Color(0xFF2563EB), Color(0xFF1E40AF)],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
          borderRadius: BorderRadius.circular(18),
          boxShadow: isLoading
              ? []
              : [
                  BoxShadow(
                    color: const Color(0xFF2563EB).withValues(alpha: 0.4),
                    blurRadius: 22,
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
              : const Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Text(
                      'Create Account',
                      style: TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.w800,
                        fontSize: 16,
                        letterSpacing: 0.3,
                      ),
                    ),
                    SizedBox(width: 10),
                    Icon(Icons.arrow_forward_rounded,
                        color: Colors.white, size: 20),
                  ],
                ),
        ),
      ),
    );
  }

  Widget _buildLoginLink() {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.85),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(
          color: const Color(0xFFE2E8F0),
          width: 1.2,
        ),
      ),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          const Text(
            'Already have an account?',
            style: TextStyle(
              color: Color(0xFF475569),
              fontSize: 13.5,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(width: 8),
          GestureDetector(
            onTap: () => Navigator.pop(context),
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
              decoration: BoxDecoration(
                color: const Color(0xFF2563EB).withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(10),
              ),
              child: const Text(
                'Login',
                style: TextStyle(
                  color: Color(0xFF2563EB),
                  fontWeight: FontWeight.w900,
                  fontSize: 13,
                  letterSpacing: 0.2,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

// ─── Animated Background ────────────────────────────────────────────────────
class _AnimatedBackground extends StatelessWidget {
  final AnimationController controller;
  final Size size;
  const _AnimatedBackground({required this.controller, required this.size});

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
    final bg = Paint()
      ..shader = const LinearGradient(
        colors: [Color(0xFFF1F5F9), Color(0xFFE2E8F0), Color(0xFFEEF2FF)],
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
      ).createShader(Rect.fromLTWH(0, 0, size.width, size.height));
    canvas.drawRect(Rect.fromLTWH(0, 0, size.width, size.height), bg);

    _blob(
        canvas,
        Offset(size.width * 0.85 + math.sin(t * math.pi * 2) * 20,
            size.height * 0.10 + math.cos(t * math.pi * 2) * 16),
        size.width * 0.55,
        const Color(0xFF3B82F6).withValues(alpha: 0.11));
    _blob(
        canvas,
        Offset(size.width * 0.1 + math.cos(t * math.pi * 2) * 14,
            size.height * 0.85 + math.sin(t * math.pi * 2) * 18),
        size.width * 0.48,
        const Color(0xFF1D4ED8).withValues(alpha: 0.08));
  }

  void _blob(Canvas canvas, Offset c, double r, Color color) {
    canvas.drawCircle(c, r,
        Paint()
          ..color = color
          ..maskFilter = const MaskFilter.blur(BlurStyle.normal, 60));
  }

  @override
  bool shouldRepaint(_BlobPainter old) => old.t != t;
}
