// import 'package:flutter/foundation.dart';

// class ApiConstants {
//   // ─── Base URL ────────────────────────────────────────────────────────────────
//   static String get baseUrl {
//     if (kIsWeb) {
//       return 'http://localhost/SmartRescueApp/smartrescue';
//     } else if (defaultTargetPlatform == TargetPlatform.android) {
//       return 'http://172.20.10.3/SmartRescueApp/smartrescue';
//     } else {
//       return 'http://localhost/SmartRescueApp/smartrescue';
//     }
//   }

//   // ─── Auth ────────────────────────────────────────────────────────────────────
//   static String get login    => '$baseUrl/auth/login.php';
//   static String get register => '$baseUrl/auth/register.php';
//   static String get logout   => '$baseUrl/auth/logout.php';

//   // ─── User APIs ───────────────────────────────────────────────────────────────
//   static String get sendSos           => '$baseUrl/api/user/send_sos.php';
//   static String get getRequestStatus  => '$baseUrl/api/user/get_request_status.php';
//   static String get getHistory        => '$baseUrl/api/user/get_history.php';
//   static String get updateUserLocation=> '$baseUrl/api/user/update_user_location.php';
//   static String get userSettings      => '$baseUrl/api/user/user_settings.php';
//   static String get uploadAvatar      => '$baseUrl/api/user/upload_avatar.php';
//   static String get getNotifications  => '$baseUrl/api/user/get_notifications.php';
//   static String get deleteNotification => '$baseUrl/api/user/delete_notification.php';
//   static String get cancelRequest         => '$baseUrl/api/user/cancel_request.php';
//   static String get getOnlineResponders   => '$baseUrl/api/user/get_online_responders.php';
//   static String get getCommunityIncidents => '$baseUrl/api/user/get_community_incidents.php';
//   static String get respondToIncident     => '$baseUrl/api/user/respond_to_incident.php';
//   static String get getAnalytics          => '$baseUrl/api/user/get_analytics.php';
//   static String get getBloodDonors        => '$baseUrl/api/user/get_blood_donors.php';
//   static String get getProfile            => '$baseUrl/api/user/get_profile.php';

//   // ─── Uploads base ────────────────────────────────────────────────────────────
//   static String get uploadsBase => '$baseUrl/uploads';

//   // ─── Avatar image URL via proxy (bypasses CORS for Flutter Web) ──────────────
//   static String avatarUrl(String profileImagePath) {
//     if (profileImagePath.isEmpty) return '';
//     // Use PHP proxy to serve image with proper CORS headers
//     final encoded = Uri.encodeComponent(profileImagePath);
//     return '$baseUrl/image_proxy.php?path=$encoded&v=${profileImagePath.hashCode}';
//   }
// }







import 'package:flutter/foundation.dart';

class ApiConstants {
  // Laptop IP Address
  static String get baseUrl {
    if (kIsWeb) {
      return 'http://localhost/SmartRescueApp/smartrescue';
    } else if (defaultTargetPlatform == TargetPlatform.android) {
      return 'http://172.20.10.3/SmartRescueApp/smartrescue';
    } else {
      return 'http://localhost/SmartRescueApp/smartrescue';
    }
  }

  // Authentication
  static String get login => '$baseUrl/auth/login.php';
  static String get register => '$baseUrl/auth/register.php';
  static String get logout => '$baseUrl/auth/logout.php';

  // User APIs
  static String get sendSos => '$baseUrl/api/user/send_sos.php';
  static String get getRequestStatus => '$baseUrl/api/user/get_request_status.php';
  static String get getHistory => '$baseUrl/api/user/get_history.php';
  static String get updateUserLocation => '$baseUrl/api/user/update_user_location.php';
  static String get userSettings => '$baseUrl/api/user/user_settings.php';
  static String get uploadAvatar => '$baseUrl/api/user/upload_avatar.php';
  static String get getNotifications => '$baseUrl/api/user/get_notifications.php';
  static String get deleteNotification => '$baseUrl/api/user/delete_notification.php';
  static String get cancelRequest => '$baseUrl/api/user/cancel_request.php';
  static String get getOnlineResponders => '$baseUrl/api/user/get_online_responders.php';
  static String get getCommunityIncidents => '$baseUrl/api/user/get_community_incidents.php';
  static String get respondToIncident => '$baseUrl/api/user/respond_to_incident.php';
  static String get getAnalytics => '$baseUrl/api/user/get_analytics.php';
  static String get getBloodDonors => '$baseUrl/api/user/get_blood_donors.php';
  static String get getProfile => '$baseUrl/api/user/get_profile.php';

  // Uploads
  static String get uploadsBase => '$baseUrl/uploads';

  // Avatar URL
  static String avatarUrl(String profileImagePath) {
    if (profileImagePath.isEmpty) return '';

    final encoded = Uri.encodeComponent(profileImagePath);

    return '$baseUrl/image_proxy.php?path=$encoded&v=${profileImagePath.hashCode}';
  }
}