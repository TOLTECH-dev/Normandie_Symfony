-- =====================================================================
-- D26013 — statut « Remboursement à réinstruire ».
--
-- Un remboursement refusé (statut 20) est aujourd'hui un terminus : le
-- partenaire ne peut plus remplacer la pièce qu'on lui reproche, et le
-- dossier disparaît même de sa liste. La demande de support porte
-- précisément là-dessus.
--
-- On rouvre le dépôt sur un remboursement refusé, et le redépôt place le
-- remboursement dans ce nouveau statut 31. Il ne se confond donc pas avec
-- un remboursement jamais instruit : l'instructeur voit qu'il reprend un
-- dossier déjà examiné une fois.
--
-- color_step 1 : dossier en cours, comme les autres statuts d'attente.
-- color 1      : même traitement visuel que « Remboursement incomplet ».
--
-- La description est lue par le bénéficiaire sur son espace. Elle ne
-- reprend PAS le motif de refus : celui-ci est effacé au redépôt, le
-- reproche n'ayant plus lieu d'être une fois la pièce remplacée.
--
-- A exécuter sur la recette d'abord, puis la production, AVANT le
-- déploiement du code.
--
-- Rejouable : INSERT conditionné à l'absence de la ligne.
-- =====================================================================

INSERT INTO remboursement_statut (id, statut, description, slug, color, color_step)
SELECT 31,
       31,
       'Votre dossier de remboursement est en cours de réexamen.',
       'Remboursement à réinstruire',
       1,
       1
WHERE NOT EXISTS (SELECT 1 FROM remboursement_statut WHERE id = 31);


-- =====================================================================
-- Contrôle
-- =====================================================================
-- SELECT id, statut, slug, color, color_step
-- FROM remboursement_statut
-- WHERE id = 31;
--
-- Remboursements qui deviendront reprenables (refusés, ni anonymisés ni
-- archivés) :
--
-- SELECT COUNT(*)
-- FROM remboursement_ r
--     JOIN demande_ d ON d.id = r.demande_id
-- WHERE r.statut_id = 20
--     AND r.rgpd <> 1
--     AND d.rgpd <> 1
--     AND NOT EXISTS (SELECT 1 FROM archive_demande_export ade WHERE ade.demande_id = d.id);
