<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Beneficiaire;
use App\Entity\Demande_;
use App\Entity\Demande_travaux_devis;
use App\Entity\Demande_travaux_devis_upload;
use App\Entity\User;
use App\Http\SecureFileResponseBuilder;
use App\Service\DocumentAccessChecker;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class DocumentController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private UserService $userService;

    public function __construct(EntityManagerInterface $entityManager, UserService $userService)
    {
        $this->entityManager = $entityManager;
        $this->userService = $userService;
    }

    /**
     * Document d'un dossier, côté bénéficiaire.
     *
     * Seuls les documents du bénéficiaire connecté sont servis. Un document d'autrui, inexistant
     * ou d'un type non proposé au front office reçoit la même réponse (404) : la réponse ne révèle
     * pas l'existence du document (VUL06).
     *
     * @param $demandeId
     * @param $typeFile
     * @param $extension
     * @param $typeDemande
     *
     * @return BinaryFileResponse
     */
    public function viewFO($demandeId, $typeFile, $extension, $typeDemande, SecureFileResponseBuilder $fileResponses, DocumentAccessChecker $accessChecker)
    {
        /* *****************************************************************
                                S E C U R I T Y
        ***************************************************************** */
        $user = $this->getUser();
        if (!$user instanceof User) {
            // Visiteur non connecté : renvoyé vers la page de connexion
            throw $this->createAccessDeniedException();
        }
        $userIdConnected = $user->getId();

        switch ($typeDemande) {
            case 0: $folder = 'demande_auditEnergie/';
                $repo_demande = $this->entityManager->getRepository(Demande_::class);
                $demande = $repo_demande->findOneBy(array(
                    'demande_auditEnergie' => $demandeId
                ));
                $this->checkBeneficiaireConnecte($userIdConnected, $demande?->getBeneficiaireId());
                break;
            case 1: $folder = 'demande_travaux/';
                $repo_demande = $this->entityManager->getRepository(Demande_::class);
                $demande = $repo_demande->findOneBy(array(
                    'demande_travaux' => $demandeId
                ));
                $this->checkBeneficiaireConnecte($userIdConnected, $demande?->getBeneficiaireId());
                break;
            case 2: $folder = 'demande_travaux_devis/';
                if (5 == $typeFile) {
                    $repo_devis_upload = $this->entityManager->getRepository(Demande_travaux_devis_upload::class);
                    $devis = $repo_devis_upload->findDemande($demandeId);

                    $demandeId_param = $devis['demandeTravauxDevisId'] ?? null;
                } else {
                    $demandeId_param = $demandeId;
                }
                $repo_demande = $this->entityManager->getRepository(Demande_travaux_devis::class);
                $demande = null === $demandeId_param ? null : $repo_demande->findOneBy(array(
                    'id' => $demandeId_param
                ));
                $this->checkBeneficiaireConnecte($userIdConnected, $demande?->getBeneficiaireId());
                break;
            case 4: $folder = 'remboursement/auditEnergie_depot/';
                // Rapport d'audit déposé pour le remboursement, affiché dans le dossier du bénéficiaire
                $demande = $accessChecker->findDemandeByDepotAuditEnergie((int) $demandeId);
                $this->checkBeneficiaireConnecte($userIdConnected, $demande?->getBeneficiaireId());
                break;
            default:
                // Fiches techniques, pièces d'instruction, RIB, factures, documents des partenaires :
                // jamais proposés au bénéficiaire (servis uniquement par le back office, avec contrôle du périmètre)
                throw $this->createNotFoundException();
        }

        $path = $this->getParameter('app_root_dossier_data_symfony') . "uploads/" . $folder;
        $suffixFile = $this->findSuffixFileByTypeFile((int)$typeFile);
        $file = $path . $demandeId . $suffixFile . $extension;

        return $fileResponses->download($file);
    }

    /**
     * Le document doit appartenir au bénéficiaire connecté ; sinon 404 (cf. UserService::checkUserSecurity).
     */
    private function checkBeneficiaireConnecte($userIdConnected, $beneficiaireId): void
    {
        $beneficiaire = null === $beneficiaireId
            ? null
            : $this->entityManager->getRepository(Beneficiaire::class)->find($beneficiaireId);

        $this->userService->checkUserSecurity($userIdConnected, $beneficiaire?->getUserId());
    }

    /**
     * Document d'un dossier, côté back office : contrôle du périmètre du compte.
     * Un refus reçoit la même réponse (404) qu'un document inexistant ; le motif est journalisé.
     */
    public function viewBO(
        int $demandeId,
        int $typeFile,
        string $extension,
        int $typeDemande,
        DocumentAccessChecker $accessChecker,
        SecureFileResponseBuilder $fileResponses,
        LoggerInterface $logger
    ): BinaryFileResponse {
        // Vérification d'accès complète
        try {
            $accessChecker->canAccess($demandeId, $typeDemande, $typeFile);
        } catch (AccessDeniedException $refus) {
            $logger->warning('Accès refusé à un document', [
                'motif'       => $refus->getMessage(),
                'username'    => $this->getUser()?->getUserIdentifier(),
                'typeDemande' => $typeDemande,
                'id'          => $demandeId,
                'typeFile'    => $typeFile,
            ]);

            throw $this->createNotFoundException();
        }

        // Mapping dossiers
        $mapTypeDemandeToFolder = [
            0  => 'demande_auditEnergie/',
            1  => 'demande_travaux/',
            2  => 'demande_travaux_devis/',
            3  => 'fiche_technique/',
            4  => 'remboursement/auditEnergie_depot/',
            5  => 'remboursement/auditEnergie_instruction/',
            6  => 'partenaire/auditeur/',
            7  => 'remboursement/auditNumerique_depot/',
            8  => 'remboursement/auditNumerique_instruction/',
            9  => 'remboursement/travaux_instruction/',
            10 => 'remboursement/travaux_instruction/conformite/',
        ];

        $folder = $mapTypeDemandeToFolder[$typeDemande] ?? '';
        $suffixFile = $this->findSuffixFileByTypeFile($typeFile);

        $path = $this->getParameter('app_root_dossier_data_symfony') . 'uploads/' . $folder;
        $file = $path . $demandeId . $suffixFile . $extension;

        return $fileResponses->download($file);
    }

    #[Security("is_granted('ROLE_CONSEILLER') or is_granted('ROLE_AUDITEUR') or is_granted('ROLE_RENOVATEUR') or is_granted('ROLE_TECHNIQUE')")]
    public function viewHelp(SecureFileResponseBuilder $fileResponses): BinaryFileResponse
    {
        $roles = $this->getUser()->getRoles();

        $fileName = '';
        if (in_array('ROLE_CONSEILLER', $roles)) {
            $fileName = 'GUIDE_CONSEILLER-Plateforme_CEEN.pptx';
        } elseif (in_array('ROLE_AUDITEUR', $roles)) {
            $fileName = 'GUIDE_AUDITEUR-Plateforme_CEEN.pptx';
        } elseif (in_array('ROLE_RENOVATEUR', $roles)) {
            $fileName = 'GUIDE_RENOVATEUR-Plateforme_CEEN.pdf';
        } elseif (in_array('ROLE_TECHNIQUE', $roles)) {
            $fileName = 'GUIDE_TECHNIQUE-Plateforme_CEEN.pdf';
        }

        $path = $this->getParameter('kernel.project_dir') . '/resources/Template/';

        // Guides internes : même réponse que les documents déposés (404 si le guide manque)
        return $fileResponses->download($path . $fileName);
    }

    private function findSuffixFileByTypeFile(int $typeFile): string
    {
        $mapTypeFileToSuffix = [
            0  => '_justificatif_propriete.',
            1  => '_piece_complement.',
            2  => '_avis_imposition.',
            3  => '_audit.',
            4  => '_xml_document.',
            5  => '_devis_document.',
            6  => '_rib.',
            7  => '_facture.',
            8  => '_recto_cheque.',
            9  => '_verso_cheque.',
            10 => '_document.',
            11 => '_avis_imposition_conjoint.',
            12 => '_fiche_travaux.',
            13 => '_infiltrometrie_document.',
            14 => '_ventilation_document.',
            15 => '_acte_engagement.',
            17 => '_audit_apres_travaux_document.',
        ];

        return $mapTypeFileToSuffix[$typeFile] ?? '';
    }
}
