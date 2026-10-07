import 'dart:io';
import 'dart:math' as math;
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sagastock_mobile/src/core/theme/app_theme.dart';
import 'package:sagastock_mobile/src/core/theme/app_tokens.dart';
import 'package:sagastock_mobile/src/core/widgets/app_badge.dart';
import 'package:sagastock_mobile/src/core/widgets/app_kpi_card.dart';

/// AM-160 — Charte des couleurs (identique au Web).
///
/// Capture de contrôle facultative :
/// `flutter test test/theme_charter_test.dart --dart-define=AM160_CAPTURE=true`
/// écrit `../.tmp/ui-screenshots/am160/mobile-charte.png`.
const _capture = bool.fromEnvironment('AM160_CAPTURE');

double _luminance(Color color) {
  double channel(double c) =>
      c <= 0.04045 ? c / 12.92 : math.pow((c + 0.055) / 1.055, 2.4).toDouble();
  return 0.2126 * channel(color.r) +
      0.7152 * channel(color.g) +
      0.0722 * channel(color.b);
}

double contrast(Color a, Color b) {
  final la = _luminance(a), lb = _luminance(b);
  return (math.max(la, lb) + 0.05) / (math.min(la, lb) + 0.05);
}

void main() {
  group('contrastes de la charte', () {
    test('le texte blanc sur la teinte forte dépasse 4,5:1', () {
      expect(AppColors.primaryStrong, const Color(0xFFB85D00));
      expect(
        contrast(Colors.white, AppColors.primaryStrong),
        greaterThanOrEqualTo(4.5),
      );
    });

    test('le survol / appui est plus foncé, jamais plus clair', () {
      expect(
        _luminance(AppColors.primaryStrongPressed),
        lessThan(_luminance(AppColors.primaryStrong)),
      );
    });

    test('l’orange de marque ne sert pas au texte sur fond clair', () {
      expect(contrast(AppColors.primary, Colors.white), lessThan(4.5));
      // Mais il reste lisible comme texte actif sur le menu sombre.
      expect(
        contrast(AppColors.sidebarActiveText, AppColors.sidebar),
        greaterThanOrEqualTo(4.5),
      );
    });

    test('textes et états respectent 4,5:1 sur leur fond', () {
      expect(
        contrast(AppColors.text, AppColors.background),
        greaterThanOrEqualTo(4.5),
      );
      expect(
        contrast(AppColors.textMuted, AppColors.surface),
        greaterThanOrEqualTo(4.5),
      );
      expect(
        contrast(AppColors.sidebarText, AppColors.sidebar),
        greaterThanOrEqualTo(4.5),
      );
      for (final (text, surface) in [
        (AppColors.successText, AppColors.successSurface),
        (AppColors.infoText, AppColors.infoSurface),
        (AppColors.dangerText, AppColors.dangerSurface),
        (AppColors.neutralText, AppColors.neutralSurface),
      ]) {
        expect(contrast(text, surface), greaterThanOrEqualTo(4.5));
      }
    });
  });

  group('rôles des couleurs dans le thème', () {
    final theme = AppTheme.light;

    test('boutons à texte blanc et bouton flottant : teinte forte', () {
      final style = theme.filledButtonTheme.style!;
      expect(style.backgroundColor!.resolve({}), AppColors.primaryStrong);
      expect(
        style.backgroundColor!.resolve({WidgetState.pressed}),
        AppColors.primaryStrongPressed,
      );
      expect(style.foregroundColor!.resolve({}), Colors.white);
      expect(
        theme.floatingActionButtonTheme.backgroundColor,
        AppColors.primaryStrong,
      );
    });

    test('indicateurs et focus : orange de marque ; texte : teinte forte', () {
      expect(theme.tabBarTheme.indicatorColor, AppColors.primary);
      expect(theme.tabBarTheme.labelColor, AppColors.primaryStrong);
      final focused =
          theme.inputDecorationTheme.focusedBorder as OutlineInputBorder;
      expect(focused.borderSide.color, AppColors.primary);
      expect(
        theme.textButtonTheme.style!.foregroundColor!.resolve({}),
        AppColors.primaryStrong,
      );
      expect(AppTheme.orange, AppColors.primaryStrong);
    });

    test(
      'pastille sélectionnée : bordure marque, texte orange lisible (5,45:1)',
      () {
        final chip = theme.chipTheme;
        expect(
          (chip.side! as WidgetStateBorderSide).resolve({
            WidgetState.selected,
          })!.color,
          AppColors.primary,
        );
        expect(
          theme.colorScheme.onSecondaryContainer,
          AppColors.primarySoftText,
        );
      },
    );

    testWidgets('texte de la pastille sélectionnée : teinte forte en Inter', (
      tester,
    ) async {
      await tester.pumpWidget(
        MaterialApp(
          theme: theme,
          home: Scaffold(
            body: Row(
              children: [
                FilterChip(
                  label: const Text('Toutes'),
                  selected: true,
                  onSelected: (_) {},
                ),
                FilterChip(
                  label: const Text('Actives'),
                  selected: false,
                  onSelected: (_) {},
                ),
              ],
            ),
          ),
        ),
      );
      TextStyle styleOf(String label) =>
          tester.renderObject<RenderParagraph>(find.text(label)).text.style!;
      expect(styleOf('Toutes').fontFamily, 'Inter');
      expect(styleOf('Toutes').color, AppColors.primaryStrong);
      expect(styleOf('Actives').color, AppColors.text);
    });

    test('police Inter et champs à 15 px', () {
      expect(theme.textTheme.bodyMedium!.fontFamily, 'Inter');
      expect(AppTypography.input.fontSize, 15);
    });
  });

  testWidgets('aucun badge d’état n’est orange', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Column(
          children: [
            for (final variant in AppBadgeVariant.values)
              AppBadge(label: variant.name, variant: variant),
          ],
        ),
      ),
    );
    for (final variant in AppBadgeVariant.values) {
      final text = tester.widget<Text>(find.text(variant.name));
      final color = text.style?.color;
      expect(
        color,
        isNot(anyOf(AppColors.primary, AppColors.primaryStrong)),
        reason: variant.name,
      );
    }
  });

  testWidgets('capture de la charte mobile', (tester) async {
    if (_capture) {
      await _loadFonts();
      // Par défaut, flutter_test remplace les ombres par un contour noir
      // (debugDisableShadows) : ombres réelles pour une capture fidèle.
      debugDisableShadows = false;
    }
    final key = GlobalKey();
    tester.view.physicalSize = const Size(390 * 2, 844 * 2);
    tester.view.devicePixelRatio = 2;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(
      RepaintBoundary(
        key: key,
        child: MaterialApp(
          debugShowCheckedModeBanner: false,
          theme: AppTheme.light,
          home: const _CharterPreview(),
        ),
      ),
    );
    await tester.pumpAndSettle();
    // Focus du champ pour montrer la bordure de marque.
    await tester.tap(find.byType(TextField).first);
    await tester.pumpAndSettle();
    expect(find.text('Ajouter une FOSA'), findsOneWidget);

    if (!_capture) return;
    await tester.runAsync(() async {
      final boundary =
          key.currentContext!.findRenderObject()! as RenderRepaintBoundary;
      final image = await boundary.toImage(pixelRatio: 2);
      final bytes = await image.toByteData(format: ui.ImageByteFormat.png);
      final file = File('../.tmp/ui-screenshots/am160/mobile-charte.png');
      await file.parent.create(recursive: true);
      await file.writeAsBytes(bytes!.buffer.asUint8List());
    });
    // Rétabli dans le corps du test : le framework vérifie cette variable
    // avant les tearDown.
    debugDisableShadows = true;
  });
}

