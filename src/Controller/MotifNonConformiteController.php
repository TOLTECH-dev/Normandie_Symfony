<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\MotifNonConformiteType;
use App\Motif\FamilleDeMotifs;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Gestion des motifs de non-conformité (D26013).
 *
 * Réservée aux administrateurs. Jusqu'ici, rendre un motif inactif demandait un
 * UPDATE en base ; cet écran l'ouvre à la Région sans intervention technique.
 *
 * L'écran présente un ONGLET PAR DOCUMENT : les motifs d'un chèque et ceux d'un
 * avis d'imposition ne se consultent jamais ensemble, et les mêler dans un seul
 * tableau de 73 lignes obligeait à trier pour retrouver une liste.
 *
 * DEUX RÈGLES PORTÉES ICI, et non par la base :
 *
 *  1. le motif « Autre » de chaque liste ne peut pas être désactivé. Le champ
 *     de saisie libre de l'écran d'instruction s'ouvre quand la DERNIÈRE case
 *     du groupe est cochée ; sans « Autre », l'instructeur ne peut plus motiver
 *     un refus hors liste, et rien ne le signale ;
 *
 *  2. aucun motif ne peut occuper la position 99, réservée à « Autre » pour la
 *     même raison : un motif placé après lui prendrait son rôle.
 *
 * Ce qui n'est pas proposé, volontairement : la SUPPRESSION d'un motif. Les
 * colonnes `*_reason` des instructions ne stockent pas des identifiants mais
 * les entités sérialisées ; supprimer la ligne ne nettoierait donc rien et
 * ferait seulement disparaître le motif des listes. La désactivation produit
 * le résultat voulu en conservant l'historique.
 */
