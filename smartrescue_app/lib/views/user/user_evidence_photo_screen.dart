import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:provider/provider.dart';
import 'package:image_picker/image_picker.dart';
import '../../providers/sos_provider.dart';
import '../../utils/translator.dart';
import '../../utils/responsive.dart';
import 'user_shell.dart';

class UserEvidencePhotoScreen extends StatelessWidget {
  const UserEvidencePhotoScreen({super.key});

  Future<void> _pickImage(BuildContext context, SosProvider sos, ImageSource source) async {
    final picker = ImagePicker();
    try {
      final pickedFile = await picker.pickImage(source: source, imageQuality: 70);
      if (pickedFile != null) {
        sos.setEvidenceImage(pickedFile);
      }
    } catch (_) {}
  }

  @override
  Widget build(BuildContext context) {
    final sos = Provider.of<SosProvider>(context);
    final scheme = Theme.of(context).colorScheme;

    return Scaffold(
      appBar: AppBar(
        leading: Builder(
          builder: (context) => IconButton(
            icon: const Icon(Icons.menu_rounded),
            onPressed: () => UserShell.scaffoldKey.currentState?.openDrawer(),
          ),
        ),
        title: Text(AppTranslator.t(context, 'Attach Evidence'), style: const TextStyle(fontWeight: FontWeight.w800)),
        centerTitle: true,
      ),
      body: Responsive(context).wrapWidescreen(
        SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                AppTranslator.t(context, 'Attach Photo Evidence'),
                style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w900, letterSpacing: -0.5),
              ),
              const SizedBox(height: 8),
              Text(
                AppTranslator.t(context, 'Capture or upload an image of the emergency scene. This helps responders assess the situation before arrival.'),
                style: TextStyle(color: Colors.grey.shade600, fontSize: 14, fontWeight: FontWeight.w500),
              ),
              const SizedBox(height: 24),
              
              // Image Preview Container
              Center(
                child: Container(
                  width: double.infinity,
                  height: 280,
                  decoration: BoxDecoration(
                    color: Theme.of(context).cardTheme.color,
                    borderRadius: BorderRadius.circular(24),
                    border: Border.all(
                      color: scheme.primary.withValues(alpha: 0.15),
                      width: 2,
                    ),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withValues(alpha: 0.04),
                        blurRadius: 20,
                        offset: const Offset(0, 8),
                      )
                    ],
                  ),
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(22),
                    child: sos.evidenceImage == null
                        ? Column(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              Icon(Icons.add_a_photo_rounded, color: scheme.primary, size: 50),
                              const SizedBox(height: 16),
                              Text(
                                AppTranslator.t(context, 'No Image Attached'),
                                style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                AppTranslator.t(context, 'Take a photo or upload from gallery'),
                                style: TextStyle(color: Colors.grey.shade500, fontSize: 13, fontWeight: FontWeight.w500),
                              ),
                            ],
                          )
                        : Stack(
                            fit: StackFit.expand,
                            children: [
                              kIsWeb ? Image.network(sos.evidenceImage!.path, fit: BoxFit.cover) : Image.file(File(sos.evidenceImage!.path), fit: BoxFit.cover),
                              Positioned(
                                right: 12,
                                top: 12,
                                child: CircleAvatar(
                                  backgroundColor: Colors.black.withValues(alpha: 0.5),
                                  child: IconButton(
                                    icon: const Icon(Icons.close_rounded, color: Colors.white),
                                    onPressed: () => sos.setEvidenceImage(null),
                                  ),
                                ),
                              ),
                            ],
                          ),
                  ),
                ),
              ),
              const SizedBox(height: 30),
              
              // Photo actions
              SizedBox(
                width: double.infinity,
                child: ElevatedButton.icon(
                  onPressed: () => _pickImage(context, sos, ImageSource.gallery),
                  icon: const Icon(Icons.photo_library_rounded, color: Colors.white),
                  label: Text(AppTranslator.t(context, 'Upload from Gallery'), style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: scheme.primary,
                    padding: const EdgeInsets.symmetric(vertical: 16),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
