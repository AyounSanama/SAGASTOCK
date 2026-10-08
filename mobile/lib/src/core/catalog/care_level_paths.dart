/// Libellés des niveaux de soins avec leur chemin complet
/// (« Soins de santé primaire › Programmes de prise en charge »).
///
/// Deux catégories du même nom sous des niveaux différents restent ainsi
/// distinctes dans les listes (au lieu de « — Programmes… » répété).
/// [levels] : arbre aplati, chaque parent avant ses enfants, avec `name` et
/// `depth` (1 = niveau, 2 = catégorie, 3 = programme). Les libellés sont
/// rendus dans le même ordre que [levels].
List<String> careLevelPaths(Iterable<Map<dynamic, dynamic>> levels) {
  final ancestors = <int, String>{};
  return [
    for (final level in levels)
      () {
        final depth = int.tryParse('${level['depth'] ?? 1}') ?? 1;
        ancestors
          ..removeWhere((key, _) => key >= depth)
          ..[depth] = '${level['name']}';
        return [
          for (var d = 1; d <= depth; d++) ?ancestors[d],
        ].join(' › ');
      }(),
  ];
}
