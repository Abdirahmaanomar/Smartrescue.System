import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:provider/provider.dart';
import 'package:image_picker/image_picker.dart';
import '../../providers/sos_provider.dart';
import '../../utils/helpers.dart';
import 'user_shell.dart';
import '../../utils/translator.dart';

class UserProofScreen extends StatefulWidget {
  const UserProofScreen({super.key});

  @override
  State<UserProofScreen> createState() => _UserProofScreenState();
}

class _UserProofScreenState extends State<UserProofScreen> {
  late final TextEditingController _controller;

  // Pre-configured Quick Tags to quickly describe emergencies in multiple languages
  final List<Map<String, String>> _quickTags = [
    {'so': 'Dhaawac badan', 'en': 'Multiple injured', 'ar': 'إصابات متعددة'},
    {'so': 'Wada xiran', 'en': 'Blocked road', 'ar': 'طريق مغلق'},
    {'so': 'Qiic madow', 'en': 'Heavy smoke', 'ar': 'دخان كثيف'},
    {'so': 'Gargaar degdeg ah', 'en': 'First aid needed', 'ar': 'مطلوب إسعافات أولية'},
    {'so': 'Khatar koronto', 'en': 'Electrical hazard', 'ar': 'خطر كهربائي'},
    {'so': 'Dab kiciyay', 'en': 'Spreading fire', 'ar': 'حريق ينتشر'},
  ];

