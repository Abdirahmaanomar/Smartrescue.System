import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// OfflineManager — handles connectivity detection, data caching,
/// and queuing pending SOS requests when offline.
class OfflineManager extends ChangeNotifier {
  static const String _pendingSosKey    = 'pending_sos_queue';
  static const String _cachedHistoryKey = 'cached_history';
  static const String _cachedNotifsKey  = 'cached_notifications';
  static const String _cachedIncidentsKey = 'cached_community_incidents';
  static const String _cachedDonorsKey  = 'cached_blood_donors';

  static OfflineManager? instance;

  bool _isOnline = true;
  bool _isSyncing = false;
  StreamSubscription<List<ConnectivityResult>>? _sub;
  Timer? _heartbeatTimer;

  bool get isOnline   => _isOnline;
  bool get isOffline  => !_isOnline;
  bool get isSyncing  => _isSyncing;

  OfflineManager() {
    instance = this;
    _init();
  }

  void setOffline() {
    if (_isOnline) {
      _isOnline = false;
      notifyListeners();
      print("OfflineManager: Forced offline due to socket exception / API failure.");
    }
  }

  // ─── Initialise & Listen ───────────────────────────────────────────────────
  Future<void> _init() async {
    // Get initial connectivity state
    final results = await Connectivity().checkConnectivity();
    _isOnline = await _checkInternetAccess(results);
    notifyListeners();

    // Listen for connectivity changes
    _sub = Connectivity().onConnectivityChanged.listen((results) async {
      await _handleResults(results);
    });

    // Start periodic heartbeat checking to catch drops/restores that don't trigger events
    _heartbeatTimer?.cancel();
    _heartbeatTimer = Timer.periodic(const Duration(seconds: 10), (_) async {
      final results = await Connectivity().checkConnectivity();
      await _handleResults(results);
    });
  }

  Future<void> _handleResults(List<ConnectivityResult> results) async {
    final nowOnline = await _checkInternetAccess(results);
    if (nowOnline != _isOnline) {
      _isOnline = nowOnline;
      notifyListeners();
      print("OfflineManager: Connectivity changed. isOnline = $nowOnline");
      if (nowOnline) {
        // came back online → try to sync pending SOS
        syncPendingSos();
      }
    }
  }

  Future<bool> _checkInternetAccess(List<ConnectivityResult> results) async {
    final hasInterface = results.isNotEmpty &&
        results.any((r) =>
            r == ConnectivityResult.mobile ||
            r == ConnectivityResult.wifi ||
            r == ConnectivityResult.ethernet ||
            r == ConnectivityResult.vpn);

    if (!hasInterface) return false;
    if (kIsWeb) return true; // Can't use InternetAddress.lookup on web

    try {
      // Direct DNS lookup check for true internet access
      final lookup = await InternetAddress.lookup('google.com')
          .timeout(const Duration(seconds: 3));
      return lookup.isNotEmpty && lookup[0].rawAddress.isNotEmpty;
    } catch (_) {
      return false;
    }
  }

  // ─── Pending SOS Queue ─────────────────────────────────────────────────────

  /// Save a pending SOS to local storage (called when offline)
  Future<void> enqueuePendingSos({
    required String userId,
    required double lat,
    required double lng,
    required double accuracy,
    required String emergencyType,
    String description = '',
  }) async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_pendingSosKey);
    final List<dynamic> queue = raw != null ? jsonDecode(raw) : [];
    queue.add({
      'user_id': userId,
      'lat': lat,
      'lng': lng,
      'accuracy': accuracy,
      'emergency_type': emergencyType,
      'description': description,
      'queued_at': DateTime.now().toIso8601String(),
    });
    await prefs.setString(_pendingSosKey, jsonEncode(queue));
    notifyListeners();
  }

  /// Returns the number of pending SOS requests in queue
  Future<int> getPendingSosCount() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_pendingSosKey);
    if (raw == null) return 0;
    final List<dynamic> queue = jsonDecode(raw);
    return queue.length;
  }

  /// Sync pending SOS requests to server (called on reconnect)
  Future<void> syncPendingSos() async {
    if (_isSyncing) return;
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_pendingSosKey);
    if (raw == null) return;

    final List<dynamic> queue = jsonDecode(raw);
    if (queue.isEmpty) return;

    _isSyncing = true;
    notifyListeners();

    // Import ApiService lazily to avoid circular dependency
    // We call it via a callback set by the app
    final List<dynamic> failed = [];
    for (final item in queue) {
      try {
        // Dynamic import to avoid circular dependency
        final result = await _sendSosCallback?.call(
          userId: item['user_id'] as String,
          lat: (item['lat'] as num).toDouble(),
          lng: (item['lng'] as num).toDouble(),
          accuracy: (item['accuracy'] as num).toDouble(),
          emergencyType: item['emergency_type'] as String,
          description: item['description'] as String? ?? '',
        );
        if (result == null || result['status'] != 'success') {
          failed.add(item);
        }
      } catch (_) {
        failed.add(item);
      }
    }

    // Keep only items that failed to sync
    await prefs.setString(_pendingSosKey, jsonEncode(failed));
    _isSyncing = false;
    notifyListeners();
  }

  // Callback for sending SOS (set by SosProvider to avoid circular dep)
  Future<Map<String, dynamic>> Function({
    required String userId,
    required double lat,
    required double lng,
    required double accuracy,
    required String emergencyType,
    required String description,
  })? _sendSosCallback;

  void setSendSosCallback(
    Future<Map<String, dynamic>> Function({
      required String userId,
      required double lat,
      required double lng,
      required double accuracy,
      required String emergencyType,
      required String description,
    }) cb,
  ) {
    _sendSosCallback = cb;
  }

  // ─── Cache Management ──────────────────────────────────────────────────────

  Future<void> cacheHistory(Map<String, dynamic> data) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_cachedHistoryKey, jsonEncode(data));
  }

  Future<Map<String, dynamic>?> getCachedHistory() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_cachedHistoryKey);
    if (raw == null) return null;
    return jsonDecode(raw) as Map<String, dynamic>;
  }

  Future<void> cacheNotifications(List<dynamic> data) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_cachedNotifsKey, jsonEncode(data));
  }

  Future<List<dynamic>?> getCachedNotifications() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_cachedNotifsKey);
    if (raw == null) return null;
    return jsonDecode(raw) as List<dynamic>;
  }

  Future<void> cacheCommunityIncidents(List<dynamic> data) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_cachedIncidentsKey, jsonEncode(data));
  }

  Future<List<dynamic>?> getCachedCommunityIncidents() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_cachedIncidentsKey);
    if (raw == null) return null;
    return jsonDecode(raw) as List<dynamic>;
  }

  Future<void> cacheBloodDonors(List<dynamic> data) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_cachedDonorsKey, jsonEncode(data));
  }

  Future<List<dynamic>?> getCachedBloodDonors() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_cachedDonorsKey);
    if (raw == null) return null;
    return jsonDecode(raw) as List<dynamic>;
  }

  // ─── Dispose ───────────────────────────────────────────────────────────────
  @override
  void dispose() {
    _sub?.cancel();
    _heartbeatTimer?.cancel();
    super.dispose();
  }
}