#[Security("is_granted('ROLE_BO_MOTIFS')")]
class MotifNonConformiteController extends AbstractController
{
    /** Paramètre d'adresse portant l'onglet à ouvrir, au format « famille|code ». */
    private const PARAMETRE_ONGLET = 'doc';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger
    ) {
    }

    public function list(Request $request): Response
    {
        $onglets = $this->construireLesOnglets();
        $demande = (string) $request->query->get(self::PARAMETRE_ONGLET, '');

        // L'onglet demandé n'existe pas forcément : une adresse peut être
        // ancienne, ou le document avoir disparu de la nomenclature.
        $actif = isset($onglets[$demande]) ? $demande : (string) array_key_first($onglets);

        return $this->render('BackOffice/MotifNonConformite/list.html.twig', [
            'onglets' => $onglets,
            'onglet_actif' => $actif,
        ]);
    }

    /**
     * La création part toujours de l'onglet d'un document : c'est de là que
     * vient le bouton, et c'est ce document qui détermine les positions encore
     * libres. Sans lui, il n'y a rien à proposer.
     */
    public function add(Request $request): Response
    {
        $document = (string) $request->query->get(self::PARAMETRE_ONGLET, '');

        if (!$this->estUnDocumentConnu($document)) {
            $this->addFlash('danger', 'Choisissez d\'abord l\'onglet du document concerné, puis « Créer un motif ».');

            return $this->redirectToRoute('motif_non_conformite_list');
        }

        [$famille, $filtre] = FamilleDeMotifs::separerLeChoix($document);
        $libres = $this->positionsLibres($famille, $filtre, null);

        if ([] === $libres) {
            $this->addFlash('danger', 'Cette liste n\'a plus de position disponible.');

            return $this->retourALaListe($famille, $filtre);
        }

        $formulaire = $this->createForm(
            MotifNonConformiteType::class,
            ['document' => $document, 'actif' => true, 'position' => $libres[0]],
            ['document' => $document, 'positions' => $libres]
        );
        $formulaire->handleRequest($request);
        $this->verifierLUniciteDeLaPosition($formulaire, $famille, $filtre, null);

        if ($formulaire->isSubmitted() && $formulaire->isValid()) {
            $donnees = $formulaire->getData();

            $classe = FamilleDeMotifs::entite($famille);
            $motif = new $classe();
            $motif->setFiltre($filtre);
            $motif->setSlug(trim((string) $donnees['libelle']));
            $motif->setPositionLast((int) $donnees['position']);
            $motif->setActif((bool) $donnees['actif']);

            $this->em->persist($motif);
            $this->em->flush();

            $this->journaliser('creation', $famille, $motif);
            $this->addFlash('success', 'Le motif « ' . $motif->getSlug() . ' » a été créé.');

            return $this->retourALaListe($famille, $filtre);
        }

        return $this->render('BackOffice/MotifNonConformite/add.html.twig', [
            'form_motif' => $formulaire->createView(),
            'onglet_depart' => $document,
            'document' => FamilleDeMotifs::libelleDuDocument($famille, $filtre),
            'familleLibelle' => FamilleDeMotifs::libelle($famille),
        ]);
    }

    public function edit(Request $request, string $famille, int $motifId): Response
    {
        $motif = $this->trouverLeMotif($famille, $motifId);
        $filtre = (string) $motif->getFiltre();

        // Le motif « Autre » n'est pas modifiable du tout. Lui laisser le moindre
        // champ ouvrait la porte au déplacement vers un autre document, ce qui
        // donnait deux « Autre » dans la même liste et aucun dans celle d'origine.
        if ($this->estLeMotifAutre($motif)) {
            $this->addFlash(
                'danger',
                'Le motif « Autre » ne se modifie pas : il est le dernier de sa liste, et c\'est '
                . "cette place qui ouvre le champ de saisie libre sur l'écran d'instruction."
            );

            return $this->retourALaListe($famille, $filtre);
        }

        $formulaire = $this->createForm(
            MotifNonConformiteType::class,
            [
                'document' => $famille . '|' . $filtre,
                'libelle' => $motif->getSlug(),
                'position' => $motif->getPositionLast(),
                'actif' => $motif->estActif(),
            ],
            [
                'document' => $famille . '|' . $filtre,
                // Le motif conserve sa place parmi les choix, en plus des
                // places libres : sans cela, il ne pourrait pas rester où il est.
                'positions' => $this->positionsLibres($famille, $filtre, $motif->getPositionLast()),
            ]
        );
        $formulaire->handleRequest($request);
        $this->verifierLUniciteDeLaPosition($formulaire, $famille, $filtre, $motifId);

        if ($formulaire->isSubmitted() && $formulaire->isValid()) {
            $donnees = $formulaire->getData();

            // Le document n'est pas relu des données soumises : le champ est
            // désactivé, et le motif reste sur sa liste d'origine.
            $motif->setSlug(trim((string) $donnees['libelle']));
            $motif->setPositionLast((int) $donnees['position']);
            $motif->setActif((bool) $donnees['actif']);

            $this->em->flush();

            $this->journaliser('modification', $famille, $motif);
            $this->addFlash('success', 'Le motif « ' . $motif->getSlug() . ' » a été modifié.');

            return $this->retourALaListe($famille, $filtre);
        }

        return $this->render('BackOffice/MotifNonConformite/edit.html.twig', [
            'form_motif' => $formulaire->createView(),
            'motif' => [
                'famille' => $famille,
                'familleLibelle' => FamilleDeMotifs::libelle($famille),
                'document' => FamilleDeMotifs::libelleDuDocument($famille, $filtre),
                'id' => $motif->getId(),
                'libelle' => $motif->getSlug(),
            ],
            'onglet_depart' => $famille . '|' . $filtre,
        ]);
    }

    /**
     * Les positions encore libres dans la liste d'un document.
     *
     * @param int|null $conservee la position du motif en cours de modification,
     *                            qu'il doit pouvoir garder
     *
     * @return list<int>
     */
    private function positionsLibres(string $famille, string $filtre, ?int $conservee): array
    {
        $prises = [];

        foreach ($this->em->getRepository(FamilleDeMotifs::entite($famille))->findBy(['filtre' => $filtre]) as $motif) {
            $prises[] = (int) $motif->getPositionLast();
        }

        return FamilleDeMotifs::positionsDisponibles($prises, $conservee);
    }

    /**
     * Activation et désactivation depuis la liste, en un clic.
     */
    public function enabled(string $famille, int $motifId, int $flag): Response
    {
        $motif = $this->trouverLeMotif($famille, $motifId);
        $filtre = (string) $motif->getFiltre();

        if ($this->estLeMotifAutre($motif)) {
            $this->addFlash(
                'danger',
                'Le motif « Autre » ne peut pas être désactivé : sans lui, le champ de saisie libre '
                . "ne s'affiche plus sur l'écran d'instruction et un refus hors liste devient impossible à motiver."
            );

            return $this->retourALaListe($famille, $filtre);
        }

        $motif->setActif(1 === $flag);
        $this->em->flush();

        $this->journaliser(1 === $flag ? 'activation' : 'desactivation', $famille, $motif);
        $this->addFlash(
            'success',
            'Le motif « ' . $motif->getSlug() . ' » est désormais ' . (1 === $flag ? 'actif' : 'inactif') . '.'
        );

        return $this->retourALaListe($famille, $filtre);
    }

    /**
     * Les motifs, rangés par document : un onglet par liste.
     *
     * La nomenclature fixe l'ordre des onglets, celui des écrans d'instruction.
     * Un motif dont le `filtre` ne s'y trouve pas obtient malgré tout son
     * onglet, nommé d'après son code brut : une ligne en base ne doit pas
     * disparaître de l'écran qui sert à l'administrer.
     *
     * @return array<string, array{cle: string, ancre: string, famille: string, familleLibelle: string, filtre: string, document: string, connu: bool, motifs: list<array<string, mixed>>, actifs: int, inactifs: int}>
     */
    private function construireLesOnglets(): array
    {
        $onglets = [];

        foreach (FamilleDeMotifs::toutes() as $famille) {
            foreach (FamilleDeMotifs::documents($famille) as $filtre => $document) {
                $onglets[$famille . '|' . $filtre] = $this->ongletVide($famille, $filtre, $document, true);
            }
        }

        foreach (FamilleDeMotifs::toutes() as $famille) {
            $motifs = $this->em->getRepository(FamilleDeMotifs::entite($famille))
                ->findBy([], ['positionLast' => 'ASC', 'id' => 'ASC']);

            foreach ($motifs as $motif) {
                $filtre = (string) $motif->getFiltre();
                $cle = $famille . '|' . $filtre;

                if (!isset($onglets[$cle])) {
                    $onglets[$cle] = $this->ongletVide($famille, $filtre, $filtre, false);
                }

                $protege = FamilleDeMotifs::estProtege($motif->getPositionLast());

                $onglets[$cle]['motifs'][] = [
                    'id' => $motif->getId(),
                    'position' => $motif->getPositionLast(),
                    'libelle' => $motif->getSlug(),
                    'actif' => $motif->estActif(),
                    'protege' => $protege,
                ];

                $onglets[$cle][$motif->estActif() ? 'actifs' : 'inactifs']++;
            }
        }

        // Les positions en double sont désormais refusées à la saisie, mais la
        // base peut en contenir d'avant : on les signale plutôt que de les
        // laisser passer inaperçues, deux motifs de même position s'affichant
        // dans un ordre que seule la clé primaire départage.
        foreach ($onglets as $cle => $onglet) {
            $comptes = array_count_values(array_map(
                static fn (array $motif): int => (int) $motif['position'],
                $onglet['motifs']
            ));

            foreach ($onglet['motifs'] as $rang => $motif) {
                $onglets[$cle]['motifs'][$rang]['doublon'] = ($comptes[(int) $motif['position']] ?? 0) > 1;
            }
        }

        return $onglets;
    }

    /**
     * @return array{cle: string, ancre: string, famille: string, familleLibelle: string, filtre: string, document: string, connu: bool, motifs: list<array<string, mixed>>, actifs: int, inactifs: int}
     */
    private function ongletVide(string $famille, string $filtre, string $document, bool $connu): array
    {
        return [
            'cle' => $famille . '|' . $filtre,
            // Un identifiant HTML, donc sans la barre verticale de la clé.
            'ancre' => 'tab_motif_' . $famille . '_' . preg_replace('/[^A-Za-z0-9_]/', '_', $filtre),
            'famille' => $famille,
            'familleLibelle' => FamilleDeMotifs::libelle($famille),
            'filtre' => $filtre,
            'document' => $document,
            'connu' => $connu,
            'motifs' => [],
            'actifs' => 0,
            'inactifs' => 0,
        ];
    }

    /**
     * Deux motifs ne peuvent pas occuper la même position dans la même liste.
     *
     * Le formulaire ne propose déjà que des places libres : ce contrôle est un
     * second verrou, pour le cas où deux administrateurs travailleraient sur la
     * même liste en même temps — le second verrait encore libre une place prise
     * entre-temps. Il donne alors un message compréhensible plutôt qu'un
     * doublon silencieux.
     */
    private function verifierLUniciteDeLaPosition(
        FormInterface $formulaire,
        string $famille,
        string $filtre,
        ?int $motifExclu
    ): void {
        if (!$formulaire->isSubmitted()) {
            return;
        }

        $position = $formulaire->getData()['position'] ?? null;

        // Position absente ou hors liste : la contrainte du champ le dit déjà,
        // inutile d'ajouter un second message.
        if (!\is_int($position)) {
            return;
        }

        $occupant = $this->em->getRepository(FamilleDeMotifs::entite($famille))
            ->findOneBy(['filtre' => $filtre, 'positionLast' => $position]);

        if (null === $occupant || $occupant->getId() === $motifExclu) {
            return;
        }

        $formulaire->get('position')->addError(new FormError(sprintf(
            'La position %d est déjà occupée par « %s ». Choisissez une position libre, ou déplacez d\'abord ce motif.',
            $position,
            $occupant->getSlug()
        )));
    }

    private function estUnDocumentConnu(string $valeur): bool
    {
        try {
            FamilleDeMotifs::separerLeChoix($valeur);
        } catch (\InvalidArgumentException) {
            return false;
        }

        return true;
    }

    /**
     * Toute action ramène à la liste SUR SON ONGLET : sans cela, chaque clic
     * renverrait sur le premier document et l'administrateur perdrait sa place
     * à chaque activation.
     */
    private function retourALaListe(string $famille, string $filtre): RedirectResponse
    {
        return $this->redirectToRoute('motif_non_conformite_list', [
            self::PARAMETRE_ONGLET => $famille . '|' . $filtre,
        ]);
    }

    private function trouverLeMotif(string $famille, int $motifId): object
    {
        if (!FamilleDeMotifs::existe($famille)) {
            throw new NotFoundHttpException('Famille de motifs inconnue.');
        }

        $motif = $this->em->getRepository(FamilleDeMotifs::entite($famille))->find($motifId);

        if (null === $motif) {
            throw new NotFoundHttpException('Motif de non-conformité introuvable.');
        }

        return $motif;
    }

    private function estLeMotifAutre(object $motif): bool
    {
        return FamilleDeMotifs::estProtege($motif->getPositionLast());
    }

    /**
     * Ces listes pilotent le refus de dossiers : qui les change, et quand, doit
     * rester lisible après coup.
     */
    private function journaliser(string $action, string $famille, object $motif): void
    {
        $utilisateur = $this->getUser();

        $this->logger->info('Motif de non-conformité : ' . $action, [
            'famille' => $famille,
            'motif' => $motif->getId(),
            'document' => $motif->getFiltre(),
            'libelle' => $motif->getSlug(),
            'position' => $motif->getPositionLast(),
            'actif' => $motif->estActif(),
            'auteur' => $utilisateur?->getUserIdentifier(),
        ]);
    }
}
