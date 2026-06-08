import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../providers/sos_provider.dart';
import '../../utils/helpers.dart';
import '../../utils/translator.dart';
import 'user_shell.dart';

class UserIncidentDescScreen extends StatefulWidget {
  const UserIncidentDescScreen({super.key});

  @override
  State<UserIncidentDescScreen> createState() => _UserIncidentDescScreenState();
}

class _UserIncidentDescScreenState extends State<UserIncidentDescScreen> {
  late final TextEditingController _controller;

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

  @override
  Widget build(BuildContext context) {
    final sos = Provider.of<SosProvider>(context);
    final scheme = Theme.of(context).colorScheme;

    // Keep controller updated if provider gets cleared from outside (user-side removal rule)
    if (sos.description == null && _controller.text.isNotEmpty) {
      _controller.clear();
    }

    return Scaffold(
      appBar: AppBar(
        leading: Builder(
          builder: (context) => IconButton(
            icon: const Icon(Icons.menu_rounded),
            onPressed: () => UserShell.scaffoldKey.currentState?.openDrawer(),
          ),
        ),
        title: Text(AppTranslator.t(context, 'Incident Description'), style: const TextStyle(fontWeight: FontWeight.w800)),
        centerTitle: true,
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              AppTranslator.t(context, 'Describe the Incident'),
              style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w900, letterSpacing: -0.5),
            ),
            const SizedBox(height: 8),
            Text(
              AppTranslator.t(context, 'Please provide clear, concise details about the current emergency. This information will be sent directly to the rescue team.'),
              style: TextStyle(color: Colors.grey.shade600, fontSize: 14, fontWeight: FontWeight.w500),
            ),
            const SizedBox(height: 24),
            Container(
              decoration: BoxDecoration(
                color: Theme.of(context).cardTheme.color,
                borderRadius: BorderRadius.circular(20),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.03),
                    blurRadius: 15,
                    offset: const Offset(0, 5),
                  )
                ],
              ),
              child: TextField(
                controller: _controller,
                maxLines: 6,
                onChanged: (val) => sos.setDescription(val),
                decoration: InputDecoration(
                  hintText: AppTranslator.t(context, 'Type critical information here (e.g. number of injured, visible hazards, specific entry points)...'),
                  hintStyle: TextStyle(color: Colors.grey.shade400, fontWeight: FontWeight.w500),
                  contentPadding: const EdgeInsets.all(20),
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(20),
                    borderSide: BorderSide(color: Colors.grey.withValues(alpha: 0.2)),
                  ),
                  focusedBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(20),
                    borderSide: BorderSide(color: scheme.primary, width: 2),
                  ),
                  filled: true,
                  fillColor: Theme.of(context).cardTheme.color,
                ),
              ),
            ),
            const SizedBox(height: 30),
            
            // Premium Save & Return Button
            SizedBox(
              width: double.infinity,
              child: ElevatedButton.icon(
                onPressed: () {
                  sos.setDescription(_controller.text);
                  UserShell.tabNotifier.value = 0;
                  AppHelpers.showSnack(context, AppTranslator.t(context, 'Incident Description Saved!'));
                },
                icon: const Icon(Icons.check_circle_rounded, color: Colors.white),
                label: Text(
                  AppTranslator.t(context, 'Save & Return to Dashboard'),
                  style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 14),
                ),
                style: ElevatedButton.styleFrom(
                  backgroundColor: scheme.primary,
                  padding: const EdgeInsets.symmetric(vertical: 16),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                  elevation: 2,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
