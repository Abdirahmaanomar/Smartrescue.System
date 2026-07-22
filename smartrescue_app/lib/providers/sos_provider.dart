import 'dart:async';
import 'dart:convert';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:image_picker/image_picker.dart';
import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:flutter/foundation.dart' show kIsWeb;
import '../models/rescue_request_model.dart';
import '../services/api_service.dart';
import '../services/sound_service.dart';
import '../services/offline_manager.dart';

enum SosState { idle, holding, sending, active, noRequest }

class SosProvider extends ChangeNotifier {
  OfflineManager? _offlineManager;

  void setOfflineManager(OfflineManager offline) {
    _offlineManager = offline;
    _offlineManager?.setSendSosCallback(({
      required String userId,
      required double lat,
      required double lng,
      required double accuracy,
      required String emergencyType,
      required String description,
    }) async {
      return await ApiService.sendSos(
        userId: userId,
        lat: lat,
        lng: lng,
        accuracy: accuracy,
        emergencyType: emergencyType,
        description: description,
      );
    });
  }
  SosState _sosState = SosState.idle;
  String _selectedType = 'Medical';
  String? _description;
  String? _customNeighborhood;
  XFile? _evidenceImage;
  List<XFile> _evidenceImages = [];
  RescueRequestModel? _activeRequest;
  String? _errorMessage;
  Timer? _pollTimer;
  String? _userId;
  // Grace period: number of consecutive no_active_request responses to ignore after sending SOS
  int _noRequestGraceTicks = 0;
  int? _dismissedCompletedRequestId;
  // Fast-poll burst: poll every 1s for the first N ticks after SOS is sent
  int _fastPollTicks = 0;
  // Optional callback to notify the UI that notifications should be refreshed
  VoidCallback? _onNotificationsChanged;

  // ── Live map position — updated by the map screen's GPS stream ──
  // This is the most accurate position: the exact coordinates the map is currently showing.
  double? _liveMapLat;
  double? _liveMapLng;
  int _liveMapTs = 0; // epoch ms of last update

  /// Called by the map screen every time its GPS stream emits a new position.
  /// This makes the SOS send use the SAME coordinates shown on the map.
  void updateLiveMapPosition(double lat, double lng) {
    _liveMapLat = lat;
    _liveMapLng = lng;
    _liveMapTs  = DateTime.now().millisecondsSinceEpoch;
  }


  SosState get sosState => _sosState;
  String get selectedType => _selectedType;
  String? get description => _description;
  String? get customNeighborhood => _customNeighborhood;
  RescueRequestModel? get activeRequest => _activeRequest;
  String? get errorMessage => _errorMessage;
  bool get hasActiveRequest => _activeRequest != null;
  XFile? get evidenceImage => _evidenceImage;
  List<XFile> get evidenceImages => List.unmodifiable(_evidenceImages);

  String? _popupMessage;
  String? get popupMessage => _popupMessage;

  void clearPopupMessage() {
    _popupMessage = null;
    notifyListeners();
  }

  /// Register a callback that fires when new notifications should be fetched.
  void setNotificationsChangedCallback(VoidCallback cb) {
    _onNotificationsChanged = cb;
  }

  void init(String userId) {
    _userId = userId;
    _startPolling();
  }

  void selectType(String type) {
    _selectedType = type;
    notifyListeners();
  }

  void setDescription(String desc) {
    _description = desc;
    notifyListeners();
  }

  void setCustomNeighborhood(String val) {
    _customNeighborhood = val;
    notifyListeners();
  }

  void setEvidenceImage(XFile? f) {
    _evidenceImage = f;
    // Also sync to list (for backward compat with home screen)
    if (f != null && !_evidenceImages.any((e) => e.path == f.path)) {
      if (_evidenceImages.length < 5) _evidenceImages.add(f);
    }
    notifyListeners();
  }

  void addEvidenceImage(XFile f) {
    if (_evidenceImages.length >= 5) return;
    if (!_evidenceImages.any((e) => e.path == f.path)) {
      _evidenceImages.add(f);
      _evidenceImage = _evidenceImages.first;
    }
    notifyListeners();
  }

  void removeEvidenceImage(int index) {
    if (index < 0 || index >= _evidenceImages.length) return;
    _evidenceImages.removeAt(index);
    _evidenceImage = _evidenceImages.isNotEmpty ? _evidenceImages.first : null;
    notifyListeners();
  }

