import 'dart:async';
import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';
import 'package:provider/provider.dart';
import 'package:http/http.dart' as http;
import 'package:font_awesome_flutter/font_awesome_flutter.dart';
import '../../providers/driver_provider.dart';

import '../../components/app_button.dart';

class DriverMapScreen extends StatefulWidget {
  const DriverMapScreen({super.key});

  @override
  State<DriverMapScreen> createState() => _DriverMapScreenState();
}

class _DriverMapScreenState extends State<DriverMapScreen> {
  final MapController _mapController = MapController();
  bool _isMapReady = false;
  bool _autoTrackEnabled = true;
  String _mapType = 'std'; // std, sat
  List<LatLng> _routePoints = [];

  
  String _distanceLabel = '—';
  String _durationLabel = '—';
  String _avgSpeedLabel = '40 km/h';
  String _lastUpdatedLabel = '—';
  bool _isRouteLoading = false;

  Timer? _routeRefreshTimer;
  LatLng? _lastFetchedVictimLocation;

  @override
  void initState() {
    super.initState();
    // Refresh route updates every 30s when we have a job
    _routeRefreshTimer = Timer.periodic(const Duration(seconds: 30), (_) {
      _refreshRouteForce();
    });
  }

  @override
  void dispose() {
    _routeRefreshTimer?.cancel();
    _mapController.dispose();
    super.dispose();
  }

  String _getTileUrl() {
    if (_mapType == 'sat') {
      return 'https://mt1.google.com/vt/lyrs=y&x={x}&y={y}&z={z}';
    }
    return 'https://mt1.google.com/vt/lyrs=m&x={x}&y={y}&z={z}';
  }

