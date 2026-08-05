import 'package:flutter/material.dart';

/// Registre unique des icônes de modules de PharmaCare.
///
/// Toutes les plateformes mobiles utilisent Material Symbols/Icons Outlined.
/// Une même clé de module produit donc toujours la même représentation.
abstract final class ModuleIconRegistry {
  static const Map<String, IconData> _icons = {
    'dashboard': Icons.dashboard_outlined,
    'configuration': Icons.settings_suggest_outlined,
    'organizations': Icons.business_outlined,
    'missions': Icons.public_outlined,
    'projects': Icons.work_outline,
    'funding': Icons.handshake_outlined,
    'facilities': Icons.local_hospital_outlined,
    'sites': Icons.location_on_outlined,
    'users': Icons.group_outlined,
    'standard_lists': Icons.format_list_bulleted_outlined,
    'products': Icons.medication_outlined,
    'stocks': Icons.inventory_2_outlined,
    'receipts': Icons.move_to_inbox_outlined,
    'dispensing': Icons.local_pharmacy_outlined,
    'dispensations': Icons.local_pharmacy_outlined,
    'inventories': Icons.inventory_outlined,
    'orders': Icons.shopping_cart_outlined,
    'reports': Icons.bar_chart_outlined,
    'synchronization': Icons.sync_outlined,
    'settings': Icons.settings_outlined,
    'activity_logs': Icons.history_outlined,
    'profile': Icons.person_outline,
  };

  static IconData resolve(String? key) =>
      _icons[key] ?? Icons.extension_outlined;
}
