class UserModel {
  final int id;
  final String fullname;
  final String phone;
  final String email;
  final String role;
  final String profileImage;
  final bool darkMode;
  final double currentLat;
  final double currentLng;
  final bool notificationsEnabled;
  final bool vibrationEnabled;
  final bool gpsEnabled;
  final bool shareLiveLocation;
  final bool locationHistory;
  final bool gpsAccess;
  final bool liveSosLocation;
  final String language;
  final String medicalInfo;
  final String emergencyContacts;
  final bool isVolunteer;
  final String birthDate;
  final String gender;

  const UserModel({
    required this.id,
    required this.fullname,
    required this.phone,
    required this.email,
    required this.role,
    this.profileImage = '',
    this.darkMode = false,
    this.currentLat = 2.0469,
    this.currentLng = 45.3182,
    this.notificationsEnabled = true,
    this.vibrationEnabled = true,
    this.gpsEnabled = true,
    this.shareLiveLocation = true,
    this.locationHistory = false,
    this.gpsAccess = true,
    this.liveSosLocation = true,
    this.language = 'en',
    this.medicalInfo = '',
    this.emergencyContacts = '',
    this.isVolunteer = false,
    this.birthDate = '',
    this.gender = '',
  });

  factory UserModel.fromJson(Map<String, dynamic> json) {
    return UserModel(
      id: int.tryParse(json['id'].toString()) ?? 0,
      fullname: json['fullname'] ?? '',
      phone: json['phone'] ?? '',
      email: json['email'] ?? '',
      role: json['role'] ?? 'user',
      profileImage: json['profile_image'] ?? '',
      darkMode: (json['dark_mode'] == 1 || json['dark_mode'] == '1' || json['dark_mode'] == true),
      currentLat: double.tryParse(json['current_lat']?.toString() ?? '') ?? 2.0469,
      currentLng: double.tryParse(json['current_lng']?.toString() ?? '') ?? 45.3182,
      notificationsEnabled: _parseBool(json['notifications_enabled'], true),
      vibrationEnabled: _parseBool(json['vibration_enabled'], true),
      gpsEnabled: _parseBool(json['gps_enabled'], true),
      shareLiveLocation: _parseBool(json['share_live_location'], true),
      locationHistory: _parseBool(json['location_history'], false),
      gpsAccess: _parseBool(json['gps_access'], true),
      liveSosLocation: _parseBool(json['live_sos_location'], true),
      language: json['language'] ?? 'en',
      medicalInfo: json['medical_info'] ?? '',
      emergencyContacts: json['emergency_contacts'] ?? '',
      isVolunteer: _parseBool(json['is_volunteer'], false),
      birthDate: json['birth_date'] ?? json['birthdate'] ?? '',
      gender: json['gender'] ?? '',
    );
  }

  static bool _parseBool(dynamic val, bool def) {
    if (val == null) return def;
    if (val is bool) return val;
    return val == 1 || val == '1' || val == 'on';
  }

  UserModel copyWith({
    bool? darkMode,
    String? fullname,
    String? phone,
    String? email,
    String? profileImage,
    bool? notificationsEnabled,
    bool? vibrationEnabled,
    bool? gpsEnabled,
    bool? shareLiveLocation,
    bool? locationHistory,
    bool? gpsAccess,
    bool? liveSosLocation,
    String? language,
    String? medicalInfo,
    String? emergencyContacts,
    bool? isVolunteer,
    String? birthDate,
    String? gender,
  }) {
    return UserModel(
      id: id,
      fullname: fullname ?? this.fullname,
      phone: phone ?? this.phone,
      email: email ?? this.email,
      role: role,
      profileImage: profileImage ?? this.profileImage,
      darkMode: darkMode ?? this.darkMode,
      currentLat: currentLat,
      currentLng: currentLng,
      notificationsEnabled: notificationsEnabled ?? this.notificationsEnabled,
      vibrationEnabled: vibrationEnabled ?? this.vibrationEnabled,
      gpsEnabled: gpsEnabled ?? this.gpsEnabled,
      shareLiveLocation: shareLiveLocation ?? this.shareLiveLocation,
      locationHistory: locationHistory ?? this.locationHistory,
      gpsAccess: gpsAccess ?? this.gpsAccess,
      liveSosLocation: liveSosLocation ?? this.liveSosLocation,
      language: language ?? this.language,
      medicalInfo: medicalInfo ?? this.medicalInfo,
      emergencyContacts: emergencyContacts ?? this.emergencyContacts,
      isVolunteer: isVolunteer ?? this.isVolunteer,
      birthDate: birthDate ?? this.birthDate,
      gender: gender ?? this.gender,
    );
  }
}
