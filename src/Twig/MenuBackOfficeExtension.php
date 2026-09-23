<?php

declare(strict_types=1);

namespace App\Twig;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Structure du menu de l'espace partenaire, partagée par la barre latérale et l'en-tête des vues
 * (fil d'Ariane, titre et icône).
 *
 * Une seule description des entrées évite que le menu et le fil d'Ariane divergent. Les droits
 * repris ici sont ceux de l'ancien menu (comparaison automatisée : 20 entrées, aucun écart).
 *
 * Chaque entrée porte :
 *   - libelle    : intitulé affiché ;
 *   - route      : route Symfony, et params éventuels ;
 *   - icone      : classe Glyphicon, pour la barre latérale ;
 *   - icone_code : même symbole en code Unicode, pour l'en-tête de vue (posé en variable CSS) ;
 *   - roles      : profils qui voient l'entrée ;
 *   - actives    : routes qui allument l'entrée, écrans de détail compris.
 */
final class MenuBackOfficeExtension extends AbstractExtension
{
    public function __construct(
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly RequestStack $requestStack
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('menu_bo_sections', [$this, 'sectionsVisibles']),
            new TwigFunction('menu_bo_entree_courante', [$this, 'entreeCourante']),
        ];
    }

    /**
     * Sections du menu, limitées aux entrées auxquelles le compte connecté a droit.
     *
     * @return list<array{titre: string, entrees: list<array<string, mixed>>}>
     */
    public function sectionsVisibles(): array
    {
        $visibles = [];

        foreach (self::sections() as $section) {
            $entrees = [];
            foreach ($section['entrees'] as $entree) {
                foreach ($entree['roles'] as $role) {
                    if ($this->authorizationChecker->isGranted($role)) {
                        $entrees[] = $entree;
                        break;
                    }
                }
            }

            if ([] !== $entrees) {
                $visibles[] = ['titre' => $section['titre'], 'entrees' => $entrees];
            }
        }

        return $visibles;
    }

    /**
     * Entrée correspondant à la page affichée, avec sa section : c'est elle qui donne le fil
     * d'Ariane, l'icône et l'adresse du bouton « Retour ». Null si la page ne relève d'aucune
     * entrée du menu (écrans de connexion, par exemple).
     *
     * @return array{section: string, libelle: string, route: string, params: array<string, mixed>, icone: string, icone_code: string, est_la_page: bool}|null
     */
    public function entreeCourante(): ?array
    {
        $requete = $this->requestStack->getCurrentRequest();
        $route = $requete?->attributes->get('_route');
        if (!\is_string($route) || '' === $route) {
            return null;
        }

        foreach (self::sections() as $section) {
            foreach ($section['entrees'] as $entree) {
                $estLaPage = $route === $entree['route'];
                if (!$estLaPage && !\in_array($route, $entree['actives'], true)) {
                    continue;
                }

                // Deux entrées peuvent partager une route et ne différer que par un paramètre :
                // les auditeurs et les rénovateurs sont la même liste, au type près. Sans cette
                // comparaison, la fiche d'un auditeur s'annoncerait comme celle d'un rénovateur.
                foreach ($entree['params'] ?? [] as $nom => $valeur) {
                    $valeurCourante = $requete->attributes->get($nom);
                    if (null !== $valeurCourante && (string) $valeurCourante !== (string) $valeur) {
                        continue 2;
                    }
                }

                return [
                    'section' => $section['titre'],
                    'libelle' => $entree['libelle'],
                    'route' => $entree['route'],
                    'params' => $entree['params'] ?? [],
                    'icone' => $entree['icone'],
                    'icone_code' => $entree['icone_code'],
                    // Faux sur un écran de détail : le bouton « Retour » ramène alors à la liste.
                    'est_la_page' => $estLaPage,
                ];
            }
        }

        return null;
    }

    /**
     * @return list<array{titre: string, entrees: list<array<string, mixed>>}>
     */
    private static function sections(): array
    {
        return [
            [
                'titre' => 'Dossiers',
                'entrees' => [
                    [
                        'libelle' => 'Consultation des dossiers',
                        'route' => 'demande_list_all',
                        'icone' => 'glyphicon-folder-open',
                        'icone_code' => '\e118',
                        'roles' => ['ROLE_CONSEILLER', 'ROLE_INSTRUCTEUR', 'ROLE_AUDITEUR', 'ROLE_RENOVATEUR', 'ROLE_EPCI', 'ROLE_CLIENT', 'ROLE_ADMIN', 'ROLE_TECHNIQUE'],
                        'actives' => ['demande_view', 'demande_historique', 'demande_fiche_liaison_bo', 'demande_edit', 'demande_reactive'],
                    ],
                    [
                        'libelle' => 'Liste des bénéficiaires',
                        'route' => 'beneficiaire_list_all',
                        'icone' => 'glyphicon-user',
                        'icone_code' => '\e008',
                        'roles' => ['ROLE_CLIENT', 'ROLE_ADMIN'],
                        'actives' => ['beneficiaire_view', 'beneficiaire_edit', 'beneficiaire_add', 'beneficiaire_delete'],
                    ],
                    [
                        'libelle' => 'Assistance bénéficiaires',
                        'route' => 'conseiller_beneficiaire_list',
                        'icone' => 'glyphicon-hand-right',
                        'icone_code' => '\e127',
                        'roles' => ['ROLE_CONSEILLER', 'ROLE_ADMIN'],
                        'actives' => [
                            'conseiller_beneficiaire_add', 'conseiller_beneficiaire_edit', 'conseiller_beneficiaire_view',
                            'conseiller_logement_list', 'conseiller_logement_add', 'conseiller_logement_edit', 'conseiller_logement_view',
                            'conseiller_demande_list', 'conseiller_demande_auditEnergie_add', 'conseiller_demande_auditEnergie_edit',
                            'conseiller_demande_auditEnergie_view', 'conseiller_demande_auditNumerique_add',
                            'conseiller_demande_auditNumerique_edit', 'conseiller_demande_auditNumerique_view',
                            'conseiller_demande_travaux_add', 'conseiller_demande_travaux_edit', 'conseiller_demande_travaux_view',
                            'conseiller_demande_devis_add', 'conseiller_demande_devis_edit', 'conseiller_demande_devis_view',
                        ],
                    ],
                ],
            ],
            [
                'titre' => 'Instruction',
                'entrees' => [
                    [
                        'libelle' => 'Instruction administrative',
                        'route' => 'demande_list_bo',
                        'icone' => 'glyphicon-inbox',
                        'icone_code' => '\e028',
                        'roles' => ['ROLE_INSTRUCTEUR', 'ROLE_CLIENT', 'ROLE_ADMIN'],
                        'actives' => ['demande_auditEnergie_examine', 'demande_auditEnergie_reexamine', 'demande_travaux_examine', 'demande_travaux_reexamine'],
                    ],
                    [
                        'libelle' => 'Instruction technique',
                        'route' => 'demande_devis_list',
                        'icone' => 'glyphicon-wrench',
                        'icone_code' => '\e136',
                        'roles' => ['ROLE_CONSEILLER', 'ROLE_AUDITEUR', 'ROLE_CLIENT', 'ROLE_ADMIN'],
                        'actives' => ['demande_devis_add', 'demande_devis_edit', 'demande_devis_view', 'demande_devis_examine', 'demande_devis_ficheTechnique_examine', 'demande_devis_ficheTechnique_reexamine'],
                    ],
                    [
                        'libelle' => 'Dates de CP',
                        'route' => 'dateCP_list',
                        'icone' => 'glyphicon-calendar',
                        'icone_code' => '\e109',
                        'roles' => ['ROLE_CLIENT', 'ROLE_ADMIN'],
                        'actives' => ['dateCP_assign', 'dateCP_export'],
                    ],
                ],
            ],
            [
                'titre' => 'Aides et paiements',
                'entrees' => [
                    [
                        'libelle' => 'Remboursement des aides',
                        'route' => 'remboursement_list',
                        'icone' => 'glyphicon-euro',
                        'icone_code' => '\20ac',
                        'roles' => ['ROLE_CONSEILLER', 'ROLE_INSTRUCTEUR', 'ROLE_INSTRUCTEUR_UP', 'ROLE_AUDITEUR', 'ROLE_RENOVATEUR', 'ROLE_CLIENT', 'ROLE_ADMIN'],
                        'actives' => [
                            'remboursement_auditEnergie_examine', 'remboursement_auditEnergie_reexamine',
                            'remboursement_auditEnergie_addDepot', 'remboursement_auditEnergie_editDepot',
                            'remboursement_auditNumerique_examine', 'remboursement_auditNumerique_reexamine',
                            'remboursement_auditNumerique_addDepot', 'remboursement_auditNumerique_editDepot',
                            'remboursement_travaux_examine', 'remboursement_travaux_reexamine',
                            'remboursement_travaux_examineFicheTechnique', 'remboursement_travaux_reexamineFicheTechnique',
                        ],
                    ],
                    [
                        'libelle' => 'Production des chèques',
                        'route' => 'production_list',
                        'icone' => 'glyphicon-print',
                        'icone_code' => '\e045',
                        'roles' => ['ROLE_INSTRUCTEUR', 'ROLE_CLIENT', 'ROLE_ADMIN'],
                        'actives' => [],
                    ],
                    [
                        'libelle' => 'Chèques émis',
                        'route' => 'titre_list',
                        'icone' => 'glyphicon-credit-card',
                        'icone_code' => '\e177',
                        'roles' => ['ROLE_CLIENT', 'ROLE_ADMIN'],
                        'actives' => ['titre_attestationNonReception_export'],
                    ],
                    [
                        'libelle' => 'Dates de RMH',
                        'route' => 'dateRMH_list',
                        'icone' => 'glyphicon-time',
                        'icone_code' => '\e023',
                        'roles' => ['ROLE_CLIENT', 'ROLE_ADMIN'],
                        'actives' => ['dateRMH_assign', 'dateRMH_documentRMH_download', 'dateRMH_documentRMH_export', 'dateRMH_recapitulatifPreRMH_export'],
                    ],
                ],
            ],
            [
                'titre' => 'Référentiels',
                'entrees' => [
                    [
                        'libelle' => 'Auditeurs',
                        'route' => 'partenaire_list',
                        'params' => ['type' => 0],
                        'icone' => 'glyphicon-search',
                        'icone_code' => '\e003',
                        'roles' => ['ROLE_CLIENT', 'ROLE_ADMIN'],
                        // Mêmes écrans que les rénovateurs : c'est le paramètre « type » qui départage
                        'actives' => ['partenaire_view', 'partenaire_edit', 'partenaire_add', 'partenaire_view_rating'],
                    ],
                    [
                        'libelle' => 'Rénovateurs',
                        'route' => 'partenaire_list',
                        'params' => ['type' => 1],
                        'icone' => 'glyphicon-home',
                        'icone_code' => '\e021',
                        'roles' => ['ROLE_CLIENT', 'ROLE_ADMIN'],
                        'actives' => ['partenaire_view', 'partenaire_edit', 'partenaire_add', 'partenaire_view_rating'],
                    ],
                    [
                        'libelle' => 'Structures',
                        'route' => 'structure_list',
                        'icone' => 'glyphicon-briefcase',
                        'icone_code' => '\e139',
                        'roles' => ['ROLE_CLIENT', 'ROLE_ADMIN'],
                        'actives' => ['structure_view', 'structure_edit', 'structure_add'],
                    ],
                    [
                        'libelle' => 'EPCI',
                        'route' => 'EPCI_list',
                        'icone' => 'glyphicon-map-marker',
                        'icone_code' => '\e062',
                        'roles' => ['ROLE_CLIENT', 'ROLE_ADMIN'],
                        'actives' => ['EPCI_view', 'EPCI_edit', 'EPCI_add'],
                    ],
                    [
                        'libelle' => 'Orientations H&E',
                        'route' => 'orientation_list',
                        'icone' => 'glyphicon-road',
                        'icone_code' => '\e024',
                        'roles' => ['ROLE_CLIENT', 'ROLE_ADMIN'],
                        'actives' => ['orientation_edit', 'orientation_add', 'orientation_view'],
                    ],
                    [
                        'libelle' => 'Critères ANAH',
                        'route' => 'ANAHCritere_list',
                        'icone' => 'glyphicon-list-alt',
                        'icone_code' => '\e032',
                        'roles' => ['ROLE_CLIENT', 'ROLE_ADMIN'],
                        'actives' => [],
                    ],
                    [
                        'libelle' => 'Banques',
                        'route' => 'banque_list',
                        'icone' => 'glyphicon-piggy-bank',
                        'icone_code' => '\e225',
                        'roles' => ['ROLE_CLIENT', 'ROLE_ADMIN'],
                        'actives' => ['banque_view', 'banque_edit', 'banque_add'],
                    ],
                    [
                        'libelle' => 'Newsletter',
                        'route' => 'contact_list',
                        'icone' => 'glyphicon-envelope',
                        'icone_code' => '\2709',
                        'roles' => ['ROLE_CLIENT', 'ROLE_ADMIN'],
                        'actives' => ['contact_addEdit', 'newsletter_add', 'newsletter_view'],
                    ],
                ],
            ],
            [
                'titre' => 'Aide',
                'entrees' => [
                    [
                        'libelle' => "Besoin d'aide ?",
                        'route' => 'document_help_view',
                        'icone' => 'glyphicon-question-sign',
                        'icone_code' => '\e085',
                        'roles' => ['ROLE_CONSEILLER', 'ROLE_AUDITEUR', 'ROLE_RENOVATEUR', 'ROLE_TECHNIQUE'],
                        'actives' => [],
                        'nouvel_onglet' => true,
                    ],
                ],
            ],
            [
                'titre' => 'Administration',
                'entrees' => [
                    [
                        'libelle' => 'Comptes utilisateurs',
                        'route' => 'admin_list',
                        'icone' => 'glyphicon-th-list',
                        'icone_code' => '\e012',
                        'roles' => ['ROLE_CLIENT', 'ROLE_ADMIN'],
                        'actives' => ['admin_view', 'admin_edit', 'admin_add'],
                    ],
                    [
                        'libelle' => 'Import de données',
                        'route' => 'admin_import',
                        'icone' => 'glyphicon-import',
                        'icone_code' => '\e169',
                        'roles' => ['ROLE_ADMIN'],
                        'actives' => [],
                    ],
                ],
            ],
        ];
    }
}
