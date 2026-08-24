import 'package:flutter/material.dart';

import '../theme/app_tokens.dart';

class AppSearchField extends StatelessWidget {
  const AppSearchField({super.key, this.controller, this.hint = 'Rechercher...', this.onChanged, this.onSubmitted, this.onClear});

  final TextEditingController? controller;
  final String hint;
  final ValueChanged<String>? onChanged;
  final ValueChanged<String>? onSubmitted;
  final VoidCallback? onClear;

  @override
  Widget build(BuildContext context) => TextField(
    controller: controller,
    textInputAction: TextInputAction.search,
    onChanged: onChanged,
    onSubmitted: onSubmitted,
    style: AppTypography.input,
    decoration: InputDecoration(
      hintText: hint,
      prefixIcon: const Icon(Icons.search, size: AppSizes.icon),
      suffixIcon: onClear == null
          ? null
          : IconButton(
              tooltip: 'Effacer la recherche',
              onPressed: onClear,
              icon: const Icon(Icons.close, size: AppSizes.icon),
            ),
    ),
  );
}
