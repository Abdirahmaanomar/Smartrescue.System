import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../providers/auth_provider.dart';
import '../../components/app_button.dart';
import '../../components/app_text_field.dart';
import '../../utils/helpers.dart';
import '../../utils/responsive.dart';

class RegisterScreen extends StatefulWidget {
  const RegisterScreen({super.key});

  @override
  State<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends State<RegisterScreen> {
  final _formKey = GlobalKey<FormState>();
  final _nameController = TextEditingController();
  final _phoneController = TextEditingController();
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  
  String _gender = 'Male';
  String _role = 'user';
  DateTime? _selectedDate;

  @override
  void dispose() {
    _nameController.dispose();
    _phoneController.dispose();
    _emailController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _pickDate() async {
    final date = await showDatePicker(
      context: context,
      initialDate: DateTime(2000),
      firstDate: DateTime(1900),
      lastDate: DateTime.now(),
    );
    if (date != null) {
      setState(() => _selectedDate = date);
    }
  }

  Future<void> _register() async {
    if (!_formKey.currentState!.validate()) return;
    if (_selectedDate == null) {
      AppHelpers.showSnack(context, 'Please select your birth date', isError: true);
      return;
    }

    FocusScope.of(context).unfocus();

    final auth = Provider.of<AuthProvider>(context, listen: false);
    final success = await auth.register(
      fullname: _nameController.text,
      phone: _phoneController.text,
      email: _emailController.text,
      password: _passwordController.text,
      role: _role,
      gender: _gender,
      birthDate: "${_selectedDate!.year}-${_selectedDate!.month.toString().padLeft(2, '0')}-${_selectedDate!.day.toString().padLeft(2, '0')}",
    );

    if (!mounted) return;

    if (success) {
      if (_role == 'user') {
        Navigator.pushReplacementNamed(context, '/user');
      } else {
        auth.logout();
        AppHelpers.showSnack(context, 'Registered successfully. Please use the web portal for $_role access.');
        Navigator.pushReplacementNamed(context, '/login');
      }
    } else {
      AppHelpers.showSnack(context, auth.errorMessage ?? 'Registration failed', isError: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    final auth = Provider.of<AuthProvider>(context);
    final scheme = Theme.of(context).colorScheme;

    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded),
          onPressed: () => Navigator.pop(context),
        ),
      ),
      body: SafeArea(
        child: Responsive(context).wrapWidescreen(
          SingleChildScrollView(
            padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 10),
            child: Form(
              key: _formKey,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Text(
                    'Create Account',
                    style: TextStyle(
                      fontSize: 32,
                      fontWeight: FontWeight.w900,
                      letterSpacing: -1,
                      color: Color(0xFF0F172A),
                    ),
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'Join the most advanced emergency network.',
                    style: TextStyle(
                      fontSize: 16,
                      color: Color(0xFF64748B),
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                  const SizedBox(height: 30),
                  
                  AppTextField(
                    label: 'Full Name',
                    controller: _nameController,
                    prefixIcon: Icons.person_rounded,
                    validator: (val) => val == null || val.isEmpty ? 'Required' : null,
                  ),
                  const SizedBox(height: 16),
                  
                  AppTextField(
                    label: 'Phone Number',
                    controller: _phoneController,
                    keyboardType: TextInputType.phone,
                    prefixIcon: Icons.phone_rounded,
                    validator: (val) => val == null || val.isEmpty ? 'Required' : null,
                  ),
                  const SizedBox(height: 16),
                  
                  AppTextField(
                    label: 'Email (Optional)',
                    controller: _emailController,
                    keyboardType: TextInputType.emailAddress,
                    prefixIcon: Icons.email_rounded,
                  ),
                  const SizedBox(height: 16),

                  
                  Row(
                    children: [
                      Expanded(
                        child: GestureDetector(
                          onTap: _pickDate,
                          child: Container(
                            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
                            decoration: BoxDecoration(
                              color: const Color(0xFFF8FAFC),
                              borderRadius: BorderRadius.circular(14),
                              border: Border.all(color: const Color(0xFFE2E8F0)),
                            ),
                            child: Row(
                              children: [
                                Icon(Icons.calendar_today_rounded, color: scheme.primary, size: 20),
                                const SizedBox(width: 10),
                                Text(
                                  _selectedDate == null 
                                      ? 'Birth Date' 
                                      : '${_selectedDate!.year}-${_selectedDate!.month.toString().padLeft(2, '0')}-${_selectedDate!.day.toString().padLeft(2, '0')}',
                                  style: TextStyle(
                                    color: _selectedDate == null ? const Color(0xFF94A3B8) : const Color(0xFF0F172A),
                                    fontWeight: FontWeight.w600,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ),
                      ),
                      const SizedBox(width: 16),
                      Expanded(
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
                          decoration: BoxDecoration(
                            color: const Color(0xFFF8FAFC),
                            borderRadius: BorderRadius.circular(14),
                            border: Border.all(color: const Color(0xFFE2E8F0)),
                          ),
                          child: DropdownButtonHideUnderline(
                            child: DropdownButton<String>(
                              value: _gender,
                              isExpanded: true,
                              icon: const Icon(Icons.keyboard_arrow_down_rounded),
                              items: ['Male', 'Female'].map((String value) {
                                return DropdownMenuItem<String>(
                                  value: value,
                                  child: Text(value, style: const TextStyle(fontWeight: FontWeight.w600)),
                                );
                              }).toList(),
                              onChanged: (val) {
                                if (val != null) setState(() => _gender = val);
                              },
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 16),
                  
                  AppTextField(
                    label: 'Password',
                    controller: _passwordController,
                    obscure: true,
                    prefixIcon: Icons.lock_rounded,
                    validator: (val) => val != null && val.length < 6 ? 'Min 6 chars' : null,
                  ),
                  const SizedBox(height: 30),
                  
                  AppButton(
                    label: 'REGISTER NOW',
                    onPressed: _register,
                    loading: auth.status == AuthStatus.loading,
                  ),
                  const SizedBox(height: 20),
                ],
              ),
            ),
          ),
          maxWidth: 500,
        ),
      ),
    );
  }
}
