import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import 'package:flutter/foundation.dart' show kIsWeb, debugPrint;
import 'package:image_picker/image_picker.dart';
import 'package:http_parser/http_parser.dart';
import '../constants/api_constants.dart';
import 'offline_manager.dart';

class ApiService {
  static const String _cookieKey = 'session_cookie';

  // ─── Cookie Management ───────────────────────────────────────────────────────
  static Future<String?> _getCookie() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString(_cookieKey);
  }

  static Future<String?> _getUserId() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString('user_id');
  }

  static Future<void> _saveCookie(http.Response response) async {
    final rawCookie = response.headers['set-cookie'];
    final prefs = await SharedPreferences.getInstance();
    debugPrint('[ApiService] _saveCookie headers: ${response.headers}');
    debugPrint('[ApiService] _saveCookie body: ${response.body}');
    if (rawCookie != null) {
      final sessionCookie = rawCookie.split(';').first;
      await prefs.setString(_cookieKey, sessionCookie);
    } else {
      // Fallback for Flutter Web where raw set-cookie header is hidden by browser
      try {
        final data = json.decode(response.body);
        if (data['session_id'] != null) {
          final String sid = data['session_id'].toString();
          await prefs.setString(_cookieKey, 'PHPSESSID=$sid');
        }
      } catch (_) {}
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

  static String _formatException(dynamic e) {
    final str = e.toString();
    if (str.contains('TimeoutException') || str.contains('Future not completed')) {
      return 'Server response timed out. Please check your network connection or server IP.';
    }
    if (str.contains('SocketException') || str.contains('Failed host lookup') || str.contains('Connection refused')) {
      return 'Unable to reach server. Please check your internet connection or server IP.';
    }
    return 'Connection failed. Please check your network connection and retry.';
  }

  // ─── Auth ────────────────────────────────────────────────────────────────────
  static Future<Map<String, dynamic>> login(
      String phoneOrEmail, String password) async {
    try {
      final response = await http.post(
        Uri.parse(ApiConstants.login),
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: {
          'phone_or_email': phoneOrEmail,
          'phone': phoneOrEmail,
          'password': password,
          'login_btn': '1',
          'flutter': '1',
        },
      ).timeout(const Duration(seconds: 45));

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
      OfflineManager.instance?.setOffline();
      return {
        'status': 'error',
        'message': _formatException(e)
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
      ).timeout(const Duration(seconds: 45));

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
      OfflineManager.instance?.setOffline();
      return {
        'status': 'error',
        'message': _formatException(e)
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

  // ─── Forgot Password ─────────────────────────────────────────────────────────
  /// Step 1: verify account exists by email or phone
  static Future<Map<String, dynamic>> forgotPasswordVerify(
      String identifier) async {
    try {
      final response = await http.post(
        Uri.parse(ApiConstants.forgotPassword),
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: {
          'action': 'verify',
          'identifier': identifier,
        },
      ).timeout(const Duration(seconds: 20));
      return json.decode(response.body);
    } catch (e) {
      return {'status': 'error', 'message': _formatException(e)};
    }
  }

  /// Step 2: reset password for verified account
  static Future<Map<String, dynamic>> forgotPasswordReset({
    required String identifier,
    required String newPassword,
  }) async {
    try {
      final response = await http.post(
        Uri.parse(ApiConstants.forgotPassword),
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: {
          'action': 'reset',
          'identifier': identifier,
          'new_password': newPassword,
        },
      ).timeout(const Duration(seconds: 20));
      return json.decode(response.body);
    } catch (e) {
      return {'status': 'error', 'message': _formatException(e)};
    }
  }

  // ─── User APIs ───────────────────────────────────────────────────────────────

  /// Returns the Mogadishu district name for a given coordinate.
  /// Used as a precise fallback when Nominatim gives wrong/generic results.
  static String _mogadishuDistrictFromCoords(double lat, double lng) {
    // Bounding boxes for each major Mogadishu district
    // Format: [latMin, latMax, lngMin, lngMax, districtName]
    const districts = [
      // Hodan
      [2.037, 2.048, 45.290, 45.320, 'Hodan'],
      // Hamar Weyne  
      [2.025, 2.040, 45.335, 45.360, 'Hamar Weyne'],
      // Hamar Jajab
      [2.035, 2.050, 45.320, 45.345, 'Hamar Jajab'],
      // Waaberi
      [2.025, 2.040, 45.315, 45.340, 'Waaberi'],
      // Howlwadaag
      [2.025, 2.040, 45.295, 45.320, 'Howlwadaag'],
      // Wadajir
      [2.008, 2.030, 45.280, 45.320, 'Wadajir'],
      // Dharkenley
      [1.985, 2.015, 45.230, 45.285, 'Dharkenley'],
      // Karan
      [2.040, 2.070, 45.295, 45.340, 'Karan'],
      // Yaqshid
      [2.055, 2.080, 45.315, 45.355, 'Yaqshid'],
      // Bondhere
      [2.040, 2.060, 45.335, 45.360, 'Bondhere'],
      // Wardhigley
      [2.015, 2.035, 45.270, 45.300, 'Wardhigley'],
      // Daynile
      [2.048, 2.090, 45.225, 45.290, 'Daynile'],
      // Waberi (alt spelling)
      [2.020, 2.035, 45.340, 45.365, 'Waaberi'],
      // Shangani
      [2.045, 2.060, 45.335, 45.360, 'Shangani'],
    ];

    for (final d in districts) {
      final latMin = d[0] as double;
      final latMax = d[1] as double;
      final lngMin = d[2] as double;
      final lngMax = d[3] as double;
      final name   = d[4] as String;
      if (lat >= latMin && lat <= latMax && lng >= lngMin && lng <= lngMax) {
        return name;
      }
    }
    return ''; // outside mapped area
  }

  /// Cleans a Nominatim result — removes generic values like country/city names
  /// that are not useful as neighborhood labels.
  static bool _isGenericPlaceName(String name) {
    const genericNames = {
      'somalia', 'muqdisho', 'mogadishu', 'xamar', 'banaadir',
      'benadir', 'somali national university', 'mogadishu university',
    };
    return genericNames.contains(name.toLowerCase());
  }

  static Future<String> reverseGeocode(double lat, double lng) async {
    // Guard: validate coordinates are in Mogadishu area
    if (lat < 1.5 || lat > 2.5 || lng < 44.5 || lng > 46.0) {
      return _mogadishuDistrictFromCoords(lat, lng);
    }

    String nominatimResult = '';
    try {
      final uri = Uri.parse(
        'https://nominatim.openstreetmap.org/reverse?format=json&lat=$lat&lon=$lng&zoom=16&addressdetails=1&namedetails=1',
      );
      final res = await http.get(uri, headers: {
        'Accept-Language': 'en',
        'User-Agent': 'SmartRescueApp/1.0 (emergency dispatch)'
      }).timeout(const Duration(seconds: 6));

      if (res.statusCode == 200) {
        final data = jsonDecode(res.body);
        final a = data['address'] as Map<String, dynamic>? ?? {};

        // 1. POI name (top-level Nominatim name field)
        final String poiName = data['name']?.toString().trim() ?? '';

        // 2. namedetails fallback
        String nameDetailsName = '';
        final nameDetails = data['namedetails'] as Map<String, dynamic>?;
        if (nameDetails != null) {
          nameDetailsName = (nameDetails['name'] ?? nameDetails['name:en'] ?? '').toString().trim();
        }
        final String bestPoiName = poiName.isNotEmpty && !_isGenericPlaceName(poiName)
            ? poiName
            : (nameDetailsName.isNotEmpty && !_isGenericPlaceName(nameDetailsName)
                ? nameDetailsName
                : '');

        // 3. Neighbourhood/suburb — prefer fine-grained keys first
        final String neighbourhood = (a['neighbourhood'] ?? a['suburb'] ?? a['quarter'] ?? '').toString().trim();
        final String district = (a['district'] ?? a['city_district'] ?? '').toString().trim();

        // Use the finest-grained area name we can find
        String areaName = neighbourhood.isNotEmpty ? neighbourhood
            : (district.isNotEmpty ? district : '');

        // Filter out generic/unhelpful area names
        if (_isGenericPlaceName(areaName)) areaName = '';

        // 4. Build label — POI + area, or just area, or just POI
        if (bestPoiName.isNotEmpty && areaName.isNotEmpty) {
          if (bestPoiName.toLowerCase() == areaName.toLowerCase()) {
            nominatimResult = areaName;
          } else {
            nominatimResult = '$bestPoiName, $areaName';
          }
        } else if (areaName.isNotEmpty) {
          nominatimResult = areaName;
        } else if (bestPoiName.isNotEmpty) {
          nominatimResult = bestPoiName;
        }
      }
    } catch (_) {}

    // If Nominatim gave a good result, use it
    if (nominatimResult.isNotEmpty && !_isGenericPlaceName(nominatimResult)) {
      return nominatimResult;
    }

    // Fallback: use precise coordinate-to-district mapping for Mogadishu
    final String districtFallback = _mogadishuDistrictFromCoords(lat, lng);
    return districtFallback;
  }

  static Future<Map<String, dynamic>> sendSos({
    required String userId,
    required double lat,
    required double lng,
    required double accuracy,
    required String emergencyType,
    String description = '',
    String neighborhood = '',
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
      request.fields['neighborhood'] = neighborhood;

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
      OfflineManager.instance?.setOffline();
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
      OfflineManager.instance?.setOffline();
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

      final prefs = await SharedPreferences.getInstance();
      await prefs.setString('cached_history', response.body);

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
      OfflineManager.instance?.setOffline();
      try {
        final prefs = await SharedPreferences.getInstance();
        final cached = prefs.getString('cached_history');
        if (cached != null) {
          final body = json.decode(cached);
          if (body is Map) {
            final totalCount = (body['total_count'] as num?)?.toInt() ?? 0;
            final list = (body['history'] as List?) ?? [];
            return {'total_count': totalCount, 'history': list};
          }
          final list = body as List? ?? [];
          return {'total_count': list.length, 'history': list};
        }
      } catch (_) {}
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
      OfflineManager.instance?.setOffline();
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
      OfflineManager.instance?.setOffline();
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
      OfflineManager.instance?.setOffline();
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
      OfflineManager.instance?.setOffline();
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
      OfflineManager.instance?.setOffline();
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
        final prefs = await SharedPreferences.getInstance();
        await prefs.setString('cached_notifications', response.body);
        return json.decode(response.body);
      }
      return [];
    } catch (e) {
      try {
        final prefs = await SharedPreferences.getInstance();
        final cached = prefs.getString('cached_notifications');
        if (cached != null) {
          return json.decode(cached);
        }
      } catch (_) {}
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
      OfflineManager.instance?.setOffline();
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
          final prefs = await SharedPreferences.getInstance();
          await prefs.setString('cached_online_responders', response.body);
          return data['data'] as List<dynamic>;
        }
      }
      return [];
    } catch (e) {
      try {
        final prefs = await SharedPreferences.getInstance();
        final cached = prefs.getString('cached_online_responders');
        if (cached != null) {
          final data = json.decode(cached);
          if (data['status'] == 'success') {
            return data['data'] as List<dynamic>;
          }
        }
      } catch (_) {}
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
          final prefs = await SharedPreferences.getInstance();
          await prefs.setString('cached_community_incidents', response.body);
          return data['data'] as List<dynamic>;
        }
      }
      return [];
    } catch (e) {
      try {
        final prefs = await SharedPreferences.getInstance();
        final cached = prefs.getString('cached_community_incidents');
        if (cached != null) {
          final data = json.decode(cached);
          if (data['status'] == 'success') {
            return data['data'] as List<dynamic>;
          }
        }
      } catch (_) {}
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
      OfflineManager.instance?.setOffline();
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
      OfflineManager.instance?.setOffline();
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
          final prefs = await SharedPreferences.getInstance();
          await prefs.setString('cached_blood_donors', response.body);
          return data['donors'] as List<dynamic>;
        }
      }
      return [];
    } catch (e) {
      OfflineManager.instance?.setOffline();
      try {
        final prefs = await SharedPreferences.getInstance();
        final cached = prefs.getString('cached_blood_donors');
        if (cached != null) {
          final data = json.decode(cached);
          if (data['success'] == true) {
            return data['donors'] as List<dynamic>;
          }
        }
      } catch (_) {}
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
        final data = json.decode(response.body);
        final prefs = await SharedPreferences.getInstance();
        await prefs.setString('cached_profile_$userId', response.body);
        return data;
      }
      return {'status': 'error', 'message': 'Server returned status ${response.statusCode}'};
    } catch (e) {
      OfflineManager.instance?.setOffline();
      try {
        final prefs = await SharedPreferences.getInstance();
        final cached = prefs.getString('cached_profile_$userId');
        if (cached != null) {
          return json.decode(cached) as Map<String, dynamic>;
        }
      } catch (_) {}
      return {'status': 'error', 'message': e.toString()};
    }
  }

  // ─── Driver APIs ─────────────────────────────────────────────────────────────
  static Future<Map<String, dynamic>> getDriverHistory() async {
    try {
      final headers = await _headers();
      final userId = await _getUserId();
      final String url = userId != null
          ? '${ApiConstants.getDriverHistory}?driver_id=$userId'
          : ApiConstants.getDriverHistory;

      final response = await http
          .get(Uri.parse(url), headers: headers)
          .timeout(const Duration(seconds: 10));
      if (response.statusCode == 200) {
        return json.decode(response.body);
      }
      return {'status': 'error', 'message': 'HTTP ${response.statusCode}'};
    } catch (e) {
      OfflineManager.instance?.setOffline();
      return {'status': 'error', 'message': e.toString()};
    }
  }

  static Future<Map<String, dynamic>> getPendingSos() async {
    try {
      final headers = await _headers();
      final userId = await _getUserId();
      final String url = userId != null
          ? '${ApiConstants.getPendingSos}?driver_id=$userId'
          : ApiConstants.getPendingSos;

      final response = await http
          .get(Uri.parse(url), headers: headers)
          .timeout(const Duration(seconds: 10));
      if (response.statusCode == 200) {
        return json.decode(response.body);
      }
      return {'status': 'error', 'message': 'HTTP ${response.statusCode}'};
    } catch (e) {
      OfflineManager.instance?.setOffline();
      return {'status': 'error', 'message': e.toString()};
    }
  }

  static Future<Map<String, dynamic>> getActiveJob() async {
    try {
      final headers = await _headers();
      final userId = await _getUserId();
      final String url = userId != null
          ? '${ApiConstants.getActiveJob}?driver_id=$userId'
          : ApiConstants.getActiveJob;

      final response = await http
          .get(Uri.parse(url), headers: headers)
          .timeout(const Duration(seconds: 10));
      if (response.statusCode == 200) {
        return json.decode(response.body);
      }
      return {'status': 'error', 'message': 'HTTP ${response.statusCode}'};
    } catch (e) {
      OfflineManager.instance?.setOffline();
      return {'status': 'error', 'message': e.toString()};
    }
  }

  static Future<Map<String, dynamic>> getVictimLocation() async {
    try {
      final headers = await _headers();
      final userId = await _getUserId();
      final String url = userId != null
          ? '${ApiConstants.getVictimLocation}?driver_id=$userId'
          : ApiConstants.getVictimLocation;

      final response = await http
          .get(Uri.parse(url), headers: headers)
          .timeout(const Duration(seconds: 10));
      if (response.statusCode == 200) {
        return json.decode(response.body);
      }
      return {'status': 'error', 'message': 'HTTP ${response.statusCode}'};
    } catch (e) {
      OfflineManager.instance?.setOffline();
      return {'status': 'error', 'message': e.toString()};
    }
  }

  static Future<Map<String, dynamic>> updateDriverLocation(double lat, double lng) async {
    try {
      final headers = await _headers();
      final userId = await _getUserId();
      final response = await http
          .post(
            Uri.parse(ApiConstants.updateDriverLocation),
            headers: {
              ...headers,
              'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: {
              'lat': lat.toString(),
              'lng': lng.toString(),
              if (userId != null) 'driver_id': userId,
            },
          )
          .timeout(const Duration(seconds: 10));
      if (response.statusCode == 200) {
        return json.decode(response.body);
      }
      return {'status': 'error', 'message': 'HTTP ${response.statusCode}'};
    } catch (e) {
      OfflineManager.instance?.setOffline();
      return {'status': 'error', 'message': e.toString()};
    }
  }

  static Future<Map<String, dynamic>> updateStatus(int requestId, int unitId, String action) async {
    try {
      final headers = await _headers();
      final userId = await _getUserId();
      final response = await http
          .post(
            Uri.parse(ApiConstants.updateStatus),
            headers: {
              ...headers,
              'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: {
              'request_id': requestId.toString(),
              'unit_id': unitId.toString(),
              'action': action,
              if (userId != null) 'driver_id': userId,
            },
          )
          .timeout(const Duration(seconds: 10));
      if (response.statusCode == 200) {
        return json.decode(response.body);
      }
      return {'status': 'error', 'message': 'HTTP ${response.statusCode}'};
    } catch (e) {
      OfflineManager.instance?.setOffline();
      return {'status': 'error', 'message': e.toString()};
    }
  }

  static Future<Map<String, dynamic>> updateUnitStatus(String status) async {
    try {
      final headers = await _headers();
      final userId = await _getUserId();
      final response = await http
          .post(
            Uri.parse(ApiConstants.updateUnitStatus),
            headers: {
              ...headers,
              'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: {
              'status': status,
              if (userId != null) 'driver_id': userId,
            },
          )
          .timeout(const Duration(seconds: 10));
      if (response.statusCode == 200) {
        return json.decode(response.body);
      }
      return {'status': 'error', 'message': 'HTTP ${response.statusCode}'};
    } catch (e) {
      OfflineManager.instance?.setOffline();
      return {'status': 'error', 'message': e.toString()};
    }
  }
}

