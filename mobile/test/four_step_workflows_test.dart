import 'dart:io';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('FOSA uses four stateful PageView steps', () {
    final source = File(
      'lib/src/features/structures/presentation/facilities_page.dart',
    ).readAsStringSync();
    for (final label in [
      'Informations',
      'Classification',
      'Localisation',
      'Résumé',
    ]) {
      expect(source, contains(label));
    }
    expect(source, contains('PageController'));
    expect(source, contains('PageView'));
    expect(source, contains('_validStep'));
    expect(source, contains('_selectedProjectNames'));
    expect(
      source,
      contains(
        "_missionId ??= widget.projects.single['mission_id']?.toString()",
      ),
    );
  });

  test(
    'FOSA user edit keeps role and scope read-only without dropdown assertion',
    () {
      final source = File(
        'lib/src/features/users/presentation/scoped_users_page.dart',
      ).readAsStringSync();
      expect(source, contains('_deduplicateById'));
      expect(source, contains("labelText: 'Rôle'"));
      expect(source, contains("labelText: 'Périmètre actuel'"));
    },
  );

  test('receipt uses four validated PageView steps', () {
    final source = File(
      'lib/src/features/receipts/presentation/receipts_page.dart',
    ).readAsStringSync();
    for (final label in [
      'Informations',
      'Produits',
      'Lots & péremptions',
      'Résumé',
    ]) {
      expect(source, contains(label));
    }
    expect(source, contains('PageView'));
    expect(source, contains('_validStep'));
    expect(source, contains('acceptée + rejetée'));
  });

  test('niveau 7 : réception avec origine obligatoire (couple ONG/Bailleur ou Autre)', () {
    final source = File(
      'lib/src/features/receipts/presentation/receipts_page.dart',
    ).readAsStringSync();
    expect(source, contains("labelText: 'Origine (couple ONG/Bailleur) *'"));
    expect(source, contains("'Choisissez l’origine de l’entrée.'"));
    expect(source, contains("'origin_type': _originChoice == 'other' ? 'other' : 'project'"));
    expect(source, contains("labelText: 'Nom du fournisseur tiers *'"));
    // Les couples proposés sont ceux du site choisi (projets de sa FOSA).
    expect(source, contains("?['origins']"));
  });

  test('inventory uses four validated PageView steps', () {
    final source = File(
      'lib/src/features/inventories/presentation/inventories_page.dart',
    ).readAsStringSync();
    for (final label in ['Informations', 'Comptage', 'Écarts', 'Résumé']) {
      expect(source, contains(label));
    }
    expect(source, contains('PageView'));
    expect(source, contains('_countComplete'));
    expect(source, contains('Enregistrer brouillon'));
  });
}
