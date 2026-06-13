import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../providers/auth_provider.dart';
import '../../services/api_service.dart';
import '../../utils/helpers.dart';
import '../../components/app_drawer.dart';
import '../../utils/translator.dart';
import '../../utils/responsive.dart';

class EmergencyContact {
  String name;
  String phone;
  String relationship;

  EmergencyContact({String? name, String? phone, String? relationship})
      : name = name ?? '',
        phone = phone ?? '',
        relationship = relationship ?? 'Family';

  @override
  String toString() => '$name: $phone: $relationship';
}

class UserContactsScreen extends StatefulWidget {
  const UserContactsScreen({super.key});

  @override
  State<UserContactsScreen> createState() => _UserContactsScreenState();
}

class _UserContactsScreenState extends State<UserContactsScreen> {
  final List<EmergencyContact> _contacts = [];

  @override
  void initState() {
    super.initState();
    _loadContacts();
  }

  void _loadContacts() {
    final auth = Provider.of<AuthProvider>(context, listen: false);
    final raw = auth.user?.emergencyContacts ?? '';
    _contacts.clear();

    if (raw.isNotEmpty) {
      final lines = raw.split('\n');
      for (var line in lines) {
        if (line.trim().isEmpty) continue;
        final parts = line.split(':');
        if (parts.length >= 3) {
          _contacts.add(
            EmergencyContact(
              name: parts[0].trim(),
              phone: parts[1].trim(),
              relationship: parts.sublist(2).join(':').trim(),
            ),
          );
        } else if (parts.length == 2) {
          _contacts.add(
            EmergencyContact(
              name: parts[0].trim(),
              phone: parts[1].trim(),
              relationship: 'Family',
            ),
          );
        } else {
          _contacts.add(
            EmergencyContact(
              name: line.trim(),
              phone: '',
              relationship: 'Family',
            ),
          );
        }
      }
    }
  }

  Future<void> _saveContacts() async {
    final auth = Provider.of<AuthProvider>(context, listen: false);

    final rawString = _contacts.map((c) => c.toString()).join('\n');

    final result = await ApiService.updateSafetyInfo(
      auth.user?.id.toString() ?? '',
      auth.user?.medicalInfo ?? '',
      rawString,
    );

    if (mounted) {
      if (result['status'] == 'success') {
        if (auth.user != null) {
          await auth.updateUser(
            auth.user!.copyWith(emergencyContacts: rawString),
          );
        }
        if (mounted) {
          AppHelpers.showSnack(context, AppTranslator.t(context, 'Contacts Saved Successfully!'));
        }
      } else {
        AppHelpers.showSnack(
          context,
          result['message'] ?? AppTranslator.t(context, 'Failed to save contacts'),
          isError: true,
        );
      }
    }
  }

