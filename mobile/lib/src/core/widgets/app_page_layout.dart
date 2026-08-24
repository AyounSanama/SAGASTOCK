import 'package:flutter/material.dart';

import '../theme/app_tokens.dart';

class AppPageLayout extends StatelessWidget {
  const AppPageLayout({super.key, required this.child, this.breadcrumb, this.header, this.scrollable = true});

  final Widget child;
  final Widget? breadcrumb;
  final Widget? header;
  final bool scrollable;

  @override
  Widget build(BuildContext context) {
    final width = MediaQuery.sizeOf(context).width;
    final padding = width < AppBreakpoints.mobile ? AppSizes.pagePaddingMobile : width < AppBreakpoints.desktop ? AppSizes.pagePaddingTablet : AppSizes.pagePaddingDesktop;
    final content = Padding(
      padding: EdgeInsets.all(padding),
      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        if (breadcrumb != null) ...[breadcrumb!, const SizedBox(height: AppSpacing.lg)],
        if (header != null) ...[header!, const SizedBox(height: AppSpacing.xl)],
        child,
      ]),
    );
    return scrollable ? SingleChildScrollView(child: content) : content;
  }
}
