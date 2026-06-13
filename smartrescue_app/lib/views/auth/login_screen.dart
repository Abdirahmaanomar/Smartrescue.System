import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../providers/auth_provider.dart';
import '../../components/app_button.dart';
import '../../components/app_text_field.dart';
import '../../utils/helpers.dart';
import '../../utils/responsive.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _identifierController = TextEditingController();
  final _passwordController = TextEditingController();

  @override
  void dispose() {
    _identifierController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _login() async {
    if (!_formKey.currentState!.validate()) return;

    FocusScope.of(context).unfocus();

    final auth = Provider.of<AuthProvider>(context, listen: false);
    final success = await auth.login(_identifierController.text, _passwordController.text);

    if (!mounted) return;

    if (success) {
       if (auth.user?.role == 'user') {
         Navigator.pushReplacementNamed(context, '/user');
       } else {
         auth.logout();
         AppHelpers.showSnack(context, 'Only Citizens can use this app. Drivers/Admins use the web portal.', isError: true);
       }
    } else {
      AppHelpers.showSnack(context, auth.errorMessage ?? 'Login failed', isError: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    final auth = Provider.of<AuthProvider>(context);

    return Scaffold(
      backgroundColor: Colors.white,
      body: SafeArea(
        child: Responsive(context).wrapWidescreen(
          SingleChildScrollView(
            padding: EdgeInsets.all(Responsive(context).hPad),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                SizedBox(height: Responsive(context).hp(0.04)),
                Container(
                  width: 70,
                  height: 70,
                  decoration: BoxDecoration(
                    gradient: const LinearGradient(
                      colors: [Color(0xFF2563EB), Color(0xFF0A58CA)],
                    ),
                    borderRadius: BorderRadius.circular(20),
                    boxShadow: [
                      BoxShadow(
                        color: const Color(0xFF2563EB).withValues(alpha: 0.3),
                        blurRadius: 20,
                        offset: const Offset(0, 10),
                      ),
                    ],
                  ),
                  child: const Icon(Icons.medical_services_rounded, color: Colors.white, size: 36),
                ),
                const SizedBox(height: 30),
                const Text(
                  'Welcome Back',
                  style: TextStyle(
                    fontSize: 32,
                    fontWeight: FontWeight.w900,
                    letterSpacing: -1,
                    color: Color(0xFF0F172A),
                  ),
                ),
                const SizedBox(height: 8),
                const Text(
                  'Login to your account to access the system.',
                  style: TextStyle(
                    fontSize: 16,
                    color: Color(0xFF64748B),
                    fontWeight: FontWeight.w500,
                  ),
                ),
                const SizedBox(height: 40),
                Form(
                  key: _formKey,
                  child: Column(
                    children: [
                      AppTextField(
                        label: 'Phone or Email',
                        hint: 'e.g., 61XXXXXXX or email@example.com',
                        controller: _identifierController,
                        keyboardType: TextInputType.emailAddress,
                        prefixIcon: Icons.person_rounded,
                        validator: (val) => val == null || val.isEmpty ? 'Required' : null,
                      ),
                      const SizedBox(height: 20),
                      AppTextField(
                        label: 'Password',
                        controller: _passwordController,
                        obscure: true,
                        prefixIcon: Icons.lock_rounded,
                        validator: (val) => val == null || val.isEmpty ? 'Required' : null,
                      ),
                      const SizedBox(height: 30),
                      AppButton(
                        label: 'LOGIN',
                        onPressed: _login,
                        loading: auth.status == AuthStatus.loading,
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 30),
                Wrap(
                  alignment: WrapAlignment.center,
                  crossAxisAlignment: WrapCrossAlignment.center,
                  children: [
                    const Text(
                      "Don't have an account? ",
                      style: TextStyle(color: Color(0xFF64748B), fontWeight: FontWeight.w500),
                    ),
                    TextButton(
                      onPressed: () => Navigator.pushNamed(context, '/register'),
                      child: const Text(
                        'Register Here',
                        style: TextStyle(
                          color: Color(0xFF2563EB),
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
          maxWidth: 500,
        ),
      ),
    );
  }
}
