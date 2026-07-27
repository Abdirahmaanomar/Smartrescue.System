import 'dart:async';
import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import '../services/api_service.dart';
import '../services/sound_service.dart';

class DriverProvider extends ChangeNotifier {
  bool _loading = false;
  bool _initialLoading = true; // Only true on first load — polling runs silently
  bool _isTogglingStatus = false; // True while online/offline API call is in-flight
  Map<String, dynamic>? _activeJob;
  List<dynamic> _pendingRequests = [];
  List<dynamic> _history = [];
  int _saves = 0;
  Map<String, dynamic>? _unit;
  String _unitStatus = 'offline'; // available (online), busy (offline)
  
  Map<String, dynamic>? _incomingSos;
  bool _isSosOverlayShowing = false;
  final Set<int> _seenSosIds = {};

  Timer? _pollTimer;
  Timer? _gpsTimer;
  StreamSubscription<Position>? _gpsStreamSubscription;
  Position? _currentPosition;

  bool _isSessionExpired = false;

  bool get loading => _loading;
  bool get initialLoading => _initialLoading;
  bool get isTogglingStatus => _isTogglingStatus;
  Map<String, dynamic>? get activeJob => _activeJob;
  List<dynamic> get pendingRequests => _pendingRequests;
  List<dynamic> get history => _history;
  int get saves => _saves;
  Map<String, dynamic>? get unit => _unit;
  String get unitStatus => _unitStatus;
  bool get isSessionExpired => _isSessionExpired;
  
  Map<String, dynamic>? get incomingSos => _incomingSos;
  bool get isSosOverlayShowing => _isSosOverlayShowing;
  Position? get currentPosition => _currentPosition;

  bool get hasActiveJob => _activeJob != null && 
      _activeJob!['status'] != null && 
      ['pending', 'accepted', 'en_route', 'arrived'].contains(_activeJob!['status']);

  // ─── Initialize Driver State ────────────────────────────────────────────────
  void init(String userId) {
    debugPrint('[DriverProvider] Init for user: $userId');
    stop(); // Reset any existing timers
    _isSessionExpired = false;
    _initialLoading = true;

    // Fetch data first, then auto-set online so driver starts receiving dispatches
    fetchDriverData().then((_) {
      _initialLoading = false;
      notifyListeners();
    });

    // Poll every 4 seconds — silently in background, no loading spinner
    _pollTimer = Timer.periodic(const Duration(seconds: 4), (_) async {
      fetchDriverData(silent: true);
    });

    // Start location updates
    _startGpsTracking();
  }

  // ─── Stop Timers & Subscriptions ────────────────────────────────────────────
  void stop() {
    _pollTimer?.cancel();
    _gpsTimer?.cancel();
    _gpsStreamSubscription?.cancel();
    _gpsStreamSubscription = null;
    _incomingSos = null;
    _isSosOverlayShowing = false;
    _activeJob = null;
    _unit = null;
    _history = [];
    _isSessionExpired = false;
    _initialLoading = true;
    _seenSosIds.clear(); // ← Clear seen IDs on logout/stop so re-logins always alert
  }

  // ─── Fetch All Data ─────────────────────────────────────────────────────────
  Future<void> fetchDriverData({bool silent = false}) async {
    if (!silent) {
      _loading = true;
      notifyListeners();
    }

    try {
      // 1. Fetch active job + unit info from dedicated driver endpoint
      final jobRes = await ApiService.getActiveJob();
      debugPrint('[DriverProvider] getActiveJob response: $jobRes');

      if (jobRes['status'] == 'error' && jobRes['message'] == 'Unauthorized') {
        _isSessionExpired = true;
        _loading = false;
        notifyListeners();
        return;
      }

      _isSessionExpired = false;

      // Always extract unit info (returned in all non-error responses)
      if (jobRes['unit'] != null) {
        _unit = Map<String, dynamic>.from(jobRes['unit'] as Map);
        if (!_isTogglingStatus) {
          _unitStatus = _unit!['status'] ?? 'offline';
        }
      }

      if (jobRes['status'] == 'success' && jobRes['request'] != null) {
        final req = jobRes['request'] as Map<String, dynamic>;
        _activeJob = {
          'id': req['id'],
          'lat': req['lat'],
          'lng': req['lng'],
          'patient_name': req['patient_name'],
          'patient_phone': req['patient_phone'],
          'emergency_type': req['emergency_type'],
          'neighborhood': req['neighborhood'],
          'status': req['status'] ?? 'pending',
          'description': req['description'] ?? '',
        };

        // Show SOS overlay for new incoming jobs:
        // - status 'pending'/'accepted' AND not yet seen OR unit is now available
        //   (admin may have re-assigned after a reject)
        final jobId = int.tryParse(req['id'].toString()) ?? 0;
        final jobStatus = req['status'] ?? '';
        final isNewDispatch = jobId != 0 &&
            !_seenSosIds.contains(jobId) &&
            (jobStatus == 'pending' || jobStatus == 'accepted');
        if (isNewDispatch) {
          _seenSosIds.add(jobId);
          _incomingSos = _activeJob;
          _isSosOverlayShowing = true;
          SoundService.playBeep();
        }
      } else {
        _activeJob = null;
      }

      // 2. Fetch history for saves count (unit info already from step 1)
      final histRes = await ApiService.getDriverHistory();
      if (histRes['status'] == 'success') {
        _history = histRes['history'] ?? [];
        _saves = histRes['saves'] ?? 0;
        // If no unit from getActiveJob, fall back to history unit
        if (_unit == null && histRes['unit'] != null) {
          _unit = histRes['unit'];
          _unitStatus = _unit!['status'] ?? 'offline';
        }
      }
    } catch (e) {
      debugPrint('[DriverProvider] Error fetching data: $e');
    }

    _loading = false;
    notifyListeners();
  }

