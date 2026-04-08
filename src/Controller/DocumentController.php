<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Beneficiaire;
use App\Entity\Demande_;
use App\Entity\Demande_travaux_devis;
use App\Entity\Demande_travaux_devis_upload;
use App\Service\DocumentAccessChecker;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
     * @param $demandeId
     * @param $typeFile
     * @param $extension
     * @param $typeDemande
     *
     * @return BinaryFileResponse
     */
    public function viewFO($demandeId, $typeFile, $extension, $typeDemande)
    {
        /* *****************************************************************
                                S E C U R I T Y
        ***************************************************************** */
        $userIdConnected = $this->getUser()->getId();

        switch ($typeDemande) {
            case 0: $folder = 'demande_auditEnergie/';
                $repo_demande = $this->entityManager->getRepository(Demande_::class);
                $demande = $repo_demande->findOneBy(array(
                    'demande_auditEnergie' => $demandeId
                ));
                $beneficiaireId = $demande->getBeneficiaireId();
                $repo_beneficiaire = $this->entityManager->getRepository(Beneficiaire::class);
                $beneficiaire = $repo_beneficiaire->findOneBy(array(
                    'id' => $beneficiaireId
                ));
                $this->userService->checkUserSecurity($userIdConnected, $beneficiaire->getUserId());
                break;
            case 1: $folder = 'demande_travaux/';
                $repo_demande = $this->entityManager->getRepository(Demande_::class);
                $demande = $repo_demande->findOneBy(array(
                    'demande_travaux' => $demandeId
                ));
                $beneficiaireId = $demande->getBeneficiaireId();
                $repo_beneficiaire = $this->entityManager->getRepository(Beneficiaire::class);
                $beneficiaire = $repo_beneficiaire->findOneBy(array(
                    'id' => $beneficiaireId
                ));
                $this->userService->checkUserSecurity($userIdConnected, $beneficiaire->getUserId());
                break;
            case 2: $folder = 'demande_travaux_devis/';
                if (5 == $typeFile) {
                    $repo_devis_upload = $this->entityManager->getRepository(Demande_travaux_devis_upload::class);
                    $devis = $repo_devis_upload->findDemande($demandeId);

                    $demandeId_param = $devis['demandeTravauxDevisId'];
                } else {
                    $demandeId_param = $demandeId;
                }
                $repo_demande = $this->entityManager->getRepository(Demande_travaux_devis::class);
                $demande = $repo_demande->findOneBy(array(
                    'id' => $demandeId_param
                ));
                $beneficiaireId = $demande->getBeneficiaireId();
                $repo_beneficiaire = $this->entityManager->getRepository(Beneficiaire::class);
                $beneficiaire = $repo_beneficiaire->findOneBy(array(
                    'id' => $beneficiaireId
                ));
                $this->userService->checkUserSecurity($userIdConnected, $beneficiaire->getUserId());
                break;
            case 3: $folder = 'fiche_technique/';
                break;
            case 4: $folder = 'remboursement/auditEnergie_depot/';
                break;
            case 5: $folder = 'remboursement/auditEnergie_instruction/';
                break;
            case 6: $folder = 'partenaire/auditeur/';
                break;
            case 7: $folder = 'remboursement/auditNumerique_depot/';
                break;
            case 8: $folder = 'remboursement/auditNumerique_instruction/';
                break;
            case 9: $folder = 'remboursement/travaux_instruction/';
                break;
            case 10: $folder = 'remboursement/travaux_instruction/conformite/';
                break;
            default: $folder = '';
                break;
        }

        $path = $this->getParameter('app_root_dossier_data_symfony') . "uploads/" . $folder;
        $suffixFile = $this->findSuffixFileByTypeFile((int)$typeFile);
        $file = $path . $demandeId . $suffixFile . $extension;

        return new BinaryFileResponse($file);
    }
    public function viewBO(
        int $demandeId,
        int $typeFile,
        string $extension,
        int $typeDemande,
        DocumentAccessChecker $accessChecker
    ): BinaryFileResponse {
        // Vérification d'accès complète
        $accessChecker->canAccess($demandeId, $typeDemande, $typeFile);

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

        return new BinaryFileResponse($file);
    }

    #[Security("is_granted('ROLE_CONSEILLER') or is_granted('ROLE_AUDITEUR') or is_granted('ROLE_RENOVATEUR') or is_granted('ROLE_TECHNIQUE')")]
    public function viewHelp(): Response
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
        $file = new File($path . $fileName);

        if(file_exists($file->getPath())) {
            return new Response(file_get_contents($file->getRealPath()), 200, [
                'Content-Type'        => $file->getMimeType(),
                'Content-Length'      => filesize($file->getRealPath()),
                'Content-Disposition' => 'attachment; filename=' . $file->getBasename(),
            ]);
        } else {
            throw new NotFoundHttpException();
        }
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
