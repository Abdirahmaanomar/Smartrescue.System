import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../services/api_service.dart';
import '../../constants/api_constants.dart';
import 'package:provider/provider.dart';
import '../../providers/auth_provider.dart';
import 'user_medical_screen.dart';
import '../../utils/translator.dart';
import '../../utils/responsive.dart';

class UserOnlineRespondersScreen extends StatefulWidget {
  const UserOnlineRespondersScreen({super.key});

  @override
  State<UserOnlineRespondersScreen> createState() => _UserOnlineRespondersScreenState();
}

class _UserOnlineRespondersScreenState extends State<UserOnlineRespondersScreen> {
  bool _isLoading = true;
  List<dynamic> _responders = [];

  // Blood Donors
  bool _isLoadingDonors = true;
  List<dynamic> _donors = [];

  // Analytics Data
  bool _isLoadingAnalytics = true;
  Map<String, dynamic> _analyticsData = {};

  @override
  void initState() {
    super.initState();
    _fetchResponders();
    _fetchBloodDonors();
    _fetchAnalytics();
  }

  Future<void> _fetchAnalytics() async {
    setState(() => _isLoadingAnalytics = true);
    final auth = Provider.of<AuthProvider>(context, listen: false);
    final userId = auth.user?.id.toString() ?? '0';
    final data = await ApiService.getAnalytics(userId);
    setState(() {
      _analyticsData = data;
      _isLoadingAnalytics = false;
    });
  }

  Future<void> _fetchResponders() async {
    setState(() => _isLoading = true);
    final responders = await ApiService.getOnlineResponders();
    setState(() {
      _responders = responders;
      _isLoading = false;
    });
  }

  Future<void> _fetchBloodDonors() async {
    setState(() => _isLoadingDonors = true);
    final donors = await ApiService.getBloodDonors();
    setState(() {
      _donors = donors;
      _isLoadingDonors = false;
    });
  }

  Future<void> _callPhone(String phone) async {
    final Uri launchUri = Uri(
      scheme: 'tel',
      path: phone,
    );
    if (await canLaunchUrl(launchUri)) {
      await launchUrl(launchUri);
    }
  }

  Color _getUnitTypeColor(String type) {
    switch (type.toLowerCase()) {
      case 'medical':
        return const Color(0xFFE11D48); // Rose red
      case 'fire':
        return const Color(0xFFF59E0B); // Amber
      case 'police':
        return const Color(0xFF2563EB); // Blue
      default:
        return const Color(0xFF8B5CF6); // Purple
    }
  }

  IconData _getUnitTypeIcon(String type) {
    switch (type.toLowerCase()) {
      case 'medical':
        return Icons.local_hospital_rounded;
      case 'fire':
        return Icons.local_fire_department_rounded;
      case 'police':
        return Icons.local_police_rounded;
      default:
        return Icons.local_shipping_rounded;
    }
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return DefaultTabController(
      length: 3,
      child: Scaffold(
        backgroundColor: isDark ? const Color(0xFF0F172A) : const Color(0xFFF1F5F9),
        body: SafeArea(
          child: Responsive(context).wrapWidescreen(
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Header
                Padding(
                  padding: const EdgeInsets.fromLTRB(20, 20, 20, 8),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            AppTranslator.t(context, 'More'),
                            style: TextStyle(
                              fontSize: 28,
                              fontWeight: FontWeight.w900,
                              color: isDark ? Colors.white : const Color(0xFF0F172A),
                              letterSpacing: -0.5,
                            ),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            AppTranslator.t(context, 'Available emergency units nearby'),
                            style: TextStyle(
                              fontSize: 13,
                              color: isDark ? Colors.grey.shade400 : Colors.grey.shade600,
                              fontWeight: FontWeight.w500,
                            ),
                          ),
                        ],
                      ),
                      IconButton(
                        onPressed: () {
                          _fetchResponders();
                          _fetchBloodDonors();
                          _fetchAnalytics();
                        },
                        icon: Icon(Icons.refresh_rounded, color: isDark ? Colors.white : Colors.black87),
                      ),
                    ],
                  ),
                ),

