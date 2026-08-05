import 'package:flutter/material.dart';

import '../theme/app_theme.dart';

class AuthorizedModulePage extends StatelessWidget {
  const AuthorizedModulePage({required this.title, required this.icon, super.key});

  final String title;
  final IconData icon;

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: Text(title)),
        body: SafeArea(
          child: Center(
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 560),
              child: Card(
                margin: const EdgeInsets.all(24),
                child: Padding(
                  padding: const EdgeInsets.all(28),
                  child: Column(mainAxisSize: MainAxisSize.min, children: [
                    Container(
                      width: 64,
                      height: 64,
                      decoration: BoxDecoration(color: AppTheme.orangeSoft, borderRadius: BorderRadius.circular(16)),
                      child: Icon(icon, color: AppTheme.orange, size: 32),
                    ),
                    const SizedBox(height: 18),
                    Text(title, style: Theme.of(context).textTheme.headlineSmall, textAlign: TextAlign.center),
                    const SizedBox(height: 8),
                    const Text('Ce module est accessible dans votre périmètre.', textAlign: TextAlign.center),
                  ]),
                ),
              ),
            ),
          ),
        ),
      );
}
