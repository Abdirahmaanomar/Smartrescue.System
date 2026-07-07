import 'dart:async';
import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:url_launcher/link.dart';
import '../utils/helpers.dart';

class CallScreen extends StatefulWidget {
  final String calleeName;
  final String calleePhone;

  const CallScreen({
    super.key,
    required this.calleeName,
    required this.calleePhone,
  });

  /// Show the call screen by launching native dialer
  static Future<void> show(
    BuildContext context, {
    required String name,
    required String phone,
  }) async {
    final cleanPhone = phone.replaceAll(RegExp(r'\s+|-|\(|\)'), '');
    if (cleanPhone.isEmpty) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Phone number is empty')),
        );
      }
      return;
    }
    final uri = Uri.parse('tel:$cleanPhone');
    try {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    } catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Could not open phone dialer: $e')),
        );
      }
    }
  }

  @override
  State<CallScreen> createState() => _CallScreenState();
}

class _CallScreenState extends State<CallScreen> with TickerProviderStateMixin {
  bool _isMuted = false;
  bool _isSpeaker = false;
  bool _isKeypad = false;
  Duration _elapsed = Duration.zero;
  Timer? _timer;
  late AnimationController _pulseController;
  late Animation<double> _pulseAnimation;
  final TextEditingController _keypadController = TextEditingController();

