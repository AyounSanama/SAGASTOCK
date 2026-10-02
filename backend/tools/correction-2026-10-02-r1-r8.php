<?php

/**
 * Corrections demandées par le porteur le 02/10/2026 sur la base réelle,
 * APRÈS sauvegarde vérifiée (3 empreintes SHA256 identiques) :
 *  - R1 : stock de sécurité du projet PSM-2026 → 2 mois (une seule ligne) ;
 *  - R8 : archivage des programmes 37-S « SOS » et 01-H « HELP » (MSM01),
 *         comme le fait l'application : inactif + deleted_at, jamais supprimés.
 * Tout se fait dans une transaction annulée si un compte de lignes diffère.
 * Chaque changement est inscrit au journal d'audit.
 *
 * Usage : php tools/correction-2026-10-02-r1-r8.php <chemin.sqlite> [--appliquer]
 * Sans --appliquer : simulation (aucune écriture validée).
 */
$path = $argv[1] ?? null;
$apply = in_array('--appliquer', $argv, true);
if (! $path || ! is_file($path)) {
    fwrite(STDERR, "Indiquez le chemin du fichier SQLite.\n");
    exit(1);
}

$pdo = new PDO('sqlite:'.$path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$now = gmdate('Y-m-d H:i:s');
$audit = $pdo->prepare('INSERT INTO audit_logs (user_id, event, auditable_type, auditable_id, old_values, new_values, ip_address, user_agent, created_at, updated_at) VALUES (NULL, ?, ?, ?, ?, ?, NULL, ?, ?, ?)');
$agent = 'correction-2026-10-02-r1-r8 (demande du porteur, sauvegarde vérifiée)';

$pdo->beginTransaction();
try {
    // R1
    $project = $pdo->query("SELECT id, safety_stock_months FROM projects WHERE code = 'PSM-2026' AND deleted_at IS NULL")->fetchAll();
    if (count($project) !== 1) {
        throw new RuntimeException('R1 : '.count($project).' projet(s) PSM-2026 trouvé(s), 1 attendu.');
    }
    $before = $project[0]['safety_stock_months'];
    echo "R1 — PSM-2026 : stock de sécurité actuel = {$before} mois\n";
    $update = $pdo->prepare("UPDATE projects SET safety_stock_months = 2, updated_at = ? WHERE id = ? AND code = 'PSM-2026'");
    $update->execute([$now, $project[0]['id']]);
    if ($update->rowCount() !== 1) {
        throw new RuntimeException('R1 : '.$update->rowCount().' ligne(s) modifiée(s), 1 attendue.');
    }
    $audit->execute(['project.updated', 'App\\Models\\Project', $project[0]['id'], json_encode(['safety_stock_months' => $before]), json_encode(['safety_stock_months' => 2]), $agent, $now, $now]);
    echo "R1 — PSM-2026 : stock de sécurité → 2 mois (1 ligne)\n";

    // R8
    $programs = $pdo->query("SELECT pg.id, pg.code, pg.name FROM programs pg JOIN organizations o ON o.id = pg.organization_id
        WHERE o.code = 'MSM01' AND pg.code IN ('37-S', '01-H') AND pg.deleted_at IS NULL")->fetchAll();
    if (count($programs) !== 2) {
        throw new RuntimeException('R8 : '.count($programs).' programme(s) trouvé(s), 2 attendus.');
    }
    $archive = $pdo->prepare('UPDATE programs SET is_active = 0, deleted_at = ?, updated_at = ? WHERE id = ? AND deleted_at IS NULL');
    foreach ($programs as $program) {
        $archive->execute([$now, $now, $program['id']]);
        if ($archive->rowCount() !== 1) {
            throw new RuntimeException("R8 : archivage de {$program['code']} : ".$archive->rowCount().' ligne(s).');
        }
        $audit->execute(['program.archived', 'App\\Models\\Program', $program['id'], null, json_encode(['motif' => 'Ancien module, archivé comme historique, non migré (décision du 02/10/2026)']), $agent, $now, $now]);
        echo "R8 — programme {$program['code']} « {$program['name']} » archivé (masqué, non supprimé ; liens conservés)\n";
    }

    if ($apply) {
        $pdo->commit();
        echo "Modifications ENREGISTRÉES.\n";
    } else {
        $pdo->rollBack();
        echo "Simulation : modifications ANNULÉES (relancer avec --appliquer).\n";
    }
} catch (Throwable $error) {
    $pdo->rollBack();
    fwrite(STDERR, 'Annulé : '.$error->getMessage()."\n");
    exit(1);
}
