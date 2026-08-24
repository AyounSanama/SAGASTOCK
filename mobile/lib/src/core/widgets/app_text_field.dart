import 'package:flutter/material.dart';

import '../theme/app_tokens.dart';

class AppTextField extends StatelessWidget {
  const AppTextField({super.key, required this.label, this.controller, this.initialValue, this.hint, this.helperText, this.errorText, this.prefixIcon, this.suffixIcon, this.obscureText = false, this.enabled = true, this.required = false, this.keyboardType, this.textInputAction, this.autofillHints, this.validator, this.onChanged, this.onSubmitted, this.maxLines = 1}) : assert(controller == null || initialValue == null);

  final String label;
  final TextEditingController? controller;
  final String? initialValue;
  final String? hint;
  final String? helperText;
  final String? errorText;
  final IconData? prefixIcon;
  final Widget? suffixIcon;
  final bool obscureText;
  final bool enabled;
  final bool required;
  final TextInputType? keyboardType;
  final TextInputAction? textInputAction;
  final Iterable<String>? autofillHints;
  final String? Function(String?)? validator;
  final ValueChanged<String>? onChanged;
  final ValueChanged<String>? onSubmitted;
  final int maxLines;

  @override
  Widget build(BuildContext context) => TextFormField(
    controller: controller,
    initialValue: initialValue,
    enabled: enabled,
    obscureText: obscureText,
    keyboardType: keyboardType,
    textInputAction: textInputAction,
    autofillHints: autofillHints,
    validator: validator,
    onChanged: onChanged,
    onFieldSubmitted: onSubmitted,
    maxLines: obscureText ? 1 : maxLines,
    style: AppTypography.input,
    decoration: InputDecoration(labelText: required ? '$label *' : label, hintText: hint, helperText: helperText, errorText: errorText, prefixIcon: prefixIcon == null ? null : Icon(prefixIcon, size: AppSizes.icon), suffixIcon: suffixIcon),
  );
}