  // ─── Refresh Active Job Only (during polling) ────────────────────────────────
  Future<void> refreshActiveJobOnly() async {
    try {
      final jobRes = await ApiService.getActiveJob();
      if (jobRes['status'] == 'error' && jobRes['message'] == 'Unauthorized') {
        _isSessionExpired = true;
        notifyListeners();
        return;
      }
      _isSessionExpired = false;
      if (jobRes['status'] == 'success' && jobRes['request'] != null) {
        final req = jobRes['request'] as Map<String, dynamic>;
        _activeJob = {
          'id': req['id'],
          'lat': req['lat'],
          'lng': req['lng'],
          'patient_name': req['patient_name'],
          'patient_phone': req['patient_phone'],
          'emergency_type': req['emergency_type'],
          'neighborhood': req['neighborhood'],
          'status': req['status'] ?? 'pending',
          'description': req['description'] ?? '',
        };
        notifyListeners();
      } else {
        if (_activeJob != null) {
          _activeJob = null;
          fetchDriverData(); // Refresh history/stats since active job completed
        }
      }
    } catch (_) {}
  }

  // ─── Poll for Pending SOS assignments ────────────────────────────────────────
  Future<void> pollPendingSos() async {
    try {
      final res = await ApiService.getPendingSos();
      if (res['status'] == 'success' && res['requests'] != null && res['requests'].isNotEmpty) {
        final list = res['requests'] as List;
        _pendingRequests = list;
        
        // Take the latest pending request and alert
        final latest = list.first;
        final int id = int.tryParse(latest['id'].toString()) ?? 0;
        
        if (id != 0 && !_seenSosIds.contains(id)) {
          _seenSosIds.add(id);
          _incomingSos = latest;
          _isSosOverlayShowing = true;
          
          // Sound alert trigger
          SoundService.playBeep();
          notifyListeners();
        }
      } else {
        _pendingRequests = [];
      }
    } catch (_) {}
  }

  // ─── Close Incoming SOS Dialog ───────────────────────────────────────────────
  void dismissSosOverlay() {
    _isSosOverlayShowing = false;
    _incomingSos = null;
    notifyListeners();
  }

  // ─── Accept Emergency Mission ────────────────────────────────────────────────
  Future<bool> acceptMission(int requestId) async {
    if (_unit == null) return false;
    final unitId = _unit!['id'];
    _loading = true;
    notifyListeners();

    try {
      final res = await ApiService.updateStatus(requestId, unitId, 'accept');
      if (res['status'] == 'success') {
        dismissSosOverlay();
        await fetchDriverData();
        return true;
      }
    } catch (_) {}

    _loading = false;
    notifyListeners();
    return false;
  }

  // ─── Reject Emergency Mission ────────────────────────────────────────────────
  Future<bool> rejectMission(int requestId) async {
    if (_unit == null) return false;
    final unitId = _unit!['id'];
    _loading = true;
    notifyListeners();

    try {
      final res = await ApiService.updateStatus(requestId, unitId, 'reject');
      if (res['status'] == 'success') {
        // Remove from seen set so if admin re-assigns, the alert fires again
        _seenSosIds.remove(requestId);
        dismissSosOverlay();
        await fetchDriverData();
        return true;
      }
    } catch (_) {}

    _loading = false;
    notifyListeners();
    return false;
  }

