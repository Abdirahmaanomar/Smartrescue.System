import 'dart:async';
import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';
import 'package:provider/provider.dart';
import 'package:geolocator/geolocator.dart';
import 'package:http/http.dart' as http;
import '../../providers/sos_provider.dart';
import '../../providers/auth_provider.dart';
import '../../services/api_service.dart';
import '../../utils/translator.dart';
import '../../components/call_screen.dart';
import 'user_shell.dart';

class UserMapScreen extends StatefulWidget {
  const UserMapScreen({super.key});

  @override
  State<UserMapScreen> createState() => _UserMapScreenState();
}

class _UserMapScreenState extends State<UserMapScreen> {
  final MapController _mapController = MapController();
  LatLng? _currentLocation;
  Timer? _locationTimer;
  String _mapType = 'std'; // std, sat, terrain
  double _gpsAccuracy = 74.0;
  bool _hasFittedBounds = false;

  List<LatLng> _routePoints = [];
  LatLng? _lastFetchedDriverLocation;
  bool _autoTrackEnabled = true;
  SosProvider? _sosProvider;

  @override
  void initState() {
    super.initState();
    _initLocation();
    _locationTimer = Timer.periodic(const Duration(seconds: 10), (_) => _updateLocation());
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final newSos = Provider.of<SosProvider>(context);
    if (_sosProvider != newSos) {
      _sosProvider?.removeListener(_onSosProviderChanged);
      _sosProvider = newSos;
      _sosProvider?.addListener(_onSosProviderChanged);
    }
  }

  @override
  void dispose() {
    _sosProvider?.removeListener(_onSosProviderChanged);
    _locationTimer?.cancel();
    _mapController.dispose();
    super.dispose();
  }

  void _onSosProviderChanged() {
    if (_sosProvider == null) return;
    final sos = _sosProvider!;
    
    if (sos.hasActiveRequest &&
        sos.activeRequest!.driverLat != null &&
        sos.activeRequest!.driverLng != null) {
      final newDriverLoc = LatLng(sos.activeRequest!.driverLat!, sos.activeRequest!.driverLng!);
      
      if (_lastFetchedDriverLocation == null ||
          _lastFetchedDriverLocation!.latitude != newDriverLoc.latitude ||
          _lastFetchedDriverLocation!.longitude != newDriverLoc.longitude) {
        _lastFetchedDriverLocation = newDriverLoc;
        
        // Fetch route if user location is available
        if (_currentLocation != null) {
          _fetchRoute(_currentLocation!, newDriverLoc);
        }
        
        // Auto-fit bounds if enabled
        if (_autoTrackEnabled) {
          _fitDriverAndMe();
        }
      }
    } else {
      if (_routePoints.isNotEmpty) {
        setState(() {
          _routePoints = [];
          _lastFetchedDriverLocation = null;
        });
      }
    }
  }

  Future<void> _initLocation() async {
    bool serviceEnabled;
    LocationPermission permission;

    serviceEnabled = await Geolocator.isLocationServiceEnabled();
    if (!serviceEnabled) return;

    permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
      if (permission == LocationPermission.denied) return;
    }

    if (permission == LocationPermission.deniedForever) return;

    // ── Step 1: Show last known position IMMEDIATELY (cached, near-instant) ──
    try {
      final lastKnown = await Geolocator.getLastKnownPosition();
      if (lastKnown != null && mounted) {
        setState(() {
          _currentLocation = LatLng(lastKnown.latitude, lastKnown.longitude);
          _gpsAccuracy = lastKnown.accuracy;
        });
        _mapController.move(_currentLocation!, 17);
      }
    } catch (_) {}

