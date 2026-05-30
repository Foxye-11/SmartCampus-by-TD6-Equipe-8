<?php
// controllers/StatistiqueController.php
// Statistiques académiques agrégées, réservées à l'administration.
// L'agrégation est faite en PHP à partir de quelques requêtes simples afin de
// rester lisible et portable (pas de window functions exotiques).

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/Auth.php';

class StatistiqueController {

    private PDO $pdo;

    // Seuils métier alignés sur le reste de l'application.
    private const SEUIL_REUSSITE = 10.0;  // moyenne de validation
    private const SEUIL_ABSENCE  = 30.0;  // % d'absence déclenchant une alerte

    public function __construct() {
        $this->pdo = getDB();
    }

    // Point d'entrée unique : renvoie tout le tableau de bord statistique.
    public function tableauDeBord(): array {
        Auth::exiger('admin');

        return [
            'succes'           => true,
            'global'           => $this->global(),
            'par_cours'        => $this->parCours(),
            'distribution'     => $this->distributionMoyennes(),
            'etudiants_risque' => $this->etudiantsARisque(),
        ];
    }

    // -----------------------------------------------
    // Indicateurs globaux
    // -----------------------------------------------
    private function global(): array {
        $nbEtudiants = (int)$this->pdo->query(
            'SELECT COUNT(*) FROM etudiants et JOIN utilisateurs u ON u.id = et.utilisateur_id WHERE u.actif = 1'
        )->fetchColumn();

        $nbEnseignants = (int)$this->pdo->query(
            'SELECT COUNT(*) FROM enseignants e JOIN utilisateurs u ON u.id = e.utilisateur_id WHERE u.actif = 1'
        )->fetchColumn();

        $nbCours        = (int)$this->pdo->query('SELECT COUNT(*) FROM cours')->fetchColumn();
        $nbInscriptions = (int)$this->pdo->query('SELECT COUNT(*) FROM inscriptions WHERE statut = "active"')->fetchColumn();
        $nbSalles       = (int)$this->pdo->query('SELECT COUNT(*) FROM salles')->fetchColumn();

        // Moyenne pondérée de toutes les notes de l'établissement.
        $moy = $this->pdo->query(
            'SELECT SUM(valeur * coefficient) / NULLIF(SUM(coefficient), 0) FROM notes'
        )->fetchColumn();
        $moyenneEtablissement = $moy !== null ? round((float)$moy, 2) : null;

        // Taux de présence global (présent + retard + excusé) / total.
        $presRow = $this->pdo->query(
            'SELECT SUM(statut IN ("present","retard","excuse")) AS ok, COUNT(*) AS total FROM presences'
        )->fetch();
        $tauxPresence = ($presRow && (int)$presRow['total'] > 0)
            ? round(100 * (int)$presRow['ok'] / (int)$presRow['total'], 1)
            : null;

        // Taux de réussite global : % d'étudiants notés dont la moyenne >= 10.
        $moyennesEtudiants = $this->moyennesParEtudiant();
        $notes = array_filter($moyennesEtudiants, fn($m) => $m !== null);
        $tauxReussite = null;
        if (count($notes) > 0) {
            $reussis = count(array_filter($notes, fn($m) => $m >= self::SEUIL_REUSSITE));
            $tauxReussite = round(100 * $reussis / count($notes), 1);
        }

        return [
            'nb_etudiants'           => $nbEtudiants,
            'nb_enseignants'         => $nbEnseignants,
            'nb_cours'               => $nbCours,
            'nb_inscriptions'        => $nbInscriptions,
            'nb_salles'              => $nbSalles,
            'moyenne_etablissement'  => $moyenneEtablissement,
            'taux_presence'          => $tauxPresence,
            'taux_reussite'          => $tauxReussite,
        ];
    }

    // -----------------------------------------------
    // Statistiques par cours
    // -----------------------------------------------
    private function parCours(): array {
        // Moyenne par inscription (active).
        $stmt = $this->pdo->query(
            'SELECT i.cours_id,
                    SUM(n.valeur * n.coefficient) / NULLIF(SUM(n.coefficient), 0) AS moyenne,
                    COUNT(n.id) AS nb_notes
             FROM inscriptions i
             LEFT JOIN notes n ON n.inscription_id = i.id
             WHERE i.statut = "active"
             GROUP BY i.id, i.cours_id'
        );
        $parCours = []; // cours_id => ['effectif','notes','somme','reussis']
        foreach ($stmt->fetchAll() as $r) {
            $cid = (int)$r['cours_id'];
            if (!isset($parCours[$cid])) $parCours[$cid] = ['effectif' => 0, 'notes' => 0, 'somme' => 0.0, 'reussis' => 0];
            $parCours[$cid]['effectif']++;
            if ($r['nb_notes'] > 0 && $r['moyenne'] !== null) {
                $parCours[$cid]['notes']++;
                $parCours[$cid]['somme']  += (float)$r['moyenne'];
                if ((float)$r['moyenne'] >= self::SEUIL_REUSSITE) $parCours[$cid]['reussis']++;
            }
        }

        // Absences par cours.
        $absRows = $this->pdo->query(
            'SELECT i.cours_id,
                    SUM(p.statut = "absent") AS absents,
                    COUNT(*) AS total
             FROM presences p
             JOIN inscriptions i ON i.id = p.inscription_id
             GROUP BY i.cours_id'
        )->fetchAll();
        $absences = [];
        foreach ($absRows as $a) {
            $absences[(int)$a['cours_id']] = [(int)$a['absents'], (int)$a['total']];
        }

        // Métadonnées des cours.
        $cours = $this->pdo->query(
            'SELECT c.id, c.code, c.intitule, s.libelle AS semestre, s.archive
             FROM cours c JOIN semestres s ON s.id = c.semestre_id
             ORDER BY s.archive, c.code'
        )->fetchAll();

        $resultat = [];
        foreach ($cours as $c) {
            $cid  = (int)$c['id'];
            $agg  = $parCours[$cid] ?? ['effectif' => 0, 'notes' => 0, 'somme' => 0.0, 'reussis' => 0];
            $abs  = $absences[$cid] ?? [0, 0];

            $moyenne      = $agg['notes'] > 0 ? round($agg['somme'] / $agg['notes'], 2) : null;
            $tauxReussite = $agg['notes'] > 0 ? round(100 * $agg['reussis'] / $agg['notes'], 1) : null;
            $tauxAbsence  = $abs[1] > 0 ? round(100 * $abs[0] / $abs[1], 1) : null;

            $resultat[] = [
                'code'          => $c['code'],
                'intitule'      => $c['intitule'],
                'semestre'      => $c['semestre'],
                'archive'       => (int)$c['archive'],
                'effectif'      => $agg['effectif'],
                'notes_saisies' => $agg['notes'],
                'moyenne'       => $moyenne,
                'taux_reussite' => $tauxReussite,
                'taux_absence'  => $tauxAbsence,
            ];
        }
        return $resultat;
    }

