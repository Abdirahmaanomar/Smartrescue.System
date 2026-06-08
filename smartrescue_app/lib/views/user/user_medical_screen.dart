import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../providers/auth_provider.dart';
import '../../services/api_service.dart';
import '../../utils/helpers.dart';
import '../../components/app_drawer.dart';
import '../../utils/translator.dart';

class UserMedicalScreen extends StatefulWidget {
  const UserMedicalScreen({super.key});

  @override
  State<UserMedicalScreen> createState() => _UserMedicalScreenState();
}

class _UserMedicalScreenState extends State<UserMedicalScreen> {
  final _formKey = GlobalKey<FormState>();
  bool _isSaving = false;
  bool _isBloodDonor = false;

  String _selectedBloodGroup = '— Select —';
  late TextEditingController _allergiesController;
  late TextEditingController _chronicConditionsController;
  late TextEditingController _medicationsController;
  late TextEditingController _emergencyNotesController;

  @override
  void initState() {
    super.initState();
    final auth = Provider.of<AuthProvider>(context, listen: false);
    _allergiesController = TextEditingController();
    _chronicConditionsController = TextEditingController();
    _medicationsController = TextEditingController();
    _emergencyNotesController = TextEditingController();
    _parseMedicalInfo(auth.user?.medicalInfo ?? '');
  }

  @override
  void dispose() {
    _allergiesController.dispose();
    _chronicConditionsController.dispose();
    _medicationsController.dispose();
    _emergencyNotesController.dispose();
    super.dispose();
  }

  void _parseMedicalInfo(String text) {
    if (text.isEmpty) return;

    // Fallback if not matching the format is to set the whole text to Emergency Notes
    _emergencyNotesController.text = text;

    final lines = text.split('\n');
    String parsedBloodGroup = '— Select —';
    String parsedAllergies = '';
    String parsedChronic = '';
    String parsedMedications = '';
    String parsedNotes = '';

    bool matchedFormat = false;

    for (var line in lines) {
      line = line.trim();
      if (line.toLowerCase().startsWith('blood group:')) {
        parsedBloodGroup = line.substring('blood group:'.length).trim();
        matchedFormat = true;
      } else if (line.toLowerCase().startsWith('allergies:')) {
        parsedAllergies = line.substring('allergies:'.length).trim();
        matchedFormat = true;
      } else if (line.toLowerCase().startsWith('chronic conditions:')) {
        parsedChronic = line.substring('chronic conditions:'.length).trim();
        matchedFormat = true;
      } else if (line.toLowerCase().startsWith('medications:')) {
        parsedMedications = line.substring('medications:'.length).trim();
        matchedFormat = true;
      } else if (line.toLowerCase().startsWith('emergency notes:')) {
        parsedNotes = line.substring('emergency notes:'.length).trim();
        matchedFormat = true;
      } else if (line.toLowerCase().startsWith('volunteer blood donor:')) {
        _isBloodDonor = line.substring('volunteer blood donor:'.length).trim().toLowerCase() == 'yes';
        matchedFormat = true;
      }
    }

    if (matchedFormat) {
      _selectedBloodGroup = parsedBloodGroup.isEmpty ? '— Select —' : parsedBloodGroup;
      _allergiesController.text = parsedAllergies;
      _chronicConditionsController.text = parsedChronic;
      _medicationsController.text = parsedMedications;
      _emergencyNotesController.text = parsedNotes;
    }
  }

  String _serializeMedicalInfo() {
    final buffer = StringBuffer();
    buffer.writeln('Blood Group: ${_selectedBloodGroup == '— Select —' ? '' : _selectedBloodGroup}');
    buffer.writeln('Allergies: ${_allergiesController.text.trim()}');
    buffer.writeln('Chronic Conditions: ${_chronicConditionsController.text.trim()}');
    buffer.writeln('Medications: ${_medicationsController.text.trim()}');
    buffer.writeln('Emergency Notes: ${_emergencyNotesController.text.trim()}');
    buffer.writeln('Volunteer Blood Donor: ${_isBloodDonor ? 'Yes' : 'No'}');
    return buffer.toString().trim();
  }