    // ── Step 2: Get accurate current position (may take a few seconds) ──
    try {
      final pos = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.high,
          timeLimit: Duration(seconds: 10),
        ),
      );
      if (mounted) {
        setState(() {
          _currentLocation = LatLng(pos.latitude, pos.longitude);
          _gpsAccuracy = pos.accuracy;
        });
        _mapController.move(_currentLocation!, 18);

        final sos = Provider.of<SosProvider>(context, listen: false);
        if (sos.hasActiveRequest &&
            sos.activeRequest!.driverLat != null &&
            sos.activeRequest!.driverLng != null) {
          _lastFetchedDriverLocation = LatLng(
              sos.activeRequest!.driverLat!, sos.activeRequest!.driverLng!);
          _fetchRoute(_currentLocation!, _lastFetchedDriverLocation!);
          _fitDriverAndMe();
        }
      }
    } catch (_) {
      // If high-accuracy fails, keep last-known position shown above
    }
  }

  Future<void> _updateLocation() async {
    try {
      final auth = Provider.of<AuthProvider>(context, listen: false);
      Position pos = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(accuracy: LocationAccuracy.high),
      ).timeout(const Duration(seconds: 8));
      if (auth.user != null) {
        await ApiService.updateUserLocation(auth.user!.id.toString(), pos.latitude, pos.longitude);
      }
      if (mounted) {
        setState(() {
          _currentLocation = LatLng(pos.latitude, pos.longitude);
          _gpsAccuracy = pos.accuracy;
        });
        if (_lastFetchedDriverLocation != null) {
          _fetchRoute(_currentLocation!, _lastFetchedDriverLocation!);
        }
        if (_autoTrackEnabled && _lastFetchedDriverLocation != null) {
          _fitDriverAndMe();
        }
      }
    } catch (_) {}
  }

  Future<void> _fetchRoute(LatLng start, LatLng end) async {
    try {
      final url = Uri.parse(
        'https://router.project-osrm.org/route/v1/driving/'
        '${start.longitude},${start.latitude};${end.longitude},${end.latitude}'
        '?overview=full&geometries=geojson'
      );
      final response = await http.get(url).timeout(const Duration(seconds: 8));
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
          
          if (mounted) {
            setState(() {
              _routePoints = points;
            });
          }
          return;
        }
      }
    } catch (e) {
      debugPrint('OSRM routing failed: $e');
    }
    
    // Fallback to straight line
    if (mounted) {
      setState(() {
        _routePoints = [start, end];
      });
    }
  }

  String _calculateDistanceStr(SosProvider sos) {
    if (_currentLocation == null ||
        !sos.hasActiveRequest ||
        sos.activeRequest!.driverLat == null ||
        sos.activeRequest!.driverLng == null) {
      return '--';
    }
    double dist = Geolocator.distanceBetween(
      _currentLocation!.latitude,
      _currentLocation!.longitude,
      sos.activeRequest!.driverLat!,
      sos.activeRequest!.driverLng!,
    );
    if (dist >= 1000) {
      return '${(dist / 1000).toStringAsFixed(1)} km';
    } else {
      return '${dist.toStringAsFixed(0)} m';
    }
  }

  String _calculateEtaStr(SosProvider sos, BuildContext ctx) {
    if (_currentLocation == null ||
        !sos.hasActiveRequest ||
        sos.activeRequest!.driverLat == null ||
        sos.activeRequest!.driverLng == null) {
      return AppTranslator.t(ctx, 'Calculating…');
    }
    double dist = Geolocator.distanceBetween(
      _currentLocation!.latitude,
      _currentLocation!.longitude,
      sos.activeRequest!.driverLat!,
      sos.activeRequest!.driverLng!,
    );
    int minutes = (dist / 1000 / 40 * 60).ceil();
    if (minutes <= 1) {
      return AppTranslator.t(ctx, 'Arriving now');
    }
    return 'ETA: ~$minutes ${AppTranslator.t(ctx, 'mins')}';
  }



  String _getTileUrl() {
    switch (_mapType) {
      case 'sat':
        return 'https://mt1.google.com/vt/lyrs=y&x={x}&y={y}&z={z}';
      case 'std':
      default:
        return 'https://mt1.google.com/vt/lyrs=m&x={x}&y={y}&z={z}';
    }
  }

  void _zoomIn() {
    final currentZoom = _mapController.camera.zoom;
    _mapController.move(_mapController.camera.center, currentZoom + 1);
  }

  void _zoomOut() {
    final currentZoom = _mapController.camera.zoom;
    _mapController.move(_mapController.camera.center, currentZoom - 1);
  }

  void _fitDriverAndMe() {
    final sos = Provider.of<SosProvider>(context, listen: false);
    if (_currentLocation != null &&
        sos.hasActiveRequest &&
        sos.activeRequest!.driverLat != null &&
        sos.activeRequest!.driverLng != null) {
      final driverLatLng = LatLng(sos.activeRequest!.driverLat!, sos.activeRequest!.driverLng!);
      
      final bounds = LatLngBounds.fromPoints([_currentLocation!, driverLatLng]);
      _mapController.fitCamera(
        CameraFit.bounds(
          bounds: bounds,
          padding: const EdgeInsets.all(70),
        ),
      );
    } else if (_currentLocation != null) {
      _mapController.move(_currentLocation!, 16);
    }
  }

  @override
  Widget build(BuildContext context) {
    final sos = Provider.of<SosProvider>(context);

    // Auto-fit camera once when a driver is assigned
    if (sos.hasActiveRequest &&
        sos.activeRequest!.driverLat != null &&
        sos.activeRequest!.driverLng != null &&
        _currentLocation != null &&
        !_hasFittedBounds) {
      _hasFittedBounds = true;
      WidgetsBinding.instance.addPostFrameCallback((_) {
        _fitDriverAndMe();
      });
    } else if (!sos.hasActiveRequest) {
      _hasFittedBounds = false; // Reset when no active request
    }

    double bottomOffset = 16.0;
    if (sos.hasActiveRequest) {
      if (sos.activeRequest!.driverAssigned || sos.activeRequest!.unitName.isNotEmpty) {
        bottomOffset = 210.0;
      } else {
        bottomOffset = 100.0;
      }
    }

    List<Marker> markers = [];
    
    if (_currentLocation != null) {
      markers.add(
        Marker(
          point: _currentLocation!,
          width: 60,
          height: 60,
          child: const Center(
            child: UserLiveLocationMarker(),
          ),
        ),
      );
    }

    if (sos.hasActiveRequest && sos.activeRequest!.driverLat != null && sos.activeRequest!.driverLng != null) {
      markers.add(
        Marker(
          point: LatLng(sos.activeRequest!.driverLat!, sos.activeRequest!.driverLng!),
          width: 80,
          height: 80,
          child: const Center(
            child: WailingAmbulanceMarker(),
          ),
        ),
      );
    }

    final latStr = _currentLocation != null ? _currentLocation!.latitude.toStringAsFixed(6) : "2.037703";
    final lngStr = _currentLocation != null ? _currentLocation!.longitude.toStringAsFixed(6) : "45.301082";

    return Scaffold(
      backgroundColor: const Color(0xFFEFF6FF), // Soft background tint
      body: SafeArea(
        child: Container(
          decoration: BoxDecoration(
            color: Colors.white,
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: 0.06),
                blurRadius: 20,
                offset: const Offset(0, 8),
              ),
            ],
          ),
            child: Column(
              children: [
                // 1. TOP HEADER PANEL
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 16.0, vertical: 12.0),
                  child: Row(
                    children: [
                      // Live Location Indicator
                      Row(
                        children: [
                          Container(
                            padding: const EdgeInsets.all(8),
                            decoration: BoxDecoration(
                              color: Colors.red.withValues(alpha: 0.1),
                              shape: BoxShape.circle,
                            ),
                            child: const Icon(
                              Icons.location_on,
                              color: Colors.red,
                              size: 20,
                            ),
                          ),
                          const SizedBox(width: 8),
                          Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                AppTranslator.t(context, 'LIVE'),
                                style: const TextStyle(
                                  fontSize: 10,
                                  fontWeight: FontWeight.w800,
                                  color: Colors.grey,
                                  letterSpacing: 1.0,
                                ),
                              ),
                              Text(
                                AppTranslator.t(context, 'LOCATION'),
                                style: const TextStyle(
                                  fontSize: 13,
                                  fontWeight: FontWeight.w900,
                                  color: Color(0xFF1E293B),
                                ),
                              ),
                            ],
                          ),
                        ],
                      ),
                      const Spacer(),
                      // Fix Location Button
                      InkWell(
                        onTap: () {
                          if (_currentLocation != null) {
                            _mapController.move(_currentLocation!, 15);
                          }
                        },
                        borderRadius: BorderRadius.circular(12),
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                          decoration: BoxDecoration(
                            color: const Color(0xFFF1F5F9),
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(color: Colors.grey.shade300),
                          ),
                          child: Row(
                            children: [
                              const Icon(
                                Icons.gps_fixed,
                                size: 14,
                                color: Colors.black87,
                              ),
                              const SizedBox(width: 6),
                              Text(
                                AppTranslator.t(context, 'Fix Location'),
                                style: const TextStyle(
                                  fontSize: 12,
                                  fontWeight: FontWeight.w700,
                                  color: Colors.black87,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                      const Spacer(),
                      // GPS Quality
                      Row(
                        children: [
                          const Icon(
                            Icons.auto_awesome,
                            color: Colors.orange,
                            size: 14,
                          ),
                          const SizedBox(width: 4),
                          Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                AppTranslator.t(context, 'GPS Good'),
                                style: const TextStyle(
                                  fontSize: 11,
                                  fontWeight: FontWeight.w800,
                                  color: Color(0xFF1E293B),
                                ),
                              ),
                              Text(
                                "(±${_gpsAccuracy.toStringAsFixed(0)}m)",
                                style: const TextStyle(
                                  fontSize: 10,
                                  fontWeight: FontWeight.w600,
                                  color: Colors.grey,
                                ),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ],
                  ),
                ),

                // Divider
                Container(
                  height: 1,
                  color: Colors.grey.shade200,
                ),

                // 2. MAP VIEW CONTAINER WITH CORNER CLIP
                Expanded(
                  child: ClipRRect(
                    borderRadius: const BorderRadius.only(
                      bottomLeft: Radius.circular(0),
                      bottomRight: Radius.circular(0),
                    ),
                    child: Stack(
                      children: [
                        FlutterMap(
                          mapController: _mapController,
                          options: MapOptions(
                            initialCenter: _currentLocation ?? const LatLng(2.037703, 45.301082),
                            initialZoom: 15,
                            minZoom: 3,
                            maxZoom: 20,
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
                                    strokeWidth: 5.0,
                                    color: const Color(0xFF3B82F6),
                                    borderColor: Colors.white,
                                    borderStrokeWidth: 1.5,
                                  ),
                                ],
                              ),
                            MarkerLayer(markers: markers),
                          ],
                        ),

                        // A. MAP LAYER SWITCHER TOGGLE
                        Positioned(
                          top: 16,
                          left: 16,
                          right: 16,
                          child: Center(
                            child: Container(
                              padding: const EdgeInsets.all(4),
                              decoration: BoxDecoration(
                                color: Colors.white,
                                borderRadius: BorderRadius.circular(14),
                                boxShadow: [
                                  BoxShadow(
                                    color: Colors.black.withValues(alpha: 0.08),
                                    blurRadius: 10,
                                    offset: const Offset(0, 4),
                                  ),
                                ],
                              ),
                              child: Row(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  _buildMapTypeBtn(context, "std", Icons.map, AppTranslator.t(context, 'Map')),
                                  _buildMapTypeBtn(context, "sat", Icons.layers, AppTranslator.t(context, 'Satellite')),
                                ],
                              ),
                            ),
                          ),
                        ),

                        // Floating App Drawer Toggle Button (placed below map switcher)
                        Positioned(
                          top: 76,
                          left: 16,
                          child: Builder(
                            builder: (context) => Container(
                              decoration: BoxDecoration(
                                color: Colors.white,
                                shape: BoxShape.circle,
                                boxShadow: [
                                  BoxShadow(
                                    color: Colors.black.withValues(alpha: 0.12),
                                    blurRadius: 8,
                                    offset: const Offset(0, 3),
                                  )
                                ],
                              ),
                              child: IconButton(
                                icon: const Icon(Icons.menu_rounded, color: Color(0xFF2563EB)),
                                onPressed: () => UserShell.scaffoldKey.currentState?.openDrawer(),
                              ),
                            ),
                          ),
                        ),

                        // B. BOTTOM FLOATING COORDINATE BADGE
                        Positioned(
                          bottom: bottomOffset,
                          left: 0,
                          right: 0,
                          child: Center(
                            child: Container(
                              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                              decoration: BoxDecoration(
                                color: const Color(0xFF2563EB),
                                borderRadius: BorderRadius.circular(20),
                                boxShadow: [
                                  BoxShadow(
                                    color: const Color(0xFF2563EB).withValues(alpha: 0.3),
                                    blurRadius: 10,
                                    offset: const Offset(0, 4),
                                  ),
                                ],
                              ),
                              child: Row(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  const Icon(
                                    Icons.gps_fixed,
                                    color: Colors.white,
                                    size: 14,
                                  ),
                                  const SizedBox(width: 6),
                                  Text(
                                    "$latStr, $lngStr",
                                    style: const TextStyle(
                                      color: Colors.white,
                                      fontWeight: FontWeight.w800,
                                      fontSize: 12,
                                      letterSpacing: 0.5,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ),
                        ),

                        // C. VERTICAL ZOOM CONTROL PANEL
                        Positioned(
                          bottom: bottomOffset,
                          right: 16,
                          child: Container(
                            decoration: BoxDecoration(
                              color: Colors.white,
                              borderRadius: BorderRadius.circular(12),
                              boxShadow: [
                                BoxShadow(
                                  color: Colors.black.withValues(alpha: 0.12),
                                  blurRadius: 10,
                                  offset: const Offset(0, 4),
                                ),
                              ],
                            ),
                            child: Column(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                _buildZoomBtn(Icons.add, _zoomIn),
                                Container(
                                  width: 24,
                                  height: 1,
                                  color: Colors.grey.shade200,
                                ),
                                _buildZoomBtn(Icons.remove, _zoomOut),
                                Container(
                                  width: 24,
                                  height: 1,
                                  color: Colors.grey.shade200,
                                ),
                                _buildZoomBtn(Icons.explore_rounded, _fitDriverAndMe),
                              ],
                            ),
                          ),
                        ),

                        // D. BOTTOM FLOATING EMERGENCY PANEL / TRACKING CARD
                        if (sos.hasActiveRequest)
                          (sos.activeRequest!.driverAssigned || sos.activeRequest!.unitName.isNotEmpty)
                              ? Positioned(
                                  bottom: 16,
                                  left: 16,
                                  right: 16,
                                  child: Container(
                                    padding: const EdgeInsets.all(16),
                                    decoration: BoxDecoration(
                                      color: Colors.white,
                                      borderRadius: BorderRadius.circular(24),
                                      border: Border.all(color: const Color(0xFFE2E8F0)),
                                      boxShadow: [
                                        BoxShadow(
                                          color: Colors.black.withValues(alpha: 0.08),
                                          blurRadius: 20,
                                          offset: const Offset(0, 6),
                                        ),
                                      ],
                                    ),
                                    child: Column(
                                      mainAxisSize: MainAxisSize.min,
                                      children: [
                                        Row(
                                          children: [
                                            Container(
                                              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                                              decoration: BoxDecoration(
                                                color: const Color(0xFFEFF6FF),
                                                borderRadius: BorderRadius.circular(20),
                                              ),
                                              child: Row(
                                                children: [
                                                  const Icon(
                                                    Icons.local_shipping_rounded,
                                                    size: 14,
                                                    color: Color(0xFF2563EB),
                                                  ),
                                                  const SizedBox(width: 4),
                                                  Text(
                                                    sos.activeRequest!.statusLabel.toUpperCase(),
                                                    style: const TextStyle(
                                                      fontSize: 10,
                                                      fontWeight: FontWeight.w800,
                                                      color: Color(0xFF2563EB),
                                                      letterSpacing: 0.5,
                                                    ),
                                                  ),
                                                ],
                                              ),
                                            ),
                                            const Spacer(),
                                            Row(
                                              children: [
                                                Text(
                                                  AppTranslator.t(context, 'Auto-Track'),
                                                  style: const TextStyle(
                                                    fontSize: 11,
                                                    fontWeight: FontWeight.w700,
                                                    color: Colors.grey,
                                                  ),
                                                ),
                                                const SizedBox(width: 4),
                                                SizedBox(
                                                  height: 20,
                                                  width: 35,
                                                  child: Switch(
                                                    value: _autoTrackEnabled,
                                                    onChanged: (val) {
                                                      setState(() {
                                                        _autoTrackEnabled = val;
                                                      });
                                                      if (val) {
                                                        _fitDriverAndMe();
                                                      }
                                                    },
                                                    activeThumbColor: const Color(0xFF2563EB),
                                                    activeTrackColor: const Color(0xFFBFDBFE),
                                                    materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
                                                  ),
                                                ),
                                              ],
                                            ),
                                          ],
                                        ),
                                        const SizedBox(height: 12),
                                        Row(
                                          children: [
                                            Container(
                                              width: 48,
                                              height: 48,
                                              decoration: BoxDecoration(
                                                color: const Color(0xFFF1F5F9),
                                                shape: BoxShape.circle,
                                                border: Border.all(color: const Color(0xFFE2E8F0)),
                                              ),
                                              child: Center(
                                                child: Text(
                                                  sos.activeRequest!.driverName.isNotEmpty
                                                      ? sos.activeRequest!.driverName[0].toUpperCase()
                                                      : 'R',
                                                  style: const TextStyle(
                                                    fontWeight: FontWeight.w900,
                                                    fontSize: 18,
                                                    color: Color(0xFF1E293B),
                                                  ),
                                                ),
                                              ),
                                            ),
                                            const SizedBox(width: 12),
                                            Expanded(
                                              child: Column(
                                                crossAxisAlignment: CrossAxisAlignment.start,
                                                children: [
                                                  Text(
                                                    sos.activeRequest!.driverName.isNotEmpty
                                                        ? sos.activeRequest!.driverName
                                                        : AppTranslator.t(context, 'Emergency Responder'),
                                                    style: const TextStyle(
                                                      fontSize: 15,
                                                      fontWeight: FontWeight.w900,
                                                      color: Color(0xFF1E293B),
                                                    ),
                                                  ),
                                                  const SizedBox(height: 2),
                                                  Text(
                                                    '${sos.activeRequest!.unitType} • ${sos.activeRequest!.unitName} (${sos.activeRequest!.plateNumber})',
                                                    style: TextStyle(
                                                      fontSize: 12,
                                                      color: Colors.grey.shade600,
                                                      fontWeight: FontWeight.w600,
                                                    ),
                                                    maxLines: 1,
                                                    overflow: TextOverflow.ellipsis,
                                                  ),
                                                  if (sos.activeRequest!.driverPhone.isNotEmpty) ...[
                                                    const SizedBox(height: 2),
                                                    Text(
                                                      sos.activeRequest!.driverPhone,
                                                      style: const TextStyle(
                                                        color: Color(0xFF10B981),
                                                        fontSize: 12,
                                                        fontWeight: FontWeight.w700,
                                                      ),
                                                    ),
                                                  ],
                                                ],
                                              ),
                                            ),
                                            Column(
                                              crossAxisAlignment: CrossAxisAlignment.end,
                                              children: [
                                                Text(
                                                  _calculateDistanceStr(sos),
                                                  style: const TextStyle(
                                                    fontSize: 14,
                                                    fontWeight: FontWeight.w900,
                                                    color: Color(0xFF1E293B),
                                                  ),
                                                ),
                                                const SizedBox(height: 2),
                                                Text(
                                                  _calculateEtaStr(sos, context),
                                                  style: const TextStyle(
                                                    fontSize: 11,
                                                    fontWeight: FontWeight.w800,
                                                    color: Colors.orange,
                                                  ),
                                                ),
                                              ],
                                            ),
                                          ],
                                        ),
                                        const SizedBox(height: 12),
                                        Row(
                                          children: [
                                            Expanded(
                                              child: ElevatedButton.icon(
                                                onPressed: () => CallScreen.show(
                                                  context,
                                                  name: sos.activeRequest!.driverName,
                                                  phone: sos.activeRequest!.driverPhone,
                                                ),
                                                icon: const Icon(Icons.phone, size: 16),
                                                label: Text(AppTranslator.t(context, 'Call Responder')),
                                                style: ElevatedButton.styleFrom(
                                                  backgroundColor: const Color(0xFF2563EB),
                                                  foregroundColor: Colors.white,
                                                  elevation: 0,
                                                  padding: const EdgeInsets.symmetric(vertical: 12),
                                                  shape: RoundedRectangleBorder(
                                                    borderRadius: BorderRadius.circular(12),
                                                  ),
                                                  textStyle: const TextStyle(
                                                    fontWeight: FontWeight.w800,
                                                    fontSize: 13,
                                                  ),
                                                ),
                                              ),
                                            ),
                                            const SizedBox(width: 8),
                                            OutlinedButton(
                                              onPressed: () {
                                                showDialog(
                                                  context: context,
                                                  builder: (ctx) => AlertDialog(
                                                    shape: RoundedRectangleBorder(
                                                      borderRadius: BorderRadius.circular(16),
                                                    ),
                                                    title: Text(AppTranslator.t(context, 'Cancel Request?'), style: const TextStyle(fontWeight: FontWeight.bold)),
                                                    content: Text(AppTranslator.t(context, 'Are you sure you want to cancel this emergency SOS request?')),
                                                    actions: [
                                                      TextButton(
                                                        onPressed: () => Navigator.pop(ctx),
                                                        child: Text(AppTranslator.t(context, 'No, Keep Active')),
                                                      ),
                                                      TextButton(
                                                        onPressed: () {
                                                          Navigator.pop(ctx);
                                                          sos.cancelActiveRequest();
                                                        },
                                                        child: Text(AppTranslator.t(context, 'Yes, Cancel'), style: const TextStyle(color: Colors.red, fontWeight: FontWeight.bold)),
                                                      ),
                                                    ],
                                                  ),
                                                );
                                              },
                                              style: OutlinedButton.styleFrom(
                                                foregroundColor: Colors.red,
                                                side: const BorderSide(color: Colors.red),
                                                padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 16),
                                                shape: RoundedRectangleBorder(
                                                  borderRadius: BorderRadius.circular(12),
                                                ),
                                              ),
                                              child: const Icon(Icons.close, size: 20),
                                            ),
                                          ],
                                        ),
                                      ],
                                    ),
                                  ),
                                )
                              : Positioned(
                                  bottom: 16,
                                  left: 16,
                                  right: 16,
                                  child: Container(
                                    padding: const EdgeInsets.all(16),
                                    decoration: BoxDecoration(
                                      color: Colors.white,
                                      borderRadius: BorderRadius.circular(16),
                                      border: Border.all(color: const Color(0xFFE2E8F0)),
                                      boxShadow: [
                                        BoxShadow(
                                          color: Colors.black.withValues(alpha: 0.08),
                                          blurRadius: 10,
                                          offset: const Offset(0, 4),
                                        ),
                                      ],
                                    ),
                                    child: Row(
                                      children: [
                                        Container(
                                          padding: const EdgeInsets.all(8),
                                          decoration: const BoxDecoration(
                                            color: Color(0xFFEFF6FF),
                                            shape: BoxShape.circle,
                                          ),
                                          child: const SizedBox(
                                            width: 20,
                                            height: 20,
                                            child: CircularProgressIndicator(
                                              strokeWidth: 2,
                                              valueColor: AlwaysStoppedAnimation<Color>(Color(0xFF2563EB)),
                                            ),
                                          ),
                                        ),
                                        const SizedBox(width: 12),
                                        Expanded(
                                          child: Column(
                                            crossAxisAlignment: CrossAxisAlignment.start,
                                            children: [
                                              Text(
                                                AppTranslator.t(context, 'Finding Emergency Unit'),
                                                style: const TextStyle(
                                                  fontWeight: FontWeight.w900,
                                                  fontSize: 14,
                                                  color: Color(0xFF1E293B),
                                                ),
                                              ),
                                              const SizedBox(height: 2),
                                              Text(
                                                sos.activeRequest!.statusLabel,
                                                style: const TextStyle(
                                                  color: Colors.grey,
                                                  fontSize: 12,
                                                  fontWeight: FontWeight.w600,
                                                ),
                                              ),
                                            ],
                                          ),
                                        ),
                                        OutlinedButton(
                                          onPressed: () {
                                            showDialog(
                                              context: context,
                                              builder: (ctx) => AlertDialog(
                                                shape: RoundedRectangleBorder(
                                                  borderRadius: BorderRadius.circular(16),
                                                ),
                                                title: Text(AppTranslator.t(context, 'Cancel Request?'), style: const TextStyle(fontWeight: FontWeight.bold)),
                                                content: Text(AppTranslator.t(context, 'Are you sure you want to cancel this emergency SOS request?')),
                                                actions: [
                                                  TextButton(
                                                    onPressed: () => Navigator.pop(ctx),
                                                    child: Text(AppTranslator.t(context, 'No, Keep Active')),
                                                  ),
                                                  TextButton(
                                                    onPressed: () {
                                                      Navigator.pop(ctx);
                                                      sos.cancelActiveRequest();
                                                    },
                                                    child: Text(AppTranslator.t(context, 'Yes, Cancel'), style: const TextStyle(color: Colors.red, fontWeight: FontWeight.bold)),
                                                  ),
                                                ],
                                              ),
                                            );
                                          },
                                          style: OutlinedButton.styleFrom(
                                            foregroundColor: Colors.red,
                                            side: const BorderSide(color: Colors.red),
                                            padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 12),
                                            shape: RoundedRectangleBorder(
                                              borderRadius: BorderRadius.circular(12),
                                            ),
                                          ),
                                          child: Text(AppTranslator.t(context, 'Cancel'), style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12)),
                                        ),
                                      ],
                                    ),
                                  ),
                                ),
                      ],
                    ),
                  ),
                ),

                // Divider
                Container(
                  height: 1,
                  color: Colors.grey.shade200,
                ),

                // 3. BOTTOM STATUS PANEL
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 16.0, vertical: 12.0),
                  child: Row(
                    children: [
                      const Icon(
                        Icons.settings_input_antenna_rounded,
                        color: Colors.grey,
                        size: 14,
                      ),
                      const SizedBox(width: 6),
                      Text(
                        "${AppTranslator.t(context, 'GPS Good')} (±${_gpsAccuracy.toStringAsFixed(0)}m)",
                        style: TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.w700,
                          color: Colors.grey.shade600,
                        ),
                      ),
                      const Spacer(),
                      const Icon(
                        Icons.auto_awesome,
                        color: Colors.orange,
                        size: 14,
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
    );
  }

  Widget _buildMapTypeBtn(BuildContext context, String type, IconData icon, String label) {
    final bool isActive = _mapType == type;

    return InkWell(
      onTap: () {
        setState(() {
          _mapType = type;
        });
      },
      borderRadius: BorderRadius.circular(10),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
        decoration: BoxDecoration(
          color: isActive ? const Color(0xFF2563EB) : Colors.transparent,
          borderRadius: BorderRadius.circular(10),
        ),
        child: Row(
          children: [
            Icon(
              icon,
              size: 14,
              color: isActive ? Colors.white : Colors.grey.shade800,
            ),
            const SizedBox(width: 6),
            Text(
              label,
              style: TextStyle(
                fontSize: 11,
                fontWeight: FontWeight.w800,
                color: isActive ? Colors.white : Colors.grey.shade800,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildZoomBtn(IconData icon, VoidCallback action) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: action,
        borderRadius: BorderRadius.circular(8),
        child: Container(
          padding: const EdgeInsets.all(10),
          child: Icon(
            icon,
            size: 20,
            color: Colors.grey.shade800,
          ),
        ),
      ),
    );
  }
}

class WailingAmbulanceMarker extends StatefulWidget {
  const WailingAmbulanceMarker({super.key});

  @override
  State<WailingAmbulanceMarker> createState() => _WailingAmbulanceMarkerState();
}

class _WailingAmbulanceMarkerState extends State<WailingAmbulanceMarker>
    with SingleTickerProviderStateMixin {
  late AnimationController _controller;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1000),
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
        // Alternating siren red/blue color
        final Color sirenColor = pulse < 0.5 ? Colors.red : Colors.blue;
        
        return Stack(
          alignment: Alignment.center,
          children: [
            // Outer wailing shockwave pulse
            Container(
              width: 45 + (pulse * 30),
              height: 45 + (pulse * 30),
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: sirenColor.withValues(alpha: 0.18 * (1.0 - pulse)),
                border: Border.all(
                  color: sirenColor.withValues(alpha: 0.35 * (1.0 - pulse)),
                  width: 1.5,
                ),
              ),
            ),
            // Inner pulsing glow ring
            Container(
              width: 44,
              height: 44,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: Colors.white,
                boxShadow: [
                  BoxShadow(
                    color: sirenColor.withValues(alpha: 0.4),
                    blurRadius: 8,
                    spreadRadius: 2,
                  ),
                ],
              ),
            ),
            // Premium Rescue vehicle circle
            Container(
              width: 36,
              height: 36,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                gradient: LinearGradient(
                  colors: [
                    sirenColor.withValues(alpha: 0.85),
                    sirenColor,
                  ],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
              ),
              child: const Icon(
                Icons.local_shipping_rounded, // Premium rescue vehicle icon
                color: Colors.white,
                size: 20,
              ),
            ),
            // Wailing little beacon light at top right of marker
            Positioned(
              top: 2,
              right: 2,
              child: Container(
                width: 8,
                height: 8,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: pulse >= 0.5 ? Colors.red : Colors.blue,
                  border: Border.all(color: Colors.white, width: 1),
                  boxShadow: [
                    BoxShadow(
                      color: (pulse >= 0.5 ? Colors.red : Colors.blue).withValues(alpha: 0.8),
                      blurRadius: 4,
                      spreadRadius: 1,
                    )
                  ]
                ),
              ),
            ),
          ],
        );
      },
    );
  }
}

