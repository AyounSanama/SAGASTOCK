<?php

/**
 * Rapports en LECTURE SEULE sur une base PharmaCare (SQLite), préalables au lot c1.
 *
 *   php tools/rapport-lecture-seule.php "chemin/vers/database.sqlite"
 *
 * Garanties :
 * - fichier ouvert avec SQLITE_OPEN_READONLY : toute écriture est refusée par SQLite ;
 * - PRAGMA query_only = ON : seconde barrière ;
 * - aucune migration, aucun chargement de Laravel, aucun accès aux mots de passe.
 */
$path = $argv[1] ?? null;
if (! $path || ! is_file($path)) {
    fwrite(STDERR, "Indiquez le chemin du fichier SQLite.\n");
    exit(1);
}

$pdo = new PDO('sqlite:'.$path, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::SQLITE_ATTR_OPEN_FLAGS => PDO::SQLITE_OPEN_READONLY,
]);
$pdo->exec('PRAGMA query_only = ON');

$queries = [
    'R1 — Projets dont le stock de sécurité n’est pas dans la nouvelle liste (0,25 / 0,5 / 0,75 / 1 / 1,5 / 2 mois)' => <<<'SQL'
        SELECT code, name AS intitule, status AS statut, safety_stock_months AS stock_securite_actuel
        FROM projects
        WHERE deleted_at IS NULL
          AND safety_stock_months IS NOT NULL
          AND safety_stock_months NOT IN (0.25, 0.5, 0.75, 1, 1.5, 2)
        ORDER BY code
        SQL,
    'R2 — Stock existant dont le couple ONG/Bailleur ne peut pas être déduit (FOSA sans projet, plusieurs projets, ou projet sans bailleur / avec plusieurs bailleurs)' => <<<'SQL'
        WITH stock AS (
            SELECT site_id, COUNT(*) AS lignes, SUM(theoretical_quantity) AS quantite
            FROM stock_balances WHERE theoretical_quantity > 0 GROUP BY site_id
        ),
        projets AS (
            SELECT hfp.health_facility_id, p.id AS project_id, p.code,
                   (SELECT COUNT(*) FROM project_donors pd WHERE pd.project_id = p.id) AS nb_bailleurs
            FROM health_facility_project hfp
            JOIN projects p ON p.id = hfp.project_id AND p.deleted_at IS NULL
        )
        SELECT hf.name AS fosa, s.name AS site, st.lignes, st.quantite,
               (SELECT COUNT(*) FROM projets pr WHERE pr.health_facility_id = hf.id) AS nb_projets,
               (SELECT GROUP_CONCAT(pr.code || ' (' || pr.nb_bailleurs || ' bailleur(s))', ', ') FROM projets pr WHERE pr.health_facility_id = hf.id) AS projets,
               CASE
                   WHEN (SELECT COUNT(*) FROM projets pr WHERE pr.health_facility_id = hf.id) = 0 THEN 'FOSA sans projet'
                   WHEN (SELECT COUNT(*) FROM projets pr WHERE pr.health_facility_id = hf.id) > 1 THEN 'FOSA rattachée à plusieurs projets'
                   ELSE 'Projet sans bailleur ou avec plusieurs bailleurs'
               END AS motif
        FROM stock st
        JOIN sites s ON s.id = st.site_id
        JOIN health_facilities hf ON hf.id = s.health_facility_id
        WHERE (SELECT COUNT(*) FROM projets pr WHERE pr.health_facility_id = hf.id) <> 1
           OR EXISTS (SELECT 1 FROM projets pr WHERE pr.health_facility_id = hf.id AND pr.nb_bailleurs <> 1)
        ORDER BY hf.name, s.name
        SQL,
    'R3 — Entrées en stock par fournisseur (origines « Autre », Ministère de la Santé, autre FOSA… à classer)' => <<<'SQL'
        SELECT COALESCE(su.name, '(aucun fournisseur)') AS fournisseur, COUNT(r.id) AS entrees,
               MIN(r.received_on) AS premiere, MAX(r.received_on) AS derniere
        FROM receipts r
        LEFT JOIN suppliers su ON su.id = r.supplier_id
        GROUP BY su.id, su.name
        ORDER BY entrees DESC
        SQL,
    'R4 — Ordonnances marquées « validées » sans validateur (contournement V1 à corriger)' => <<<'SQL'
        SELECT COUNT(*) AS ordonnances
        FROM prescriptions
        WHERE status = 'validated' AND validated_by IS NULL AND validated_at IS NULL
        SQL,
    'R5 — Comptes dont le périmètre ne correspond pas au rôle (plateforme hors Admin Sago, comptes FOSA hors site)' => <<<'SQL'
        SELECT u.name AS nom, u.email, r.code AS role, ru.scope_type AS perimetre,
               CASE WHEN u.is_active = 1 THEN 'actif' ELSE 'inactif' END AS etat,
               CASE WHEN u.deleted_at IS NULL THEN 'non' ELSE 'oui' END AS archive
        FROM role_user ru
        JOIN users u ON u.id = ru.user_id
        JOIN roles r ON r.id = ru.role_id
        WHERE (ru.scope_type = 'platform' AND r.code NOT IN ('sago_admin', 'owner', 'platform_owner'))
           OR (r.code IN ('site_admin', 'site_user', 'facility_manager', 'pharmacist', 'clinician', 'supervisor') AND ru.scope_type <> 'site')
        ORDER BY r.code, u.name
        SQL,
];

foreach ($queries as $title => $sql) {
    echo "\n=== $title ===\n";
    $rows = $pdo->query($sql)->fetchAll();
    if ($rows === []) {
        echo "(aucun résultat)\n";
        continue;
    }
    foreach ($rows as $row) {
        echo implode(' | ', array_map(fn ($key, $value) => "$key: ".($value ?? '—'), array_keys($row), $row)), "\n";
    }
}
echo "\nAucune écriture effectuée (connexion en lecture seule).\n";
