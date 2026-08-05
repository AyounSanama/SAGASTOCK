import 'package:flutter/material.dart';

import '../theme/app_theme.dart';

class PharmaCareWordmark extends StatelessWidget {
  const PharmaCareWordmark({
    super.key,
    this.fontSize = 24,
    this.textAlign = TextAlign.center,
  });

  final double fontSize;
  final TextAlign textAlign;

  @override
  Widget build(BuildContext context) {
    return Text.rich(
      const TextSpan(
        children: [
          TextSpan(
            text: 'Pharma',
            style: TextStyle(color: AppTheme.orange),
          ),
          TextSpan(
            text: 'Care',
            style: TextStyle(color: AppTheme.green),
          ),
        ],
      ),
      maxLines: 1,
      textAlign: textAlign,
      style: TextStyle(
        fontSize: fontSize,
        fontWeight: FontWeight.w900,
        letterSpacing: -.4,
      ),
    );
  }
}