  @override
  void initState() {
    super.initState();
    _startTimer();
    _pulseController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1200),
    )..repeat(reverse: true);
    _pulseAnimation = Tween<double>(begin: 0.95, end: 1.05).animate(
      CurvedAnimation(parent: _pulseController, curve: Curves.easeInOut),
    );


  }

  void _startTimer() {
    _timer = Timer.periodic(const Duration(seconds: 1), (_) {
      if (mounted) {
        setState(() => _elapsed += const Duration(seconds: 1));
      }
    });
  }

  String get _elapsedStr {
    final m = _elapsed.inMinutes.remainder(60).toString().padLeft(2, '0');
    final s = _elapsed.inSeconds.remainder(60).toString().padLeft(2, '0');
    return '$m:$s';
  }

  void _endCall() {
    _timer?.cancel();
    Navigator.of(context).pop();
  }

  void _tapKeypad(String digit) {
    setState(() {
      _keypadController.text += digit;
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    _pulseController.dispose();
    _keypadController.dispose();
    super.dispose();
  }

  Widget _buildActionButton({
    required IconData icon,
    required String label,
    required VoidCallback onTap,
    bool active = false,
    Color? activeColor,
  }) {
    return GestureDetector(
      onTap: onTap,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          AnimatedContainer(
            duration: const Duration(milliseconds: 200),
            width: 64,
            height: 64,
            decoration: BoxDecoration(
              color: active
                  ? (activeColor ?? Colors.white).withValues(alpha: 0.85)
                  : Colors.white.withValues(alpha: 0.15),
              shape: BoxShape.circle,
            ),
            child: Icon(
              icon,
              color: active ? Colors.black : Colors.white,
              size: 28,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            label,
            style: const TextStyle(
              color: Colors.white,
              fontSize: 12,
              fontWeight: FontWeight.w500,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildKeypad() {
    const keys = [
      ['1', '2', '3'],
      ['4', '5', '6'],
      ['7', '8', '9'],
      ['*', '0', '#'],
    ];
    return Column(
      children: [
        Text(
          _keypadController.text,
          style: const TextStyle(
            color: Colors.white,
            fontSize: 26,
            fontWeight: FontWeight.w300,
            letterSpacing: 6,
          ),
        ),
        const SizedBox(height: 16),
        ...keys.map((row) => Padding(
          padding: const EdgeInsets.only(bottom: 12),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceEvenly,
            children: row.map((digit) => GestureDetector(
              onTap: () => _tapKeypad(digit),
              child: Container(
                width: 72,
                height: 72,
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.15),
                  shape: BoxShape.circle,
                ),
                child: Center(
                  child: Text(
                    digit,
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 26,
                      fontWeight: FontWeight.w400,
                    ),
                  ),
                ),
              ),
            )).toList(),
          ),
        )),
      ],
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.transparent,
      body: Container(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [
              Color(0xFF2D2010),
              Color(0xFF1A1508),
              Color(0xFF0D0D0D),
            ],
          ),
        ),
        child: SafeArea(
          child: Column(
            children: [
              const SizedBox(height: 48),

              // Avatar with pulse
              ScaleTransition(
                scale: _pulseAnimation,
                child: Container(
                  width: 96,
                  height: 96,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: Colors.white.withValues(alpha: 0.15),
                    border: Border.all(
                      color: Colors.white.withValues(alpha: 0.3),
                      width: 2,
                    ),
                  ),
                  child: Center(
                    child: Text(
                      AppHelpers.initials(widget.calleeName),
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 36,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                ),
              ),

              const SizedBox(height: 20),

              // Callee name
              Text(
                widget.calleeName.isNotEmpty ? widget.calleeName : 'Responder',
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 28,
                  fontWeight: FontWeight.w400,
                  letterSpacing: 0.3,
                ),
              ),

              const SizedBox(height: 8),

              // Calling status / timer
              Text(
                _elapsed.inSeconds == 0 ? 'Calling...' : _elapsedStr,
                style: TextStyle(
                  color: Colors.white.withValues(alpha: 0.65),
                  fontSize: 16,
                  fontWeight: FontWeight.w300,
                ),
              ),

              const SizedBox(height: 4),

              // Phone number
              Text(
                widget.calleePhone,
                style: TextStyle(
                  color: Colors.white.withValues(alpha: 0.5),
                  fontSize: 14,
                  fontWeight: FontWeight.w300,
                  letterSpacing: 1.5,
                ),
              ),

              const Spacer(),

              // Keypad or Action buttons
              if (_isKeypad)
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 24),
                  child: _buildKeypad(),
                )
              else
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 32),
                  child: Column(
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                        children: [
                          _buildActionButton(
                            icon: _isSpeaker
                                ? Icons.volume_up_rounded
                                : Icons.volume_up_outlined,
                            label: 'Speaker',
                            onTap: () => setState(() => _isSpeaker = !_isSpeaker),
                            active: _isSpeaker,
                          ),
                          Link(
                            uri: Uri.parse('tel:${widget.calleePhone.replaceAll(RegExp(r'\s+|-|\(|\)'), '')}'),
                            target: LinkTarget.self,
                            builder: (context, followLink) => _buildActionButton(
                              icon: Icons.phonelink_ring_rounded,
                              label: 'Phone Link',
                              onTap: followLink!,
                            ),
                          ),
                          _buildActionButton(
                            icon: _isMuted
                                ? Icons.mic_off_rounded
                                : Icons.mic_off_outlined,
                            label: 'Mute',
                            onTap: () => setState(() => _isMuted = !_isMuted),
                            active: _isMuted,
                          ),
                        ],
                      ),
                      const SizedBox(height: 24),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                        children: [
                          _buildActionButton(
                            icon: Icons.more_horiz,
                            label: 'More',
                            onTap: () {},
                          ),
                          // End call (red) — center
                          Column(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              GestureDetector(
                                onTap: _endCall,
                                child: Container(
                                  width: 72,
                                  height: 72,
                                  decoration: const BoxDecoration(
                                    color: Color(0xFFE52222),
                                    shape: BoxShape.circle,
                                  ),
                                  child: const Icon(
                                    Icons.call_end_rounded,
                                    color: Colors.white,
                                    size: 32,
                                  ),
                                ),
                              ),
                              const SizedBox(height: 8),
                              const Text(
                                'End',
                                style: TextStyle(
                                  color: Colors.white,
                                  fontSize: 12,
                                  fontWeight: FontWeight.w500,
                                ),
                              ),
                            ],
                          ),
                          _buildActionButton(
                            icon: Icons.dialpad_rounded,
                            label: 'Keypad',
                            onTap: () => setState(() => _isKeypad = !_isKeypad),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),

              // Back from keypad
              if (_isKeypad)
                TextButton(
                  onPressed: () => setState(() => _isKeypad = false),
                  child: Text(
                    'Hide Keypad',
                    style: TextStyle(
                      color: Colors.white.withValues(alpha: 0.6),
                      fontSize: 14,
                    ),
                  ),
                )
              else
                const SizedBox(height: 48),
            ],
          ),
        ),
      ),
    );
  }
}