class UserLiveLocationMarker extends StatefulWidget {
  const UserLiveLocationMarker({super.key});

  @override
  State<UserLiveLocationMarker> createState() => _UserLiveLocationMarkerState();
}

class _UserLiveLocationMarkerState extends State<UserLiveLocationMarker>
    with SingleTickerProviderStateMixin {
  late AnimationController _controller;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(seconds: 2),
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
        return Stack(
          alignment: Alignment.center,
          children: [
            // Outer pulsing ring
            Container(
              width: 16 + (pulse * 28),
              height: 16 + (pulse * 28),
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: const Color(0xFF3B82F6).withValues(alpha: 0.4 * (1.0 - pulse)),
              ),
            ),
            // White border ring
            Container(
              width: 18,
              height: 18,
              decoration: const BoxDecoration(
                shape: BoxShape.circle,
                color: Colors.white,
                boxShadow: [
                  BoxShadow(
                    color: Colors.black12,
                    blurRadius: 4,
                    offset: Offset(0, 2),
                  ),
                ],
              ),
            ),
            // Blue core dot
            Container(
              width: 12,
              height: 12,
              decoration: const BoxDecoration(
                shape: BoxShape.circle,
                color: Color(0xFF2563EB),
              ),
            ),
          ],
        );
      },
    );
  }
}