  // ─── Update Active Mission Status ───────────────────────────────────────────
  Future<bool> updateMissionStatus(String action) async {
    if (_activeJob == null || _unit == null) return false;
    final reqId = int.tryParse(_activeJob!['id'].toString()) ?? 0;
    final unitId = _unit!['id'];
    if (reqId == 0) return false;

    _loading = true;
    notifyListeners();

    try {
      final res = await ApiService.updateStatus(reqId, unitId, action);
      if (res['status'] == 'success') {
        await fetchDriverData();
        return true;
      }
    } catch (_) {}

    _loading = false;
    notifyListeners();
    return false;
  }

  // ─── Update Unit Availability ───────────────────────────────────────────────
  Future<bool> updateUnitAvailability(bool isOnline, {bool forceOffline = false}) async {
    // Don't allow going offline while an active job is in progress (unless forced — e.g. logout)
    if (!isOnline && hasActiveJob && !forceOffline) return false;
    if (_isTogglingStatus) return false; // Already toggling

    final statusStr = isOnline ? 'available' : 'offline';

    // Optimistic update + show spinner
    _isTogglingStatus = true;
    _unitStatus = statusStr;
    if (_unit != null) _unit!['status'] = statusStr;
    notifyListeners();

    try {
      final res = await ApiService.updateUnitStatus(statusStr);
      debugPrint('[DriverProvider] updateUnitStatus response: $res');
      if (res['status'] == 'success') {
        _isTogglingStatus = false;
        notifyListeners();
        return true;
      }
      debugPrint('[DriverProvider] updateUnitStatus FAILED: ${res['message']}');
      // Revert on failure (only if not forced offline — logout doesn't need revert)
      if (!forceOffline) {
        final reverted = isOnline ? 'offline' : 'available';
        _unitStatus = reverted;
        if (_unit != null) _unit!['status'] = reverted;
      }
    } catch (e) {
      debugPrint('[DriverProvider] updateUnitStatus EXCEPTION: $e');
      if (!forceOffline) {
        final reverted = isOnline ? 'offline' : 'available';
        _unitStatus = reverted;
        if (_unit != null) _unit!['status'] = reverted;
      }
    }

    _isTogglingStatus = false;
    notifyListeners();
    return false;
  }


  // ─── Live GPS Location Tracking ─────────────────────────────────────────────
  Future<void> _startGpsTracking() async {
    try {
      bool serviceEnabled = await Geolocator.isLocationServiceEnabled();
      if (!serviceEnabled) {
        debugPrint("Driver Location: service disabled.");
        return;
      }

      LocationPermission permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
        if (permission == LocationPermission.denied) {
          debugPrint("Driver Location: permission denied.");
          return;
        }
      }

      if (permission == LocationPermission.deniedForever) {
        debugPrint("Driver Location: permission permanently denied.");
        return;
      }

      // Try to get initial position immediately to populate _currentPosition
      try {
        final initPos = await Geolocator.getCurrentPosition(
          locationSettings: const LocationSettings(accuracy: LocationAccuracy.high),
        ).timeout(const Duration(seconds: 4));
        _currentPosition = initPos;
        notifyListeners();
        if (_unitStatus == 'available' || hasActiveJob) {
          await ApiService.updateDriverLocation(initPos.latitude, initPos.longitude);
        }
      } catch (_) {
        try {
          final lastKnown = await Geolocator.getLastKnownPosition();
          if (lastKnown != null) {
            _currentPosition = lastKnown;
            notifyListeners();
            if (_unitStatus == 'available' || hasActiveJob) {
              await ApiService.updateDriverLocation(lastKnown.latitude, lastKnown.longitude);
            }
          }
        } catch (_) {}
      }

      // Stream position updates
      _gpsStreamSubscription = Geolocator.getPositionStream(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.high,
          distanceFilter: 2, // Updates every 2m
        ),
      ).listen((Position pos) async {
        _currentPosition = pos;
        notifyListeners();

        if (_unitStatus == 'available' || hasActiveJob) {
          try {
            await ApiService.updateDriverLocation(pos.latitude, pos.longitude);
          } catch (_) {}
        }
      }, onError: (error) {
        debugPrint("Driver GPS stream error: $error");
      });

      // Backup timer every 15s in case stream is silent
      _gpsTimer = Timer.periodic(const Duration(seconds: 15), (_) async {
        try {
          final pos = await Geolocator.getCurrentPosition(
            locationSettings: const LocationSettings(accuracy: LocationAccuracy.high),
          ).timeout(const Duration(seconds: 4));
          _currentPosition = pos;
          notifyListeners();

          if (_unitStatus == 'available' || hasActiveJob) {
            await ApiService.updateDriverLocation(pos.latitude, pos.longitude);
          }
        } catch (_) {}
      });
    } catch (e) {
      debugPrint("Error starting GPS tracking: $e");
    }
  }
}
