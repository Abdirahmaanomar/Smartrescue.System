import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import '../../providers/sos_provider.dart';
import '../../utils/helpers.dart';
import '../../utils/translator.dart';
import '../../components/call_screen.dart';
import 'user_shell.dart';

enum TimelineStepState { completed, current, upcoming }

class UserResponseTimelineScreen extends StatelessWidget {
  const UserResponseTimelineScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final sos = Provider.of<SosProvider>(context);
    final scheme = Theme.of(context).colorScheme;
    final request = sos.activeRequest;

    return Scaffold(
      appBar: AppBar(
        leading: Builder(
          builder: (context) => IconButton(
            icon: const Icon(Icons.menu_rounded),
            onPressed: () => UserShell.scaffoldKey.currentState?.openDrawer(),
          ),
        ),
        title: Text(AppTranslator.t(context, 'Response Timeline'), style: const TextStyle(fontWeight: FontWeight.w800)),
        centerTitle: true,
      ),
      body: _buildActiveTimeline(context, sos, request, scheme),
    );
  }

  Widget _buildPremiumTimelineItem({
    required IconData icon,
    required String title,
    required String subtitle,
    required TimelineStepState state,
    required bool isLineActive,
    required bool isLast,
    required bool isDark,
  }) {
    final activeColor = const Color(0xFF2563EB); // Deep premium blue
    final currentColor = const Color(0xFFEF4444); // Urgent red
    
    Widget circleWidget;
    if (state == TimelineStepState.current) {
      circleWidget = Container(
        width: 44,
        height: 44,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: currentColor.withValues(alpha: 0.15),
          shape: BoxShape.circle,
        ),
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 300),
          width: 32,
          height: 32,
          decoration: BoxDecoration(
            color: Colors.white,
            shape: BoxShape.circle,
            border: Border.all(
              color: currentColor,
              width: 2.5,
            ),
          ),
          child: Icon(
            icon,
            color: currentColor,
            size: 15,
          ),
        ),
      );
    } else if (state == TimelineStepState.completed) {
      circleWidget = AnimatedContainer(
        duration: const Duration(milliseconds: 300),
        width: 34,
        height: 34,
        decoration: BoxDecoration(
          color: activeColor.withValues(alpha: 0.1),
          shape: BoxShape.circle,
          border: Border.all(
            color: activeColor,
            width: 2,
          ),
        ),
        child: Icon(
          icon,
          color: activeColor,
          size: 15,
        ),
      );
    } else {
      circleWidget = AnimatedContainer(
        duration: const Duration(milliseconds: 300),
        width: 34,
        height: 34,
        decoration: BoxDecoration(
          color: isDark ? const Color(0xFF0F172A) : Colors.grey.shade50,
          shape: BoxShape.circle,
          border: Border.all(
            color: isDark ? const Color(0xFF334155) : Colors.grey.shade300,
            width: 1.5,
          ),
        ),
        child: Icon(
          icon,
          color: Colors.grey.shade400,
          size: 15,
        ),
      );
    }

    Color titleColor;
    if (state == TimelineStepState.completed) {
      titleColor = activeColor;
    } else if (state == TimelineStepState.current) {
      titleColor = currentColor;
    } else {
      titleColor = isDark ? Colors.white70 : const Color(0xFF1E293B);
    }

    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // Icon and vertical connector
        Column(
          children: [
            SizedBox(
              width: 44,
              height: 44,
              child: Center(child: circleWidget),
            ),
            // Vertical Line
            if (!isLast)
              AnimatedContainer(
                duration: const Duration(milliseconds: 300),
                width: 2,
                height: 28,
                color: isLineActive
                    ? activeColor
                    : (isDark ? const Color(0xFF1E293B) : Colors.grey.shade200),
              ),
          ],
        ),
        const SizedBox(width: 16),
        // Title and Subtitle
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const SizedBox(height: 12), // Align vertically with the circle
              Text(
                title,
                style: TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.bold,
                  color: titleColor,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                subtitle,
                style: TextStyle(
                  fontSize: 11,
                  color: state == TimelineStepState.current
                      ? Colors.grey.shade500
                      : Colors.grey.shade400,
                  fontWeight: FontWeight.w500,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildActiveTimeline(BuildContext context, SosProvider sos, dynamic request, ColorScheme scheme) {
    final status = request?.status;
    final isDark = Theme.of(context).brightness == Brightness.dark;
    
    // Use the unified timelineStep from the model
    final int currentIdx = request != null ? request.timelineStep : -1;

    // Determine banner state
    bool isCompleted = request != null && status == 'completed';
    bool isArrived   = request != null && status == 'arrived';
    bool isEnRoute   = request != null && status == 'en_route';
    bool isAssigned  = request != null && (request.driverAssigned || request.unitName.isNotEmpty) && !isEnRoute && !isArrived && !isCompleted;
    bool isDispatched = request != null && (status == 'dispatched' || (status == 'accepted' && !request.driverAssigned && request.unitName.isEmpty));

    TimelineStepState getStepState(int index) {
      if (index < currentIdx) return TimelineStepState.completed;
      if (index == currentIdx) return TimelineStepState.current;
      return TimelineStepState.upcoming;
    }

    return SingleChildScrollView(
      padding: const EdgeInsets.all(24),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          // Active pulsing status banner (only if request is not null)
          if (request != null) ...[
            Container(
              padding: const EdgeInsets.all(18),
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  colors: isCompleted
                      ? [Colors.green.shade600, Colors.green.shade500]
                      : isArrived
                          ? [Colors.teal.shade600, Colors.teal.shade500]
                          : isEnRoute
                              ? [Colors.purple.shade600, Colors.purple.shade500]
                              : isAssigned
                                  ? [Colors.indigo.shade600, Colors.indigo.shade500]
                                  : isDispatched
                                      ? [scheme.primary, scheme.primary.withValues(alpha: 0.8)]
                                      : [Colors.orange.shade600, Colors.orange.shade500],
                ),
                borderRadius: BorderRadius.circular(20),
                boxShadow: [
                  BoxShadow(
                    color: (isCompleted
                            ? Colors.green
                            : isArrived
                                ? Colors.teal
                                : isEnRoute
                                    ? Colors.purple
                                    : isAssigned
                                        ? Colors.indigo
                                        : isDispatched
                                            ? scheme.primary
                                            : Colors.orange)
                        .withValues(alpha: 0.3),
                    blurRadius: 15,
                    offset: const Offset(0, 5),
                  )
                ],
              ),
              child: Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(10),
                    decoration: const BoxDecoration(
                      color: Colors.white24,
                      shape: BoxShape.circle,
                    ),
                    child: Icon(
                      isCompleted
                          ? Icons.check_circle_rounded
                          : isArrived
                              ? Icons.navigation_rounded
                              : isEnRoute
                                  ? Icons.airport_shuttle_rounded
                                  : isAssigned
                                      ? Icons.assignment_ind_rounded
                                      : isDispatched
                                          ? Icons.support_agent_rounded
                                          : Icons.sensors_rounded,
                      color: Colors.white,
                      size: 24,
                    ),
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          isCompleted
                              ? AppTranslator.t(context, 'Incident Resolved')
                              : isArrived
                                  ? AppTranslator.t(context, 'Responder Arrived')
                                  : isEnRoute
                                      ? AppTranslator.t(context, 'Rescue On The Way')
                                      : isAssigned
                                          ? AppTranslator.t(context, 'Team Assigned')
                                          : isDispatched
                                              ? AppTranslator.t(context, 'Responder Dispatched')
                                              : AppTranslator.t(context, 'SOS Transmitted'),
                          style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w900, fontSize: 16),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          isCompleted
                              ? AppTranslator.t(context, 'The issue has been closed successfully.')
                              : isArrived
                                  ? AppTranslator.t(context, 'Assigned responder is currently with you.')
                                  : isEnRoute
                                      ? AppTranslator.t(context, 'Rescue team is heading to your location.')
                                      : isAssigned
                                          ? AppTranslator.t(context, 'A rescue unit has been assigned to you.')
                                          : isDispatched
                                              ? AppTranslator.t(context, 'Dispatch center has acknowledged your SOS.')
                                              : AppTranslator.t(context, 'Awaiting dispatcher verification.'),
                          style: TextStyle(color: Colors.white.withValues(alpha: 0.9), fontSize: 12, fontWeight: FontWeight.w500),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 28),
          ],
          
          // Premium Timeline Milestones Container
          Container(
            padding: const EdgeInsets.all(24),
            decoration: BoxDecoration(
              color: isDark ? const Color(0xFF1E293B) : Colors.white,
              borderRadius: BorderRadius.circular(24),
              border: Border.all(
                color: isDark ? const Color(0xFF334155) : Colors.grey.shade100,
                width: 1.5,
              ),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withValues(alpha: isDark ? 0.2 : 0.04),
                  blurRadius: 20,
                  offset: const Offset(0, 8),
                )
              ],
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Row(
                      children: [
                        Container(
                          padding: const EdgeInsets.all(8),
                          decoration: BoxDecoration(
                            color: const Color(0xFF2563EB).withValues(alpha: 0.1),
                            shape: BoxShape.circle,
                          ),
                          child: const Icon(
                            Icons.alt_route_rounded,
                            color: Color(0xFF2563EB),
                            size: 18,
                          ),
                        ),
                        const SizedBox(width: 10),
                        Text(
                          AppTranslator.t(context, 'RESPONSE TIMELINE'),
                          style: TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.w900,
                            color: isDark ? Colors.grey.shade300 : const Color(0xFF475569),
                            letterSpacing: 0.5,
                          ),
                        ),
                      ],
                    ),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                      decoration: BoxDecoration(
                        color: const Color(0xFFEFF6FF),
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: Text(
                        AppTranslator.t(context, 'LIVE'),
                        style: const TextStyle(
                          color: Color(0xFF2563EB),
                          fontSize: 10,
                          fontWeight: FontWeight.w900,
                          letterSpacing: 0.5,
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 24),

                // Milestones — 6 steps (0–5)
                _buildPremiumTimelineItem(
                  icon: Icons.sensors_rounded,
                  title: AppTranslator.t(context, 'SOS Sent'),
                  subtitle: AppTranslator.t(context, 'Emergency signal broadcast'),
                  state: getStepState(0),
                  isLineActive: currentIdx > 0,
                  isLast: false,
                  isDark: isDark,
                ),
                _buildPremiumTimelineItem(
                  icon: Icons.support_agent_rounded,
                  title: AppTranslator.t(context, 'Dispatched'),
                  subtitle: AppTranslator.t(context, 'Dispatch center notified'),
                  state: getStepState(1),
                  isLineActive: currentIdx > 1,
                  isLast: false,
                  isDark: isDark,
                ),
                _buildPremiumTimelineItem(
                  icon: Icons.assignment_ind_rounded,
                  title: AppTranslator.t(context, 'Team Assigned'),
                  subtitle: AppTranslator.t(context, 'Rescue unit selected'),
                  state: getStepState(2),
                  isLineActive: currentIdx > 2,
                  isLast: false,
                  isDark: isDark,
                ),
                _buildPremiumTimelineItem(
                  icon: Icons.airport_shuttle_rounded,
                  title: AppTranslator.t(context, 'On The Way'),
                  subtitle: AppTranslator.t(context, 'Team heading to location'),
                  state: getStepState(3),
                  isLineActive: currentIdx > 3,
                  isLast: false,
                  isDark: isDark,
                ),
                _buildPremiumTimelineItem(
                  icon: Icons.location_on_rounded,
                  title: AppTranslator.t(context, 'Arrived'),
                  subtitle: AppTranslator.t(context, 'Team on scene'),
                  state: getStepState(4),
                  isLineActive: currentIdx > 4,
                  isLast: false,
                  isDark: isDark,
                ),
                _buildPremiumTimelineItem(
                  icon: Icons.verified_rounded,
                  title: AppTranslator.t(context, 'Completed'),
                  subtitle: AppTranslator.t(context, 'Emergency successfully resolved'),
                  state: getStepState(5),
                  isLineActive: false,
                  isLast: true,
                  isDark: isDark,
                ),

                // Driver details inside
                if (request != null && (request.driverAssigned || request.unitName.isNotEmpty)) ...[
                  const Padding(
                    padding: EdgeInsets.only(top: 20, bottom: 16),
                    child: Divider(height: 1),
                  ),
                  Row(
                    children: [
                      Container(
                        width: 48,
                        height: 48,
                        decoration: BoxDecoration(
                          color: const Color(0xFF2563EB).withValues(alpha: 0.1),
                          shape: BoxShape.circle,
                        ),
                        child: Center(
                          child: Text(
                            AppHelpers.initials(request.driverName),
                            style: const TextStyle(
                              color: Color(0xFF2563EB),
                              fontWeight: FontWeight.w800,
                              fontSize: 16,
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
                              request.driverName,
                              style: TextStyle(
                                fontWeight: FontWeight.w800,
                                fontSize: 15,
                                color: isDark ? Colors.white : const Color(0xFF1E293B),
                              ),
                            ),
                            const SizedBox(height: 2),
                            Text(
                              '${request.unitName} • ${request.plateNumber}',
                              style: TextStyle(
                                color: Colors.grey.shade500,
                                fontSize: 11,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ],
                        ),
                      ),
                      IconButton(
                        icon: const Icon(Icons.phone_rounded, color: Colors.green, size: 20),
                        tooltip: AppTranslator.t(context, 'Call Responder'),
                        onPressed: () => CallScreen.show(
                          context,
                          name: request.driverName,
                          phone: request.driverPhone,
                        ),
                        style: IconButton.styleFrom(
                          backgroundColor: Colors.green.withValues(alpha: 0.1),
                        ),
                      ),
                      const SizedBox(width: 8),
                      IconButton(
                        icon: const Icon(Icons.copy_rounded, color: Colors.blue, size: 20),
                        tooltip: AppTranslator.t(context, 'Copy Phone Number'),
                        onPressed: () {
                          Clipboard.setData(ClipboardData(text: request.driverPhone));
                            ScaffoldMessenger.of(context).showSnackBar(
                            SnackBar(
                              content: Text('${AppTranslator.t(context, 'Phone number copied to clipboard!')} (${request.driverPhone})'),
                              behavior: SnackBarBehavior.floating,
                              backgroundColor: Colors.blue.shade700,
                            ),
                          );
                        },
                        style: IconButton.styleFrom(
                          backgroundColor: Colors.blue.withValues(alpha: 0.1),
                        ),
                      ),
                    ],
                  ),
                ]
              ],
            ),
          ),
        ],
      ),
    );
  }
}
