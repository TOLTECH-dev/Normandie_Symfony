<?php

declare(strict_types=1);

namespace App\Service;

use setasign\Fpdi\Fpdi;

/**
 * Service pour générer des tableaux formatés dans des documents PDF en utilisant FPDI
 */
class FpdiTableService
{
    /**
     * Transforme un tableau d'enregistrements en format de données pour FancyTable
     *
     * @param array $cells Données brutes
     * @return array
     */
    public function loadData(array $cells): array
    {
        $data = [];
        foreach ($cells as $row) {
            $data[] = array_values($row);
        }
        return $data;
    }

    /**
     * Calcule les largeurs de colonne en fonction du contenu
     * Méthode compatible avec le legacy (pas de largeur minimale forcée)
     *
     * @param array $header En-têtes de colonnes
     * @param array $data Données du tableau
     * @return array Largeurs de chaque colonne
     */
    public function getWidthCol(array $header, array $data): array
    {
        $widths = [];
        $numCols = count($header);

        for ($i = 0; $i < $numCols; $i++) {
            $width = strlen($header[$i] ?? '') * 2.5;

            // Vérifier le contenu des lignes
            foreach ($data as $row) {
                if (isset($row[$i])) {
                    $width = max($width, strlen((string)$row[$i]) * 2.5);
                }
            }

            $widths[] = $width;
        }

        return $widths;
    }

    /**
     * Affiche un tableau formaté avec en-têtes et données
     *
     * @param Fpdi $pdf Instance PDF
     * @param array $header En-têtes de colonnes
     * @param array $data Données du tableau
     * @param array $widths Largeurs de colonnes (de getWidthCol)
     * @param string $title Titre optionnel du tableau
     * @return void
     */
    public function fancyTable(Fpdi $pdf, array $header, array $data, array $widths, string $title = ''): void
    {
        $width = array_sum($widths);

        if (!empty($title)) {
            $pdf->SetFillColor(255, 255, 255);
            $pdf->SetTextColor(0);
            $pdf->SetLineWidth(0.3);
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell($width, 7, iconv('UTF-8', 'windows-1252', $title), 1, 0, 'C', true);
            $pdf->Ln();
        }

        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetTextColor(0);
        $pdf->SetDrawColor(0);
        $pdf->SetLineWidth(0.3);
        $pdf->SetFont('Arial', 'B', 9);

        // En-tête
        $numCols = count($header);
        $border = 'LRT';
        if ($numCols > 0) {
            $border = 'LR';
            for ($i = 0; $i < $numCols; $i++) {
                $pdf->Cell($widths[$i], 7, iconv('UTF-8', 'windows-1252', $header[$i] ?? ''), 1, 0, 'C', true);
            }
            $pdf->Ln();
        }

        $pdf->SetFillColor(180, 180, 180);
        $pdf->SetTextColor(0);
        $pdf->SetFont('Arial', '', 8);

        $fill = false;
        foreach ($data as $row) {
            for ($i = 0; $i < $numCols; $i++) {
                $value = $row[$i] ?? '';
                $pdf->Cell($widths[$i], 6, iconv('UTF-8', 'windows-1252', (string)$value), $border, 0, 'C', $fill);
            }
            $border = 'LR';
            $pdf->Ln();
            $fill = !$fill;
        }

        $pdf->Cell($width, 0, '', 'T');
    }
}