  void clearEvidenceImages() {
    _evidenceImages = [];
    _evidenceImage = null;
    notifyListeners();
  }

  // ─── Send SOS ────────────────────────────────────────────────────────────────
  Future<bool> sendSos() async {
    _sosState = SosState.sending;
    _errorMessage = null;
    _dismissedCompletedRequestId = null;
    // Set placeholder request immediately so UI shows tracker right away
    _activeRequest = RescueRequestModel(
      id: 0,
      emergencyType: _selectedType,
      status: 'pending',
      lat: 0.0,
      lng: 0.0,
    );
    notifyListeners();

    if (_userId == null) {
      try {
        final prefs = await SharedPreferences.getInstance();
        final userDataStr = prefs.getString('user_data');
        if (userDataStr != null) {
          final userData = jsonDecode(userDataStr);
          _userId = userData['id']?.toString();
        } else {
          _userId = prefs.getString('user_id');
        }
      } catch (_) {}
      
      if (_userId == null) {
        _errorMessage = 'User session missing. Please restart the app.';
        _sosState = SosState.idle;
        _activeRequest = null;
        notifyListeners();
        return false;
      }
    }

    Position? pos = await _determinePosition();
    if (pos == null) {
      // Use specific error set by _determinePosition, or fallback generic message
      _errorMessage ??= 'Could not get your location. Please ensure GPS is enabled and permissions are granted.';
      _sosState = SosState.idle;
      _activeRequest = null;
      notifyListeners();
      return false;
    }

    // Update placeholder with actual location coordinates once obtained
    _activeRequest = RescueRequestModel(
      id: 0,
      emergencyType: _selectedType,
      status: 'pending',
      lat: pos.latitude,
      lng: pos.longitude,
    );
    notifyListeners();

    final isOffline = _offlineManager?.isOffline ?? false;
    if (isOffline) {
      try {
        await _offlineManager?.enqueuePendingSos(
          userId: _userId!,
          lat: pos.latitude,
          lng: pos.longitude,
          accuracy: pos.accuracy,
          emergencyType: _selectedType,
          description: _description ?? '',
        );
        _errorMessage = 'Offline mode: SOS saved. It will be sent automatically when connection is restored.';
        _sosState = SosState.idle;
        _activeRequest = null;
        notifyListeners();
        return true;
      } catch (e) {
        _errorMessage = 'Failed to queue SOS: $e';
        _sosState = SosState.idle;
        _activeRequest = null;
        notifyListeners();
        return false;
      }
    }

    // Use custom neighborhood if provided. The server will reverse geocode asynchronously in the background.
    String neighborhood = _customNeighborhood ?? '';

    try {
      final result = await ApiService.sendSos(
        userId: _userId!,
        lat: pos.latitude,
        lng: pos.longitude,
        accuracy: pos.accuracy,
        emergencyType: _selectedType,
        description: _description ?? '',
        neighborhood: neighborhood,
        evidenceImage: _evidenceImage,
        evidenceImages: _evidenceImages.isNotEmpty ? _evidenceImages : null,
      );

      if (result['status'] == 'success') {
        _sosState = SosState.active;
        _evidenceImage = null;
        _evidenceImages = [];
        _description = null;
        _popupMessage = 'SOS_SENT';
        // Grace period: ignore no_active_request for a short while
        _noRequestGraceTicks = 3;
        // Fast-poll burst: poll every 1s for the next 8 ticks to pick up status fast
        _fastPollTicks = 8;
        
        final insertedId = int.tryParse(result['id']?.toString() ?? '0') ?? 0;
        // Update placeholder request with the real ID returned from server
        _activeRequest = RescueRequestModel(
          id: insertedId,
          emergencyType: _selectedType,
          status: 'pending',
          lat: pos.latitude,
          lng: pos.longitude,
        );
        notifyListeners();
        // Switch to 1s fast-poll immediately
        _updatePollingInterval(1);
        // Immediate poll to get the real request from DB
        _fetchStatus();
        // Notify listeners to refresh notifications panel
        _onNotificationsChanged?.call();
        return true;
      } else {
        _errorMessage = result['message'] ?? 'Failed to send SOS';
        _sosState = SosState.idle;
        _activeRequest = null;
        notifyListeners();
        return false;
      }
    } catch (e) {
      _errorMessage = 'Connection Error: Unable to send SOS. Please check your network or server URL. (Error: $e)';
      _sosState = SosState.idle;
      _activeRequest = null;
      notifyListeners();
      return false;
    }
  }

