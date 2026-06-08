import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../models/user_model.dart';
import '../services/api_service.dart';

enum AuthStatus { initial, authenticated, unauthenticated, loading }

class AuthProvider extends ChangeNotifier {
  AuthStatus _status = AuthStatus.initial;
  UserModel? _user;
  String? _errorMessage;

  AuthStatus get status => _status;
  UserModel? get user => _user;
  String? get errorMessage => _errorMessage;
  bool get isLoggedIn => _status == AuthStatus.authenticated && _user != null;

  // ─── Restore session on app start ────────────────────────────────────────────
  Future<void> checkSession() async {
    _status = AuthStatus.loading;
    notifyListeners();

    final prefs = await SharedPreferences.getInstance();
    final userId = prefs.getString('user_id');
    final cachedJson = prefs.getString('user_data');

    if (userId != null && cachedJson != null) {
      try {
        final Map<String, dynamic> userData = json.decode(cachedJson);
        _user = UserModel.fromJson(userData);
        _status = AuthStatus.authenticated;
        notifyListeners();
        // Silent refresh from server in background
        refreshFromServer();
        return;
      } catch (_) {
        _status = AuthStatus.unauthenticated;
      }
    } else {
      _status = AuthStatus.unauthenticated;
    }
    notifyListeners();
  }

  // ─── Login ───────────────────────────────────────────────────────────────────
  Future<bool> login(String phone, String password) async {
    _status = AuthStatus.loading;
    _errorMessage = null;
    notifyListeners();

    final result = await ApiService.login(phone, password);

    if (result['status'] == 'success') {
      final userData = <String, dynamic>{
        'id': result['id'] ?? 0,
        'fullname': result['fullname'] ?? '',
        'phone': phone,
        'email': result['email'] ?? '',
        'role': result['role'] ?? 'user',
        'profile_image': result['profile_image'] ?? '',
        'dark_mode': result['dark_mode'] ?? 0,
        'current_lat': result['current_lat'] ?? 2.0469,
        'current_lng': result['current_lng'] ?? 45.3182,
        'notifications_enabled': result['notifications_enabled'] ?? 1,
        'vibration_enabled': result['vibration_enabled'] ?? 1,
        'gps_enabled': result['gps_enabled'] ?? 1,
        'medical_info': result['medical_info'] ?? '',
        'emergency_contacts': result['emergency_contacts'] ?? '',
        'language': result['language'] ?? 'en',
        'is_volunteer': result['is_volunteer'] ?? 0,
      };

      _user = UserModel.fromJson(userData);
      _status = AuthStatus.authenticated;

      final prefs = await SharedPreferences.getInstance();
      await prefs.setString('user_id', _user!.id.toString());
      await prefs.setString('user_data', json.encode(userData));

      notifyListeners();
      return true;
    } else {
      _errorMessage = result['message'] ?? 'Login failed. Please check your credentials.';
      _status = AuthStatus.unauthenticated;
      notifyListeners();
      return false;
    }
  }

  // ─── Register ────────────────────────────────────────────────────────────────
  Future<bool> register({
    required String fullname,
    required String phone,
    required String email,
    required String password,
    required String role,
    required String gender,
    required String birthDate,
  }) async {
    _status = AuthStatus.loading;
    _errorMessage = null;
    notifyListeners();

    final result = await ApiService.register(
      fullname: fullname,
      phone: phone,
      email: email,
      password: password,
      role: role,
      gender: gender,
      birthDate: birthDate,
    );

    if (result['status'] == 'success') {
      final userData = <String, dynamic>{
        'id': result['id'] ?? 0,
        'fullname': fullname,
        'phone': phone,
        'email': email,
        'role': result['role'] ?? role,
        'profile_image': '',
        'dark_mode': 0,
        'current_lat': 2.0469,
        'current_lng': 45.3182,
        'is_volunteer': 0,
      };
      _user = UserModel.fromJson(userData);
      _status = AuthStatus.authenticated;

      final prefs = await SharedPreferences.getInstance();
      await prefs.setString('user_id', _user!.id.toString());
      await prefs.setString('user_data', json.encode(userData));

      notifyListeners();
      return true;
    } else {
      _errorMessage = result['message'] ?? 'Registration failed.';
      _status = AuthStatus.unauthenticated;
      notifyListeners();
      return false;
    }
  }

