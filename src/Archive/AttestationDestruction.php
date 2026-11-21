<?php

declare(strict_types=1);

namespace App\Archive;

use App\Entity\ArchiveLot;

/**
 * Remplissage de l'attestation de destruction (Reco-03).
 *
 * Le service d'archivage de la Région a fourni sa trame le 21/11/2026 :
 * `resources/Template/attestation_destruction_modele.docx`. Elle attend un
 * emplacement « NOM SOCIETE – NOM APPLICATION » et un tableau à trois colonnes
 * — description du contenu, volumétrie, date de destruction.
 *
 * La trame est REMPLIE, jamais recréée : sa mise en forme, ses logos et son
 * libellé sont ceux de la Région, et c'est elle qui sera signée. On ne touche
 * qu'au texte.
 *
 * PIÈGE : les cellules du tableau contiennent un paragraphe SANS AUCUN run.
 * Les remplir demande donc de créer un run, et non de remplacer le texte d'un
 * run existant — c'est la différence avec la plupart des remplissages de
 * documents Word.
 */
final class AttestationDestruction
{
    private const NAMESPACE_W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    private const NAMESPACE_W14 = 'http://schemas.microsoft.com/office/word/2010/wordml';

    /** Texte à remplacer dans la trame. */
    private const EMPLACEMENT_SOCIETE = 'NOM SOCIETE – NOM APPLICATION';

    /** Ce par quoi on le remplace. */
    private const SOCIETE = 'TOLTECH – Chèque éco-énergie Normandie';

    public function __construct(private readonly string $modele)
    {
    }

    /**
     * Produit l'attestation d'un lot.
     *
     * @param list<array{description: string, volumetrie: string, date: string}> $lignes
     *
     * @throws \RuntimeException
     */
    public function produire(ArchiveLot $lot, array $lignes, string $destination): string
    {
        if (!is_file($this->modele) || !is_readable($this->modele)) {
            throw new \RuntimeException(sprintf('Trame d\'attestation introuvable : %s', $this->modele));
        }

        if (!copy($this->modele, $destination)) {
            throw new \RuntimeException(sprintf('Copie de la trame impossible vers %s', $destination));
        }

        $zip = new \ZipArchive();

        if (true !== $zip->open($destination)) {
            throw new \RuntimeException(sprintf('Attestation illisible après copie : %s', $destination));
        }

        try {
            $xml = $zip->getFromName('word/document.xml');

            if (false === $xml) {
                throw new \RuntimeException('La trame ne contient pas word/document.xml.');
            }

            $zip->addFromString('word/document.xml', $this->remplir($xml, $lot, $lignes));
        } catch (\Throwable $e) {
            $zip->close();
            unlink($destination);

            throw $e;
        }

        if (!$zip->close()) {
            throw new \RuntimeException(sprintf('Écriture de l\'attestation en échec : %s', $destination));
        }

        return $destination;
    }

    /**
     * @param list<array{description: string, volumetrie: string, date: string}> $lignes
     */
    private function remplir(string $xml, ArchiveLot $lot, array $lignes): string
    {
        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = true;

        if (!$dom->loadXML($xml)) {
            throw new \RuntimeException('Le document de la trame est illisible.');
        }

        $xp = new \DOMXPath($dom);
        $xp->registerNamespace('w', self::NAMESPACE_W);
        $xp->registerNamespace('w14', self::NAMESPACE_W14);

        $this->remplacerLaSociete($xp);
        $this->remplirLeTableau($dom, $xp, $lignes);
        $this->remplirLaDate($xp, $lot);

        return (string) $dom->saveXML();
    }

    private function remplacerLaSociete(\DOMXPath $xp): void
    {
        $trouve = false;

        foreach ($xp->query('//w:t') as $noeud) {
            if (str_contains($noeud->textContent, self::EMPLACEMENT_SOCIETE)) {
                $noeud->textContent = str_replace(
                    self::EMPLACEMENT_SOCIETE,
                    self::SOCIETE,
                    $noeud->textContent
                );
                $trouve = true;
            }
        }

        if (!$trouve) {
            // Arrêt volontaire : une attestation qui porterait encore
            // « NOM SOCIETE » serait renvoyée par le service des Archives, et
            // une trame modifiée mérite qu'on le sache tout de suite.
            throw new \RuntimeException(sprintf(
                "La trame ne contient plus l'emplacement « %s » : elle a changé, "
                . 'vérifier AttestationDestruction avant de continuer.',
                self::EMPLACEMENT_SOCIETE
            ));
        }
    }

