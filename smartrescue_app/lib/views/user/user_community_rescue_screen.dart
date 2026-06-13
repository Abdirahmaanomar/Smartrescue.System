import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:geolocator/geolocator.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:intl/intl.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';
import '../../providers/auth_provider.dart';
import '../../services/api_service.dart';
import '../../constants/api_constants.dart';
import 'user_shell.dart';
import '../../utils/translator.dart';
import '../../utils/responsive.dart';
class UserCommunityRescueScreen extends StatefulWidget {
  const UserCommunityRescueScreen({super.key});

  @override
  State<UserCommunityRescueScreen> createState() => _UserCommunityRescueScreenState();
}

class _UserCommunityRescueScreenState extends State<UserCommunityRescueScreen> {
  List<dynamic> _incidents = [];
  bool _isLoading = false;
  double? _userLat;
  double? _userLng;

  @override
  void initState() {
    super.initState();
    _initUserLocation();
    _fetchIncidents();
  }

  // Determine initial coordinates and check live GPS
  Future<void> _initUserLocation() async {
    final auth = Provider.of<AuthProvider>(context, listen: false);
    if (auth.user != null) {
      _userLat = auth.user!.currentLat;
      _userLng = auth.user!.currentLng;
    }

    try {
      LocationPermission permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
      }
      if (permission == LocationPermission.whileInUse || permission == LocationPermission.always) {
        final pos = await Geolocator.getCurrentPosition(
          locationSettings: const LocationSettings(accuracy: LocationAccuracy.medium),
        ).timeout(const Duration(seconds: 4));
        if (mounted) {
          setState(() {
            _userLat = pos.latitude;
            _userLng = pos.longitude;
          });
          // Re-fetch to update distance display
          _fetchIncidents();
        }
      }
    } catch (e) {
      debugPrint("GPS check error: $e");
    }
  }

  Future<void> _fetchIncidents() async {
    if (mounted) {
      setState(() {
        _isLoading = true;
      });
    }

    try {
      final data = await ApiService.getCommunityIncidents();
      if (mounted) {
        setState(() {
          _incidents = data;
          _isLoading = false;
        });
      }
    } catch (e) {
      debugPrint("Fetch incidents error: $e");
      if (mounted) {
        setState(() {
          _isLoading = false;
        });
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(AppTranslator.t(context, 'Failed to load incidents. Please pull to refresh.'))),
        );
      }
    }
  }

  // Toggle volunteer status
  Future<void> _toggleVolunteerStatus(bool currentStatus) async {
    final auth = Provider.of<AuthProvider>(context, listen: false);
    final user = auth.user;
    if (user == null) return;

    final targetStatus = !currentStatus;

    if (mounted) {
      setState(() {
        _isLoading = true;
      });
    }

    try {
      final res = await ApiService.togglePreference(user.id.toString(), 'is_volunteer', targetStatus);
      if (res['status'] == 'success') {
        final updated = user.copyWith(isVolunteer: targetStatus);
        await auth.updateUser(updated);
        
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              backgroundColor: targetStatus ? Colors.green : Colors.blueGrey,
              content: Text(
                targetStatus
                    ? AppTranslator.t(context, 'Congratulations! You joined the Community Rescue Force! 🎉')
                    : AppTranslator.t(context, 'You have left the Volunteer Force.'),
                style: const TextStyle(fontWeight: FontWeight.bold),
              ),
            ),
          );
        }
      } else {
        throw Exception(res['message'] ?? 'API failed');
      }
    } catch (e) {
      debugPrint("Toggle volunteer error: $e");
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('${AppTranslator.t(context, 'Failed to update status')}: ${e.toString()}')),
        );
      }
    } finally {
      if (mounted) {
        setState(() {
          _isLoading = false;
        });
      }
    }
  }

  // Offer help
  Future<void> _respondToIncident(int requestId, String action) async {
    final auth = Provider.of<AuthProvider>(context, listen: false);
    final user = auth.user;
    if (user == null) return;

    if (mounted) {
      setState(() {
        _isLoading = true;
      });
    }

    try {
      final res = await ApiService.respondToIncident(
        requestId: requestId,
        volunteerId: user.id,
        action: action,
      );

      if (res['status'] == 'success') {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              backgroundColor: action == 'accept' ? Colors.green : Colors.orange,
              content: Text(
                action == 'accept'
                    ? AppTranslator.t(context, 'Thank you! Your help offer has been registered. 👍')
                    : AppTranslator.t(context, 'You have cancelled your response.'),
                style: const TextStyle(fontWeight: FontWeight.bold),
              ),
            ),
          );
        }
        _fetchIncidents();
      } else {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(res['message'] ?? AppTranslator.t(context, 'Failed to update response'))),
          );
        }
      }
    } catch (e) {
      debugPrint("Respond to incident error: $e");
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(AppTranslator.t(context, 'Connection error. Please try again.'))),
        );
      }
    } finally {
      if (mounted) {
        setState(() {
          _isLoading = false;
        });
      }
    }
  }

  // Post Alert Form Dialog
  void _showPostAlertDialog() {
    final auth = Provider.of<AuthProvider>(context, listen: false);
    final user = auth.user;
    if (user == null) return;

    String selectedType = 'Medical';
    final descController = TextEditingController();
    bool dialogSubmitting = false;

    showDialog(
      context: context,
      builder: (context) {
        final isDark = Theme.of(context).brightness == Brightness.dark;
        return StatefulBuilder(
          builder: (context, setDialogState) {
            return AlertDialog(
              backgroundColor: isDark ? const Color(0xFF1E293B) : Colors.white,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
              title: Text(AppTranslator.t(context, 'Post Community Alert'), style: const TextStyle(fontWeight: FontWeight.w900)),
              content: SingleChildScrollView(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(AppTranslator.t(context, 'Select Alert Type:'), style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                    const SizedBox(height: 8),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 12),
                      decoration: BoxDecoration(
                        color: isDark ? const Color(0xFF0F172A) : Colors.grey.shade100,
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(color: Colors.grey.shade400.withValues(alpha: 0.3)),
                      ),
                      child: DropdownButtonHideUnderline(
                        child: DropdownButton<String>(
                          value: selectedType,
                          isExpanded: true,
                          dropdownColor: isDark ? const Color(0xFF1E293B) : Colors.white,
                          items: <String>['Medical', 'Fire', 'Accident', 'Police', 'Missing Person']
                              .map((String value) {
                            return DropdownMenuItem<String>(
                              value: value,
                              child: Text(AppTranslator.t(context, value), style: const TextStyle(fontWeight: FontWeight.bold)),
                            );
                          }).toList(),
                          onChanged: (val) {
                            if (val != null) {
                              setDialogState(() {
                                selectedType = val;
                              });
                            }
                          },
                        ),
                      ),
                    ),
                    const SizedBox(height: 18),
                    Text(AppTranslator.t(context, 'Description / Location details:'), style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                    const SizedBox(height: 8),
                    TextField(
                      controller: descController,
                      maxLines: 4,
                      maxLength: 250,
                      decoration: InputDecoration(
                        hintText: AppTranslator.t(context, 'Enter incident details, landmarks, or help needed...'),
                        filled: true,
                        fillColor: isDark ? const Color(0xFF0F172A) : Colors.grey.shade100,
                        border: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(16),
                          borderSide: BorderSide.none,
                        ),
                        contentPadding: const EdgeInsets.all(16),
                      ),
                    ),
                  ],
                ),
              ),
              actionsPadding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
              actions: [
                TextButton(
                  onPressed: dialogSubmitting ? null : () => Navigator.pop(context),
                  child: Text(AppTranslator.t(context, 'Cancel'), style: const TextStyle(fontWeight: FontWeight.bold, color: Colors.grey)),
                ),
                ElevatedButton(
                  onPressed: dialogSubmitting
                      ? null
                      : () async {
                          if (descController.text.trim().isEmpty) {
                            ScaffoldMessenger.of(context).showSnackBar(
                              SnackBar(content: Text(AppTranslator.t(context, 'Please write a brief description'))),
                            );
                            return;
                          }

                          setDialogState(() {
                            dialogSubmitting = true;
                          });

                          try {
                            final res = await ApiService.sendSos(
                              userId: user.id.toString(),
                              lat: _userLat ?? 2.0469,
                              lng: _userLng ?? 45.3182,
                              accuracy: 10.0,
                              emergencyType: selectedType,
                              description: descController.text.trim(),
                            );

                            if (res['status'] == 'success') {
                              if (mounted) {
                                ScaffoldMessenger.of(context).showSnackBar(
                                  SnackBar(
                                    backgroundColor: Colors.green,
                                    content: Text(AppTranslator.t(context, 'Alert posted successfully! Nearby volunteers can see it now. 🚨')),
                                  ),
                                );
                              }
                              _fetchIncidents();
                              Navigator.pop(context);
                            } else {
                              throw Exception(res['message'] ?? AppTranslator.t(context, 'Failed to send'));
                            }
                          } catch (e) {
                            if (mounted) {
                              ScaffoldMessenger.of(context).showSnackBar(
                                SnackBar(content: Text('${AppTranslator.t(context, 'Failed to post alert')}: ${e.toString()}')),
                              );
                            }
                          } finally {
                            setDialogState(() {
                              dialogSubmitting = false;
                            });
                          }
                        },
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Theme.of(context).colorScheme.primary,
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                  child: dialogSubmitting
                      ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                      : Text(AppTranslator.t(context, 'Post Alert'), style: const TextStyle(fontWeight: FontWeight.bold)),
                ),
              ],
            );
          },
        );
      },
    );
  }

  // Detailed view dialog
  void _showDetailsDialog(Map<String, dynamic> incident, double? distanceMeters) {
    showDialog(
      context: context,
      builder: (context) {
        final isDark = Theme.of(context).brightness == Brightness.dark;
        final formattedDate = incident['created_at'] != null
            ? DateFormat('yyyy-MM-dd HH:mm').format(DateTime.parse(incident['created_at']))
            : AppTranslator.t(context, 'Unknown time');

        String distanceStr = AppTranslator.t(context, 'Unknown');
        if (distanceMeters != null) {
          distanceStr = '${(distanceMeters / 1000).toStringAsFixed(2)} ${AppTranslator.t(context, 'km away')}';
        }

        final hasEvidenceImage = incident['evidence_image'] != null && incident['evidence_image'].toString().isNotEmpty;

        return AlertDialog(
          backgroundColor: isDark ? const Color(0xFF1E293B) : Colors.white,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
          title: Row(
            children: [
              Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: _getIncidentColor(incident['emergency_type']).withValues(alpha: 0.15),
                  shape: BoxShape.circle,
                ),
                child: Icon(
                  _getIncidentIcon(incident['emergency_type']),
                  color: _getIncidentColor(incident['emergency_type']),
                  size: 24,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      AppTranslator.t(context, incident['emergency_type'] ?? 'Emergency'),
                      style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 18),
                    ),
                    Text(
                      distanceStr,
                      style: TextStyle(color: Colors.grey.shade500, fontSize: 12, fontWeight: FontWeight.bold),
                    ),
                  ],
                ),
              ),
            ],
          ),
          content: SingleChildScrollView(
            child: SizedBox(
              width: double.maxFinite,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    AppTranslator.t(context, 'Victim Contact:'),
                    style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14),
                  ),
                  const SizedBox(height: 4),
                  Row(
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(incident['victim_name'] ?? AppTranslator.t(context, 'Anonymous'), style: const TextStyle(fontWeight: FontWeight.bold)),
                            Text(incident['victim_phone'] ?? AppTranslator.t(context, 'No phone'), style: TextStyle(color: Colors.grey.shade600, fontSize: 13)),
                          ],
                        ),
                      ),
                      if (incident['victim_phone'] != null && incident['victim_phone'].toString().isNotEmpty)
                        IconButton(
                          icon: const Icon(Icons.phone_in_talk_rounded, color: Colors.green),
                          style: IconButton.styleFrom(backgroundColor: Colors.green.withValues(alpha: 0.1)),
                          onPressed: () async {
                            final uri = Uri.parse('tel:${incident['victim_phone']}');
                            if (await canLaunchUrl(uri)) {
                              await launchUrl(uri);
                            }
                          },
                        ),
                    ],
                  ),
                  const Divider(height: 24),
                  Text(
                    AppTranslator.t(context, 'Description:'),
                    style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    incident['description'] == null || incident['description'].toString().trim().isEmpty
                        ? AppTranslator.t(context, 'No details provided.')
                        : incident['description'],
                    style: const TextStyle(fontSize: 14, height: 1.4),
                  ),
                  const SizedBox(height: 12),
                  Text(
                    '${AppTranslator.t(context, 'Reported on')}: $formattedDate',
                    style: TextStyle(color: Colors.grey.shade500, fontSize: 11, fontWeight: FontWeight.bold),
                  ),
                  if (hasEvidenceImage) ...[
                    const Divider(height: 24),
                    Text(
                      AppTranslator.t(context, 'Evidence Attachment:'),
                      style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14),
                    ),
                    const SizedBox(height: 10),
                    (() {
                      final evidenceStr = incident['evidence_image']?.toString() ?? '';
                      final evidenceImages = evidenceStr
                          .split(',')
                          .map((s) => s.trim())
                          .where((s) => s.isNotEmpty)
                          .toList();

                      if (evidenceImages.isEmpty) {
                        return const SizedBox.shrink();
                      }

                      if (evidenceImages.length == 1) {
                        final imgUrl = '${ApiConstants.baseUrl}/${evidenceImages.first}';
                        return ClipRRect(
                          borderRadius: BorderRadius.circular(16),
                          child: GestureDetector(
                            onTap: () {
                              showDialog(
                                context: context,
                                builder: (context) => Dialog(
                                  backgroundColor: Colors.transparent,
                                  insetPadding: const EdgeInsets.all(16),
                                  child: Stack(
                                    alignment: Alignment.topRight,
                                    children: [
                                      InteractiveViewer(
                                        child: Image.network(imgUrl),
                                      ),
                                      Positioned(
                                        top: 10,
                                        right: 10,
                                        child: IconButton(
                                          icon: const Icon(Icons.close, color: Colors.white, size: 30),
                                          onPressed: () => Navigator.of(context).pop(),
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                              );
                            },
                            child: Image.network(
                              imgUrl,
                              fit: BoxFit.cover,
                              width: double.infinity,
                              height: 180,
                              errorBuilder: (context, error, stackTrace) {
                                return Container(
                                  height: 120,
                                  color: Colors.grey.shade800,
                                  alignment: Alignment.center,
                                  child: const Icon(Icons.broken_image_rounded, color: Colors.grey, size: 40),
                                );
                              },
                            ),
                          ),
                        );
                      }

                      return SizedBox(
                        height: 180,
                        child: ListView.builder(
                          scrollDirection: Axis.horizontal,
                          itemCount: evidenceImages.length,
                          itemBuilder: (context, idx) {
                            final imgPath = evidenceImages[idx];
                            final imgUrl = '${ApiConstants.baseUrl}/$imgPath';
                            return Padding(
                              padding: const EdgeInsets.only(right: 8.0),
                              child: ClipRRect(
                                borderRadius: BorderRadius.circular(16),
                                child: GestureDetector(
                                  onTap: () {
                                    showDialog(
                                      context: context,
                                      builder: (context) => Dialog(
                                        backgroundColor: Colors.transparent,
                                        insetPadding: const EdgeInsets.all(16),
                                        child: Stack(
                                          alignment: Alignment.topRight,
                                          children: [
                                            InteractiveViewer(
                                              child: Image.network(imgUrl),
                                            ),
                                            Positioned(
                                              top: 10,
                                              right: 10,
                                              child: IconButton(
                                                icon: const Icon(Icons.close, color: Colors.white, size: 30),
                                                onPressed: () => Navigator.of(context).pop(),
                                              ),
                                            ),
                                          ],
                                        ),
                                      ),
                                    );
                                  },
                                  child: Image.network(
                                    imgUrl,
                                    fit: BoxFit.cover,
                                    width: 240,
                                    height: 180,
                                    errorBuilder: (context, error, stackTrace) {
                                      return Container(
                                        width: 240,
                                        height: 180,
                                        color: Colors.grey.shade800,
                                        alignment: Alignment.center,
                                        child: const Icon(Icons.broken_image_rounded, color: Colors.grey, size: 40),
                                      );
                                    },
                                  ),
                                ),
                              ),
                            );
                          },
                        ),
                      );
                    }()),
                  ],
                  if (incident['lat'] != null && incident['lng'] != null) ...[
                    const Divider(height: 24),
                    Text(
                      AppTranslator.t(context, 'Incident Location:'),
                      style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14),
                    ),
                    const SizedBox(height: 10),
                    Container(
                      height: 180,
                      width: double.infinity,
                      decoration: BoxDecoration(
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(color: Colors.grey.shade300),
                      ),
                      child: ClipRRect(
                        borderRadius: BorderRadius.circular(15),
                        child: FlutterMap(
                          options: MapOptions(
                            initialCenter: LatLng(
                              double.tryParse(incident['lat'].toString()) ?? 2.0469,
                              double.tryParse(incident['lng'].toString()) ?? 45.3182,
                            ),
                            initialZoom: 15.0,
                          ),
                          children: [
                            TileLayer(
                              urlTemplate: 'https://mt1.google.com/vt/lyrs=m&x={x}&y={y}&z={z}',
                              userAgentPackageName: 'com.smartrescue.app',
                            ),
                            MarkerLayer(
                              markers: [
                                Marker(
                                  point: LatLng(
                                    double.tryParse(incident['lat'].toString()) ?? 2.0469,
                                    double.tryParse(incident['lng'].toString()) ?? 45.3182,
                                  ),
                                  width: 40,
                                  height: 40,
                                  child: const Icon(
                                    Icons.location_on,
                                    color: Colors.red,
                                    size: 40,
                                  ),
                                ),
                              ],
                            ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: 8),
                    SizedBox(
                      width: double.infinity,
                      child: ElevatedButton.icon(
                        onPressed: () async {
                          final lat = incident['lat'];
                          final lng = incident['lng'];
                          final uri = Uri.parse('https://www.google.com/maps/dir/?api=1&destination=$lat,$lng');
                          if (await canLaunchUrl(uri)) {
                            await launchUrl(uri);
                          }
                        },
                        icon: const Icon(Icons.directions_rounded, color: Colors.white, size: 18),
                        label: Text(AppTranslator.t(context, 'Navigate There'), style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: Colors.blue.shade600,
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                        ),
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context),
              child: Text(AppTranslator.t(context, 'Close'), style: const TextStyle(fontWeight: FontWeight.bold)),
            ),
          ],
        );
      },
    );
  }

  Color _getIncidentColor(String? type) {
    switch (type) {
      case 'Medical':
        return const Color(0xFFEF4444);
      case 'Fire':
        return const Color(0xFFF97316);
      case 'Accident':
        return const Color(0xFFDC2626);
      case 'Police':
        return const Color(0xFF3B82F6);
      case 'Missing Person':
        return const Color(0xFF8B5CF6);
      default:
        return const Color(0xFF10B981);
    }
  }

  IconData _getIncidentIcon(String? type) {
    switch (type) {
      case 'Medical':
        return Icons.medical_services_rounded;
      case 'Fire':
        return Icons.local_fire_department_rounded;
      case 'Accident':
        return Icons.car_crash_rounded;
      case 'Police':
        return Icons.local_police_rounded;
      case 'Missing Person':
        return Icons.person_search_rounded;
      default:
        return Icons.warning_rounded;
    }
  }

  @override
  Widget build(BuildContext context) {
    final auth = Provider.of<AuthProvider>(context);
    final user = auth.user;
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final scheme = Theme.of(context).colorScheme;

    final isVolunteer = user?.isVolunteer ?? false;

    return Scaffold(
      backgroundColor: isDark ? const Color(0xFF0F172A) : const Color(0xFFF1F5F9),
      appBar: AppBar(
        leading: Builder(
          builder: (context) => IconButton(
            icon: const Icon(Icons.menu_rounded),
            onPressed: () => UserShell.scaffoldKey.currentState?.openDrawer(),
          ),
        ),
        title: Text(AppTranslator.t(context, 'Community Rescue'), style: const TextStyle(fontWeight: FontWeight.w800)),
        centerTitle: true,
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh_rounded),
            onPressed: _fetchIncidents,
          ),
        ],
      ),
      body: SafeArea(
        child: Responsive(context).wrapWidescreen(
          RefreshIndicator(
            onRefresh: _fetchIncidents,
            color: scheme.primary,
            child: ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.all(20),
              children: [
                // Header Banner Card
                AnimatedContainer(
                  duration: const Duration(milliseconds: 300),
                  width: double.infinity,
                  padding: const EdgeInsets.all(24),
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      colors: isVolunteer
                          ? [const Color(0xFF059669), const Color(0xFF10B981)]
                          : [const Color(0xFF1D4ED8), const Color(0xFF3B82F6)],
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                    ),
                    borderRadius: BorderRadius.circular(24),
                    boxShadow: [
                      BoxShadow(
                        color: (isVolunteer ? const Color(0xFF10B981) : const Color(0xFF3B82F6)).withValues(alpha: 0.3),
                        blurRadius: 15,
                        offset: const Offset(0, 8),
                      )
                    ],
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Container(
                            padding: const EdgeInsets.all(8),
                            decoration: BoxDecoration(
                              color: Colors.white.withValues(alpha: 0.2),
                              borderRadius: BorderRadius.circular(12),
                            ),
                            child: Icon(
                              isVolunteer ? Icons.volunteer_activism_rounded : Icons.shield_rounded,
                              color: Colors.white,
                              size: 24,
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Text(
                              isVolunteer
                                  ? AppTranslator.t(context, 'Active Volunteer Force')
                                  : AppTranslator.t(context, 'Local Heroes Needed'),
                              style: const TextStyle(
                                color: Colors.white,
                                fontSize: 18,
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                          ),
                          if (isVolunteer)
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                              decoration: BoxDecoration(
                                color: Colors.green.shade800,
                                borderRadius: BorderRadius.circular(10),
                                border: Border.all(color: Colors.green.shade300, width: 1.5),
                              ),
                              child: Row(
                                children: [
                                  const Icon(Icons.lens, color: Colors.green, size: 8),
                                  const SizedBox(width: 4),
                                  Text(
                                    AppTranslator.t(context, 'ON DUTY'),
                                    style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.bold),
                                  )
                                ],
                              ),
                            ),
                        ],
                      ),
                      const SizedBox(height: 16),
                      Text(
                        isVolunteer
                            ? AppTranslator.t(context, 'Thank you for serving the Mogadishu community! You can now view active community rescue incidents and offer support.')
                            : AppTranslator.t(context, 'Join the community rescue force and help neighbors in need during local emergencies.'),
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 14,
                          fontWeight: FontWeight.w500,
                          height: 1.4,
                        ),
                      ),
                      const SizedBox(height: 20),
                      ElevatedButton(
                        onPressed: () => _toggleVolunteerStatus(isVolunteer),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: Colors.white,
                          foregroundColor: isVolunteer ? const Color(0xFF059669) : const Color(0xFF1D4ED8),
                          elevation: 0,
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
                        ),
                        child: Text(
                          isVolunteer ? AppTranslator.t(context, 'Leave Force') : AppTranslator.t(context, 'Join as Volunteer'),
                          style: const TextStyle(fontWeight: FontWeight.bold),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 28),

                // Subheading
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(
                      AppTranslator.t(context, 'Nearby Incidents'),
                      style: TextStyle(
                        fontSize: 20,
                        fontWeight: FontWeight.w900,
                        color: isDark ? Colors.white : const Color(0xFF0F172A),
                        letterSpacing: -0.5,
                      ),
                    ),
                    if (_isLoading)
                      const SizedBox(
                        width: 20,
                        height: 20,
                        child: CircularProgressIndicator(strokeWidth: 2.5),
                      ),
                  ],
                ),
                const SizedBox(height: 16),

                // Incident list view
                if (_incidents.isEmpty && !_isLoading)
                  Container(
                    padding: const EdgeInsets.all(40),
                    alignment: Alignment.center,
                    child: Column(
                      children: [
                        Icon(Icons.assignment_turned_in_rounded, size: 60, color: Colors.grey.shade500),
                        const SizedBox(height: 12),
                        Text(
                          AppTranslator.t(context, 'No Active Incidents'),
                          style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: Colors.grey),
                        ),
                        const SizedBox(height: 6),
                        Text(
                          AppTranslator.t(context, 'The community is currently safe. Thank you!'),
                          style: TextStyle(fontSize: 13, color: Colors.grey.shade500),
                        ),
                      ],
                    ),
                  )
                else
                  ..._incidents.map((incident) {
                    // Calculate distance
                    double? distanceMeters;
                    if (_userLat != null && _userLng != null && incident['lat'] != null && incident['lng'] != null) {
                      distanceMeters = Geolocator.distanceBetween(
                        _userLat!,
                        _userLng!,
                        incident['lat'],
                        incident['lng'],
                      );
                    }

                    String distanceText = AppTranslator.t(context, 'Distance unknown');
                    if (distanceMeters != null) {
                      if (distanceMeters < 1000) {
                        distanceText = '${distanceMeters.toStringAsFixed(0)} ${AppTranslator.t(context, 'm away')}';
                      } else {
                        distanceText = '${(distanceMeters / 1000).toStringAsFixed(1)} ${AppTranslator.t(context, 'km away')}';
                      }
                    }

                    return _buildIncidentCard(
                      context: context,
                      incident: incident,
                      isDark: isDark,
                      isVolunteer: isVolunteer,
                      currentUserId: user?.id ?? 0,
                      distanceText: distanceText,
                      distanceMeters: distanceMeters,
                    );
                  }),
              ],
            ),
          ),
        ),
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _showPostAlertDialog,
        backgroundColor: scheme.primary,
        icon: const Icon(Icons.add_alert_rounded, color: Colors.white),
        label: Text(AppTranslator.t(context, 'Post Alert'), style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
      ),
    );
  }

  Widget _buildIncidentCard({
    required BuildContext context,
    required Map<String, dynamic> incident,
    required bool isDark,
    required bool isVolunteer,
    required int currentUserId,
    required String distanceText,
    required double? distanceMeters,
  }) {
    final rawType = incident['emergency_type'] ?? 'Emergency';
    final title = AppTranslator.t(context, rawType);
    final desc = incident['description'] == null || incident['description'].toString().trim().isEmpty
        ? AppTranslator.t(context, 'Emergency reported, awaiting assistance details.')
        : incident['description'];
    final color = _getIncidentColor(rawType);

    final assignedVolunteerId = incident['volunteer_id'];
    final isIResponded = assignedVolunteerId != null && assignedVolunteerId == currentUserId;
    final isSomeoneElseResponded = assignedVolunteerId != null && assignedVolunteerId != currentUserId;

    return Container(
      margin: const EdgeInsets.only(bottom: 16),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: isDark ? const Color(0xFF1E293B) : Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: isIResponded ? Border.all(color: Colors.green, width: 2) : null,
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: isDark ? 0.3 : 0.05),
            blurRadius: 10,
            offset: const Offset(0, 4),
          )
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Flexible(
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                  decoration: BoxDecoration(
                    color: color.withValues(alpha: 0.15),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(_getIncidentIcon(rawType), color: color, size: 14),
                      const SizedBox(width: 6),
                      Flexible(
                        child: Text(
                          title,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(color: color, fontWeight: FontWeight.bold, fontSize: 12),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              const Spacer(),
              Icon(Icons.location_on_rounded, size: 14, color: Colors.grey.shade500),
              const SizedBox(width: 4),
              Text(
                distanceText,
                style: TextStyle(
                  color: isDark ? Colors.grey.shade400 : Colors.grey.shade600,
                  fontSize: 12,
                  fontWeight: FontWeight.bold,
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Text(
            desc,
            maxLines: 3,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              fontSize: 14,
              color: isDark ? Colors.grey.shade300 : const Color(0xFF334155),
              height: 1.4,
              fontWeight: FontWeight.w500,
            ),
          ),
          const SizedBox(height: 16),

          // Volunteer Assignment Status Alert Banners
          if (isIResponded)
            Container(
              margin: const EdgeInsets.only(bottom: 12),
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
              decoration: BoxDecoration(
                color: Colors.green.withValues(alpha: 0.15),
                borderRadius: BorderRadius.circular(10),
              ),
              child: Row(
                children: [
                  const Icon(Icons.check_circle_rounded, color: Colors.green, size: 16),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      AppTranslator.t(context, 'You are responding to this alert'),
                      style: const TextStyle(color: Colors.green, fontSize: 12, fontWeight: FontWeight.bold),
                    ),
                  ),
                ],
              ),
            )
          else if (isSomeoneElseResponded)
            Container(
              margin: const EdgeInsets.only(bottom: 12),
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
              decoration: BoxDecoration(
                color: Colors.grey.withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(10),
              ),
              child: Row(
                children: [
                  const Icon(Icons.volunteer_activism_rounded, color: Colors.grey, size: 16),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      '${AppTranslator.t(context, 'Assisted by')} ${incident['volunteer_name'] ?? AppTranslator.t(context, 'another volunteer')}',
                      style: TextStyle(color: isDark ? Colors.grey.shade400 : Colors.grey.shade700, fontSize: 12, fontWeight: FontWeight.bold),
                    ),
                  ),
                ],
              ),
            ),

          Row(
            children: [
              Expanded(
                child: OutlinedButton(
                  onPressed: () => _showDetailsDialog(incident, distanceMeters),
                  style: OutlinedButton.styleFrom(
                    foregroundColor: isDark ? Colors.grey.shade300 : const Color(0xFF475569),
                    side: BorderSide(color: isDark ? Colors.grey.shade700 : Colors.grey.shade300),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                  child: Text(AppTranslator.t(context, 'View Details')),
                ),
              ),
              const SizedBox(width: 12),
              if (!isSomeoneElseResponded)
                Expanded(
                  child: ElevatedButton(
                    onPressed: () {
                      if (!isVolunteer) {
                        ScaffoldMessenger.of(context).showSnackBar(
                          SnackBar(
                            content: Text(AppTranslator.t(context, 'Please join the Volunteer Force to respond!')),
                          ),
                        );
                        return;
                      }
                      if (isIResponded) {
                        _respondToIncident(incident['id'], 'cancel');
                      } else {
                        _respondToIncident(incident['id'], 'accept');
                      }
                    },
                    style: ElevatedButton.styleFrom(
                      backgroundColor: isIResponded ? Colors.grey.shade700 : color,
                      foregroundColor: Colors.white,
                      elevation: 0,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    ),
                    child: Text(isIResponded ? AppTranslator.t(context, 'Cancel Help') : AppTranslator.t(context, 'I Can Help')),
                  ),
                ),
            ],
          ),
        ],
      ),
    );
  }
}
