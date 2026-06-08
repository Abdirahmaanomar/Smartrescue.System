import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../providers/auth_provider.dart';
import '../constants/api_constants.dart';
import '../views/user/user_shell.dart';

class AppBarUserAvatar extends StatelessWidget {
  const AppBarUserAvatar({super.key});

  @override
  Widget build(BuildContext context) {
    final auth = Provider.of<AuthProvider>(context);
    final user = auth.user;
    final isDark = Theme.of(context).brightness == Brightness.dark;

    if (user == null) {
      return IconButton(
        icon: Icon(Icons.menu_rounded, color: isDark ? Colors.white : Colors.black87),
        onPressed: () {
          try {
            UserShell.scaffoldKey.currentState?.openDrawer();
          } catch (_) {
            Scaffold.of(context).openDrawer();
          }
        },
      );
    }

    final initials = user.fullname.isNotEmpty ? user.fullname[0].toUpperCase() : '?';

    return GestureDetector(
      onTap: () {
        try {
          if (UserShell.scaffoldKey.currentState != null) {
            UserShell.scaffoldKey.currentState!.openDrawer();
          } else {
            Scaffold.of(context).openDrawer();
          }
        } catch (_) {
          try {
            Scaffold.of(context).openDrawer();
          } catch (_) {}
        }
      },
      child: Center(
        child: Container(
          margin: const EdgeInsets.only(left: 16),
          width: 38,
          height: 38,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            border: Border.all(
              color: const Color(0xFF5850EC).withValues(alpha: 0.2),
              width: 1.5,
            ),
            boxShadow: [
              BoxShadow(
                color: const Color(0xFF5850EC).withValues(alpha: 0.15),
                blurRadius: 8,
                offset: const Offset(0, 2),
              ),
            ],
          ),
          child: ClipOval(
            child: CircleAvatar(
              backgroundColor: const Color(0xFF5850EC), // Premium Purple avatar background
              backgroundImage: user.profileImage.isNotEmpty
                  ? NetworkImage(ApiConstants.avatarUrl(user.profileImage))
                  : null,
              child: user.profileImage.isEmpty
                  ? Text(
                      initials,
                      style: const TextStyle(
                        fontSize: 14,
                        color: Colors.white,
                        fontWeight: FontWeight.w900,
                        fontFamily: 'Inter',
                      ),
                    )
                  : null,
            ),
          ),
        ),
      ),
    );
  }
}