  // ─── Update local user data ───────────────────────────────────────────────────
  Future<void> updateUser(UserModel updated) async {
    _user = updated;
    final prefs = await SharedPreferences.getInstance();
    final map = <String, dynamic>{
      'id': updated.id,
      'fullname': updated.fullname,
      'phone': updated.phone,
      'email': updated.email,
      'role': updated.role,
      'profile_image': updated.profileImage,
      'dark_mode': updated.darkMode ? 1 : 0,
      'current_lat': updated.currentLat,
      'current_lng': updated.currentLng,
      'notifications_enabled': updated.notificationsEnabled ? 1 : 0,
      'vibration_enabled': updated.vibrationEnabled ? 1 : 0,
      'gps_enabled': updated.gpsEnabled ? 1 : 0,
      'share_live_location': updated.shareLiveLocation ? 1 : 0,
      'location_history': updated.locationHistory ? 1 : 0,
      'gps_access': updated.gpsAccess ? 1 : 0,
      'live_sos_location': updated.liveSosLocation ? 1 : 0,
      'medical_info': updated.medicalInfo,
      'emergency_contacts': updated.emergencyContacts,
      'language': updated.language,
      'is_volunteer': updated.isVolunteer ? 1 : 0,
    };
    await prefs.setString('user_data', json.encode(map));
    notifyListeners();
  }

  // ─── Logout ──────────────────────────────────────────────────────────────────
  Future<void> logout() async {
    await ApiService.logout();
    _user = null;
    _status = AuthStatus.unauthenticated;
    notifyListeners();
  }

  // ─── Refresh from server ──────────────────────────────────────────────────────
  Future<bool> refreshFromServer() async {
    if (_user == null) return false;
    final userId = _user!.id.toString();
    final result = await ApiService.getProfile(userId);
    if (result['status'] == 'success') {
      final userData = <String, dynamic>{
        'id': result['id'] ?? _user!.id,
        'fullname': result['fullname'] ?? _user!.fullname,
        'phone': result['phone'] ?? _user!.phone,
        'email': result['email'] ?? _user!.email,
        'role': result['role'] ?? _user!.role,
        'profile_image': result['profile_image'] ?? _user!.profileImage,
        'dark_mode': result['dark_mode'] ?? (_user!.darkMode ? 1 : 0),
        'current_lat': result['current_lat'] ?? _user!.currentLat,
        'current_lng': result['current_lng'] ?? _user!.currentLng,
        'notifications_enabled': result['notifications_enabled'] ?? (_user!.notificationsEnabled ? 1 : 0),
        'vibration_enabled': result['vibration_enabled'] ?? (_user!.vibrationEnabled ? 1 : 0),
        'gps_enabled': result['gps_enabled'] ?? (_user!.gpsEnabled ? 1 : 0),
        'share_live_location': result['share_live_location'] ?? (_user!.shareLiveLocation ? 1 : 0),
        'location_history': result['location_history'] ?? (_user!.locationHistory ? 1 : 0),
        'gps_access': result['gps_access'] ?? (_user!.gpsAccess ? 1 : 0),
        'live_sos_location': result['live_sos_location'] ?? (_user!.liveSosLocation ? 1 : 0),
        'medical_info': result['medical_info'] ?? _user!.medicalInfo,
        'emergency_contacts': result['emergency_contacts'] ?? _user!.emergencyContacts,
        'language': result['language'] ?? _user!.language,
        'is_volunteer': result['is_volunteer'] ?? (_user!.isVolunteer ? 1 : 0),
      };
      _user = UserModel.fromJson(userData);
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString('user_data', json.encode(userData));
      notifyListeners();
      return true;
    }
    return false;
  }
}