    // -----------------------------------------------
    // Distribution des moyennes générales des étudiants
    // -----------------------------------------------
    private function distributionMoyennes(): array {
        $tranches = [
            ['label' => '0 – 8',   'min' => 0,  'max' => 8,    'nb' => 0],
            ['label' => '8 – 10',  'min' => 8,  'max' => 10,   'nb' => 0],
            ['label' => '10 – 12', 'min' => 10, 'max' => 12,   'nb' => 0],
            ['label' => '12 – 14', 'min' => 12, 'max' => 14,   'nb' => 0],
            ['label' => '14 – 16', 'min' => 14, 'max' => 16,   'nb' => 0],
            ['label' => '16 – 20', 'min' => 16, 'max' => 20.01,'nb' => 0],
        ];
        foreach ($this->moyennesParEtudiant() as $m) {
            if ($m === null) continue;
            foreach ($tranches as &$t) {
                if ($m >= $t['min'] && $m < $t['max']) { $t['nb']++; break; }
            }
            unset($t);
        }
        // On ne renvoie que label + nb.
        return array_map(fn($t) => ['label' => $t['label'], 'nb' => $t['nb']], $tranches);
    }

    // -----------------------------------------------
    // Étudiants à risque (moyenne < 10 OU taux d'absence > seuil)
    // -----------------------------------------------
    private function etudiantsARisque(): array {
        // Moyennes par étudiant (avec identité).
        $rows = $this->pdo->query(
            'SELECT et.id, et.numero_etudiant, CONCAT(u.prenom, " ", u.nom) AS nom,
                    SUM(n.valeur * n.coefficient) / NULLIF(SUM(n.coefficient), 0) AS moyenne,
                    COUNT(n.id) AS nb_notes
             FROM etudiants et
             JOIN utilisateurs u ON u.id = et.utilisateur_id
             JOIN inscriptions i ON i.etudiant_id = et.id AND i.statut = "active"
             LEFT JOIN notes n ON n.inscription_id = i.id
             WHERE u.actif = 1
             GROUP BY et.id'
        )->fetchAll();

        // Absences par étudiant.
        $absRows = $this->pdo->query(
            'SELECT i.etudiant_id,
                    SUM(p.statut = "absent") AS absents,
                    COUNT(*) AS total
             FROM presences p
             JOIN inscriptions i ON i.id = p.inscription_id
             GROUP BY i.etudiant_id'
        )->fetchAll();
        $absences = [];
        foreach ($absRows as $a) {
            $total = (int)$a['total'];
            $absences[(int)$a['etudiant_id']] = $total > 0
                ? round(100 * (int)$a['absents'] / $total, 1) : 0.0;
        }

        $risque = [];
        foreach ($rows as $r) {
            $id          = (int)$r['id'];
            $moyenne     = ($r['nb_notes'] > 0 && $r['moyenne'] !== null) ? round((float)$r['moyenne'], 2) : null;
            $tauxAbsence = $absences[$id] ?? 0.0;

            $raisons = [];
            if ($moyenne !== null && $moyenne < self::SEUIL_REUSSITE) $raisons[] = 'Moyenne < 10';
            if ($tauxAbsence > self::SEUIL_ABSENCE)                   $raisons[] = 'Absentéisme élevé';

            if (!empty($raisons)) {
                $risque[] = [
                    'nom'             => $r['nom'],
                    'numero_etudiant' => $r['numero_etudiant'],
                    'moyenne'         => $moyenne,
                    'taux_absence'    => $tauxAbsence,
                    'raisons'         => implode(' · ', $raisons),
                ];
            }
        }
        // Tri : moyenne croissante (les plus faibles d'abord).
        usort($risque, fn($a, $b) => ($a['moyenne'] ?? 99) <=> ($b['moyenne'] ?? 99));
        return $risque;
    }

    // -----------------------------------------------
    // Helper : moyenne générale pondérée par étudiant (valeurs seules).
    // -----------------------------------------------
    private function moyennesParEtudiant(): array {
        $rows = $this->pdo->query(
            'SELECT i.etudiant_id,
                    SUM(n.valeur * n.coefficient) / NULLIF(SUM(n.coefficient), 0) AS moyenne
             FROM inscriptions i
             JOIN notes n ON n.inscription_id = i.id
             WHERE i.statut = "active"
             GROUP BY i.etudiant_id'
        )->fetchAll();
        return array_map(fn($r) => $r['moyenne'] !== null ? (float)$r['moyenne'] : null, $rows);
    }
}
