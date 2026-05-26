<?php
// controllers/ReleveNotesController.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/Auth.php';

class ReleveNotesController {

    private PDO $pdo;

    public function __construct() {
        $this->pdo = getDB();
    }

    // -----------------------------------------------
    // Générer les données complètes du relevé
    // -----------------------------------------------
    public function genererDonnees(int $etudiantId, int $semestreId): array {
        Auth::exiger('etudiant', 'admin');

        if (Auth::getRole() === 'etudiant' && $_SESSION['etudiant_id'] !== $etudiantId) {
            return ['succes' => false, 'erreur' => 'Accès non autorisé.'];
        }

        // Infos étudiant
        $etudiant = $this->getInfosEtudiant($etudiantId);
        if (!$etudiant) {
            return ['succes' => false, 'erreur' => 'Étudiant introuvable.'];
        }

        // Infos semestre
        $semestre = $this->getInfosSemestre($semestreId);
        if (!$semestre) {
            return ['succes' => false, 'erreur' => 'Semestre introuvable.'];
        }

        // Cours + notes du semestre
        $cours = $this->getCoursSemestre($etudiantId, $semestreId);

        $moyenneGenerale = 0.0;
        $totalCredits    = 0;
        $totalCreditsVal = 0;

        foreach ($cours as &$c) {
            $notes = $this->getNotesParCours($etudiantId, (int)$c['cours_id']);
            $c['notes'] = $notes;
            $c['moyenne'] = $this->calculerMoyenne($notes);
            $c['mention'] = $this->getMention($c['moyenne']);

            if ($c['moyenne'] !== null) {
                $moyenneGenerale += $c['moyenne'] * (int)$c['credits'];
                $totalCredits    += (int)$c['credits'];
                if ($c['moyenne'] >= 10) {
                    $totalCreditsVal += (int)$c['credits'];
                }
            }
        }
        unset($c);

        $moyenneFinale = $totalCredits > 0
            ? round($moyenneGenerale / $totalCredits, 2)
            : null;

        return [
            'succes'           => true,
            'etudiant'         => $etudiant,
            'semestre'         => $semestre,
            'cours'            => $cours,
            'moyenne_generale' => $moyenneFinale,
            'mention_generale' => $this->getMention($moyenneFinale),
            'credits_valides'  => $totalCreditsVal,
            'credits_total'    => $totalCredits,
            'date_generation'  => date('d/m/Y H:i'),
        ];
    }

    // -----------------------------------------------
    // Générer le PDF via FPDF (sans dépendance Composer)
    // -----------------------------------------------
    public function genererPDF(int $etudiantId, int $semestreId): void {
        Auth::exiger('etudiant', 'admin');

        $donnees = $this->genererDonnees($etudiantId, $semestreId);
        if (!$donnees['succes']) {
            http_response_code(422);
            header('Content-Type: application/json');
            echo json_encode($donnees);
            return;
        }

        // FPDF doit être placé dans /lib/fpdf/fpdf.php
        require_once __DIR__ . '/../lib/fpdf/fpdf.php';

        $pdf = new FPDF('P', 'mm', 'A4');
        $pdf->SetMargins(15, 15, 15);
        $pdf->AddPage();
        $pdf->SetAutoPageBreak(true, 15);

        // --- En-tête établissement ---
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->SetTextColor(31, 78, 121);
        $pdf->Cell(0, 10, 'SmartCampus', 0, 1, 'C');
        $pdf->SetFont('Arial', '', 10);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell(0, 6, 'Relevé de notes officiel', 0, 1, 'C');
        $pdf->Ln(4);

        // Ligne séparatrice
        $pdf->SetDrawColor(31, 78, 121);
        $pdf->SetLineWidth(0.8);
        $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
        $pdf->Ln(6);

        // --- Infos étudiant ---
        $e = $donnees['etudiant'];
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(0, 7, 'Informations de l\'étudiant', 0, 1);
        $pdf->SetFont('Arial', '', 10);

        $lignes = [
            ['Nom complet',        $e['prenom'] . ' ' . $e['nom']],
            ['Numéro étudiant',    $e['numero_etudiant']],
            ['Email',              $e['email']],
            ['Niveau',             $e['niveau']],
            ['Département',        $e['departement'] ?? '-'],
            ['Année scolaire',     $e['annee_scolaire']],
            ['Semestre',           $donnees['semestre']['libelle']],
        ];

        foreach ($lignes as [$label, $valeur]) {
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(55, 6, $label . ' :', 0, 0);
            $pdf->SetFont('Arial', '', 10);
            $pdf->Cell(0, 6, $valeur, 0, 1);
        }

        $pdf->Ln(4);

        // --- Tableau des notes ---
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(0, 7, 'Résultats par cours', 0, 1);
        $pdf->Ln(2);

        // En-têtes tableau
        $pdf->SetFillColor(31, 78, 121);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(10,  7, 'Code',    1, 0, 'C', true);
        $pdf->Cell(70,  7, 'Intitulé', 1, 0, 'C', true);
        $pdf->Cell(20,  7, 'Crédits', 1, 0, 'C', true);
        $pdf->Cell(25,  7, 'Moyenne', 1, 0, 'C', true);
        $pdf->Cell(55,  7, 'Mention', 1, 1, 'C', true);

        // Lignes cours
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('Arial', '', 9);
        $fill = false;

        foreach ($donnees['cours'] as $c) {
            $pdf->SetFillColor(235, 242, 250);
            $moyenne = $c['moyenne'] !== null ? number_format($c['moyenne'], 2) . '/20' : 'N/A';
            $pdf->Cell(10,  6, $c['code'],      1, 0, 'C', $fill);
            $pdf->Cell(70,  6, $c['intitule'],  1, 0, 'L', $fill);
            $pdf->Cell(20,  6, $c['credits'],   1, 0, 'C', $fill);
            $pdf->Cell(25,  6, $moyenne,        1, 0, 'C', $fill);
            $pdf->Cell(55,  6, $c['mention'],   1, 1, 'C', $fill);

            // Détail des évaluations
            if (!empty($c['notes'])) {
                foreach ($c['notes'] as $n) {
                    $pdf->SetFont('Arial', 'I', 8);
                    $pdf->SetTextColor(80, 80, 80);
                    $pdf->Cell(10, 5, '', 0, 0);
                    $pdf->Cell(70, 5, '  › ' . ucfirst($n['type_evaluation']), 0, 0);
                    $pdf->Cell(20, 5, 'Coeff. ' . $n['coefficient'], 0, 0, 'C');
                    $pdf->Cell(25, 5, number_format($n['valeur'], 2) . '/20', 0, 1, 'C');
                    $pdf->SetTextColor(0, 0, 0);
                }
                $pdf->SetFont('Arial', '', 9);
            }

            $fill = !$fill;
        }

        $pdf->Ln(4);

        // --- Récapitulatif ---
        $pdf->SetFillColor(220, 230, 242);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(100, 8, 'Moyenne générale :', 1, 0, 'R', true);
        $pdf->Cell(80,  8,
            $donnees['moyenne_generale'] !== null
                ? number_format($donnees['moyenne_generale'], 2) . '/20  —  ' . $donnees['mention_generale']
                : 'N/A',
            1, 1, 'C', true
        );
        $pdf->Cell(100, 8, 'Crédits validés / total :', 1, 0, 'R', true);
        $pdf->Cell(80,  8,
            $donnees['credits_valides'] . ' / ' . $donnees['credits_total'] . ' ECTS',
            1, 1, 'C', true
        );

        $pdf->Ln(8);

        // --- Pied de page ---
        $pdf->SetFont('Arial', 'I', 8);
        $pdf->SetTextColor(120, 120, 120);
        $pdf->Cell(0, 5, 'Document généré le ' . $donnees['date_generation'] . ' — SmartCampus', 0, 1, 'C');
        $pdf->Cell(0, 5, 'Ce relevé est fourni à titre informatif.', 0, 1, 'C');

        // Envoi du PDF
        $nomFichier = 'releve_' . $e['numero_etudiant'] . '_' . $donnees['semestre']['libelle'] . '.pdf';
        $nomFichier = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $nomFichier);

