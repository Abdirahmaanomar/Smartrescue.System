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
  XFile? _evidenceImage;
  List<XFile> _evidenceImages = [];
  RescueRequestModel? _activeRequest;
  String? _errorMessage;
  Timer? _pollTimer;
  String? _userId;
  // Grace period: number of consecutive no_active_request responses to ignore after sending SOS
  int _noRequestGraceTicks = 0;
  int? _dismissedCompletedRequestId;

  SosState get sosState => _sosState;
  String get selectedType => _selectedType;
  String? get description => _description;
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
        notifyListeners();
        return false;
      }
    }

    Position? pos = await _determinePosition();
    if (pos == null) {
      // Use specific error set by _determinePosition, or fallback generic message
      _errorMessage ??= 'Could not get your location. Please ensure GPS is enabled and permissions are granted.';
      _sosState = SosState.idle;
      notifyListeners();
      return false;
    }

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
        notifyListeners();
        return true;
      } catch (e) {
        _errorMessage = 'Failed to queue SOS: $e';
        _sosState = SosState.idle;
        notifyListeners();
        return false;
      }
    }

    try {
      final result = await ApiService.sendSos(
        userId: _userId!,
        lat: pos.latitude,
        lng: pos.longitude,
        accuracy: pos.accuracy,
        emergencyType: _selectedType,
        description: _description ?? '',
        evidenceImage: _evidenceImage,
        evidenceImages: _evidenceImages.isNotEmpty ? _evidenceImages : null,
      );

      if (result['status'] == 'success') {
        _sosState = SosState.active;
        _evidenceImage = null;
        _evidenceImages = [];
        _description = null;
        _popupMessage = 'SOS_SENT';
        // Set a grace period so fetchStatus won't clear UI if API is slow
        _noRequestGraceTicks = 5;
        // Set placeholder request immediately so UI shows tracker right away
        _activeRequest = RescueRequestModel(
          id: 0,
          emergencyType: _selectedType,
          status: 'pending',
          lat: pos.latitude,
          lng: pos.longitude,
        );
        notifyListeners();
        // Immediate poll to get the real request from DB
        await _fetchStatus();
        return true;
      } else {
        _errorMessage = result['message'] ?? 'Failed to send SOS';
        _sosState = SosState.idle;
        notifyListeners();
        return false;
      }
    } catch (e) {
      _errorMessage = 'Connection Error: Unable to send SOS. Please check your network or server URL. (Error: $e)';
      _sosState = SosState.idle;
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
          Position pos = await Geolocator.getCurrentPosition(
            locationSettings: const LocationSettings(accuracy: LocationAccuracy.high),
          ).timeout(const Duration(seconds: 5));
          
          await ApiService.updateUserLocation(
            _userId!,
            pos.latitude,
            pos.longitude,
          );
          print("SOS_LIVE_LOCATION_STREAM: Sent ${pos.latitude}, ${pos.longitude}");
        }
      } catch (e) {
        print("SOS_LIVE_LOCATION_STREAM_ERROR: $e");
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

  Future<Position> _getFallbackPosition() async {
    double fallbackLat = 2.0469;
    double fallbackLng = 45.3182;
    try {
      final prefs = await SharedPreferences.getInstance();
      final userDataStr = prefs.getString('user_data');
      if (userDataStr != null) {
        final userData = jsonDecode(userDataStr);
        fallbackLat = (userData['current_lat'] as num?)?.toDouble() ?? 2.0469;
        fallbackLng = (userData['current_lng'] as num?)?.toDouble() ?? 45.3182;
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

      // 2. Try to get last known position first (instant) - not supported on web
      if (!kIsWeb) {
        Position? position = await Geolocator.getLastKnownPosition();
        if (position != null) {
          // If last known position is recent (within 60 seconds), use it
          final age = DateTime.now().difference(position.timestamp);
          if (age.inSeconds < 60) {
            print("SOS_LOCATION: Using last known location (age: ${age.inSeconds}s)");
            return position;
          }
        }
      }

      // 3. Try high accuracy with shorter timeout if offline (8 seconds), otherwise 15 seconds
      final isOffline = _offlineManager?.isOffline ?? false;
      final timeoutDuration = isOffline ? const Duration(seconds: 8) : const Duration(seconds: 15);

      try {
        print("SOS_LOCATION: Requesting high accuracy position...");
        return await Geolocator.getCurrentPosition(
          locationSettings: const LocationSettings(
            accuracy: LocationAccuracy.high,
          ),
        ).timeout(timeoutDuration);
      } catch (e) {
        print("SOS_LOCATION: High accuracy failed or timed out: $e. Falling back to medium...");
        // 4. Fallback to medium accuracy (faster, works better indoors)
        try {
          return await Geolocator.getCurrentPosition(
            locationSettings: const LocationSettings(
              accuracy: LocationAccuracy.medium,
            ),
          ).timeout(isOffline ? const Duration(seconds: 5) : const Duration(seconds: 10));
        } catch (e2) {
          print("SOS_LOCATION: Medium accuracy also failed: $e2. Using fallback...");
          return await _getFallbackPosition();
        }
      }
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
