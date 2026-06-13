import 'package:flutter/material.dart';
import '../../services/api_service.dart';
import '../../components/app_drawer.dart';
import '../../utils/translator.dart';
import '../../utils/responsive.dart';

class UserNotificationsScreen extends StatefulWidget {
  const UserNotificationsScreen({super.key});

  @override
  State<UserNotificationsScreen> createState() => _UserNotificationsScreenState();
}

class _UserNotificationsScreenState extends State<UserNotificationsScreen>
    with AutomaticKeepAliveClientMixin {
  late Future<List<dynamic>> _notifsFuture;

  @override
  bool get wantKeepAlive => false;

  @override
  void initState() {
    super.initState();
    _refresh();
  }

  void _refresh() {
    setState(() {
      _notifsFuture = ApiService.getNotifications();
    });
  }

  Future<void> _deleteNotification(String notifId) async {
    if (notifId.isEmpty) return;

    final bool? confirm = await showDialog<bool>(
      context: context,
      barrierDismissible: true,
      builder: (ctx) {
        final isDark = Theme.of(ctx).brightness == Brightness.dark;
        return Dialog(
          backgroundColor: Colors.transparent,
          insetPadding: const EdgeInsets.symmetric(horizontal: 24),
          child: Container(
            padding: const EdgeInsets.all(28),
            decoration: BoxDecoration(
              color: isDark ? const Color(0xFF1E293B) : Colors.white,
              borderRadius: BorderRadius.circular(28),
              border: Border.all(
                color: isDark ? const Color(0xFF334155) : Colors.grey.shade100,
                width: 1.5,
              ),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withValues(alpha: isDark ? 0.35 : 0.10),
                  blurRadius: 40,
                  offset: const Offset(0, 12),
                )
              ],
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(
                  width: 72,
                  height: 72,
                  decoration: BoxDecoration(
                    color: const Color(0xFFEF4444).withValues(alpha: 0.12),
                    shape: BoxShape.circle,
                  ),
                  child: const Icon(
                    Icons.delete_forever_rounded,
                    color: Color(0xFFEF4444),
                    size: 36,
                  ),
                ),
                const SizedBox(height: 20),
                Text(
                  AppTranslator.t(ctx, 'Delete Notification'),
                  style: TextStyle(
                    fontSize: 20,
                    fontWeight: FontWeight.w900,
                    color: isDark ? Colors.white : const Color(0xFF1E293B),
                  ),
                ),
                const SizedBox(height: 10),
                Text(
                  AppTranslator.t(ctx, 'Are you sure you want to delete this notification?'),
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontSize: 13,
                    height: 1.6,
                    color: isDark ? Colors.grey.shade400 : Colors.grey.shade600,
                    fontWeight: FontWeight.w500,
                  ),
                ),
                const SizedBox(height: 28),
                Row(
                  children: [
                    Expanded(
                      child: TextButton(
                        style: TextButton.styleFrom(
                          padding: const EdgeInsets.symmetric(vertical: 14),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(14),
                          ),
                          backgroundColor: isDark
                              ? const Color(0xFF334155)
                              : Colors.grey.shade100,
                        ),
                        onPressed: () => Navigator.pop(ctx, false),
                        child: Text(
                          AppTranslator.t(ctx, 'Cancel'),
                          style: TextStyle(
                            fontWeight: FontWeight.w700,
                            color: isDark ? Colors.grey.shade300 : Colors.grey.shade700,
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: DecoratedBox(
                        decoration: BoxDecoration(
                          borderRadius: BorderRadius.circular(14),
                          boxShadow: [
                            BoxShadow(
                              color: const Color(0xFFEF4444).withValues(alpha: 0.3),
                              blurRadius: 12,
                              offset: const Offset(0, 4),
                            )
                          ],
                        ),
                        child: ElevatedButton(
                          style: ElevatedButton.styleFrom(
                            backgroundColor: const Color(0xFFEF4444),
                            foregroundColor: Colors.white,
                            padding: const EdgeInsets.symmetric(vertical: 14),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(14),
                            ),
                            elevation: 0,
                          ),
                          onPressed: () => Navigator.pop(ctx, true),
                          child: Text(
                            AppTranslator.t(ctx, 'Delete'),
                            style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15),
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        );
      },
    );

    if (confirm != true) return;

    try {
      final res = await ApiService.deleteNotification(notifId);
      if (res['success'] == true) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Row(
                children: [
                  const Icon(Icons.check_circle_rounded, color: Colors.white, size: 18),
                  const SizedBox(width: 10),
                  Text(
                    AppTranslator.t(context, 'Notification deleted successfully!'),
                    style: const TextStyle(fontWeight: FontWeight.w700),
                  ),
                ],
              ),
              backgroundColor: const Color(0xFF10B981),
              behavior: SnackBarBehavior.floating,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              margin: const EdgeInsets.all(16),
            ),
          );
          _refresh();
        }
      } else {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(res['message'] ?? AppTranslator.t(context, 'Failed to delete notification')),
              backgroundColor: const Color(0xFFEF4444),
              behavior: SnackBarBehavior.floating,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              margin: const EdgeInsets.all(16),
            ),
          );
        }
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('${AppTranslator.t(context, 'Error')}: $e'),
            backgroundColor: const Color(0xFFEF4444),
            behavior: SnackBarBehavior.floating,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
            margin: const EdgeInsets.all(16),
          ),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Scaffold(
      backgroundColor: isDark ? const Color(0xFF0F172A) : const Color(0xFFF8FAFC),
      drawer: AppDrawer(
        currentIndex: 22, // or any custom index representing notifications if desired
        onTabSelected: (index) {},
        isSubScreen: true,
      ),
      appBar: AppBar(
        automaticallyImplyLeading: false,
        backgroundColor: Colors.transparent,
        elevation: 0,
        leading: Builder(
          builder: (ctx) {
            final canPop = Navigator.canPop(ctx);
            return IconButton(
              icon: Icon(
                canPop ? Icons.arrow_back_ios_new_rounded : Icons.menu_rounded,
                color: isDark ? Colors.white : Colors.black87,
              ),
              onPressed: () {
                if (canPop) {
                  Navigator.pop(ctx);
                } else {
                  Scaffold.of(ctx).openDrawer();
                }
              },
            );
          },
        ),
        title: Text(
          AppTranslator.t(context, 'Notifications'),
          style: TextStyle(
            fontWeight: FontWeight.w900,
            fontSize: 20,
            color: isDark ? Colors.white : Colors.black87,
          ),
        ),
        centerTitle: true,
        actions: [
          IconButton(
            icon: Icon(Icons.refresh_rounded,
                color: isDark ? Colors.white : Colors.black87),
            onPressed: _refresh,
          ),
          const SizedBox(width: 4),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () async => _refresh(),
        color: const Color(0xFF2563EB),
        child: Responsive(context).wrapWidescreen(
          FutureBuilder<List<dynamic>>(
            future: _notifsFuture,
            builder: (context, snapshot) {
              if (snapshot.connectionState == ConnectionState.waiting) {
                return const Center(
                  child: CircularProgressIndicator(
                    valueColor:
                        AlwaysStoppedAnimation<Color>(Color(0xFF2563EB)),
                  ),
                );
              }

              final list = snapshot.data ?? [];

              if (list.isEmpty) {
                return _buildEmptyState(isDark);
              }

              return ListView.separated(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.fromLTRB(20, 12, 20, 32),
                itemCount: list.length + 1, // +1 for header
                separatorBuilder: (_, __) => const SizedBox(height: 12),
                itemBuilder: (context, index) {
                  if (index == 0) return _buildHeader(isDark, list.length);

                  final item = list[index - 1];
                  return _buildNotifCard(context, item, isDark);
                },
              );
            },
          ),
        ),
      ),
    );
  }

  Widget _buildHeader(bool isDark, int count) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              gradient: const LinearGradient(
                colors: [Color(0xFF2563EB), Color(0xFF7C3AED)],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
              borderRadius: BorderRadius.circular(14),
              boxShadow: [
                BoxShadow(
                  color: const Color(0xFF2563EB).withValues(alpha: 0.3),
                  blurRadius: 12,
                  offset: const Offset(0, 4),
                ),
              ],
            ),
            child: const Icon(
              Icons.notifications_active_rounded,
              color: Colors.white,
              size: 20,
            ),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '$count ${AppTranslator.t(context, 'Notifications Count')}',
                  style: TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w900,
                    color: isDark ? Colors.white : const Color(0xFF1E293B),
                  ),
                ),
                Text(
                  AppTranslator.t(context, 'Pull down to refresh'),
                  style: TextStyle(
                    fontSize: 11,
                    color: Colors.grey.shade500,
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ],
            ),
          ),
          // Unread badge
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
            decoration: BoxDecoration(
              color: const Color(0xFF2563EB).withValues(alpha: 0.1),
              borderRadius: BorderRadius.circular(20),
              border: Border.all(
                color: const Color(0xFF2563EB).withValues(alpha: 0.2),
              ),
            ),
            child: const Text(
              'LIVE',
              style: TextStyle(
                fontSize: 10,
                fontWeight: FontWeight.w900,
                color: Color(0xFF2563EB),
                letterSpacing: 1,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildNotifCard(
      BuildContext context, Map<String, dynamic> item, bool isDark) {
    final title = item['title'] ?? 'System Update';
    final msg = item['message'] ?? '';
    final time = item['created_at'] ?? '';
    final isRead = item['is_read'] == true;
    final String notifId = item['id']?.toString() ?? '';

    // Pick icon & color based on title keywords
    IconData icon;
    Color iconColor;
    List<Color> gradientColors;

    if (title.toLowerCase().contains('sos') ||
        title.toLowerCase().contains('emergency') ||
        title.toLowerCase().contains('gurmad')) {
      icon = Icons.emergency_rounded;
      iconColor = const Color(0xFFEF4444);
      gradientColors = [const Color(0xFFEF4444), const Color(0xFFF97316)];
    } else if (title.toLowerCase().contains('gps') ||
        title.toLowerCase().contains('location')) {
      icon = Icons.location_on_rounded;
      iconColor = const Color(0xFF10B981);
      gradientColors = [const Color(0xFF10B981), const Color(0xFF06B6D4)];
    } else if (title.toLowerCase().contains('driver') ||
        title.toLowerCase().contains('assigned')) {
      icon = Icons.local_taxi_rounded;
      iconColor = const Color(0xFFF59E0B);
      gradientColors = [const Color(0xFFF59E0B), const Color(0xFFEF4444)];
    } else {
      icon = Icons.notifications_rounded;
      iconColor = const Color(0xFF2563EB);
      gradientColors = [const Color(0xFF2563EB), const Color(0xFF7C3AED)];
    }

    return AnimatedContainer(
      duration: const Duration(milliseconds: 300),
      decoration: BoxDecoration(
        color: isDark ? const Color(0xFF1E293B) : Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(
          color: isRead
              ? (isDark ? const Color(0xFF334155) : Colors.grey.shade100)
              : iconColor.withValues(alpha: 0.25),
          width: isRead ? 1.5 : 2,
        ),
        boxShadow: [
          BoxShadow(
            color: isRead
                ? Colors.black.withValues(alpha: isDark ? 0.15 : 0.04)
                : iconColor.withValues(alpha: 0.08),
            blurRadius: 16,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Icon
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                gradient: isRead
                    ? null
                    : LinearGradient(
                        colors: gradientColors,
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                      ),
                color: isRead ? Colors.grey.withValues(alpha: 0.1) : null,
                borderRadius: BorderRadius.circular(14),
                boxShadow: isRead
                    ? []
                    : [
                        BoxShadow(
                          color: iconColor.withValues(alpha: 0.3),
                          blurRadius: 10,
                          offset: const Offset(0, 3),
                        )
                      ],
              ),
              child: Icon(
                icon,
                color: isRead ? Colors.grey.shade400 : Colors.white,
                size: 20,
              ),
            ),
            const SizedBox(width: 14),
            // Content
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          title,
                          style: TextStyle(
                            fontWeight: FontWeight.w800,
                            fontSize: 14,
                            color: isDark
                                ? Colors.white
                                : const Color(0xFF1E293B),
                          ),
                        ),
                      ),
                      if (!isRead) ...[
                        Container(
                          width: 8,
                          height: 8,
                          decoration: BoxDecoration(
                            color: iconColor,
                            shape: BoxShape.circle,
                            boxShadow: [
                              BoxShadow(
                                color: iconColor.withValues(alpha: 0.5),
                                blurRadius: 6,
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(width: 8),
                      ],
                      IconButton(
                        icon: Icon(
                          Icons.delete_outline_rounded,
                          color: isDark ? Colors.red.shade400 : Colors.red.shade600,
                          size: 18,
                        ),
                        tooltip: AppTranslator.t(context, 'Delete'),
                        onPressed: () => _deleteNotification(notifId),
                        constraints: const BoxConstraints(),
                        padding: EdgeInsets.zero,
                        splashRadius: 18,
                      ),
                    ],
                  ),
                  const SizedBox(height: 6),
                  Text(
                    msg,
                    style: TextStyle(
                      fontSize: 12,
                      height: 1.5,
                      color: isDark
                          ? Colors.grey.shade400
                          : Colors.grey.shade600,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                  const SizedBox(height: 10),
                  Row(
                    children: [
                      Icon(
                        Icons.access_time_rounded,
                        size: 11,
                        color: Colors.grey.shade400,
                      ),
                      const SizedBox(width: 4),
                      Text(
                        time,
                        style: TextStyle(
                          fontSize: 10,
                          color: Colors.grey.shade400,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildEmptyState(bool isDark) {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Container(
            width: 100,
            height: 100,
            decoration: BoxDecoration(
              gradient: LinearGradient(
                colors: [
                  const Color(0xFF2563EB).withValues(alpha: 0.1),
                  const Color(0xFF7C3AED).withValues(alpha: 0.1),
                ],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
              shape: BoxShape.circle,
            ),
            child: Icon(
              Icons.notifications_none_rounded,
              size: 48,
              color: const Color(0xFF2563EB).withValues(alpha: 0.5),
            ),
          ),
          const SizedBox(height: 24),
          Text(
            AppTranslator.t(context, 'No notifications'),
            style: TextStyle(
              fontSize: 20,
              fontWeight: FontWeight.w900,
              color: isDark ? Colors.white : const Color(0xFF1E293B),
            ),
          ),
          const SizedBox(height: 8),
          Text(
            AppTranslator.t(context, 'Updates about your rescue will appear here.'),
            textAlign: TextAlign.center,
            style: TextStyle(
              fontSize: 13,
              height: 1.6,
              color: Colors.grey.shade500,
              fontWeight: FontWeight.w500,
            ),
          ),
          const SizedBox(height: 32),
          ElevatedButton.icon(
            onPressed: _refresh,
            icon: const Icon(Icons.refresh_rounded, size: 18),
            label: Text(
              AppTranslator.t(context, 'Refresh'),
              style: const TextStyle(fontWeight: FontWeight.w700),
            ),
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFF2563EB),
              foregroundColor: Colors.white,
              padding:
                  const EdgeInsets.symmetric(horizontal: 28, vertical: 14),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(14),
              ),
              elevation: 0,
            ),
          ),
        ],
      ),
    );
  }
}