        $pdf->Output('D', $nomFichier); // D = téléchargement direct
        exit;
    }

    // -----------------------------------------------
    // Helpers privés
    // -----------------------------------------------
    private function getInfosEtudiant(int $id): ?array {
        $stmt = $this->pdo->prepare(
            'SELECT u.nom, u.prenom, u.email,
                    et.numero_etudiant, et.niveau, et.annee_scolaire,
                    d.nom AS departement
             FROM etudiants et
             JOIN utilisateurs u ON u.id = et.utilisateur_id
             LEFT JOIN departements d ON d.id = et.departement_id
             WHERE et.id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function getInfosSemestre(int $id): ?array {
        $stmt = $this->pdo->prepare(
            'SELECT id, libelle, annee_scolaire, numero, date_debut, date_fin
             FROM semestres WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function getCoursSemestre(int $etudiantId, int $semestreId): array {
        $stmt = $this->pdo->prepare(
            'SELECT c.id AS cours_id, c.code, c.intitule, c.credits
             FROM inscriptions i
             JOIN cours c ON c.id = i.cours_id
             WHERE i.etudiant_id = :eid
               AND c.semestre_id = :sid
               AND i.statut = "active"
             ORDER BY c.intitule'
        );
        $stmt->execute([':eid' => $etudiantId, ':sid' => $semestreId]);
        return $stmt->fetchAll();
    }

    private function getNotesParCours(int $etudiantId, int $coursId): array {
        $stmt = $this->pdo->prepare(
            'SELECT n.type_evaluation, n.valeur, n.coefficient
             FROM notes n
             JOIN inscriptions i ON i.id = n.inscription_id
             WHERE i.etudiant_id = :eid AND i.cours_id = :cid AND i.statut = "active"
             ORDER BY n.type_evaluation'
        );
        $stmt->execute([':eid' => $etudiantId, ':cid' => $coursId]);
        return $stmt->fetchAll();
    }

    private function calculerMoyenne(array $notes): ?float {
        if (empty($notes)) return null;
        $somme = 0.0;
        $totalCoeff = 0.0;
        foreach ($notes as $n) {
            $somme      += (float)$n['valeur'] * (float)$n['coefficient'];
            $totalCoeff += (float)$n['coefficient'];
        }
        return $totalCoeff > 0 ? round($somme / $totalCoeff, 2) : null;
    }

    private function getMention(?float $moyenne): string {
        if ($moyenne === null) return '-';
        if ($moyenne >= 16) return 'Très Bien';
        if ($moyenne >= 14) return 'Bien';
        if ($moyenne >= 12) return 'Assez Bien';
        if ($moyenne >= 10) return 'Passable';
        return 'Insuffisant';
    }
}
