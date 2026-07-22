import 'package:flutter/material.dart';
import '../../services/api_service.dart';
import '../../utils/helpers.dart';
import '../../models/rescue_request_model.dart';
import '../../utils/translator.dart';
import '../../utils/responsive.dart';

class UserHistoryScreen extends StatefulWidget {
  const UserHistoryScreen({super.key});

  @override
  State<UserHistoryScreen> createState() => _UserHistoryScreenState();
}

class _UserHistoryScreenState extends State<UserHistoryScreen> {
  List<RescueRequestModel> _history = [];
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _fetchHistory();
  }

  Future<void> _fetchHistory() async {
    setState(() => _loading = true);
    final result = await ApiService.getHistory();
    if (mounted) {
      final list = result['history'] as List? ?? [];
      setState(() {
        _history = list.map((e) => RescueRequestModel.fromJson(e)).toList();
        _loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final bgColor = isDark ? const Color(0xFF0F172A) : const Color(0xFFF8FAFC);
    final cardColor = isDark ? const Color(0xFF1E293B) : Colors.white;
    final headerTextColor = isDark ? Colors.grey.shade400 : const Color(0xFF64748B);

    return Scaffold(
      backgroundColor: bgColor,
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        elevation: 0,
        leading: IconButton(
          icon: Icon(Icons.arrow_back_ios_new_rounded, color: isDark ? Colors.white : Colors.black87, size: 20),
          onPressed: () => Navigator.pop(context),
        ),
      ),
      body: SafeArea(
        child: Responsive(context).wrapWidescreen(
          Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // Title & Refresh Row
            Padding(
              padding: EdgeInsets.symmetric(horizontal: Responsive(context).hPad, vertical: 10),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'RECORDS',
                        style: TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.w800,
                          color: isDark ? Colors.grey.shade400 : const Color(0xFF64748B),
                          letterSpacing: 1.5,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        AppTranslator.t(context, 'Rescue History'),
                        style: TextStyle(
                          fontSize: 24,
                          fontWeight: FontWeight.w900,
                          color: isDark ? Colors.white : const Color(0xFF1E293B),
                        ),
                      ),
                    ],
                  ),
                  // Refresh Button
                  OutlinedButton.icon(
                    onPressed: _fetchHistory,
                    icon: Icon(
                      Icons.refresh_rounded,
                      size: 16,
                      color: isDark ? Colors.white : const Color(0xFF1E293B),
                    ),
                    label: Text(
                      AppTranslator.t(context, 'Refresh'),
                      style: TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.bold,
                        color: isDark ? Colors.white : const Color(0xFF1E293B),
                      ),
                    ),
                    style: OutlinedButton.styleFrom(
                      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                      side: BorderSide(color: isDark ? const Color(0xFF334155) : Colors.grey.shade300, width: 1.5),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),

            // Card Table Container
            Expanded(
              child: Padding(
                padding: EdgeInsets.fromLTRB(Responsive(context).hPad, 0, Responsive(context).hPad, Responsive(context).hPad),
                child: Container(
                  decoration: BoxDecoration(
                    color: cardColor,
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(
                      color: isDark ? const Color(0xFF334155) : Colors.grey.shade100,
                      width: 1.5,
                    ),
                    boxShadow: [
                      BoxShadow(
                        color: isDark
                            ? Colors.black.withValues(alpha: 0.3)
                            : Colors.grey.shade200.withValues(alpha: 0.3),
                        blurRadius: 10,
                        offset: const Offset(0, 4),
                      ),
                    ],
                  ),
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(20),
                    child: Column(
                      children: [
                        // Table Header Row
                        Container(
                          padding: EdgeInsets.symmetric(horizontal: Responsive(context).hPad, vertical: 16),
                          decoration: BoxDecoration(
                            color: isDark ? const Color(0xFF0F172A) : const Color(0xFFF8FAFC),
                            border: Border(
                              bottom: BorderSide(
                                color: isDark ? const Color(0xFF334155) : Colors.grey.shade100,
                                width: 1.5,
                              ),
                            ),
                          ),
                          child: Row(
                            children: [
                              Expanded(
                                flex: 2,
                                child: Text(
                                  AppTranslator.t(context, 'Date').toUpperCase(),
                                  style: TextStyle(
                                    fontSize: 10,
                                    fontWeight: FontWeight.w800,
                                    color: headerTextColor,
                                    letterSpacing: 1.0,
                                  ),
                                ),
                              ),
                              Expanded(
                                flex: 2,
                                child: Text(
                                  AppTranslator.t(context, 'Type').toUpperCase(),
                                  style: TextStyle(
                                    fontSize: 10,
                                    fontWeight: FontWeight.w800,
                                    color: headerTextColor,
                                    letterSpacing: 1.0,
                                  ),
                                ),
                              ),
                              Expanded(
                                flex: 2,
                                child: Text(
                                  AppTranslator.t(context, 'Status').toUpperCase(),
                                  style: TextStyle(
                                    fontSize: 10,
                                    fontWeight: FontWeight.w800,
                                    color: headerTextColor,
                                    letterSpacing: 1.0,
                                  ),
                                ),
                              ),
                              Expanded(
                                flex: 3,
                                child: Text(
                                  AppTranslator.t(context, 'Details').toUpperCase(),
                                  style: TextStyle(
                                    fontSize: 10,
                                    fontWeight: FontWeight.w800,
                                    color: headerTextColor,
                                    letterSpacing: 1.0,
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),

                        // Table Body
                        Expanded(
                          child: _loading
                              ? const Center(child: CircularProgressIndicator())
                              : _history.isEmpty
                                  ? Container(
                                      alignment: Alignment.topCenter,
                                      padding: const EdgeInsets.only(top: 48),
                                      child: Text(
                                        AppTranslator.t(context, 'No rescue history yet.'),
                                        style: TextStyle(
                                          fontSize: 14,
                                          fontWeight: FontWeight.w600,
                                          color: isDark ? Colors.grey.shade400 : const Color(0xFF64748B),
                                        ),
                                      ),
                                    )
                                  : RefreshIndicator(
                                      onRefresh: _fetchHistory,
                                      child: ListView.builder(
                                        padding: const EdgeInsets.symmetric(vertical: 8),
                                        itemCount: _history.length,
                                        itemBuilder: (context, index) {
                                          final item = _history[index];
                                          return _buildHistoryRow(item);
                                        },
                                      ),
                                    ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
      ),
    );
  }

  Widget _buildHistoryRow(RescueRequestModel item) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final rowBorderColor = isDark ? const Color(0xFF334155) : Colors.grey.shade100;

    return Container(
      margin: const EdgeInsets.symmetric(vertical: 6, horizontal: 16),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: isDark ? const Color(0xFF1E293B) : Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: isDark ? 0.2 : 0.02),
            blurRadius: 10,
            offset: const Offset(0, 4),
          ),
        ],
        border: Border.all(
          color: rowBorderColor,
          width: 1.0,
        ),
      ),
      child: Row(
        children: [
          // DATE Column
          Expanded(
            flex: 2,
            child: Text(
              AppHelpers.formatDate(item.createdAt),
              style: TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w600,
                color: isDark ? Colors.white70 : Colors.black87,
              ),
            ),
          ),

          // TYPE Column
          Expanded(
            flex: 2,
            child: Row(
              children: [
                Icon(
                  AppHelpers.emergencyIcon(item.emergencyType),
                  color: AppHelpers.emergencyColor(item.emergencyType),
                  size: 16,
                ),
                const SizedBox(width: 8),
                Flexible(
                  child: Text(
                    AppTranslator.t(context, item.emergencyType),
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w700,
                      color: isDark ? Colors.white : const Color(0xFF1E293B),
                    ),
                  ),
                ),
              ],
            ),
          ),

          // STATUS Column
          Expanded(
            flex: 2,
            child: Align(
              alignment: Alignment.centerLeft,
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                decoration: BoxDecoration(
                  color: AppHelpers.statusColor(item.status).withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Text(
                  AppTranslator.t(context, item.statusLabel),
                  style: TextStyle(
                    color: AppHelpers.statusColor(item.status),
                    fontWeight: FontWeight.w800,
                    fontSize: 11,
                  ),
                ),
              ),
            ),
          ),

          // DETAILS Column (showing driver name or custom details)
          Expanded(
            flex: 3,
            child: Text(
              item.driverAssigned
                  ? '${AppTranslator.t(context, 'Driver')}: ${item.driverName}'
                  : AppTranslator.t(context, 'Waiting for responder...'),
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w600,
                color: isDark ? Colors.grey.shade400 : const Color(0xFF64748B),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
