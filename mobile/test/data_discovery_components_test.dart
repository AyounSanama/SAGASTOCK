import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sagastock_mobile/src/core/widgets/app_data_table.dart';
import 'package:sagastock_mobile/src/core/widgets/app_empty_state.dart';
import 'package:sagastock_mobile/src/core/widgets/app_filter_bar.dart';
import 'package:sagastock_mobile/src/core/widgets/app_pagination.dart';
import 'package:sagastock_mobile/src/core/widgets/app_search_field.dart';

void main() {
  testWidgets('la recherche, les filtres et le tableau partagent le système global', (tester) async {
    await tester.pumpWidget(
      const MaterialApp(
        home: Scaffold(
          body: SingleChildScrollView(
            child: Column(
              children: [
                AppFilterBar(children: [SizedBox(width: 280, child: AppSearchField())]),
                AppDataTable(
                  columns: [DataColumn(label: Text('Nom'))],
                  rows: [DataRow(cells: [DataCell(Text('PharmaCare'))])],
                ),
              ],
            ),
          ),
        ),
      ),
    );

    expect(find.text('Rechercher...'), findsOneWidget);
    expect(find.text('PharmaCare'), findsOneWidget);
  });

  testWidgets('pagination et état vide restent accessibles', (tester) async {
    var selected = 2;
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: Column(
            children: [
              const AppEmptyState(title: 'Aucun résultat', description: 'Modifiez votre recherche.'),
              AppPagination(currentPage: 1, totalPages: 2, onPageChanged: (value) => selected = value),
            ],
          ),
        ),
      ),
    );

    expect(find.text('Aucun résultat'), findsOneWidget);
    expect(find.byTooltip('Page précédente'), findsOneWidget);
    await tester.tap(find.byTooltip('Page suivante'));
    expect(selected, 2);
  });
}
