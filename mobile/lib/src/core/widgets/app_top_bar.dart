import 'package:flutter/material.dart';

import '../theme/app_tokens.dart';

class AppTopBar extends StatelessWidget implements PreferredSizeWidget {
  const AppTopBar({super.key, this.leading, this.actions = const [], this.profile});

  final Widget? leading;
  final List<Widget> actions;
  final Widget? profile;

  @override
  Size get preferredSize => const Size.fromHeight(AppSizes.topBarHeight);

  @override
  Widget build(BuildContext context) => AppBar(
    automaticallyImplyLeading: leading == null,
    leading: leading,
    title: const SizedBox.shrink(),
    toolbarHeight: AppSizes.topBarHeight,
    actions: [...actions, ?profile, const SizedBox(width: AppSpacing.sm)],
  );
}
