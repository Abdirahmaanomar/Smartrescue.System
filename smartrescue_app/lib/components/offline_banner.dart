import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../services/offline_manager.dart';

class OfflineBanner extends StatefulWidget {
  final Widget child;
  const OfflineBanner({super.key, required this.child});

  @override
  State<OfflineBanner> createState() => _OfflineBannerState();
}

class _OfflineBannerState extends State<OfflineBanner> with SingleTickerProviderStateMixin {
  late AnimationController _controller;
  late Animation<double> _heightFactor;
  bool _wasOffline = false;
  bool _showBanner = false;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      duration: const Duration(milliseconds: 300),
      vsync: this,
    );
    _heightFactor = CurvedAnimation(
      parent: _controller,
      curve: Curves.easeInOut,
    );
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  void _updateBanner(bool isOffline) {
    if (isOffline) {
      _wasOffline = true;
      if (!_showBanner) {
        setState(() {
          _showBanner = true;
        });
        _controller.forward();
      }
    } else {
      if (_wasOffline) {
        // Just reconnected
        _wasOffline = false;
        // Keep banner showing for 2 seconds to show connection restored message
        Future.delayed(const Duration(seconds: 2), () {
          if (mounted && !_wasOffline) {
            _controller.reverse().then((_) {
              if (mounted) {
                setState(() {
                  _showBanner = false;
                });
              }
            });
          }
        });
      } else {
        if (_showBanner) {
          _controller.reverse().then((_) {
            if (mounted) {
              setState(() {
                _showBanner = false;
              });
            }
          });
        }
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final offlineManager = Provider.of<OfflineManager>(context);
    final isOffline = offlineManager.isOffline;
    final isSyncing = offlineManager.isSyncing;

    _updateBanner(isOffline);

    return Column(
      children: [
        if (_showBanner)
          SizeTransition(
            sizeFactor: _heightFactor,
            child: Material(
              color: isOffline
                  ? const Color(0xFFEF4444)
                  : const Color(0xFF10B981),
              child: SafeArea(
                bottom: false,
                child: Container(
                  width: double.infinity,
                  padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 16),
                  alignment: Alignment.center,
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(
                        isOffline
                            ? Icons.wifi_off_rounded
                            : (isSyncing ? Icons.sync_rounded : Icons.wifi_rounded),
                        color: Colors.white,
                        size: 16,
                      ),
                      const SizedBox(width: 8),
                      Text(
                        isOffline
                            ? 'Offline Mode — Showing cached data'
                            : (isSyncing ? 'Connection Restored — Syncing requests...' : 'Connection Restored'),
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 13,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      if (isSyncing) ...[
                        const SizedBox(width: 8),
                        const SizedBox(
                          width: 12,
                          height: 12,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
              ),
            ),
          ),
        Expanded(child: widget.child),
      ],
    );
  }
}