  // ─── Polling ─────────────────────────────────────────────────────────────────
  int _currentPollIntervalSeconds = 6;

  void _updatePollingInterval(int seconds) {
    if (_currentPollIntervalSeconds == seconds) return;
    _currentPollIntervalSeconds = seconds;
    _pollTimer?.cancel();
    _pollTimer = Timer.periodic(Duration(seconds: seconds), (_) => _fetchStatus());
    print("SOS_POLLING_INTERVAL: Updated to $seconds seconds");
  }

  /// Called each tick during fast-poll burst to wind it down.
  void _tickFastPoll() {
    if (_fastPollTicks <= 0) return;
    _fastPollTicks--;
    if (_fastPollTicks == 0) {
      // Burst finished — return to normal active polling speed
      _updatePollingInterval(_activeRequest != null ? 3 : 8);
    }
  }

  void _startPolling() {
    _pollTimer?.cancel();
    _pollTimer =
        Timer.periodic(Duration(seconds: _currentPollIntervalSeconds), (_) => _fetchStatus());
    _fetchStatus(); // immediate first fetch
  }

  Future<void> _fetchStatus() async {
    if (_offlineManager?.isOffline == true) {
      return;
    }
    if (_userId == null) {
      try {
        final prefs = await SharedPreferences.getInstance();
        final userDataStr = prefs.getString('user_data');
        if (userDataStr != null) {
          final userData = jsonDecode(userDataStr);
          _userId = userData['id']?.toString();
        } else {
          _userId = prefs.getString('user_id');
        }
      } catch (_) {}
    }

    if (_userId == null) return;

    // If we are currently sending an SOS, DO NOT poll or update status from server.
    if (_sosState == SosState.sending) {
      print("SOS_POLLING: Currently sending SOS, skipping status fetch.");
      return;
    }

    // Wind down fast-poll burst each tick
    _tickFastPoll();

    final result = await ApiService.getRequestStatus(_userId);
    print("SOS_API_RESPONSE: $result");
    if (result['status'] == 'success') {
      final newRequest = RescueRequestModel.fromJson(result);

      // ── Guard: if this request was already dismissed by the user, stay idle ──
      if (newRequest.status == 'completed' &&
          newRequest.id == _dismissedCompletedRequestId) {
        _sosState = SosState.idle;
        _activeRequest = null;
        SoundService.stopAmbulanceSirenLoop();
        _updatePollingInterval(8);
        notifyListeners();
        return;
      }

      final justAssigned = newRequest.driverAssigned &&
          (_activeRequest != null && !_activeRequest!.driverAssigned);
          
      final justStartedTrip = newRequest.status == 'en_route' &&
          (_activeRequest != null && _activeRequest!.status != 'en_route' && _activeRequest!.status != 'arrived' && _activeRequest!.status != 'completed');

      final justDispatched = (newRequest.status == 'dispatched' || newRequest.status == 'accepted') &&
          (_activeRequest == null || (_activeRequest!.status != 'dispatched' && _activeRequest!.status != 'accepted' && _activeRequest!.status != 'en_route' && _activeRequest!.status != 'arrived' && _activeRequest!.status != 'completed'));

      final justCompleted = newRequest.status == 'completed' &&
          (_activeRequest == null || _activeRequest!.status != 'completed') &&
          newRequest.id != _dismissedCompletedRequestId;

      _activeRequest = newRequest;
      
      if (justCompleted) {
        _dismissedCompletedRequestId = newRequest.id;
        SoundService.stopAmbulanceSirenLoop();
        SoundService.playNotificationBeep();
        _popupMessage = 'INCIDENT_COMPLETED';
        _sosState = SosState.active; // keep active briefly so UI shows completed state
        final completedId = newRequest.id;
        notifyListeners();
        // Auto-reset after 4 seconds
        Future.delayed(const Duration(seconds: 4), () {
          if (_activeRequest != null && _activeRequest!.id == completedId) {
            _sosState = SosState.idle;
            _activeRequest = null;
            _updatePollingInterval(8);
            notifyListeners();
          }
        });
        return;
      } else if (justAssigned) {
        SoundService.playNotificationBeep();
        _popupMessage = 'DRIVER_ASSIGNED';
      } else if (justStartedTrip) {
        SoundService.playNotificationBeep();
        _popupMessage = 'TRIP_STARTED';
      } else if (justDispatched) {
        SoundService.playNotificationBeep();
        _popupMessage = 'DRIVER_ON_THE_WAY';
      }
      
      if (_sosState != SosState.active && _sosState != SosState.sending) {
        _sosState = SosState.active;
      }
      _updatePollingInterval(3);
      
      // Control ambulance siren loop based on actual request status
      final status = newRequest.status;
      if (status == 'arrived' || status == 'completed') {
        SoundService.stopAmbulanceSirenLoop();
      } else {
        // Continue looping wail siren if en_route/pending/dispatched/accepted
        SoundService.playAmbulanceSirenLoop();
      }

      // Continuously stream user location during active SOS if preference is enabled
      // Uses getLastKnownPosition() (instant, non-blocking) instead of
      // getCurrentPosition() which could block for up to 15 seconds.
      try {
        final prefs = await SharedPreferences.getInstance();
        final userDataStr = prefs.getString('user_data');
        bool liveSosEnabled = true;
        bool gpsAccessEnabled = true;
        if (userDataStr != null) {
          final userData = jsonDecode(userDataStr);
          liveSosEnabled = userData['live_sos_location'] == 1 || userData['live_sos_location'] == '1' || userData['live_sos_location'] == true;
          gpsAccessEnabled = userData['gps_access'] == 1 || userData['gps_access'] == '1' || userData['gps_access'] == true;
        }

        if (liveSosEnabled && gpsAccessEnabled) {
          // getLastKnownPosition is instant — no GPS hardware wait
          Position? pos = await Geolocator.getLastKnownPosition();
          if (pos != null) {
            // Only update if the cached position is recent (within 60 seconds)
            final age = DateTime.now().difference(pos.timestamp);
            if (age.inSeconds < 60) {
              await ApiService.updateUserLocation(
                _userId!,
                pos.latitude,
                pos.longitude,
              );
            }
          }
        }
      } catch (e) {
        // Silent — location update failure should never block SOS polling
      }
    } else if (result['status'] == 'no_active_request') {
      // If we just sent a SOS, give a grace period before clearing UI
      if (_noRequestGraceTicks > 0) {
        _noRequestGraceTicks--;
        print('SOS_GRACE: no_active_request received but grace ticks=$_noRequestGraceTicks, keeping UI alive');
      } else {
        if (_sosState == SosState.active) {
          _sosState = SosState.idle;
        }
        _activeRequest = null;
        _updatePollingInterval(8);
        // Stop wail siren when there is no active request
        SoundService.stopAmbulanceSirenLoop();
      }
    }
    notifyListeners();
  }

