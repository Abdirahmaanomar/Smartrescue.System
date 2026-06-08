class RescueRequestModel {
  final int id;
  final String emergencyType;
  final String status;
  final double lat;
  final double lng;
  final String description;
  final String createdAt;
  final bool driverAssigned;
  final String driverName;
  final String driverPhone;
  final String unitName;
  final String plateNumber;
  final String unitType;
  final double? driverLat;
  final double? driverLng;

  const RescueRequestModel({
    required this.id,
    required this.emergencyType,
    required this.status,
    required this.lat,
    required this.lng,
    this.description = '',
    this.createdAt = '',
    this.driverAssigned = false,
    this.driverName = '',
    this.driverPhone = '',
    this.unitName = '',
    this.plateNumber = '',
    this.unitType = '',
    this.driverLat,
    this.driverLng,
  });

  factory RescueRequestModel.fromJson(Map<String, dynamic> json) {
    return RescueRequestModel(
      id: int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      emergencyType: json['emergency_type'] ?? 'Unknown',
      status: json['request_status'] ?? json['status'] ?? 'pending',
      lat: double.tryParse(json['lat']?.toString() ?? '0') ?? 0,
      lng: double.tryParse(json['lng']?.toString() ?? '0') ?? 0,
      description: json['description'] ?? '',
      createdAt: json['created_at'] ?? '',
      driverAssigned: json['driver_assigned'] == true || json['driver_assigned'] == 1 || json['driver_assigned'] == '1',
      driverName: json['driver_name'] ?? '',
      driverPhone: json['driver_phone'] ?? '',
      unitName: json['unit_name'] ?? '',
      plateNumber: json['plate_number'] ?? '',
      unitType: json['unit_type'] ?? '',
      driverLat: json['driver_lat'] != null ? double.tryParse(json['driver_lat'].toString()) : null,
      driverLng: json['driver_lng'] != null ? double.tryParse(json['driver_lng'].toString()) : null,
    );
  }

  String get statusLabel {
    switch (status) {
      case 'pending':   return 'Awaiting Response';
      case 'accepted':  return driverAssigned ? 'Team Assigned' : 'Accepted';
      case 'dispatched': return 'Dispatched';
      case 'en_route':  return 'En Route';
      case 'arrived':   return 'Arrived';
      case 'completed': return 'Completed';
      default:          return status;
    }
  }

  /// Returns a step index 0-5 matching the timeline screens:
  /// 0 = pending, 1 = dispatched/accepted(no unit), 2 = assigned, 3 = en_route, 4 = arrived, 5 = completed
  int get timelineStep {
    if (status == 'completed') return 5;
    if (status == 'arrived')   return 4;
    if (status == 'en_route')  return 3;
    if (driverAssigned)        return 2;  // accepted + unit assigned
    if (status == 'accepted' || status == 'dispatched') return 1;
    return 0; // pending
  }
}