  void _showContactDialog({EmergencyContact? contact, int? index}) {
    final nameController = TextEditingController(text: contact?.name ?? '');
    final phoneController = TextEditingController(text: contact?.phone ?? '');
    final relationshipController = TextEditingController(text: contact?.relationship ?? 'Family');
    final isDark = Theme.of(context).brightness == Brightness.dark;

    final relationshipOptions = [
      'Spouse',
      'Father',
      'Mother',
      'Brother',
      'Sister',
      'Son',
      'Daughter',
      'Family',
      'Friend',
      'Guardian',
      'Other'
    ];
    final currentVal = relationshipController.text.trim();
    if (currentVal.isNotEmpty && !relationshipOptions.contains(currentVal)) {
      relationshipOptions.add(currentVal);
    }

    showDialog(
      context: context,
      builder: (context) {
        return AlertDialog(
          backgroundColor: isDark ? const Color(0xFF1E293B) : Colors.white,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(16),
          ),
          title: Text(
            contact == null ? AppTranslator.t(context, 'Add Contact') : AppTranslator.t(context, 'Edit Contact'),
            style: TextStyle(
              fontWeight: FontWeight.w900,
              color: isDark ? Colors.white : const Color(0xFF1E293B),
            ),
          ),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                TextField(
                  controller: nameController,
                  style: TextStyle(color: isDark ? Colors.white : Colors.black87),
                  decoration: InputDecoration(
                    labelText: AppTranslator.t(context, 'Name'),
                    labelStyle: const TextStyle(color: Colors.grey),
                    prefixIcon: const Icon(Icons.person_rounded, color: Colors.grey),
                    enabledBorder: UnderlineInputBorder(
                      borderSide: BorderSide(color: isDark ? const Color(0xFF334155) : Colors.grey.shade300),
                    ),
                    focusedBorder: const UnderlineInputBorder(
                      borderSide: BorderSide(color: Color(0xFF2563EB)),
                    ),
                  ),
                ),
                const SizedBox(height: 16),
                TextField(
                  controller: phoneController,
                  keyboardType: TextInputType.phone,
                  style: TextStyle(color: isDark ? Colors.white : Colors.black87),
                  decoration: InputDecoration(
                    labelText: AppTranslator.t(context, 'Phone'),
                    labelStyle: const TextStyle(color: Colors.grey),
                    prefixIcon: const Icon(Icons.phone_rounded, color: Colors.grey),
                    enabledBorder: UnderlineInputBorder(
                      borderSide: BorderSide(color: isDark ? const Color(0xFF334155) : Colors.grey.shade300),
                    ),
                    focusedBorder: const UnderlineInputBorder(
                      borderSide: BorderSide(color: Color(0xFF2563EB)),
                    ),
                  ),
                ),
                const SizedBox(height: 16),
                DropdownButtonFormField<String>(
                  initialValue: relationshipController.text.isEmpty ? 'Family' : relationshipController.text,
                  dropdownColor: isDark ? const Color(0xFF1E293B) : Colors.white,
                  style: TextStyle(color: isDark ? Colors.white : Colors.black87),
                  decoration: InputDecoration(
                    labelText: AppTranslator.t(context, 'Family Relationship'),
                    labelStyle: const TextStyle(color: Colors.grey),
                    prefixIcon: const Icon(Icons.people_alt_rounded, color: Colors.grey),
                    enabledBorder: UnderlineInputBorder(
                      borderSide: BorderSide(color: isDark ? const Color(0xFF334155) : Colors.grey.shade300),
                    ),
                    focusedBorder: const UnderlineInputBorder(
                      borderSide: BorderSide(color: Color(0xFF2563EB)),
                    ),
                  ),
                  items: relationshipOptions.map((String value) {
                    return DropdownMenuItem<String>(
                      value: value,
                      child: Text(AppTranslator.t(context, value)),
                    );
                  }).toList(),
                  onChanged: (val) {
                    if (val != null) {
                      relationshipController.text = val;
                    }
                  },
                ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context),
              child: Text(AppTranslator.t(context, 'Cancel'), style: const TextStyle(color: Colors.grey, fontWeight: FontWeight.bold)),
            ),
            ElevatedButton(
              onPressed: () {
                final name = nameController.text.trim();
                final phone = phoneController.text.trim();
                final relationship = relationshipController.text.trim();

                if (name.isEmpty || phone.isEmpty || relationship.isEmpty) {
                  AppHelpers.showSnack(
                    context,
                    AppTranslator.t(context, 'Please fill all fields'),
                    isError: true,
                  );
                  return;
                }

                setState(() {
                  if (contact == null) {
                    _contacts.add(EmergencyContact(name: name, phone: phone, relationship: relationship));
                  } else {
                    _contacts[index!].name = name;
                    _contacts[index].phone = phone;
                    _contacts[index].relationship = relationship;
                  }
                });

                Navigator.pop(context);
                _saveContacts();
              },
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFF2563EB),
                foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
              ),
              child: Text(AppTranslator.t(context, 'Save'), style: const TextStyle(fontWeight: FontWeight.bold)),
            ),
          ],
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final bgColor = isDark ? const Color(0xFF0F172A) : const Color(0xFFF8FAFC);
    final cardColor = isDark ? const Color(0xFF1E293B) : Colors.white;
    final titleColor = isDark ? Colors.white : const Color(0xFF1E293B);

    return Scaffold(
      backgroundColor: bgColor,
      drawer: AppDrawer(
        currentIndex: 11,
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
        child: Responsive(context).wrapWidescreen(
          SingleChildScrollView(
            padding: EdgeInsets.symmetric(horizontal: Responsive(context).hPad, vertical: 10),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              // Title & Add Row
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          AppTranslator.t(context, 'GUARDIAN NETWORK'),
                          style: TextStyle(
                            fontSize: 11,
                            fontWeight: FontWeight.w800,
                            color: isDark ? Colors.grey.shade400 : const Color(0xFF64748B),
                            letterSpacing: 1.5,
                          ),
                        ),
                        const SizedBox(height: 4),
                        RichText(
                          text: TextSpan(
                            children: [
                              TextSpan(
                                text: '${AppTranslator.t(context, 'Emergency')} ',
                                style: TextStyle(
                                  fontSize: 24,
                                  fontWeight: FontWeight.w900,
                                  color: titleColor,
                                ),
                              ),
                              TextSpan(
                                text: AppTranslator.t(context, 'Contacts'),
                                style: const TextStyle(
                                  fontSize: 24,
                                  fontWeight: FontWeight.w900,
                                  color: Color(0xFF2563EB), // Blue color
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 16),
                  // Add Contact Button
                  ElevatedButton.icon(
                    onPressed: () => _showContactDialog(),
                    icon: const Icon(Icons.add, size: 16),
                    label: Text(
                      AppTranslator.t(context, 'Add Contact'),
                      style: const TextStyle(
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

              // Main Card Container
              Container(
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
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(20),
                  child: Column(
                    children: [
                      if (_contacts.isEmpty)
                        Container(
                          padding: const EdgeInsets.symmetric(vertical: 80, horizontal: 24),
                          alignment: Alignment.center,
                          child: Column(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              // People Icon
                              Icon(
                                Icons.people_alt_rounded,
                                size: 48,
                                color: isDark ? const Color(0xFF475569) : const Color(0xFFCBD5E1),
                              ),
                              const SizedBox(height: 16),
                              Text(
                                AppTranslator.t(context, 'No emergency contacts yet.'),
                                style: TextStyle(
                                  fontSize: 15,
                                  fontWeight: FontWeight.w700,
                                  color: isDark ? Colors.white : const Color(0xFF1E293B),
                                ),
                              ),
                              const SizedBox(height: 6),
                              Text(
                                AppTranslator.t(context, 'Add contacts to notify loved ones in an emergency.'),
                                style: TextStyle(
                                  fontSize: 12,
                                  fontWeight: FontWeight.w500,
                                  color: isDark ? Colors.grey.shade400 : const Color(0xFF64748B),
                                ),
                                textAlign: TextAlign.center,
                              ),
                            ],
                          ),
                        )
                      else
                        ListView.separated(
                          shrinkWrap: true,
                          physics: const NeverScrollableScrollPhysics(),
                          itemCount: _contacts.length,
                          separatorBuilder: (context, index) => Divider(
                            height: 1,
                            color: isDark ? const Color(0xFF334155) : Colors.grey.shade100,
                          ),
                          itemBuilder: (context, index) {
                            final contact = _contacts[index];
                            return ListTile(
                              contentPadding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
                              leading: Container(
                                width: 44,
                                height: 44,
                                decoration: BoxDecoration(
                                  color: isDark ? const Color(0xFF0F172A) : const Color(0xFFEFF6FF),
                                  shape: BoxShape.circle,
                                ),
                                alignment: Alignment.center,
                                child: Text(
                                  contact.name.isNotEmpty ? contact.name[0].toUpperCase() : '?',
                                  style: const TextStyle(
                                    color: Color(0xFF2563EB),
                                    fontWeight: FontWeight.w800,
                                    fontSize: 16,
                                  ),
                                ),
                              ),
                              title: Row(
                                children: [
                                  Flexible(
                                    child: Text(
                                      contact.name,
                                      overflow: TextOverflow.ellipsis,
                                      style: TextStyle(
                                        fontWeight: FontWeight.w800,
                                        fontSize: 15,
                                        color: isDark ? Colors.white : const Color(0xFF1E293B),
                                      ),
                                    ),
                                  ),
                                  const SizedBox(width: 8),
                                  Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                                    decoration: BoxDecoration(
                                      color: const Color(0xFF2563EB).withValues(alpha: 0.1),
                                      borderRadius: BorderRadius.circular(12),
                                    ),
                                    child: Text(
                                      AppTranslator.t(context, contact.relationship),
                                      style: const TextStyle(
                                        color: Color(0xFF2563EB),
                                        fontSize: 11,
                                        fontWeight: FontWeight.w700,
                                      ),
                                    ),
                                  ),
                                ],
                              ),
                              subtitle: Text(
                                contact.phone,
                                style: TextStyle(
                                  color: isDark ? Colors.grey.shade400 : const Color(0xFF64748B),
                                  fontWeight: FontWeight.w600,
                                  fontSize: 13,
                                ),
                              ),
                              trailing: Row(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  IconButton(
                                    icon: const Icon(Icons.edit_rounded, color: Colors.blue, size: 20),
                                    onPressed: () => _showContactDialog(
                                      contact: contact,
                                      index: index,
                                    ),
                                  ),
                                  IconButton(
                                    icon: const Icon(Icons.delete_rounded, color: Colors.red, size: 20),
                                    onPressed: () {
                                      setState(() {
                                        _contacts.removeAt(index);
                                      });
                                      _saveContacts();
                                    },
                                  ),
                                ],
                              ),
                            );
                          },
                        ),
                    ],
                  ),
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