  // ─── Cancel Request ─────────────────────────────────────────────────────────
  Future<bool> cancelActiveRequest() async {
    if (_userId == null) {
      try {
        final prefs = await SharedPreferences.getInstance();
        final userDataStr = prefs.getString('user_data');
        if (userDataStr != null) {
          final userData = jsonDecode(userDataStr);
          _userId = userData['id']?.toString();
        } else {
          _userId = prefs.getString('user_id');
        }
      } catch (_) {}
    }

    if (_userId == null) return false;
    try {
      final result = await ApiService.cancelRequest(_userId!);
      if (result['status'] == 'success') {
        _sosState = SosState.idle;
        _activeRequest = null;
        _noRequestGraceTicks = 0;
        SoundService.stopAmbulanceSirenLoop();
        notifyListeners();
        return true;
      }
    } catch (_) {}
    return false;
  }

  // ─── Reset State ─────────────────────────────────────────────────────────────
  void reset() {
    _pollTimer?.cancel();
    _userId = null;
    _activeRequest = null;
    _evidenceImage = null;
    _description = null;
    _customNeighborhood = null;
    _sosState = SosState.idle;
    _errorMessage = null;
    _popupMessage = null;
    _noRequestGraceTicks = 0;
    _dismissedCompletedRequestId = null;
    SoundService.stopAmbulanceSirenLoop();
    notifyListeners();
  }