Future<void> _loadFonts() async {
  final inter = FontLoader('Inter');
  for (final weight in ['Regular', 'Medium', 'SemiBold', 'Bold']) {
    inter.addFont(
      Future.value(
        ByteData.sublistView(
          File('assets/fonts/Inter-$weight.ttf').readAsBytesSync(),
        ),
      ),
    );
  }
  await inter.load();
  final root = Platform.environment['FLUTTER_ROOT'];
  final icons = File(
    '$root/bin/cache/artifacts/material_fonts/MaterialIcons-Regular.otf',
  );
  if (root != null && icons.existsSync()) {
    final loader = FontLoader('MaterialIcons')
      ..addFont(Future.value(ByteData.sublistView(icons.readAsBytesSync())));
    await loader.load();
  }
}

/// Aperçu des éléments de la charte, disposé comme l’écran FOSA (maquette 06).
class _CharterPreview extends StatelessWidget {
  const _CharterPreview();

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 3,
      child: Scaffold(
        appBar: AppBar(
          title: const Text('Formations sanitaires'),
          bottom: const TabBar(
            tabs: [
              Tab(text: 'FOSA (14)'),
              Tab(text: 'Comptes (31)'),
              Tab(text: 'Appro.'),
            ],
          ),
        ),
        floatingActionButton: FloatingActionButton(
          onPressed: () {},
          tooltip: 'Ajouter',
          child: const Icon(Icons.add),
        ),
        bottomNavigationBar: NavigationBar(
          selectedIndex: 1,
          destinations: const [
            NavigationDestination(
              icon: Icon(Icons.home_outlined),
              label: 'Accueil',
            ),
            NavigationDestination(
              icon: Icon(Icons.local_hospital_outlined),
              label: 'FOSA',
            ),
            NavigationDestination(
              icon: Icon(Icons.list_alt_outlined),
              label: 'Liste',
            ),
            NavigationDestination(
              icon: Icon(Icons.person_outline),
              label: 'Profil',
            ),
          ],
        ),
        body: ListView(
          padding: const EdgeInsets.all(AppSpacing.lg),
          children: [
            const TextField(
              decoration: InputDecoration(
                hintText: 'Rechercher une FOSA (nom ou code)',
                prefixIcon: Icon(Icons.search),
              ),
            ),
            const SizedBox(height: AppSpacing.md),
            Wrap(
              spacing: AppSpacing.sm,
              children: [
                FilterChip(
                  label: const Text('Toutes'),
                  selected: true,
                  onSelected: (_) {},
                ),
                FilterChip(
                  label: const Text('Actives'),
                  selected: false,
                  onSelected: (_) {},
                ),
                FilterChip(
                  label: const Text('Inactives'),
                  selected: false,
                  onSelected: (_) {},
                ),
              ],
            ),
            const SizedBox(height: AppSpacing.md),
            const Row(
              children: [
                Expanded(
                  child: AppKpiCard(
                    label: 'FOSA actives',
                    value: '12/14',
                    icon: Icons.local_hospital_outlined,
                    caption: '2 désactivées',
                  ),
                ),
                SizedBox(width: AppSpacing.sm),
                Expanded(
                  child: AppKpiCard(
                    label: 'Échecs de synchro',
                    value: '1',
                    icon: Icons.sync_problem_outlined,
                    tone: AppKpiTone.red,
                  ),
                ),
              ],
            ),
            const SizedBox(height: AppSpacing.md),
            const Wrap(
              spacing: AppSpacing.sm,
              runSpacing: AppSpacing.sm,
              children: [
                AppBadge(label: 'À jour', variant: AppBadgeVariant.success),
                AppBadge(
                  label: 'En attente · 3 op.',
                  variant: AppBadgeVariant.info,
                ),
                AppBadge(
                  label: 'Échec de synchro',
                  variant: AppBadgeVariant.danger,
                ),
                AppBadge(label: 'Inactive', variant: AppBadgeVariant.neutral),
              ],
            ),
            const SizedBox(height: AppSpacing.lg),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    onPressed: () {},
                    child: const Text('Annuler'),
                  ),
                ),
                const SizedBox(width: AppSpacing.sm),
                Expanded(
                  child: FilledButton(
                    onPressed: () {},
                    child: const Text('Enregistrer'),
                  ),
                ),
              ],
            ),
            const SizedBox(height: AppSpacing.sm),
            FilledButton.icon(
              onPressed: () {},
              icon: const Icon(Icons.add),
              label: const Text('Ajouter une FOSA'),
            ),
            TextButton(
              onPressed: () {},
              child: const Text('Voir toutes les FOSA'),
            ),
            SwitchListTile(
              value: true,
              onChanged: (_) {},
              title: const Text('FOSA active'),
            ),
          ],
        ),
      ),
    );
  }
}