  Future<void> _fetchRoute(LatLng start, LatLng end) async {
    if (_isRouteLoading) return;
    setState(() => _isRouteLoading = true);

    try {
      final url = Uri.parse(
        'https://router.project-osrm.org/route/v1/driving/'
        '${start.longitude},${start.latitude};${end.longitude},${end.latitude}'
        '?overview=full&geometries=geojson&steps=true'
      );
      final response = await http.get(url).timeout(const Duration(seconds: 10));
      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['code'] == 'Ok' && data['routes'] != null && data['routes'].isNotEmpty) {
          final route = data['routes'][0];
          final geometry = route['geometry'];
          final coordinates = geometry['coordinates'] as List;
          
          final List<LatLng> points = coordinates.map((coord) {
            final lng = (coord[0] as num).toDouble();
            final lat = (coord[1] as num).toDouble();
            return LatLng(lat, lng);
          }).toList();

          final double distKm = (route['distance'] as num) / 1000.0;
          final double durationSec = (route['duration'] as num).toDouble();
          final int durMin = (durationSec / 60.0).ceil();
          final double speedKmh = durationSec > 0 ? (distKm / (durationSec / 3600.0)) : 40.0;



          if (mounted) {
            setState(() {
              _routePoints = points;
              _distanceLabel = '${distKm.toStringAsFixed(1)} km';
              _durationLabel = '$durMin mins';
              _avgSpeedLabel = '${speedKmh.toStringAsFixed(0)} km/h';
              _lastUpdatedLabel = TimeOfDay.now().format(context);
            });
            
            if (_autoTrackEnabled) {
              _fitBounds(start, end);
            }
          }
        }
      }
    } catch (_) {
      // Fallback straight line
      if (mounted) {
        setState(() {
          _routePoints = [start, end];
          _distanceLabel = 'Straight Line';
          _durationLabel = 'Calculating…';
        });
      }
    } finally {
      if (mounted) {
        setState(() => _isRouteLoading = false);
      }
    }
  }

  void _fitBounds(LatLng start, LatLng end) {
    if (!_isMapReady) return;
    try {
      final bounds = LatLngBounds.fromPoints([start, end]);
      _mapController.fitCamera(
        CameraFit.bounds(
          bounds: bounds,
          padding: const EdgeInsets.all(50.0),
        ),
      );
    } catch (_) {}
  }

  void _refreshRouteForce() {
    final driver = Provider.of<DriverProvider>(context, listen: false);
    if (driver.hasActiveJob && driver.currentPosition != null) {
      final job = driver.activeJob!;
      final double? vLat = job['lat'] != null ? double.tryParse(job['lat'].toString()) : null;
      final double? vLng = job['lng'] != null ? double.tryParse(job['lng'].toString()) : null;
      
      if (vLat != null && vLng != null) {
        final start = LatLng(driver.currentPosition!.latitude, driver.currentPosition!.longitude);
        final end = LatLng(vLat, vLng);
        _fetchRoute(start, end);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final driver = Provider.of<DriverProvider>(context);
    final isDark = Theme.of(context).brightness == Brightness.dark;

    LatLng? startLoc;
    LatLng? endLoc;

    if (driver.currentPosition != null) {
      startLoc = LatLng(driver.currentPosition!.latitude, driver.currentPosition!.longitude);
    }
    
    if (driver.hasActiveJob) {
      final job = driver.activeJob!;
      final double? vLat = job['lat'] != null ? double.tryParse(job['lat'].toString()) : null;
      final double? vLng = job['lng'] != null ? double.tryParse(job['lng'].toString()) : null;
      if (vLat != null && vLng != null) {
        endLoc = LatLng(vLat, vLng);
        
        // Trigger fetch route if victim location changed or we don't have route yet
        if (_lastFetchedVictimLocation != endLoc && startLoc != null) {
          _lastFetchedVictimLocation = endLoc;
          WidgetsBinding.instance.addPostFrameCallback((_) {
            _fetchRoute(startLoc!, endLoc!);
          });
        }
      }
    } else {
      _lastFetchedVictimLocation = null;
      if (_routePoints.isNotEmpty) {
        WidgetsBinding.instance.addPostFrameCallback((_) {
          setState(() {
            _routePoints = [];
            _distanceLabel = '—';
            _durationLabel = '—';
          });
        });
      }
    }

    List<Marker> markers = [];
    if (startLoc != null) {
      markers.add(
        Marker(
          point: startLoc,
          width: 70,
          height: 70,
          child: const WailingAmbulanceDriverMarker(),
        ),
      );
    }

    if (endLoc != null) {
      markers.add(
        Marker(
          point: endLoc,
          width: 16,
          height: 16,
          child: Container(
            decoration: BoxDecoration(
              color: const Color(0xFF2563EB),
              shape: BoxShape.circle,
              boxShadow: [
                BoxShadow(
                  color: const Color(0xFF2563EB).withValues(alpha: 0.4),
                  blurRadius: 6,
                  spreadRadius: 1,
                )
              ],
            ),
          ),
        ),
      );
    }

    final initialMapCenter = startLoc ?? endLoc ?? const LatLng(2.0469, 45.3182);

    return Scaffold(
      body: Stack(
        children: [
          // 1. FLUTTER MAP LAYER
          FlutterMap(
            mapController: _mapController,
            options: MapOptions(
              initialCenter: initialMapCenter,
              initialZoom: 14,
              minZoom: 3,
              maxZoom: 20,
              onMapReady: () {
                _isMapReady = true;
                if (startLoc != null) {
                  _mapController.move(startLoc, 15);
                }
              },
              onPositionChanged: (pos, hasGesture) {
                if (hasGesture && _autoTrackEnabled) {
                  setState(() {
                    _autoTrackEnabled = false;
                  });
                }
              }
            ),
            children: [
              TileLayer(
                urlTemplate: _getTileUrl(),
                userAgentPackageName: 'com.smartrescue.app',
              ),
              if (_routePoints.isNotEmpty)
                PolylineLayer(
                  polylines: [
                    Polyline(
                      points: _routePoints,
                      strokeWidth: 6.0,
                      color: const Color(0xFF2563EB),
                      borderColor: Colors.white,
                      borderStrokeWidth: 2.0,
                    ),
                  ],
                ),
              MarkerLayer(markers: markers),
            ],
          ),

          // 2. MAP LAYER CONTROL (Satellite/Standard)
          Positioned(
            top: 16,
            left: 16,
            right: 16,
            child: Center(
              child: Container(
                padding: const EdgeInsets.all(4),
                decoration: BoxDecoration(
                  color: isDark ? const Color(0xFF1E293B) : Colors.white,
                  borderRadius: BorderRadius.circular(16),
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withValues(alpha: isDark ? 0.25 : 0.04),
                      blurRadius: 12,
                      offset: const Offset(0, 4),
                    )
                  ]
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    _buildMapTypeToggle('std', Icons.map, 'Map', isDark),
                    _buildMapTypeToggle('sat', Icons.layers, 'Satellite', isDark),
                  ],
                ),
              ),
            ),
          ),

          // 3. FLOATING ACTION PANELS
          Positioned(
            bottom: driver.hasActiveJob ? 280 : 16,
            right: 16,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                // Follow Me / Center on Driver Button
                FloatingActionButton.small(
                  heroTag: 'follow_btn',
                  backgroundColor: _autoTrackEnabled ? const Color(0xFF2563EB) : (isDark ? const Color(0xFF1E293B) : Colors.white),
                  foregroundColor: _autoTrackEnabled ? Colors.white : (isDark ? Colors.white : Colors.black87),
                  onPressed: () {
                    setState(() => _autoTrackEnabled = !_autoTrackEnabled);
                    if (_autoTrackEnabled && startLoc != null) {
                      if (endLoc != null) {
                        _fitBounds(startLoc, endLoc);
                      } else {
                        _mapController.move(startLoc, 16);
                      }
                    }
                  },
                  child: const Icon(Icons.gps_fixed_rounded),
                ),
                const SizedBox(height: 8),
                // Zoom In
                FloatingActionButton.small(
                  heroTag: 'zoom_in',
                  backgroundColor: isDark ? const Color(0xFF1E293B) : Colors.white,
                  foregroundColor: isDark ? Colors.white : Colors.black87,
                  onPressed: () => _mapController.move(_mapController.camera.center, _mapController.camera.zoom + 1),
                  child: const Icon(Icons.add_rounded),
                ),
                const SizedBox(height: 8),
                // Zoom Out
                FloatingActionButton.small(
                  heroTag: 'zoom_out',
                  backgroundColor: isDark ? const Color(0xFF1E293B) : Colors.white,
                  foregroundColor: isDark ? Colors.white : Colors.black87,
                  onPressed: () => _mapController.move(_mapController.camera.center, _mapController.camera.zoom - 1),
                  child: const Icon(Icons.remove_rounded),
                ),
              ],
            ),
          ),

          // 4. BOTTOM ROUTE PANEL
          if (driver.hasActiveJob)
            Positioned(
              bottom: 16,
              left: 16,
              right: 16,
              child: Container(
                padding: const EdgeInsets.all(20),
                decoration: BoxDecoration(
                  color: isDark ? const Color(0xFF1E293B) : Colors.white,
                  borderRadius: BorderRadius.circular(24),
                  border: Border.all(color: isDark ? const Color(0xFF334155) : Colors.grey.shade200, width: 1.5),
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withValues(alpha: isDark ? 0.25 : 0.04),
                      blurRadius: 20,
                      offset: const Offset(0, 6),
                    )
                  ]
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Row(
                      children: [
                        const Icon(Icons.route_rounded, color: Color(0xFF2563EB)),
                        const SizedBox(width: 8),
                        const Text(
                          'Route Information',
                          style: TextStyle(fontWeight: FontWeight.w900, fontSize: 16),
                        ),
                        const Spacer(),
                        if (_isRouteLoading)
                          const SizedBox(
                            width: 14,
                            height: 14,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          ),
                      ],
                    ),
                    const SizedBox(height: 16),
                    Row(
                      children: [
                        Expanded(
                          child: _buildRouteStatItem(
                            icon: Icons.alt_route_rounded,
                            iconColor: Colors.blue,
                            label: 'Road Distance',
                            value: _distanceLabel,
                          ),
                        ),
                        Expanded(
                          child: _buildRouteStatItem(
                            icon: Icons.timer_rounded,
                            iconColor: Colors.orange,
                            label: 'Estimated Time',
                            value: _durationLabel,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),
                    Row(
                      children: [
                        Expanded(
                          child: _buildRouteStatItem(
                            icon: Icons.speed_rounded,
                            iconColor: Colors.green,
                            label: 'Avg Speed',
                            value: _avgSpeedLabel,
                          ),
                        ),
                        Expanded(
                          child: _buildRouteStatItem(
                            icon: Icons.update_rounded,
                            iconColor: Colors.grey,
                            label: 'Last Updated',
                            value: _lastUpdatedLabel,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 16),
                    AppButton(
                      label: 'Refresh Route',
                      onPressed: _refreshRouteForce,
                      loading: _isRouteLoading,
                    ),
                  ],
                ),
              ),
            )
          else
            Positioned(
              bottom: 16,
              left: 16,
              right: 16,
              child: Container(
                padding: const EdgeInsets.symmetric(vertical: 24, horizontal: 16),
                decoration: BoxDecoration(
                  color: isDark ? const Color(0xFF1E293B) : Colors.white,
                  borderRadius: BorderRadius.circular(24),
                  border: Border.all(color: isDark ? const Color(0xFF334155) : Colors.grey.shade200, width: 1.5),
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withValues(alpha: isDark ? 0.25 : 0.04),
                      blurRadius: 20,
                      offset: const Offset(0, 6),
                    )
                  ]
                ),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Icon(Icons.location_pin, color: Colors.grey, size: 36),
                    const SizedBox(height: 10),
                    const Text(
                      'No Active Mission',
                      style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      'Accept an emergency SOS to view live route coordinates.',
                      textAlign: TextAlign.center,
                      style: TextStyle(color: Colors.grey.shade500, fontSize: 12),
                    ),
                  ],
                ),
              ),
            ),
        ],
      ),
    );
  }

  Widget _buildMapTypeToggle(String type, IconData icon, String label, bool isDark) {
    final bool isActive = _mapType == type;
    return InkWell(
      onTap: () {
        setState(() {
          _mapType = type;
        });
      },
      borderRadius: BorderRadius.circular(12),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
        decoration: BoxDecoration(
          color: isActive ? const Color(0xFF2563EB) : Colors.transparent,
          borderRadius: BorderRadius.circular(12),
        ),
        child: Row(
          children: [
            Icon(icon, size: 14, color: isActive ? Colors.white : (isDark ? Colors.white70 : Colors.black87)),
            const SizedBox(width: 6),
            Text(
              label,
              style: TextStyle(
                fontSize: 11,
                fontWeight: FontWeight.w800,
                color: isActive ? Colors.white : (isDark ? Colors.white70 : Colors.black87),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildRouteStatItem({
    required IconData icon,
    required Color iconColor,
    required String label,
    required String value,
  }) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return Row(
      children: [
        Container(
          padding: const EdgeInsets.all(8),
          decoration: BoxDecoration(
            color: iconColor.withValues(alpha: 0.1),
            borderRadius: BorderRadius.circular(12),
          ),
          child: Icon(icon, color: iconColor, size: 16),
        ),
        const SizedBox(width: 8),
        Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              label,
              style: TextStyle(color: Colors.grey.shade500, fontSize: 10, fontWeight: FontWeight.bold),
            ),
            Text(
              value,
              style: TextStyle(
                fontSize: 13, 
                fontWeight: FontWeight.w800,
                color: isDark ? Colors.white : const Color(0xFF1E293B),
              ),
            ),
          ],
        )
      ],
    );
  }
}

// ─── Animated Siren Ambulance Marker ────────────────────────────────────────
class WailingAmbulanceDriverMarker extends StatefulWidget {
  const WailingAmbulanceDriverMarker({super.key});

  @override
  State<WailingAmbulanceDriverMarker> createState() =>
      _WailingAmbulanceDriverMarkerState();
}

class _WailingAmbulanceDriverMarkerState
    extends State<WailingAmbulanceDriverMarker>
    with SingleTickerProviderStateMixin {
  late AnimationController _controller;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1200),
    )..repeat();
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _controller,
      builder: (context, child) {
        final double pulse = _controller.value;
        // Single solid blue — no red/siren switching
        const Color blueColor = Color(0xFF2563EB);

        return Stack(
          alignment: Alignment.center,
          children: [
            // Outer pulsing blue ring
            Container(
              width: 45 + (pulse * 25),
              height: 45 + (pulse * 25),
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: blueColor.withValues(alpha: 0.22 * (1.0 - pulse)),
                border: Border.all(
                  color: blueColor.withValues(alpha: 0.35 * (1.0 - pulse)),
                  width: 1.5,
                ),
              ),
            ),
            // Inner white glow ring
            Container(
              width: 50,
              height: 50,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: Colors.white,
                boxShadow: [
                  BoxShadow(
                    color: blueColor.withValues(alpha: 0.38),
                    blurRadius: 12,
                    spreadRadius: 2,
                  ),
                ],
              ),
            ),
            // Main solid blue circle with ambulance icon
            Container(
              width: 40,
              height: 40,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: blueColor,
                boxShadow: [
                  BoxShadow(
                    color: blueColor.withValues(alpha: 0.5),
                    blurRadius: 14,
                    spreadRadius: 2,
                  ),
                ],
              ),
              child: const Center(
                child: FaIcon(
                  FontAwesomeIcons.truckMedical,
                  color: Colors.white,
                  size: 19,
                ),
              ),
            ),
            // Blue beacon dot top-right
            Positioned(
              top: 6,
              right: 6,
              child: Container(
                width: 9,
                height: 9,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: const Color(0xFF60A5FA),
                  border: Border.all(color: Colors.white, width: 1.5),
                  boxShadow: [
                    BoxShadow(
                      color: blueColor.withValues(alpha: 0.8),
                      blurRadius: 6,
                      spreadRadius: 1,
                    ),
                  ],
                ),
              ),
            ),
          ],
        );
      },
    );
  }
}