  Future<void> _saveMedicalInfo() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() => _isSaving = true);
    final auth = Provider.of<AuthProvider>(context, listen: false);

    final serializedData = _serializeMedicalInfo();

    final result = await ApiService.updateSafetyInfo(
      auth.user?.id.toString() ?? '',
      serializedData,
      auth.user?.emergencyContacts ?? '',
      isBloodDonor: _isBloodDonor,
      bloodGroup: _selectedBloodGroup,
    );

    if (mounted) {
      setState(() => _isSaving = false);
      if (result['status'] == 'success') {
        if (auth.user != null) {
          await auth.updateUser(
            auth.user!.copyWith(medicalInfo: serializedData),
          );
        }
        if (mounted) {
          AppHelpers.showSnack(context, AppTranslator.t(context, 'Medical ID Saved Successfully!'));
        }
      } else {
        AppHelpers.showSnack(
          context,
          result['message'] ?? AppTranslator.t(context, 'Failed to save Medical ID'),
          isError: true,
        );
      }
    }
  }

  Widget _buildFieldCard({
    required IconData icon,
    required String label,
    required Widget child,
  }) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final cardBg = isDark ? const Color(0xFF1E293B) : Colors.white;
    final borderColor = isDark ? const Color(0xFF334155) : Colors.grey.shade100;
    final iconContainerColor = isDark ? const Color(0xFF0F172A) : const Color(0xFFEFF6FF);

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: cardBg,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: borderColor, width: 1.5),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Icon container
          Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: iconContainerColor,
              shape: BoxShape.circle,
            ),
            child: Icon(
              icon,
              color: const Color(0xFF2563EB),
              size: 20,
            ),
          ),
          const SizedBox(height: 12),
          // Label
          Text(
            label,
            style: TextStyle(
              fontSize: 10,
              fontWeight: FontWeight.w800,
              color: isDark ? Colors.grey.shade400 : const Color(0xFF64748B),
              letterSpacing: 0.8,
            ),
          ),
          const SizedBox(height: 8),
          // Child input field
          child,
        ],
      ),
    );
  }

  Widget _buildBloodGroupDropdown() {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final fieldBg = isDark ? const Color(0xFF0F172A) : const Color(0xFFF8FAFC);
    final borderColor = isDark ? const Color(0xFF334155) : Colors.grey.shade200;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 4),
      decoration: BoxDecoration(
        color: fieldBg,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: borderColor, width: 1.5),
      ),
      child: DropdownButtonHideUnderline(
        child: DropdownButton<String>(
          value: _selectedBloodGroup,
          isExpanded: true,
          dropdownColor: isDark ? const Color(0xFF1E293B) : Colors.white,
          icon: Icon(Icons.keyboard_arrow_down_rounded, color: Colors.grey.shade500),
          onChanged: (val) {
            if (val != null) setState(() => _selectedBloodGroup = val);
          },
          items: [
            '— Select —', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'
          ].map<DropdownMenuItem<String>>((String val) {
            return DropdownMenuItem<String>(
              value: val,
              child: Text(
                AppTranslator.t(context, val),
                style: TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w600,
                  color: isDark ? Colors.white : Colors.black87,
                ),
              ),
            );
          }).toList(),
        ),
      ),
    );
  }

  Widget _buildTextField({
    required TextEditingController controller,
    required String hintText,
    int maxLines = 1,
  }) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final fieldBg = isDark ? const Color(0xFF0F172A) : const Color(0xFFF8FAFC);
    final borderColor = isDark ? const Color(0xFF334155) : Colors.grey.shade200;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 4),
      decoration: BoxDecoration(
        color: fieldBg,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: borderColor, width: 1.5),
      ),
      child: TextFormField(
        controller: controller,
        maxLines: maxLines,
        style: TextStyle(
          fontSize: 13,
          fontWeight: FontWeight.w600,
          color: isDark ? Colors.white : Colors.black87,
        ),
        decoration: InputDecoration(
          hintText: hintText,
          hintStyle: TextStyle(
            color: Colors.grey.shade400,
            fontSize: 13,
            fontWeight: FontWeight.w500,
          ),
          border: InputBorder.none,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final bgColor = isDark ? const Color(0xFF0F172A) : const Color(0xFFF8FAFC);
    final cardColor = isDark ? const Color(0xFF1E293B) : Colors.white;
    final titleColor = isDark ? Colors.white : const Color(0xFF1E293B);
    final isWide = MediaQuery.of(context).size.width > 800;

    return Scaffold(
      backgroundColor: bgColor,
      drawer: AppDrawer(
        currentIndex: 10,
        onTabSelected: (index) {},
        isSubScreen: true,
      ),
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        elevation: 0,
        automaticallyImplyLeading: false,
        leading: Builder(
          builder: (context) => IconButton(
            icon: Icon(Icons.menu_rounded, color: isDark ? Colors.white : Colors.black87),
            onPressed: () => Scaffold.of(context).openDrawer(),
          ),
        ),
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 10),
          child: Form(
            key: _formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                // Title & Save Row
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          AppTranslator.t(context, 'MEDICAL RECORDS'),
                          style: TextStyle(
                            fontSize: 11,
                            fontWeight: FontWeight.w800,
                            color: isDark ? Colors.grey.shade400 : const Color(0xFF64748B),
                            letterSpacing: 1.5,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          AppTranslator.t(context, 'Medical Identity Card'),
                          style: TextStyle(
                            fontSize: 24,
                            fontWeight: FontWeight.w900,
                            color: titleColor,
                          ),
                        ),
                      ],
                    ),
                    // Save Button
                    ElevatedButton.icon(
                      onPressed: _isSaving ? null : _saveMedicalInfo,
                      icon: _isSaving
                          ? const SizedBox(
                              height: 16,
                              width: 16,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                              ),
                            )
                          : const Icon(Icons.save_rounded, size: 16),
                      label: Text(
                        AppTranslator.t(context, 'Save'),
                        style: TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: const Color(0xFF2563EB),
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 14),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                        elevation: 2,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 24),

                // Main Identity Card Container
                Container(
                  padding: const EdgeInsets.all(24),
                  decoration: BoxDecoration(
                    color: cardColor,
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(
                      color: isDark ? const Color(0xFF334155) : Colors.grey.shade100,
                      width: 1.5,
                    ),
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
                      // Grid fields row/column (Blood Group & Allergies)
                      if (isWide)
                        Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Expanded(
                              child: _buildFieldCard(
                                icon: Icons.water_drop_rounded,
                                label: AppTranslator.t(context, 'Blood Type'),
                                child: _buildBloodGroupDropdown(),
                              ),
                            ),
                            const SizedBox(width: 24),
                            Expanded(
                              child: _buildFieldCard(
                                icon: Icons.back_hand_rounded,
                                label: AppTranslator.t(context, 'Allergies'),
                                child: _buildTextField(
                                  controller: _allergiesController,
                                  hintText: AppTranslator.t(context, 'Penicillin, Nuts...'),
                                ),
                              ),
                            ),
                          ],
                        )
                      else ...[
                        _buildFieldCard(
                          icon: Icons.water_drop_rounded,
                          label: AppTranslator.t(context, 'Blood Type'),
                          child: _buildBloodGroupDropdown(),
                        ),
                        const SizedBox(height: 24),
                        _buildFieldCard(
                          icon: Icons.back_hand_rounded,
                          label: AppTranslator.t(context, 'Allergies'),
                          child: _buildTextField(
                            controller: _allergiesController,
                            hintText: AppTranslator.t(context, 'Penicillin, Nuts...'),
                          ),
                        ),
                      ],
                      const SizedBox(height: 24),

                      // Grid fields row/column (Chronic Conditions & Medications)
                      if (isWide)
                        Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Expanded(
                              child: _buildFieldCard(
                                icon: Icons.health_and_safety_rounded,
                                label: AppTranslator.t(context, 'Medical Conditions'),
                                child: _buildTextField(
                                  controller: _chronicConditionsController,
                                  hintText: AppTranslator.t(context, 'Diabetes, Asthma...'),
                                ),
                              ),
                            ),
                            const SizedBox(width: 24),
                            Expanded(
                              child: _buildFieldCard(
                                icon: Icons.medication_rounded,
                                label: AppTranslator.t(context, 'Current Medications'),
                                child: _buildTextField(
                                  controller: _medicationsController,
                                  hintText: AppTranslator.t(context, 'Metformin 500mg...'),
                                ),
                              ),
                            ),
                          ],
                        )
                      else ...[
                        _buildFieldCard(
                          icon: Icons.health_and_safety_rounded,
                          label: AppTranslator.t(context, 'Medical Conditions'),
                          child: _buildTextField(
                            controller: _chronicConditionsController,
                            hintText: AppTranslator.t(context, 'Diabetes, Asthma...'),
                          ),
                        ),
                        const SizedBox(height: 24),
                        _buildFieldCard(
                          icon: Icons.medication_rounded,
                          label: AppTranslator.t(context, 'Current Medications'),
                          child: _buildTextField(
                            controller: _medicationsController,
                            hintText: AppTranslator.t(context, 'Metformin 500mg...'),
                          ),
                        ),
                      ],
                      const SizedBox(height: 24),

                      // Emergency Notes (Full Width)
                      _buildFieldCard(
                        icon: Icons.edit_note_rounded,
                        label: AppTranslator.t(context, 'Emergency Notes'),
                        child: _buildTextField(
                          controller: _emergencyNotesController,
                          hintText: AppTranslator.t(context, 'Additional info for first responders...'),
                          maxLines: 4,
                        ),
                      ),
                      const SizedBox(height: 24),
 
                      // Blood Donor Switch
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                        decoration: BoxDecoration(
                          color: isDark ? const Color(0xFF1E293B) : Colors.white,
                          borderRadius: BorderRadius.circular(16),
                          border: Border.all(color: isDark ? const Color(0xFF334155) : Colors.grey.shade100, width: 1.5),
                        ),
                        child: SwitchListTile(
                          contentPadding: EdgeInsets.zero,
                          title: Text(
                            AppTranslator.t(context, 'Volunteer as a Blood Donor'),
                            style: TextStyle(
                              fontSize: 14,
                              fontWeight: FontWeight.w700,
                              color: isDark ? Colors.white : const Color(0xFF1E293B),
                            ),
                          ),
                          subtitle: Text(
                            AppTranslator.t(context, 'Your name, blood group, and contact will be visible to the community in emergencies.'),
                            style: TextStyle(
                              fontSize: 11,
                              fontWeight: FontWeight.w500,
                              color: isDark ? Colors.grey.shade400 : const Color(0xFF64748B),
                            ),
                          ),
                          value: _isBloodDonor,
                          activeThumbColor: Colors.white,
                          activeTrackColor: Colors.redAccent,
                          onChanged: (val) {
                            setState(() => _isBloodDonor = val);
                          },
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
