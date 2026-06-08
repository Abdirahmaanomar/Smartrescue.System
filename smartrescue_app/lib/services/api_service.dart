import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:image_picker/image_picker.dart';
import 'package:http_parser/http_parser.dart';
import '../constants/api_constants.dart';

class ApiService {
  static const String _cookieKey = 'session_cookie';

  // ─── Cookie Management ───────────────────────────────────────────────────────
  static Future<String?> _getCookie() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString(_cookieKey);
  }

  static Future<void> _saveCookie(http.Response response) async {
    final rawCookie = response.headers['set-cookie'];
    if (rawCookie != null) {
      final sessionCookie = rawCookie.split(';').first;
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(_cookieKey, sessionCookie);
    }
  }

  static Future<void> clearCookie() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_cookieKey);
    await prefs.remove('user_id');
    await prefs.remove('user_role');
  }

  static Future<Map<String, String>> _headers() async {
    final cookie = await _getCookie();
    final Map<String, String> headers = {
      'Cookie': cookie ?? '',
    };
    if (cookie != null) {
      String sessionId = cookie;
      if (cookie.contains('=')) {
        sessionId = cookie.split('=').last;
      }
      headers['X-Session-ID'] = sessionId;
    }
    return headers;
  }

  // ─── Auth ────────────────────────────────────────────────────────────────────
  static Future<Map<String, dynamic>> login(
      String phone, String password) async {
    try {
      final response = await http.post(
        Uri.parse(ApiConstants.login),
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: {
          'phone': phone,
          'password': password,
          'login_btn': '1',
          'flutter': '1',
        },
      ).timeout(const Duration(seconds: 15));

      await _saveCookie(response);

      // The PHP login redirects on success, so we detect by checking headers/body
      if (response.statusCode == 200 || response.statusCode == 302) {
        try {
          final data = json.decode(response.body);
          return data;
        } catch (_) {
          // Not JSON — parse the redirect or detect success
          if (response.headers['location']?.contains('/user/') == true) {
            return {'status': 'success', 'role': 'user'};
          }
          if (response.headers['location']?.contains('/driver/') == true) {
            return {'status': 'success', 'role': 'driver'};
          }
          if (response.headers['location']?.contains('/admin/') == true) {
            return {'status': 'success', 'role': 'admin'};
          }
          return {'status': 'error', 'message': 'Unexpected server response'};
        }
      }
      return {
        'status': 'error',
        'message': 'Server error: ${response.statusCode}'
      };
    } catch (e) {
      return {
        'status': 'error',
        'message': 'Connection failed: ${e.toString()}'
      };
    }
  }

  static Future<Map<String, dynamic>> register({
    required String fullname,
    required String phone,
    required String email,
    required String password,
    required String role,
    required String gender,
    required String birthDate,
  }) async {
    try {
      final response = await http.post(
        Uri.parse(ApiConstants.register),
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: {
          'fullname': fullname,
          'phone': phone,
          'email': email,
          'password': password,
          'role': role,
          'gender': gender,
          'birth_date': birthDate,
          'flutter': '1',
        },
      ).timeout(const Duration(seconds: 15));

      await _saveCookie(response);

      try {
        return json.decode(response.body);
      } catch (_) {
        if (response.headers['location'] != null) {
          return {'status': 'success', 'role': role};
        }
        return {'status': 'error', 'message': 'Registration failed'};
      }
    } catch (e) {
      return {
        'status': 'error',
        'message': 'Connection failed: ${e.toString()}'
      };
    }
  }

  static Future<void> logout() async {
    try {
      final headers = await _headers();
      await http
          .get(Uri.parse(ApiConstants.logout), headers: headers)
          .timeout(const Duration(seconds: 10));
    } catch (_) {}
    await clearCookie();
  }

  // ─── User APIs ───────────────────────────────────────────────────────────────
  static Future<Map<String, dynamic>> sendSos({
    required String userId,
    required double lat,
    required double lng,
    required double accuracy,
    required String emergencyType,
    String description = '',
    XFile? evidenceImage,
    List<XFile>? evidenceImages,
  }) async {
    try {
      final cookie = await _getCookie();
      final request =
          http.MultipartRequest('POST', Uri.parse(ApiConstants.sendSos));
      request.headers['Cookie'] = cookie ?? '';
      if (cookie != null) {
        String sessionId = cookie;
        if (cookie.contains('=')) {
          sessionId = cookie.split('=').last;
        }
        request.headers['X-Session-ID'] = sessionId;
      }
      request.fields['user_id'] = userId;
      request.fields['lat'] = lat.toString();
      request.fields['lng'] = lng.toString();
      request.fields['accuracy'] = accuracy.toString();
      request.fields['emergency_type'] = emergencyType;
      request.fields['description'] = description;

      // Use evidenceImages list if provided, else fall back to single evidenceImage
      final List<XFile> imagesToUpload = evidenceImages != null && evidenceImages.isNotEmpty
          ? evidenceImages
          : (evidenceImage != null ? [evidenceImage] : []);

      for (int i = 0; i < imagesToUpload.length; i++) {
        final img = imagesToUpload[i];
        final fieldName = i == 0 ? 'evidence_image' : 'evidence_image_$i';
        if (kIsWeb) {
          final bytes = await img.readAsBytes();
          request.files.add(http.MultipartFile.fromBytes(
            fieldName,
            bytes,
            filename: img.name,
          ));
        } else {
          request.files.add(await http.MultipartFile.fromPath(
            fieldName,
            img.path,
          ));
        }
      }

      final streamed =
          await request.send().timeout(const Duration(seconds: 30));
      final response = await http.Response.fromStream(streamed);
      return json.decode(response.body);
    } catch (e) {
      return {
        'status': 'error',
        'message': 'Failed to send SOS: ${e.toString()}'
      };
    }
  }

  static Future<Map<String, dynamic>> getRequestStatus([String? userId]) async {
    try {
      final headers = await _headers();
      String url = ApiConstants.getRequestStatus;
      if (userId != null && userId.isNotEmpty) {
        url += '?user_id=$userId';
      }
      final response = await http
          .get(
            Uri.parse(url),
            headers: headers,
          )
          .timeout(const Duration(seconds: 10));
      return json.decode(response.body);
    } catch (e) {
      return {'status': 'error'};
    }
  }

  static Future<Map<String, dynamic>> cancelRequest(String userId) async {
    try {
      final headers = await _headers();
      final response = await http
          .post(
            Uri.parse('${ApiConstants.cancelRequest}?user_id=$userId'),
            headers: headers,
          )
          .timeout(const Duration(seconds: 10));
      return json.decode(response.body);
    } catch (e) {
      return {'status': 'error', 'message': e.toString()};
    }
  }

  static Future<Map<String, dynamic>> getHistory() async {
    try {
      final headers = await _headers();

      String? userId;
      try {
        final prefs = await SharedPreferences.getInstance();
        final userDataStr = prefs.getString('user_data');
        if (userDataStr != null) {
          final userData = jsonDecode(userDataStr);
          userId = userData['id']?.toString();
        } else {
          userId = prefs.getString('user_id');
        }
      } catch (_) {}

      String url = ApiConstants.getHistory;
      if (userId != null && userId.isNotEmpty) {
        url += '?user_id=$userId';
      }

      final response = await http
          .get(Uri.parse(url), headers: headers)
          .timeout(const Duration(seconds: 10));

      // Backend returns { total_count: N, history: [...] }
      final body = json.decode(response.body);
      if (body is Map) {
        final totalCount = (body['total_count'] as num?)?.toInt() ?? 0;
        final list = (body['history'] as List?) ?? [];
        return {'total_count': totalCount, 'history': list};
      }
      // Fallback: old API returned a plain list
      final list = body as List? ?? [];
      return {'total_count': list.length, 'history': list};
    } catch (e) {
      return {'total_count': 0, 'history': []};
    }
  }

  static Future<void> updateUserLocation(
      String userId, double lat, double lng) async {
    try {
      final headers = await _headers();
      headers['Content-Type'] = 'application/x-www-form-urlencoded';
      await http.post(
        Uri.parse(ApiConstants.updateUserLocation),
        headers: headers,
        body: {'user_id': userId, 'lat': lat.toString(), 'lng': lng.toString()},
      ).timeout(const Duration(seconds: 8));
    } catch (_) {}
  }

  static Future<Map<String, dynamic>> updateProfile({
    required String userId,
    required String fullname,
    required String phone,
    required String email,
    String? birthDate,
    String? gender,
    XFile? avatar,
  }) async {
    try {

      final cookie = await _getCookie();
      final request =
          http.MultipartRequest('POST', Uri.parse(ApiConstants.userSettings));
      request.headers['Cookie'] = cookie ?? '';
      if (cookie != null) {
        String sessionId = cookie;
        if (cookie.contains('=')) {
          sessionId = cookie.split('=').last;
        }
        request.headers['X-Session-ID'] = sessionId;
      }
      request.fields['action'] = 'update_profile';
      request.fields['user_id'] = userId;
      request.fields['fullname'] = fullname;
      request.fields['phone'] = phone;
      request.fields['email'] = email;
      if (birthDate != null) request.fields['birth_date'] = birthDate;
      if (gender != null) request.fields['gender'] = gender;
      if (avatar != null) {
        if (kIsWeb) {
          final bytes = await avatar.readAsBytes();
          final extension = avatar.name.split('.').last.toLowerCase();
          String mimeType = 'image/jpeg';
          if (extension == 'png') {
            mimeType = 'image/png';
          } else if (extension == 'webp') {
            mimeType = 'image/webp';
          } else if (extension == 'gif') {
            mimeType = 'image/gif';
          }
          request.files.add(http.MultipartFile.fromBytes(
            'avatar',
            bytes,
            filename: avatar.name,
            contentType: MediaType.parse(mimeType),
          ));
        } else {
          request.files.add(await http.MultipartFile.fromPath('avatar', avatar.path));
        }
      }
      final streamed =
          await request.send().timeout(const Duration(seconds: 30));
      final response = await http.Response.fromStream(streamed);
      return json.decode(response.body);
    } catch (e) {
      return {'status': 'error', 'message': e.toString()};
    }
  }

  static Future<Map<String, dynamic>> changePassword(
      String userId, String oldPass, String newPass) async {
    try {
      final headers = await _headers();
      headers['Content-Type'] = 'application/x-www-form-urlencoded';
      final response = await http.post(
        Uri.parse(ApiConstants.userSettings),
        headers: headers,
        body: {
          'action': 'change_password',
          'old_password': oldPass,
          'new_password': newPass,
          'user_id': userId,
        },
      ).timeout(const Duration(seconds: 15));
      return json.decode(response.body);
    } catch (e) {
      return {'status': 'error', 'message': e.toString()};
    }
  }

  static Future<Map<String, dynamic>> deleteAccount(
      String userId, String password) async {
    try {
      final headers = await _headers();
      headers['Content-Type'] = 'application/x-www-form-urlencoded';
      final response = await http.post(
        Uri.parse(ApiConstants.userSettings),
        headers: headers,
        body: {
          'action': 'delete_account',
          'password': password,
          'user_id': userId,
        },
      ).timeout(const Duration(seconds: 15));
      return json.decode(response.body);
    } catch (e) {
      return {'status': 'error', 'message': e.toString()};
    }
  }

  static Future<Map<String, dynamic>> togglePreference(
      String userId, String pref, dynamic value) async {
    try {
      final headers = await _headers();
      headers['Content-Type'] = 'application/x-www-form-urlencoded';
      final response = await http.post(
        Uri.parse(ApiConstants.userSettings),
        headers: headers,
        body: {
          'action': 'toggle_preference',
          'preference': pref,
          'value': value.toString(),
          'user_id': userId,
        },
      ).timeout(const Duration(seconds: 10));
      print('togglePreference response for $pref: ${response.statusCode} - ${response.body}');
      return json.decode(response.body);
    } catch (e) {
      print('togglePreference error: $e');
      return {'status': 'error', 'message': e.toString()};
    }
  }

  static Future<Map<String, dynamic>> updateSafetyInfo(
      String userId, String medicalInfo, String emergencyContacts, {bool isBloodDonor = false, String bloodGroup = ''}) async {
    try {
      final headers = await _headers();
      headers['Content-Type'] = 'application/x-www-form-urlencoded';
      final response = await http.post(
        Uri.parse(ApiConstants.userSettings),
        headers: headers,
        body: {
          'action': 'update_safety_info',
          'medical_info': medicalInfo,
          'emergency_contacts': emergencyContacts,
          'user_id': userId,
          'is_blood_donor': isBloodDonor ? '1' : '0',
          'blood_group': bloodGroup,
        },
      ).timeout(const Duration(seconds: 10));
      return json.decode(response.body);
    } catch (e) {
      return {'status': 'error', 'message': e.toString()};
    }
  }

  static Future<List<dynamic>> getNotifications([String? userId]) async {
    try {
      final headers = await _headers();
      
      String? actualUserId = userId;
      if (actualUserId == null || actualUserId.isEmpty) {
        try {
          final prefs = await SharedPreferences.getInstance();
          final userDataStr = prefs.getString('user_data');
          if (userDataStr != null) {
            final userData = jsonDecode(userDataStr);
            actualUserId = userData['id']?.toString();
          } else {
            actualUserId = prefs.getString('user_id');
          }
        } catch (_) {}
      }

      String url = ApiConstants.getNotifications;
      if (actualUserId != null && actualUserId.isNotEmpty) {
        url += '?user_id=$actualUserId';
      }

      final response = await http.get(
        Uri.parse(url),
        headers: headers,
      ).timeout(const Duration(seconds: 10));
      if (response.statusCode == 200) {
        return json.decode(response.body);
      }
      return [];
    } catch (e) {
      return [];
    }
  }

  static Future<Map<String, dynamic>> deleteNotification(String notificationId, [String? userId]) async {
    try {
      final headers = await _headers();
      headers['Content-Type'] = 'application/x-www-form-urlencoded';

      String? actualUserId = userId;
      if (actualUserId == null || actualUserId.isEmpty) {
        try {
          final prefs = await SharedPreferences.getInstance();
          final userDataStr = prefs.getString('user_data');
          if (userDataStr != null) {
            final userData = jsonDecode(userDataStr);
            actualUserId = userData['id']?.toString();
          } else {
            actualUserId = prefs.getString('user_id');
          }
        } catch (_) {}
      }

      final response = await http.post(
        Uri.parse(ApiConstants.deleteNotification),
        headers: headers,
        body: {
          'notification_id': notificationId,
          'user_id': actualUserId ?? '',
        },
      ).timeout(const Duration(seconds: 10));

      if (response.statusCode == 200) {
        return json.decode(response.body);
      }
      return {'success': false, 'message': 'Server error: ${response.statusCode}'};
    } catch (e) {
      return {'success': false, 'message': e.toString()};
    }
  }

  static Future<List<dynamic>> getOnlineResponders() async {
    try {
      final headers = await _headers();
      final response = await http
          .get(Uri.parse(ApiConstants.getOnlineResponders), headers: headers)
          .timeout(const Duration(seconds: 10));
      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['status'] == 'success') {
          return data['data'] as List<dynamic>;
        }
      }
      return [];
    } catch (e) {
      return [];
    }
  }

  static Future<List<dynamic>> getCommunityIncidents() async {
    try {
      final headers = await _headers();
      final response = await http
          .get(Uri.parse(ApiConstants.getCommunityIncidents), headers: headers)
          .timeout(const Duration(seconds: 10));
      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['status'] == 'success') {
          return data['data'] as List<dynamic>;
        }
      }
      return [];
    } catch (e) {
      return [];
    }
  }

  static Future<Map<String, dynamic>> respondToIncident({
    required int requestId,
    required int volunteerId,
    required String action,
  }) async {
    try {
      final headers = await _headers();
      headers['Content-Type'] = 'application/x-www-form-urlencoded';
      final response = await http.post(
        Uri.parse(ApiConstants.respondToIncident),
        headers: headers,
        body: {
          'request_id': requestId.toString(),
          'volunteer_id': volunteerId.toString(),
          'action': action,
        },
      ).timeout(const Duration(seconds: 10));
      return json.decode(response.body);
    } catch (e) {
      return {'status': 'error', 'message': e.toString()};
    }
  }

  static Future<Map<String, dynamic>> getAnalytics(String userId) async {
    try {
      final headers = await _headers();
      final url = '${ApiConstants.getAnalytics}?user_id=$userId';
      final response = await http
          .get(Uri.parse(url), headers: headers)
          .timeout(const Duration(seconds: 10));
      if (response.statusCode == 200) {
        return json.decode(response.body);
      }
      return {};
    } catch (e) {
      return {};
    }
  }

  static Future<List<dynamic>> getBloodDonors() async {
    try {
      final headers = await _headers();
      final response = await http
          .get(Uri.parse(ApiConstants.getBloodDonors), headers: headers)
          .timeout(const Duration(seconds: 10));
      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['success'] == true) {
          return data['donors'] as List<dynamic>;
        }
      }
      return [];
    } catch (e) {
      return [];
    }
  }

  static Future<Map<String, dynamic>> getProfile(String userId) async {
    try {
      final headers = await _headers();
      final response = await http
          .get(
            Uri.parse('${ApiConstants.getProfile}?user_id=$userId'),
            headers: headers,
          )
          .timeout(const Duration(seconds: 10));
      if (response.statusCode == 200) {
        return json.decode(response.body);
      }
      return {'status': 'error', 'message': 'Server returned status ${response.statusCode}'};
    } catch (e) {
      return {'status': 'error', 'message': e.toString()};
    }
  }
}