                // TabBar
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 8),
                  child: Container(
                    height: 44,
                    decoration: BoxDecoration(
                      color: isDark ? const Color(0xFF1E293B) : Colors.grey.shade200,
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: TabBar(
                      indicatorSize: TabBarIndicatorSize.tab,
                      dividerColor: Colors.transparent,
                      indicator: BoxDecoration(
                        color: const Color(0xFFE11D48),
                        borderRadius: BorderRadius.circular(12),
                      ),
                      labelColor: Colors.white,
                      unselectedLabelColor: isDark ? Colors.grey.shade400 : Colors.grey.shade600,
                      labelStyle: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13),
                      tabs: [
                        Tab(text: AppTranslator.t(context, 'Rescue Units')),
                        Tab(text: AppTranslator.t(context, 'Blood Donors')),
                        Tab(text: AppTranslator.t(context, 'Analytics')),
                      ],
                    ),
                  ),
                ),
                
                // Tab Views
                Expanded(
                  child: TabBarView(
                    children: [
                      // Tab 1: Rescue Units
                      _isLoading
                          ? const Center(child: CircularProgressIndicator())
                          : _responders.isEmpty
                              ? _buildEmptyState(isDark, AppTranslator.t(context, 'No Responders Online'), AppTranslator.t(context, 'Check back later for available units.'))
                              : RefreshIndicator(
                                  onRefresh: _fetchResponders,
                                  child: ListView.builder(
                                    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                                    itemCount: _responders.length,
                                    itemBuilder: (context, index) {
                                      return _buildResponderCard(isDark, _responders[index]);
                                    },
                                  ),
                                ),
                                
                      // Tab 2: Blood Donors
                      _isLoadingDonors
                          ? const Center(child: CircularProgressIndicator())
                          : _buildBloodDonorsSection(isDark),

                      // Tab 3: Analytics Dashboard
                      _isLoadingAnalytics
                          ? const Center(child: CircularProgressIndicator())
                          : _buildAnalyticsSection(isDark),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildEmptyState(bool isDark, String title, String subtitle) {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(
            Icons.group_off_rounded,
            size: 64,
            color: isDark ? Colors.grey.shade700 : Colors.grey.shade300,
          ),
          const SizedBox(height: 16),
          Text(
            title,
            style: TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.bold,
              color: isDark ? Colors.grey.shade400 : Colors.grey.shade600,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            subtitle,
            style: TextStyle(
              fontSize: 14,
              color: isDark ? Colors.grey.shade500 : Colors.grey.shade500,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildResponderCard(bool isDark, Map<String, dynamic> responder) {
    final String fullname = responder['fullname'] ?? AppTranslator.t(context, 'Unknown Responder');
    final String phone = responder['phone'] ?? '';
    final String profileImage = responder['profile_image'] ?? '';
    final String unitName = responder['unit_name'] ?? AppTranslator.t(context, 'No Unit');
    final String unitType = responder['unit_type'] ?? 'general';
    final String plateNumber = responder['plate_number'] ?? '';
    final String status = responder['unit_status'] ?? 'unavailable';

    final Color typeColor = _getUnitTypeColor(unitType);
    final IconData typeIcon = _getUnitTypeIcon(unitType);
    
    final bool isAvailable = status.toLowerCase() == 'available';

    return Container(
      margin: const EdgeInsets.only(bottom: 16),
      decoration: BoxDecoration(
        color: isDark ? const Color(0xFF1E293B) : Colors.white,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(
          color: isDark ? Colors.white.withValues(alpha: 0.05) : Colors.black.withValues(alpha: 0.03),
          width: 1,
        ),
        boxShadow: [
          BoxShadow(
            color: typeColor.withValues(alpha: isDark ? 0.05 : 0.08),
            blurRadius: 24,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Material(
        color: Colors.transparent,
        borderRadius: BorderRadius.circular(24),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: phone.isNotEmpty ? () => _callPhone(phone) : null,
          highlightColor: typeColor.withValues(alpha: 0.05),
          splashColor: typeColor.withValues(alpha: 0.1),
          child: Padding(
            padding: const EdgeInsets.all(20),
            child: Row(
              children: [
                // Avatar with glowing online status
                Stack(
                  clipBehavior: Clip.none,
                  children: [
                    Container(
                      width: 64,
                      height: 64,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        color: typeColor.withValues(alpha: 0.1),
                        image: profileImage.isNotEmpty
                            ? DecorationImage(
                                image: NetworkImage(ApiConstants.avatarUrl(profileImage)),
                                fit: BoxFit.cover,
                              )
                            : null,
                        border: Border.all(color: typeColor.withValues(alpha: 0.2), width: 2),
                      ),
                      child: profileImage.isEmpty
                          ? Icon(typeIcon, color: typeColor, size: 28)
                          : null,
                    ),
                    Positioned(
                      bottom: 0,
                      right: 0,
                      child: Container(
                        width: 18,
                        height: 18,
                        decoration: BoxDecoration(
                          color: isAvailable ? const Color(0xFF10B981) : Colors.grey.shade400,
                          shape: BoxShape.circle,
                          border: Border.all(
                            color: isDark ? const Color(0xFF1E293B) : Colors.white, 
                            width: 3,
                          ),
                          boxShadow: isAvailable ? [
                            BoxShadow(
                              color: const Color(0xFF10B981).withValues(alpha: 0.4),
                              blurRadius: 6,
                              spreadRadius: 1,
                            ),
                          ] : null,
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(width: 16),
                
                // Info
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        fullname,
                        style: TextStyle(
                          fontSize: 17,
                          fontWeight: FontWeight.w800,
                          color: isDark ? Colors.white : const Color(0xFF0F172A),
                          letterSpacing: -0.3,
                        ),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                      const SizedBox(height: 6),
                      Wrap(
                        spacing: 6,
                        runSpacing: 6,
                        children: [
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                            decoration: BoxDecoration(
                              color: typeColor.withValues(alpha: 0.1),
                              borderRadius: BorderRadius.circular(8),
                            ),
                            child: Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                Icon(typeIcon, size: 12, color: typeColor),
                                const SizedBox(width: 4),
                                Text(
                                  unitName,
                                  style: TextStyle(
                                    fontSize: 11,
                                    fontWeight: FontWeight.w700,
                                    color: typeColor,
                                  ),
                                ),
                              ],
                            ),
                          ),
                          if (plateNumber.isNotEmpty)
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                              decoration: BoxDecoration(
                                color: isDark ? const Color(0xFF334155) : Colors.grey.shade100,
                                borderRadius: BorderRadius.circular(8),
                              ),
                              child: Text(
                                plateNumber,
                                style: TextStyle(
                                  fontSize: 11,
                                  fontWeight: FontWeight.w700,
                                  color: isDark ? Colors.grey.shade300 : Colors.grey.shade600,
                                ),
                              ),
                            ),
                        ],
                      ),
                    ],
                  ),
                ),
                
                // Action Button (Call)
                if (phone.isNotEmpty)
                  Container(
                    margin: const EdgeInsets.only(left: 12),
                    width: 48,
                    height: 48,
                    decoration: BoxDecoration(
                      color: const Color(0xFF10B981).withValues(alpha: 0.1),
                      shape: BoxShape.circle,
                    ),
                    child: const Icon(
                      Icons.phone_in_talk_rounded,
                      color: Color(0xFF10B981),
                      size: 22,
                    ),
                  ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildBecomeDonorBanner(bool isDark) {
    return GestureDetector(
      onTap: () {
        Navigator.push(
          context,
          MaterialPageRoute(builder: (context) => const UserMedicalScreen()),
        ).then((_) => _fetchBloodDonors());
      },
      child: Container(
        margin: const EdgeInsets.fromLTRB(16, 8, 16, 4),
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          gradient: const LinearGradient(
            colors: [Color(0xFFE11D48), Color(0xFFBE123C)],
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
          ),
          borderRadius: BorderRadius.circular(20),
          boxShadow: [
            BoxShadow(
              color: const Color(0xFFE11D48).withValues(alpha: 0.35),
              blurRadius: 16,
              offset: const Offset(0, 6),
            ),
          ],
        ),
        child: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: 0.2),
                shape: BoxShape.circle,
              ),
              child: const Icon(Icons.volunteer_activism_rounded, color: Colors.white, size: 24),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    AppTranslator.t(context, 'Become a Blood Donor'),
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 15,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    AppTranslator.t(context, 'Register in Medical ID to help save lives'),
                    style: TextStyle(
                      color: Colors.white.withValues(alpha: 0.8),
                      fontSize: 12,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ],
              ),
            ),
            const Icon(Icons.arrow_forward_ios_rounded, color: Colors.white, size: 16),
          ],
        ),
      ),
    );
  }

  Widget _buildBloodDonorsSection(bool isDark) {
    if (_donors.isEmpty) {
      return Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          _buildBecomeDonorBanner(isDark),
          const SizedBox(height: 32),
          Icon(
            Icons.water_drop_outlined,
            size: 72,
            color: const Color(0xFFE11D48).withValues(alpha: 0.25),
          ),
          const SizedBox(height: 16),
          Text(
            AppTranslator.t(context, 'No Blood Donors Yet'),
            style: TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.w800,
              color: isDark ? Colors.grey.shade300 : const Color(0xFF1E293B),
            ),
          ),
          const SizedBox(height: 8),
          Text(
            AppTranslator.t(context, 'Be the first to volunteer!\nRegister via Medical Identity Card.'),
            textAlign: TextAlign.center,
            style: TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.w500,
              color: isDark ? Colors.grey.shade500 : Colors.grey.shade500,
            ),
          ),
        ],
      );
    }

    return Column(
      children: [
        _buildBecomeDonorBanner(isDark),
        Expanded(
          child: RefreshIndicator(
            onRefresh: _fetchBloodDonors,
            child: ListView.builder(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              itemCount: _donors.length,
              itemBuilder: (context, index) {
                final donor = _donors[index] as Map<String, dynamic>;
                final String name  = donor['name']  ?? AppTranslator.t(context, 'Unknown');
                final String blood = donor['blood_type'] ?? '?';
                final String phone = donor['phone'] ?? '';

                return Container(
                  margin: const EdgeInsets.only(bottom: 12),
                  decoration: BoxDecoration(
                    color: isDark ? const Color(0xFF1E293B) : Colors.white,
                    borderRadius: BorderRadius.circular(20),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withValues(alpha: isDark ? 0.3 : 0.05),
                        blurRadius: 10,
                        offset: const Offset(0, 4),
                      ),
                    ],
                  ),
                  padding: const EdgeInsets.all(16),
                  child: Row(
                    children: [
                      // Blood Type Badge
                      Container(
                        width: 54,
                        height: 54,
                        decoration: BoxDecoration(
                          gradient: const LinearGradient(
                            colors: [Color(0xFFE11D48), Color(0xFFBE123C)],
                            begin: Alignment.topLeft,
                            end: Alignment.bottomRight,
                          ),
                          shape: BoxShape.circle,
                          boxShadow: [
                            BoxShadow(
                              color: const Color(0xFFE11D48).withValues(alpha: 0.3),
                              blurRadius: 8,
                              offset: const Offset(0, 3),
                            ),
                          ],
                        ),
                        child: Center(
                          child: Text(
                            blood,
                            style: const TextStyle(
                              color: Colors.white,
                              fontWeight: FontWeight.w900,
                              fontSize: 16,
                            ),
                          ),
                        ),
                      ),
                      const SizedBox(width: 16),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              name,
                              style: TextStyle(
                                fontSize: 16,
                                fontWeight: FontWeight.w800,
                                color: isDark ? Colors.white : const Color(0xFF0F172A),
                              ),
                            ),
                            const SizedBox(height: 4),
                            Row(
                              children: [
                                Icon(Icons.phone_rounded, size: 13, color: isDark ? Colors.grey.shade400 : Colors.grey.shade500),
                                const SizedBox(width: 4),
                                Text(
                                  phone.isNotEmpty ? phone : AppTranslator.t(context, 'No phone'),
                                  style: TextStyle(
                                    fontSize: 13,
                                    fontWeight: FontWeight.w500,
                                    color: isDark ? Colors.grey.shade400 : Colors.grey.shade600,
                                  ),
                                ),
                              ],
                            ),
                          ],
                        ),
                      ),
                      // Call Button
                      if (phone.isNotEmpty)
                        GestureDetector(
                          onTap: () => _callPhone(phone),
                          child: Container(
                            width: 44,
                            height: 44,
                            decoration: BoxDecoration(
                              color: const Color(0xFF10B981).withValues(alpha: 0.12),
                              shape: BoxShape.circle,
                            ),
                            child: const Icon(Icons.phone_rounded, color: Color(0xFF10B981), size: 20),
                          ),
                        ),
                    ],
                  ),
                );
              },
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildAnalyticsSection(bool isDark) {
    final dailyReports = _analyticsData['daily_reports']?.toString() ?? '0';
    final weeklySos = _analyticsData['weekly_sos']?.toString() ?? '0';
    final successRate = _analyticsData['success_rate']?.toString() ?? '0%';
    final safetyScore = _analyticsData['safety_score']?.toString() ?? 'D';
    final dispatchMins = _analyticsData['avg_dispatch_mins']?.toString() ?? '0.0';
    final arrivalMins = _analyticsData['avg_arrival_mins']?.toString() ?? '0.0';

    return SingleChildScrollView(
      padding: const EdgeInsets.all(16.0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Row 1: Daily & Weekly
          Row(
            children: [
              Expanded(child: _buildStatCard(isDark, AppTranslator.t(context, 'Daily Reports'), dailyReports, Icons.today_rounded, const Color(0xFF3B82F6))),
              const SizedBox(width: 16),
              Expanded(child: _buildStatCard(isDark, AppTranslator.t(context, 'Weekly SOS'), weeklySos, Icons.calendar_view_week_rounded, const Color(0xFF8B5CF6))),
            ],
          ),
          const SizedBox(height: 16),

          // Performance Card
          _buildWideCard(
            isDark,
            AppTranslator.t(context, 'Response Performance'),
            Icons.speed_rounded,
            const Color(0xFFF59E0B),
            dispatchMins,
            arrivalMins,
          ),
          const SizedBox(height: 16),

          // Row 2: Success Rate & Safety
          Row(
            children: [
              Expanded(child: _buildStatCard(isDark, AppTranslator.t(context, 'Success Rate'), successRate, Icons.verified_rounded, const Color(0xFF10B981))),
              const SizedBox(width: 16),
              Expanded(child: _buildStatCard(isDark, AppTranslator.t(context, 'Safety Score'), safetyScore, Icons.health_and_safety_rounded, const Color(0xFFE11D48))),
            ],
          ),
          const SizedBox(height: 24),
        ],
      ),
    );
  }

  Widget _buildStatCard(bool isDark, String title, String value, IconData icon, Color color) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: isDark ? const Color(0xFF1E293B) : Colors.white,
        borderRadius: BorderRadius.circular(20),
        boxShadow: [
          BoxShadow(
            color: color.withValues(alpha: 0.1),
            blurRadius: 15,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: color.withValues(alpha: 0.15),
              shape: BoxShape.circle,
            ),
            child: Icon(icon, color: color, size: 24),
          ),
          const SizedBox(height: 16),
          Text(
            value,
            style: TextStyle(
              fontSize: 28,
              fontWeight: FontWeight.w900,
              color: isDark ? Colors.white : const Color(0xFF0F172A),
            ),
          ),
          const SizedBox(height: 4),
          Text(
            title,
            style: TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.w600,
              color: isDark ? Colors.grey.shade400 : Colors.grey.shade600,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildWideCard(bool isDark, String title, IconData icon, Color color, String avgDispatch, String avgArrival) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: isDark ? const Color(0xFF1E293B) : Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: color.withValues(alpha: 0.3), width: 1.5),
        boxShadow: [
          BoxShadow(
            color: color.withValues(alpha: 0.08),
            blurRadius: 15,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: color.withValues(alpha: 0.15),
              borderRadius: BorderRadius.circular(16),
            ),
            child: Icon(icon, color: color, size: 32),
          ),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w800,
                    color: isDark ? Colors.white : const Color(0xFF0F172A),
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  '${AppTranslator.t(context, 'Average dispatch time')}: $avgDispatch ${AppTranslator.t(context, 'mins')}',
                  style: TextStyle(fontSize: 13, color: isDark ? Colors.grey.shade400 : Colors.grey.shade600),
                ),
                const SizedBox(height: 4),
                Text(
                  '${AppTranslator.t(context, 'Average arrival time')}: $avgArrival ${AppTranslator.t(context, 'mins')}',
                  style: TextStyle(fontSize: 13, color: isDark ? Colors.grey.shade400 : Colors.grey.shade600),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