  void clearCompletedRequest() {
    if (_activeRequest != null && _activeRequest!.status == 'completed') {
      _dismissedCompletedRequestId = _activeRequest!.id;
      _sosState = SosState.idle;
      _activeRequest = null;
      _updatePollingInterval(8);
      notifyListeners();
    }
  }

  Future<void> _saveToPrefs(Position pos) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setDouble('last_lat', pos.latitude);
      await prefs.setDouble('last_lng', pos.longitude);
      // Also save timestamp so we can check freshness
      await prefs.setInt('last_lat_lng_ts', DateTime.now().millisecondsSinceEpoch);
    } catch (_) {}
  }

  /// Last-resort fallback position when all GPS sources fail.
  /// Uses SharedPreferences saved coords, then user profile coords, then Dharkenley defaults.
  Future<Position> _getFallbackPosition() async {
    double fallbackLat = 2.008;
    double fallbackLng = 45.263;
    try {
      final prefs = await SharedPreferences.getInstance();
      final savedLat = prefs.getDouble('last_lat');
      final savedLng = prefs.getDouble('last_lng');
      final savedTs  = prefs.getInt('last_lat_lng_ts');
      if (savedLat != null && savedLng != null) {
        final ageMs = savedTs != null
            ? DateTime.now().millisecondsSinceEpoch - savedTs
            : 0;
        // Use saved coords if within 2 hours
        if (ageMs < 2 * 60 * 60 * 1000) {
          fallbackLat = savedLat;
          fallbackLng = savedLng;
        }
      } else {
        final userDataStr = prefs.getString('user_data');
        if (userDataStr != null) {
          final userData = jsonDecode(userDataStr);
          fallbackLat = (userData['current_lat'] as num?)?.toDouble() ?? 2.008;
          fallbackLng = (userData['current_lng'] as num?)?.toDouble() ?? 45.263;
        }
      }
    } catch (_) {}
    print("SOS_LOCATION: Using fallback coordinates: $fallbackLat, $fallbackLng");
    return Position(
      latitude: fallbackLat,
      longitude: fallbackLng,
      timestamp: DateTime.now(),
      accuracy: 74.0,
      altitude: 0.0,
      altitudeAccuracy: 0.0,
      heading: 0.0,
      headingAccuracy: 0.0,
      speed: 0.0,
      speedAccuracy: 0.0,
    );
  }

  /// Returns the best available position for an SOS, in this priority order:
  ///   1. Fresh GPS from device (timeout 6s high → 3s medium)
  ///   2. SharedPreferences last_lat/last_lng (saved by map screen live stream — most accurate)
  ///   3. Android getLastKnownPosition() (OS cache — may be stale/from another app)
  ///   4. User profile coordinates / defaults
  Future<Position?> _determinePosition() async {
    try {
      // 0. Check if location services are enabled on the device
      bool serviceEnabled = await Geolocator.isLocationServiceEnabled();
      if (!serviceEnabled) {
        print("SOS_LOCATION: Location services are disabled on device.");
        _errorMessage = 'Location services are disabled. Please enable GPS in device settings.';
        return await _getFallbackPosition();
      }

      // 1. Check and request location permission
      LocationPermission permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        print("SOS_LOCATION: Permission denied, requesting...");
        permission = await Geolocator.requestPermission();
        if (permission == LocationPermission.denied) {
          print("SOS_LOCATION: Permission denied after request.");
          _errorMessage = 'Location permission denied. Please allow location access for this app.';
          return await _getFallbackPosition();
        }
      }
      if (permission == LocationPermission.deniedForever) {
        print("SOS_LOCATION: Permission permanently denied.");
        _errorMessage = 'Location permission is permanently denied. Please enable it in app settings.';
        return await _getFallbackPosition();
      }

      // 2. HIGHEST PRIORITY: Use live map position if fresh (map screen streams GPS continuously)
      //    This is the EXACT position shown on the map — no mismatch possible.
      if (_liveMapLat != null && _liveMapLng != null) {
        final ageMs = DateTime.now().millisecondsSinceEpoch - _liveMapTs;
        if (ageMs < 2 * 60 * 1000) { // within last 2 minutes
          print("SOS_LOCATION: Using live map position (age: ${ageMs ~/ 1000}s): $_liveMapLat, $_liveMapLng");
          return Position(
            latitude: _liveMapLat!,
            longitude: _liveMapLng!,
            timestamp: DateTime.now(),
            accuracy: 20.0, // stream accuracy is very good
            altitude: 0.0,
            altitudeAccuracy: 0.0,
            heading: 0.0,
            headingAccuracy: 0.0,
            speed: 0.0,
            speedAccuracy: 0.0,
          );
        }
      }

      // 3. Try fresh high-accuracy GPS (6 seconds timeout)
      try {
        print("SOS_LOCATION: Requesting high accuracy position...");
        final pos = await Geolocator.getCurrentPosition(
          locationSettings: const LocationSettings(
            accuracy: LocationAccuracy.high,
          ),
        ).timeout(const Duration(seconds: 6));
        print("SOS_LOCATION: Got fresh GPS: ${pos.latitude}, ${pos.longitude}, acc=${pos.accuracy}m");
        _saveToPrefs(pos);
        return pos;
      } catch (e) {
        print("SOS_LOCATION: High accuracy failed: $e. Trying medium...");
        // 3. Try medium accuracy (3 seconds timeout)
        try {
          final pos = await Geolocator.getCurrentPosition(
            locationSettings: const LocationSettings(
              accuracy: LocationAccuracy.medium,
            ),
          ).timeout(const Duration(seconds: 3));
          print("SOS_LOCATION: Got medium GPS: ${pos.latitude}, ${pos.longitude}, acc=${pos.accuracy}m");
          _saveToPrefs(pos);
          return pos;
        } catch (e2) {
          print("SOS_LOCATION: Medium GPS also failed: $e2");
        }
      }

      // 4. Use SharedPreferences coordinates saved by the map screen live stream
      //    These are the most accurate local fallback — updated every time map streams a position
      if (!kIsWeb) {
        try {
          final prefs = await SharedPreferences.getInstance();
          final savedLat = prefs.getDouble('last_lat');
          final savedLng = prefs.getDouble('last_lng');
          final savedTs  = prefs.getInt('last_lat_lng_ts');
          if (savedLat != null && savedLng != null) {
            final ageMs = savedTs != null
                ? DateTime.now().millisecondsSinceEpoch - savedTs
                : 0;
            // Accept SharedPreferences coords if saved within last 30 minutes
            if (ageMs < 30 * 60 * 1000) {
              print("SOS_LOCATION: Using SharedPreferences coords (age: ${ageMs ~/ 1000}s): $savedLat, $savedLng");
              return Position(
                latitude: savedLat,
                longitude: savedLng,
                timestamp: DateTime.now(),
                accuracy: 50.0,
                altitude: 0.0,
                altitudeAccuracy: 0.0,
                heading: 0.0,
                headingAccuracy: 0.0,
                speed: 0.0,
                speedAccuracy: 0.0,
              );
            } else {
              print("SOS_LOCATION: SharedPreferences coords too old (${ageMs ~/ 1000}s), skipping");
            }
          }
        } catch (_) {}

        // 5. Last resort: Android OS getLastKnownPosition (may be stale)
        try {
          final position = await Geolocator.getLastKnownPosition();
          if (position != null) {
            final age = DateTime.now().difference(position.timestamp);
            print("SOS_LOCATION: Android last known (age: ${age.inSeconds}s): ${position.latitude}, ${position.longitude}");
            // Accept if within 30 minutes (same threshold as SharedPreferences)
            if (age.inMinutes < 30) {
              return position;
            } else {
              print("SOS_LOCATION: Android last known too old (${age.inMinutes}min), skipping");
            }
          }
        } catch (_) {}
      }

      // 6. All GPS sources exhausted — use our best offline fallback
      print("SOS_LOCATION: All GPS sources failed, using offline fallback.");
      return await _getFallbackPosition();
    } catch (e) {
      print("SOS_LOCATION: Error determining position: $e");
      return await _getFallbackPosition();
    }
  }

  @override
  void dispose() {
    _pollTimer?.cancel();
    SoundService.stopAmbulanceSirenLoop();
    super.dispose();
  }
}