  @override
  void initState() {
    super.initState();
    final sos = Provider.of<SosProvider>(context, listen: false);
    _controller = TextEditingController(text: sos.description ?? '');
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<void> _addImage(SosProvider sos, ImageSource source) async {
    if (sos.evidenceImages.length >= 5) {
      AppHelpers.showSnack(context, AppTranslator.t(context, 'Maximum 5 photos allowed.'), isError: true);
      return;
    }
    final picker = ImagePicker();
    try {
      final pickedFile = await picker.pickImage(
        source: source,
        imageQuality: 75,
        preferredCameraDevice: CameraDevice.rear,
      );
      if (!mounted) return;
      if (pickedFile != null) {
        sos.addEvidenceImage(pickedFile);
        AppHelpers.showSnack(context, '${AppTranslator.t(context, 'Photo')} ${sos.evidenceImages.length} ${AppTranslator.t(context, 'attached! 📸')}');
      }
    } catch (e) {
      debugPrint("Pick image error: $e");
      if (!mounted) return;
      AppHelpers.showSnack(
        context,
        source == ImageSource.camera
            ? AppTranslator.t(context, 'Camera access failed. Please allow camera permission.')
            : AppTranslator.t(context, 'Could not open gallery. Please try again.'),
        isError: true,
      );
    }
  }

  void _showImageSourceSheet(BuildContext context, SosProvider sos) {
    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (_) {
        final isDark = Theme.of(context).brightness == Brightness.dark;
        return Container(
          margin: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: isDark ? const Color(0xFF1E293B) : Colors.white,
            borderRadius: BorderRadius.circular(20),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const SizedBox(height: 8),
              Container(width: 40, height: 4, decoration: BoxDecoration(color: Colors.grey.shade300, borderRadius: BorderRadius.circular(4))),
              const SizedBox(height: 16),
              ListTile(
                leading: const Icon(Icons.photo_library_rounded, color: Color(0xFF2563EB)),
                title: Text(AppTranslator.t(context, 'Choose from Gallery'), style: const TextStyle(fontWeight: FontWeight.w700)),
                onTap: () { Navigator.pop(context); _addImage(sos, ImageSource.gallery); },
              ),
              ListTile(
                leading: const Icon(Icons.camera_alt_rounded, color: Color(0xFF2563EB)),
                title: Text(AppTranslator.t(context, 'Take a Photo'), style: const TextStyle(fontWeight: FontWeight.w700)),
                onTap: () { Navigator.pop(context); _addImage(sos, ImageSource.camera); },
              ),
              const SizedBox(height: 16),
            ],
          ),
        );
      },
    );
  }


  void _appendTag(String tag, SosProvider sos) {
    String currentText = _controller.text.trim();
    String updatedText = currentText.isEmpty ? tag : '$currentText, $tag';
    
    // Limit to safe lengths
    if (updatedText.length > 300) return;

    setState(() {
      _controller.text = updatedText;
      // Position cursor at the end
      _controller.selection = TextSelection.fromPosition(
        TextPosition(offset: _controller.text.length),
      );
    });
    sos.setDescription(updatedText);
  }

  @override
  Widget build(BuildContext context) {
    final sos = Provider.of<SosProvider>(context);
    final scheme = Theme.of(context).colorScheme;
    final isDark = Theme.of(context).brightness == Brightness.dark;

    // Keep controller updated if provider gets cleared from outside
    if (sos.description == null && _controller.text.isNotEmpty) {
      _controller.clear();
    }

    return Scaffold(
      backgroundColor: isDark ? const Color(0xFF0F172A) : const Color(0xFFF8FAFC),
      appBar: AppBar(
        leading: Builder(
          builder: (context) => IconButton(
            icon: const Icon(Icons.menu_rounded),
            onPressed: () => UserShell.scaffoldKey.currentState?.openDrawer(),
          ),
        ),
        title: Text(AppTranslator.t(context, 'Incident Proof'), style: const TextStyle(fontWeight: FontWeight.w900)),
        centerTitle: true,
      ),
      body: SingleChildScrollView(
        child: Column(
          children: [
            // Top Location / GPS Status Banner
            Container(
              width: double.infinity,
              padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
              color: isDark ? const Color(0xFF1E293B) : const Color(0xFFEFF6FF),
              child: Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(6),
                    decoration: BoxDecoration(
                      color: Colors.green.withValues(alpha: 0.15),
                      shape: BoxShape.circle,
                    ),
                    child: const Icon(Icons.gps_fixed_rounded, color: Colors.green, size: 16),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          AppTranslator.t(context, 'GPS Connection Active'),
                          style: TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.w800,
                            color: isDark ? Colors.grey.shade300 : const Color(0xFF1E3A8A),
                          ),
                        ),
                        Text(
                          sos.activeRequest != null
                              ? '${AppTranslator.t(context, 'Coordinates')}: ${sos.activeRequest!.lat.toStringAsFixed(4)}, ${sos.activeRequest!.lng.toStringAsFixed(4)}'
                              : AppTranslator.t(context, 'Your real-time coordinates will be sent with this proof.'),
                          style: TextStyle(
                            fontSize: 10,
                            color: isDark ? Colors.grey.shade400 : const Color(0xFF3B82F6),
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                      ],
                    ),
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                    decoration: BoxDecoration(
                      color: Colors.green.shade800,
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Text(
                      AppTranslator.t(context, 'SECURE'),
                      style: const TextStyle(color: Colors.white, fontSize: 8, fontWeight: FontWeight.bold, letterSpacing: 0.5),
                    ),
                  ),
                ],
              ),
            ),

            Padding(
              padding: const EdgeInsets.all(24),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Text Description Section Header
                  Row(
                    children: [
                      Icon(Icons.edit_note_rounded, color: scheme.primary, size: 24),
                      const SizedBox(width: 8),
                      Text(
                        AppTranslator.t(context, 'Describe the Incident'),
                        style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w900, letterSpacing: -0.5),
                      ),
                    ],
                  ),
                  const SizedBox(height: 6),
                  Text(
                    AppTranslator.t(context, 'Provide details about the emergency to guide responders.'),
                    style: TextStyle(color: isDark ? Colors.grey.shade400 : Colors.grey.shade600, fontSize: 13, fontWeight: FontWeight.w500),
                  ),
                  const SizedBox(height: 16),

                  // Modern Text Area Container
                  Container(
                    decoration: BoxDecoration(
                      color: isDark ? const Color(0xFF1E293B) : Colors.white,
                      borderRadius: BorderRadius.circular(24),
                      boxShadow: [
                        BoxShadow(
                          color: Colors.black.withValues(alpha: isDark ? 0.3 : 0.04),
                          blurRadius: 15,
                          offset: const Offset(0, 6),
                        )
                      ],
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        TextField(
                          controller: _controller,
                          maxLines: 5,
                          maxLength: 300,
                          onChanged: (val) => sos.setDescription(val),
                          style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w500),
                          decoration: InputDecoration(
                            hintText: AppTranslator.t(context, 'Example: Injured lying on road, heavy smoke spreading, etc...'),
                            hintStyle: TextStyle(color: Colors.grey.shade400, fontWeight: FontWeight.w500, fontSize: 14),
                            contentPadding: const EdgeInsets.all(20),
                            border: InputBorder.none,
                            counterText: '', // Hide standard counter to use our styled one
                          ),
                        ),
                        Padding(
                          padding: const EdgeInsets.fromLTRB(0, 0, 16, 12),
                          child: Text(
                            '${_controller.text.length} / 300 ${AppTranslator.t(context, 'characters')}',
                            style: TextStyle(
                              fontSize: 11,
                              color: _controller.text.length >= 280 ? Colors.red : Colors.grey.shade500,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 12),

                  // Quick Language Tag Helpers
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: _quickTags.map((tag) {
                      final lang = AppTranslator.langCode(context);
                      final tagText = tag[lang] ?? tag['en'] ?? '';
                      return ActionChip(
                        elevation: 0,
                        pressElevation: 2,
                        backgroundColor: isDark ? const Color(0xFF334155) : Colors.grey.shade100,
                        side: BorderSide(color: isDark ? const Color(0xFF475569) : Colors.grey.shade200),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                        padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 2),
                        avatar: Icon(Icons.add_rounded, size: 14, color: scheme.primary),
                        label: Text(
                          tagText,
                          style: TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.w700,
                            color: isDark ? Colors.grey.shade200 : const Color(0xFF334155),
                          ),
                        ),
                        onPressed: () => _appendTag(tagText, sos),
                      );
                    }).toList(),
                  ),
                  const SizedBox(height: 28),

                  // Photo Evidence Section Header
                  Row(
                    children: [
                      Icon(Icons.add_a_photo_rounded, color: scheme.primary, size: 22),
                      const SizedBox(width: 8),
                      Text(
                        AppTranslator.t(context, 'Attach Photo Evidence'),
                        style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w900, letterSpacing: -0.5),
                      ),
                    ],
                  ),
                  const SizedBox(height: 6),
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          AppTranslator.t(context, 'Add up to 5 photos of the scene to help responders.'),
                          style: TextStyle(color: isDark ? Colors.grey.shade400 : Colors.grey.shade600, fontSize: 13, fontWeight: FontWeight.w500),
                        ),
                      ),
                      Text(
                        '${sos.evidenceImages.length}/5',
                        style: TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w800,
                          color: sos.evidenceImages.length >= 5 ? Colors.red : scheme.primary,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 16),

                   // Multi-Photo Grid / Placeholder Card
                  if (sos.evidenceImages.isNotEmpty)
                    GridView.builder(
                      shrinkWrap: true,
                      physics: const NeverScrollableScrollPhysics(),
                      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                        crossAxisCount: 3,
                        crossAxisSpacing: 10,
                        mainAxisSpacing: 10,
                        childAspectRatio: 1,
                      ),
                      itemCount: sos.evidenceImages.length < 5
                          ? sos.evidenceImages.length + 1
                          : 5,
                      itemBuilder: (context, index) {
                        // Last item = Add button (only if < 5)
                        if (index == sos.evidenceImages.length) {
                          return GestureDetector(
                            onTap: () => _showImageSourceSheet(context, sos),
                            child: Container(
                              decoration: BoxDecoration(
                                color: isDark ? const Color(0xFF1E293B) : Colors.white,
                                borderRadius: BorderRadius.circular(16),
                                border: Border.all(
                                  color: scheme.primary.withValues(alpha: 0.3),
                                  width: 2,
                                  strokeAlign: BorderSide.strokeAlignInside,
                                ),
                              ),
                              child: Column(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  Icon(Icons.add_a_photo_rounded, color: scheme.primary, size: 28),
                                  const SizedBox(height: 6),
                                  Text(
                                    AppTranslator.t(context, 'Add Photo'),
                                    style: TextStyle(
                                      fontSize: 11,
                                      fontWeight: FontWeight.w700,
                                      color: scheme.primary,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          );
                        }

                        // Photo thumbnails
                        final img = sos.evidenceImages[index];
                        return Stack(
                          clipBehavior: Clip.none,
                          children: [
                            ClipRRect(
                              borderRadius: BorderRadius.circular(16),
                              child: kIsWeb
                                  ? Image.network(img.path, fit: BoxFit.cover, width: double.infinity, height: double.infinity)
                                  : Image.file(File(img.path), fit: BoxFit.cover, width: double.infinity, height: double.infinity),
                            ),
                            Positioned(
                              top: -6,
                              right: -6,
                              child: GestureDetector(
                                onTap: () => sos.removeEvidenceImage(index),
                                child: Container(
                                  width: 24,
                                  height: 24,
                                  decoration: const BoxDecoration(
                                    color: Colors.red,
                                    shape: BoxShape.circle,
                                  ),
                                  child: const Icon(Icons.close_rounded, color: Colors.white, size: 14),
                                ),
                              ),
                            ),
                            Positioned(
                              bottom: 4,
                              left: 4,
                              child: Container(
                                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                decoration: BoxDecoration(
                                  color: Colors.black.withValues(alpha: 0.55),
                                  borderRadius: BorderRadius.circular(6),
                                ),
                                child: Text(
                                  '${index + 1}',
                                  style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w800),
                                ),
                              ),
                            ),
                          ],
                        );
                      },
                    )
                  else
                    GestureDetector(
                      onTap: () => _showImageSourceSheet(context, sos),
                      child: Container(
                        width: double.infinity,
                        height: 130,
                        margin: const EdgeInsets.only(top: 4),
                        decoration: BoxDecoration(
                          color: isDark ? const Color(0xFF1E293B) : Colors.white,
                          borderRadius: BorderRadius.circular(20),
                          border: Border.all(color: scheme.primary.withValues(alpha: 0.15), width: 2.5),
                        ),
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Container(
                              padding: const EdgeInsets.all(12),
                              decoration: BoxDecoration(color: scheme.primary.withValues(alpha: 0.1), shape: BoxShape.circle),
                              child: Icon(Icons.add_photo_alternate_rounded, color: scheme.primary, size: 32),
                            ),
                            const SizedBox(height: 8),
                            Text(AppTranslator.t(context, 'No Photos Attached'), style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14)),
                            const SizedBox(height: 4),
                            Text(AppTranslator.t(context, 'Tap to add up to 5 photos'), style: TextStyle(color: Colors.grey.shade500, fontSize: 12)),
                          ],
                        ),
                      ),
                    ),
                  const SizedBox(height: 28),

                  // Safety Alert Banner (Somali Language Warning)
                  Container(
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      color: Colors.red.withValues(alpha: 0.1),
                      borderRadius: BorderRadius.circular(16),
                      border: Border.all(color: Colors.red.withValues(alpha: 0.2)),
                    ),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Icon(Icons.warning_amber_rounded, color: Colors.red, size: 20),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                AppTranslator.t(context, 'Safety First'),
                                style: const TextStyle(color: Colors.red, fontWeight: FontWeight.bold, fontSize: 13),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                AppTranslator.t(context, 'Do not endanger yourself to take photos or describe the incident. First ensure your safety and move to a safe place!'),
                                style: const TextStyle(color: Colors.red, fontSize: 11.5, fontWeight: FontWeight.w600, height: 1.4),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 32),

                  // Save & Return Action Button
                  Container(
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(18),
                      boxShadow: [
                        BoxShadow(
                          color: scheme.primary.withValues(alpha: 0.35),
                          blurRadius: 15,
                          offset: const Offset(0, 6),
                        )
                      ],
                    ),
                    width: double.infinity,
                    child: ElevatedButton.icon(
                      onPressed: () {
                        sos.setDescription(_controller.text);
                        UserShell.tabNotifier.value = 0;
                        AppHelpers.showSnack(context, AppTranslator.t(context, 'Proof information saved! ✅'));
                      },
                      icon: const Icon(Icons.check_circle_rounded, color: Colors.white),
                      label: Text(
                        AppTranslator.t(context, 'Save & Return to Dashboard'),
                        style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w900, fontSize: 15),
                      ),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: scheme.primary,
                        padding: const EdgeInsets.symmetric(vertical: 18),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
                        elevation: 0,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