    /**
     * @param list<array{description: string, volumetrie: string, date: string}> $lignes
     */
    private function remplirLeTableau(\DOMDocument $dom, \DOMXPath $xp, array $lignes): void
    {
        $tableau = $xp->query('//w:tbl')->item(0);

        if (!$tableau instanceof \DOMElement) {
            throw new \RuntimeException('La trame ne contient pas le tableau attendu.');
        }

        $toutes = [];

        foreach ($xp->query('./w:tr', $tableau) as $tr) {
            $toutes[] = $tr;
        }

        if (count($toutes) < 2) {
            throw new \RuntimeException('Le tableau de la trame n\'a pas de ligne à remplir.');
        }

        // La première ligne est l'en-tête ; les suivantes sont vides et prêtes
        // à recevoir le contenu.
        $vides = array_slice($toutes, 1);
        $modele = $vides[0];

        foreach ($lignes as $index => $ligne) {
            $tr = $vides[$index] ?? $this->ajouterUneLigne($tableau, $modele, $xp);

            $this->ecrireLaLigne($dom, $xp, $tr, [
                $ligne['description'],
                $ligne['volumetrie'],
                $ligne['date'],
            ]);
        }

        // Les lignes vides en trop sont retirées : une attestation ne doit pas
        // comporter de ligne blanche qu'on pourrait croire effacée.
        foreach (array_slice($vides, count($lignes)) as $enTrop) {
            $enTrop->parentNode?->removeChild($enTrop);
        }
    }

    private function ajouterUneLigne(\DOMElement $tableau, \DOMElement $modele, \DOMXPath $xp): \DOMElement
    {
        /** @var \DOMElement $nouvelle */
        $nouvelle = $modele->cloneNode(true);

        // Les identifiants de révision doivent rester uniques.
        foreach ($xp->query('descendant-or-self::*[@w14:paraId or @w14:textId]', $nouvelle) as $noeud) {
            if ($noeud instanceof \DOMElement) {
                $noeud->removeAttributeNS(self::NAMESPACE_W14, 'paraId');
                $noeud->removeAttributeNS(self::NAMESPACE_W14, 'textId');
            }
        }

        $tableau->appendChild($nouvelle);

        return $nouvelle;
    }

    /**
     * Écrit les cellules d'une ligne.
     *
     * Les cellules de la trame contiennent un paragraphe sans run : il faut en
     * créer un, en respectant l'ordre imposé par le schéma — `w:rPr` d'abord,
     * `w:t` ensuite.
     *
     * @param list<string> $valeurs
     */
    private function ecrireLaLigne(\DOMDocument $dom, \DOMXPath $xp, \DOMElement $tr, array $valeurs): void
    {
        foreach ($xp->query('./w:tc', $tr) as $index => $tc) {
            if (!$tc instanceof \DOMElement || !isset($valeurs[$index])) {
                continue;
            }

            $paragraphe = $xp->query('./w:p', $tc)->item(0);

            if (!$paragraphe instanceof \DOMElement) {
                $paragraphe = $dom->createElementNS(self::NAMESPACE_W, 'w:p');
                $tc->appendChild($paragraphe);
            }

            foreach (iterator_to_array($xp->query('./w:r', $paragraphe)) as $ancien) {
                $paragraphe->removeChild($ancien);
            }

            $run = $dom->createElementNS(self::NAMESPACE_W, 'w:r');
            $texte = $dom->createElementNS(self::NAMESPACE_W, 'w:t');
            $texte->appendChild($dom->createTextNode($valeurs[$index]));
            $texte->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
            $run->appendChild($texte);
            $paragraphe->appendChild($run);
        }
    }

    /**
     * Complète la mention « Date : » du pied de l'attestation.
     *
     * La trame emploie une ESPACE INSÉCABLE avant le deux-points, comme le
     * veut la typographie française (U+00A0). Une comparaison sur une espace
     * ordinaire ne la reconnaîtrait pas, et la date ne serait pas portée —
     * sans erreur visible. On compare donc après normalisation, et on réécrit
     * en conservant l'insécable.
     */
    private function remplirLaDate(\DOMXPath $xp, ArchiveLot $lot): void
    {
        $date = $lot->getDateDestruction() ?? new \DateTime();

        foreach ($xp->query('//w:t') as $noeud) {
            $normalise = trim(str_replace("\u{00A0}", ' ', $noeud->textContent));

            if ('Date :' === $normalise) {
                $noeud->textContent = sprintf("Date\u{00A0}: %s", $date->format('d/m/Y'));

                return;
            }
        }

        throw new \RuntimeException(
            "La trame ne porte plus la mention « Date : » : elle a changé, vérifier AttestationDestruction."
        );
    }
}
