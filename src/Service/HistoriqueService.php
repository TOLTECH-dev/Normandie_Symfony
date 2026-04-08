<?php

namespace App\Service;

use App\Entity\Demande_;
use App\Entity\Demande_statut;
use App\Entity\Historique_;
use App\Entity\Historique_email;
use App\Entity\Historique_post;
use App\Entity\Remboursement_;
use App\Entity\Remboursement_statut;
use App\Repository\Demande_Repository;
use App\Repository\Demande_statutRepository;
use App\Repository\Remboursement_Repository;
use App\Repository\Remboursement_statutRepository;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;

class HistoriqueService
{
    /**
     * @var EntityManagerInterface
     */
    private $EM;

    /**
     * @var Environment
     */
    private $environment;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var DemandeServiceFO
     */
    private $demandeService;

    /**
     * @var ParameterBagInterface
     */
    private $parameterBag;

    /**
     * @var RemboursementService
     */
    private $remboursementService;
    
    /**
     * @var MailerService
     */
    private $mailerService;

    /**
     * @var Demande_Repository
     */
    private $repo_demande;

    /**
     * @var Demande_statutRepository
     */
    private $repo_demandeStatut;

    /**
     * @var Remboursement_Repository
     */
    private $remboursementRepository;

    /**
     * @var Remboursement_statutRepository
     */
    private $repo_remboursementStatut;

    /**
     * @var array
     */
    private static $roles = [
        'ROLE_AUTOMATE'       => 'Automate',
        'ROLE_MEMBER'         => 'Bénéficiaire',
        'ROLE_CLIENT'         => 'Région',
        'ROLE_AUDITEUR'       => 'Auditeur',
        'ROLE_RENOVATEUR'     => 'Rénovateur',
        'ROLE_EPCI'           => 'EPCI',
        'ROLE_INSTRUCTEUR'    => 'Instructeur',
        'ROLE_INSTRUCTEUR_UP' => 'Instructeur UP',
        'ROLE_CONSEILLER'     => 'Conseiller H&E',
        'ROLE_TECHNIQUE'      => 'Technique',
        'ROLE_ADMIN'          => 'Administrateur',
        'ROLE_SUPER_ADMIN'    => 'Super administrateur',
    ];

    const BASE_TEMPLATE_PATH_DEMANDE = 'FrontOffice/Demande/';
    const BASE_TEMPLATE_PATH_REMBOURSEMENT = 'BackOffice/Remboursement/';


    public function __construct(
        EntityManagerInterface $entityManager,
        Environment $environment,
        RequestStack $requestStack,
        RouterInterface $router,
        ParameterBagInterface $parameterBag,
        RemboursementService $remboursementService,
        MailerService $mailerService
    ) {
        $this->EM = $entityManager;
        $this->environment = $environment;
        $this->requestStack = $requestStack;
        $this->router = $router;
        $this->parameterBag = $parameterBag;
        $this->remboursementService = $remboursementService;
        $this->mailerService = $mailerService;

        $this->repo_demande = $this->EM->getRepository(Demande_::class);
        $this->repo_demandeStatut = $this->EM->getRepository(Demande_statut::class);
        $this->remboursementRepository = $this->EM->getRepository(Remboursement_::class);
        $this->repo_remboursementStatut = $this->EM->getRepository(Remboursement_statut::class);
    }



    /* *****************************************************************
    ********************************************************************
                    P U B L I C   F U N C T I O N
    ********************************************************************
    *******************************************************************/

    public function setDemandeService(DemandeServiceFO $demandeServiceFO)
    {
        $this->demandeService = $demandeServiceFO;
    }

    /**
     * @param $demandeId
     * @param $statutId
     * @param $demandeType
     * @param $userRoles
     * @param $envoiEmail
     * @param $action
     * @param $destinataire
     * @param $beneficiaireType
     * @param $documentJPAlt
     * @param $documentKBISAlt
     * @param $documentAIAlt
     * @param $returnHistorique
     * @param $remboursementId
     * @param $ribAlt
     * @param $factureAlt
     * @param $rectoChequeAlt
     * @param $versoChequeAlt
     * @param $ficheTravauxAlt
     * @param $isBBC1
     * @param $dateEmissionTitre
     * @param $options
     * @return false|Historique_
     */
    public function save(
        $demandeId,
        $statutId,
        $demandeType,
        $userRoles,
        $envoiEmail = false,
        $action = null,
        $destinataire = null,
        $beneficiaireType = null,
        $documentJPAlt = null,
        $documentKBISAlt = null,
        $documentAIAlt = null,
        $returnHistorique = false,
        $remboursementId = null,
        $ribAlt = null,
        $factureAlt = null,
        $rectoChequeAlt = null,
        $versoChequeAlt = null,
        $ficheTravauxAlt = null,
        $isBBC1 = null,
        $dateEmissionTitre = null,
        $options = []
    ) {
        if ($remboursementId) {
            $statutSlug = $this->repo_remboursementStatut->findSlugByStatut($statutId);
        } else {
            $statutSlug = $this->repo_demandeStatut->findSlugByStatut($statutId);
        }
        $roleNom = $this->findRole($userRoles);

        $historique = new Historique_(
            $action,
            $demandeId,
            $remboursementId,
            $envoiEmail,
            $roleNom,
            $statutSlug
        );
//        if (false === $historique) {
//            return false;
//        }

        $request = isset($this->requestStack) ? $this->requestStack->getCurrentRequest() : null;
        $allRequest = $request ? $request->request->all() : array();
        $historiqueData = new Historique_post($allRequest);
        $historique->addHistoriquePost($historiqueData);

        $this->EM->persist($historique);
        $this->EM->flush();

        if ($envoiEmail) {
            $this->sendEmail(
                $statutId,
                $demandeId,
                $demandeType,
                $destinataire,
                $beneficiaireType,
                $historique,
                $documentJPAlt,
                $documentKBISAlt,
                $documentAIAlt,
                $remboursementId,
                $ribAlt,
                $factureAlt,
                $rectoChequeAlt,
                $versoChequeAlt,
                $ficheTravauxAlt,
                $isBBC1,
                $dateEmissionTitre,
                $options
            );
        }

        if ($returnHistorique) {
            return $historique;
        }

        return false;
    }

    /**
     * @param Historique_ $historique
     * @param $body
     * @param $fromMailerAddress
     * @param $destinataire
     * @param $subject
     * @param null $contentType
     */
    public function saveHistoriqueEmail(
        Historique_ $historique,
                    $body,
                    $fromMailerAddress,
                    $destinataire,
                    $subject,
                    $contentType = null
    ) {
        /* /////////////////////////////////////////////////////////////////
                            FILL UP EMAIL HISTORIQUE
        ///////////////////////////////////////////////////////////////// */
        $historiqueEmail = new Historique_email(
            $body,
            $fromMailerAddress,
            [$destinataire],
            $subject,
            $contentType
        );
        $historiqueEmail->setHistorique($historique);

        $this->EM->persist($historiqueEmail);
        $this->EM->flush();
    }

    /**
     * @param array $historiques
     *
     * @return Demande_statut|null
     */
    public function findPreviousDemandeStatutAfterReactivationDemande(array $historiques)
    {
        /**
         * @var Historique_ $historique
         */
        foreach ($historiques as $historique) {
            if (Demande_statut::LABEL_SLUG_DEMANDE_REFUSEE != $historique->getStatutSlug()
                && Historique_::LABEL_HISTORIQUE_ACTION_COMMENTAIRE != $historique->getAction()
            ) {
                return $this->repo_demandeStatut->findOneBy([
                    'slug' => $historique->getStatutSlug()
                ]);
            }
        }
        return null;
    }

    /* *****************************************************************
    ********************************************************************
                    P R I V A T E   F U N C T I O N
    ********************************************************************
    *******************************************************************/

    /**
     * @param array $roles
     * @return bool|mixed|string
     */
    private function findRole(array $roles)
    {
        $roleNom = '';
        $roleUser = 'ROLE_USER';
        $role = 'Automate';

        if (!empty($roles)) {
            foreach ($roles as $row) {
                if ($row != $roleUser) {
                    if (self::$roles[$row]) {
                        $roleNom .= self::$roles[$row];
                        if (FALSE !== next($roles) and $roleUser != next($roles)) {
                            $roleNom .= ' | ';
                        }
                    }
                }
            }
            $role = $roleNom;
        }

        return $role;
    }

    /**
     * Destinataire : Set the to addresses of this message
     *
     * If multiple recipients will receive the message an array should be used.
     * Example: array('receiver@domain.org', 'other@domain.org' => 'A name')
     *
     * @param $statutId
     * @param $demandeId
     * @param $demandeType
     * @param $destinataire
     * @param $beneficiaireType
     * @param Historique_|null $historique
     * @param $documentJPAlt
     * @param $documentKBISAlt
     * @param $documentAIAlt
     * @param $remboursementId
     * @param $ribAlt
     * @param $factureAlt
     * @param $rectoChequeAlt
     * @param $versoChequeAlt
     * @param $ficheTravauxAlt
     * @param $isBBC1
     * @param $dateEmissionTitre
     * @param $options
     * @return void
     */
    private function sendEmail(
        $statutId,
        $demandeId,
        $demandeType,
        $destinataire = null,
        $beneficiaireType = null,
        Historique_ $historique = null,
        $documentJPAlt = null,
        $documentKBISAlt = null,
        $documentAIAlt = null,
        $remboursementId = null,
        $ribAlt = null,
        $factureAlt = null,
        $rectoChequeAlt = null,
        $versoChequeAlt = null,
        $ficheTravauxAlt = null,
        $isBBC1 = null,
        $dateEmissionTitre = null,
        $options = []
    ) {
        if (!$remboursementId) {
            $data = $this->findDataForDemandeEmail(
                $statutId,
                $demandeId,
                $demandeType,
                $beneficiaireType,
                $documentJPAlt,
                $documentKBISAlt,
                $documentAIAlt,
                $options
            );
        } else {
            $data = $this->findDataForRemboursementEmail(
                $statutId,
                $demandeId,
                $demandeType,
                $ribAlt,
                $factureAlt,
                $rectoChequeAlt,
                $versoChequeAlt,
                $ficheTravauxAlt,
                $isBBC1,
                $dateEmissionTitre,
                $remboursementId
            );
        }

        $contentType = 'text/html';
        $fromMailerAddress = $this->parameterBag->get('mailer_address_from');

        if ($destinataire) {
            $subject = $data['subject'];
            $templatePath = $data['templatePath'];
            $templateData = $data['templateData'];

            if ($subject and $templatePath) {
                $body = $this->environment->render($templatePath, $templateData);

                // Images à embarquer UNIQUEMENT si token présent
                $embeddedImages = [];

                if (!empty($data['carnetInformationLogementToken'])) {
                    $logoPath = $this->parameterBag->get('app_logo_path');
                    $embeddedImages['LOGO-NORMANDIE-EMAIL'] = $logoPath . 'logo_normandie_email.png';
                }

                $nbSent = $this->mailerService->sendGeneriqueEmail(
                    $subject,
                    $body,
                    $fromMailerAddress,
                    $destinataire,
                    null,
                    $contentType,
                    'UTF-8',
                    null,
                    null,
                    null,
                    null,
                    null,
                    $embeddedImages
                );

                // If Email success sent
                if ($nbSent) {
                    if (!empty($data['carnetInformationLogementToken'])) {
                        // LA PHRASE D'ACCES AU LIEN CARNET INFORMATION LOGEMENT A ETE AUSSI INCLUSE DANS LE MAIL

                        /**
                         * @var Demande_ $demande
                         */
                        $demande = $this->repo_demande->find($demandeId);
                        if (!empty($demande)) {
                            $demande->setCarnetInformationRequestedAt(new \DateTime());
                            $demande->setCarnetInformationToken($data['carnetInformationLogementToken']);
                            $this->EM->persist($demande);
                            $this->EM->flush();
                        }
                    }

                    if ($historique) {
                        $this->saveHistoriqueEmail(
                            $historique,
                            $body,
                            $fromMailerAddress,
                            $destinataire,
                            $subject,
                            $contentType
                        );
                    }

                }
            }
        }

        /* /////////////////////////////////////////////////////////////////
                            CAS AUDITEUR
        ///////////////////////////////////////////////////////////////// */
        if (isset($data['subject_auditeur']) && $data['email_auditeur']) {
            $body = $this->environment->render($data['templatePath_auditeur'], $data['templateData_auditeur']);

            $nbSent = $this->mailerService->sendGeneriqueEmail(
                $data['subject_auditeur'],
                $body,
                $fromMailerAddress,
                $data['email_auditeur'],
                null,
                $contentType,
                'UTF-8'
            );

            // If Email success sent
            if ($nbSent && $historique) {
                $this->saveHistoriqueEmail(
                    $historique,
                    $body,
                    $fromMailerAddress,
                    $data['email_auditeur'],
                    $data['subject_auditeur'],
                    $contentType
                );
            }
        }

        /* /////////////////////////////////////////////////////////////////
                            CAS RENOVATEUR
        ///////////////////////////////////////////////////////////////// */
        if (isset($data['subject_renovateur']) && $data['email_renovateur']) {
            $body = $this->environment->render($data['templatePath_renovateur'], $data['templateData_renovateur']);

            $nbSent = $this->mailerService->sendGeneriqueEmail(
                $data['subject_renovateur'],
                $body,
                $fromMailerAddress,
                $data['email_renovateur'],
                null,
                $contentType,
                'UTF-8'
            );

            // If Email success sent
            if ($nbSent && $historique) {
                $this->saveHistoriqueEmail(
                    $historique,
                    $body,
                    $fromMailerAddress,
                    $data['email_renovateur'],
                    $data['subject_renovateur'],
                    $contentType
                );
            }
        }

        /* /////////////////////////////////////////////////////////////////
                            CAS CONSEILLER
        ///////////////////////////////////////////////////////////////// */
        if (isset($data['subject_conseiller']) && $data['email_conseiller']) {
            $body = $this->environment->render($data['templatePath_conseiller'], $data['templateData_conseiller']);

            $nbSent = $this->mailerService->sendGeneriqueEmail(
                $data['subject_conseiller'],
                $body,
                $fromMailerAddress,
                $data['email_conseiller'],
                null,
                $contentType,
                'UTF-8'
            );

            // If Email success sent
            if ($nbSent && $historique) {
                $this->saveHistoriqueEmail(
                    $historique,
                    $body,
                    $fromMailerAddress,
                    $data['email_conseiller'],
                    $data['subject_conseiller'],
                    $contentType
                );
            }
        }

        /* /////////////////////////////////////////////////////////////////
                            CAS CONSEILLER EMAIL 2
        ///////////////////////////////////////////////////////////////// */
        if (isset($data['subject_conseiller2']) && $data['email_conseiller2']) {
            $body = $this->environment->render($data['templatePath_conseiller2'], $data['templateData_conseiller2']);

            $nbSent = $this->mailerService->sendGeneriqueEmail(
                $data['subject_conseiller2'],
                $body,
                $fromMailerAddress,
                $data['email_conseiller2'],
                null,
                $contentType,
                'UTF-8'
            );

            // If Email success sent
            if ($nbSent && $historique) {
                $this->saveHistoriqueEmail(
                    $historique,
                    $body,
                    $fromMailerAddress,
                    $data['email_conseiller2'],
                    $data['subject_conseiller2'],
                    $contentType
                );
            }
        }

    }

    /**
     * @param $statutId
     * @param $demandeId
     * @param $demandeType
     * @param $beneficiaireType
     * @param $documentJPAlt
     * @param $documentKBISAlt
     * @param $documentAIAlt
     * @param $options
     * @return array
     */
    private function findDataForDemandeEmail(
        $statutId,
        $demandeId,
        $demandeType,
        $beneficiaireType,
        $documentJPAlt = null,
        $documentKBISAlt = null,
        $documentAIAlt = null,
        $options = []
    ) {
        /**
         * @var Demande_ $demande
         */
        $demande = $this->repo_demande->find($demandeId);
        $demandeTypeLabel = Demande_::$demandeType[$demandeType];
        $data = array(
            'subject'       => null,
            'templatePath'  => null,
            'templateData'  => [
                'demandeId'        => $demandeId,
                'demandeTypeLabel' => $demandeTypeLabel
            ]
        );
        $dataDocumentManquant = array();
        $dataForEmail = array();

        /* /////////////////////////////////////////////////////////////////
                            INITIATION LIST DOC MANQUANT
        ///////////////////////////////////////////////////////////////// */
        // Pour document manquant
        $statutIncompletAuditE = array(
            Demande_statut::STATUS_1,
            Demande_statut::STATUS_3
        );
        $statutIncompletTravaux = array(
            Demande_statut::STATUS_3,
            Demande_statut::STATUS_18,
            Demande_statut::STATUS_20,
            Demande_statut::STATUS_23,
            Demande_statut::STATUS_25
        );

        if (
            ($demandeType == Demande_::DEMANDE_AUDIT_ENERGIE_TYPE && in_array($statutId, $statutIncompletAuditE))
            || ($demandeType == Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE && in_array($statutId, $statutIncompletAuditE))
            || ($demandeType == Demande_::DEMANDE_TRAVAUX_TYPE && in_array($statutId, $statutIncompletTravaux))
        ) {
            $dataDocumentManquant = $this->demandeService->findDocumentManquant(
                $demandeType,
                $beneficiaireType,
                $documentJPAlt,
                $documentKBISAlt,
                $documentAIAlt
            );
        }

        /* /////////////////////////////////////////////////////////////////
                            INIT LIST NON CONFORMITE
        ///////////////////////////////////////////////////////////////// */
        $statutNonConforme = [
            Demande_statut::STATUS_6,
            Demande_statut::STATUS_9,
            Demande_statut::STATUS_27,
            Demande_statut::STATUS_30,
            Demande_statut::STATUS_33,
            Demande_statut::STATUS_36,
            Demande_statut::STATUS_41,
            Demande_statut::STATUS_46
        ];
        if (in_array($demandeType, [
                Demande_::DEMANDE_AUDIT_ENERGIE_TYPE,
                Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE,
                Demande_::DEMANDE_TRAVAUX_TYPE
            ])
            && in_array($statutId, $statutNonConforme)
            && !empty($options['isFromInstructionAdministrative'])
        ) {
            $dataDocumentNonConforme = $this->demandeService->findDataTagNonConformeByDemande($demandeId);
        }

        /* /////////////////////////////////////////////////////////////////
                        INITIATION DATA FOR AUDITEUR EMAIL
        ///////////////////////////////////////////////////////////////// */
        $statutAuditeurEmailAuditE = array(Demande_statut::STATUS_12);
        $statutAuditeurEmailAuditN = array(Demande_statut::STATUS_12);
        $statutAuditeurEmailTravaux = array(
            Demande_statut::STATUS_38,
            Demande_statut::STATUS_39,
            Demande_statut::STATUS_40,
            Demande_statut::STATUS_41,
            Demande_statut::STATUS_42
        );

        /* /////////////////////////////////////////////////////////////////
                        INITIATION DATA FOR RENOVATEUR EMAIL
        ///////////////////////////////////////////////////////////////// */
        $statutRenovateurEmailTravaux = array(
            Demande_statut::STATUS_38,
            Demande_statut::STATUS_39,
            Demande_statut::STATUS_40,
            Demande_statut::STATUS_41,
            Demande_statut::STATUS_42
        );
        $isEmailDemandeSelectionRenovateur = $this->demandeService->isEmailDemandeSelectionRenovateur(
            $demandeId,
            $demandeType,
            $statutId,
            $statutRenovateurEmailTravaux
        );

        /* /////////////////////////////////////////////////////////////////
                        INITIATION DATA FOR CONSEILLER EMAIL
        ///////////////////////////////////////////////////////////////// */
        $arrayStatutConseillerEmailTravaux = [
            Demande_statut::STATUS_20,
            Demande_statut::STATUS_21,
            Demande_statut::STATUS_22,
            Demande_statut::STATUS_23,
            Demande_statut::STATUS_29,
            Demande_statut::STATUS_30,
            Demande_statut::STATUS_31,
            Demande_statut::STATUS_32,
            Demande_statut::STATUS_33,
            Demande_statut::STATUS_34
        ];

        if (
            ($demandeType == Demande_::DEMANDE_AUDIT_ENERGIE_TYPE && in_array($statutId, $statutAuditeurEmailAuditE))
            || ($demandeType == Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE && in_array($statutId, $statutAuditeurEmailAuditE))
            || ($demandeType == Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE && in_array($statutId, $statutAuditeurEmailAuditN))
            || ($demandeType == Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE && in_array($statutId, $statutAuditeurEmailAuditN))
            || ($demandeType == Demande_::DEMANDE_TRAVAUX_TYPE && in_array($statutId, $statutAuditeurEmailTravaux))
            || ($demandeType == Demande_::DEMANDE_TRAVAUX_TYPE && in_array($statutId, $arrayStatutConseillerEmailTravaux))
            || $isEmailDemandeSelectionRenovateur
            || (in_array($demandeType, [
                    Demande_::DEMANDE_AUDIT_ENERGIE_TYPE,
                    Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE,
                    Demande_::DEMANDE_TRAVAUX_TYPE
                ])
                && in_array($statutId, $statutNonConforme)
                && !empty($options['isFromInstructionAdministrative'])
            )
            || (Demande_statut::STATUS_15 == $statutId)
        ) {
            $dataForEmail = $this->repo_demande->findDataForEmail($demandeId);
        }

        if (Demande_statut::STATUS_11 == $statutId) {
            $dateCPFormatted = $this->demandeService->getEnabledDemandeDateCP($demande);
        } elseif (Demande_statut::STATUS_15 == $statutId) {
            $demandeMotifRefus = $demande->getMotifRefus();
        }

        if ($demandeType == Demande_::DEMANDE_AUDIT_ENERGIE_TYPE
            || $demandeType == Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE
        ) {

            $templateDirName =  self::BASE_TEMPLATE_PATH_DEMANDE . 'AuditEnergie/email';

            switch ($statutId) {
                /*  DEMANDE COMPLETE  */
                case Demande_statut::STATUS_2:
                case Demande_statut::STATUS_4:
                    $data['subject'] = 'Région Normandie - Demande de "Chèque éco-énergie / Audit" enregistrée';
                    $data['templatePath'] = $templateDirName . '/demande_complete.html.twig';
                    if ($statutId == Demande_statut::STATUS_2) {
                        $data['templateData'] = array_merge(
                            $data['templateData'],
                            array('auditeur' => 1)
                        );
                    }
                    break;

                /*  DEMANDE INCOMPLETE  */
                case Demande_statut::STATUS_1:
                case Demande_statut::STATUS_3:
                    $data['subject'] = 'Région Normandie - Demande de "Chèque éco-énergie / Audit" à compléter';
                    $data['templatePath'] = $templateDirName . '/demande_incomplete.html.twig';
                    if ($statutId == Demande_statut::STATUS_1) {
                        $data['templateData'] = array_merge(
                            $data['templateData'],
                            array(
                                'auditeur'          => 1,
                                'listDocManquant'   => $dataDocumentManquant
                            )
                        );
                    } else {
                        $data['templateData'] = array_merge(
                            $data['templateData'],
                            array('listDocManquant' => $dataDocumentManquant)
                        );
                    }
                    break;

                /*  INSTRUCTION PRESTA UP */
                case Demande_statut::STATUS_5:
                    $data['subject'] = 'Région Normandie - Demande de "Chèque éco-énergie / Audit" - Auditeur à sélectionner';
                    $data['templatePath'] = $templateDirName . '/instruction_prestataire.html.twig';
                    $data['templateData'] = array_merge(
                        $data['templateData'],
                        array('auditeur' => 1)
                    );
                    break;

                /*  ATTENTE CP */
                case Demande_statut::STATUS_11:
                    // Pour beneficiaire
                    $data['subject'] = 'Région Normandie - Demande de "Chèque éco-énergie / Audit" en attente commission';
                    $data['templatePath'] = $templateDirName . '/attente-cp-beneficiaire.html.twig';
                    $data['templateData'] = array_merge(
                        $data['templateData'],
                        ['dateCPFormatted' => $dateCPFormatted]
                    );
                    break;

                /*  APPROBATION PAR LA CP */
                case Demande_statut::STATUS_12:
                    // Pour beneficiaire
                    $data['subject'] = 'Région Normandie - Demande de "Chèque éco-énergie / Audit" acceptée';
                    $data['templatePath'] = $templateDirName . '/approbation-cp-beneficiaire.html.twig';

                    if ($dataForEmail['auditeurEmailAE']) {
                        $data['email_auditeur'] = $dataForEmail['auditeurEmailAE'];
                        $data['subject_auditeur'] = '"Chèque éco-énergie / Audit" accepté pour ' . $dataForEmail['beneficiaireCivilite'] .
                            ' ' . $dataForEmail['beneficiaireNom'] . '/' . $dataForEmail['logementVille'] . '/' . $demandeId;
                        $data['templatePath_auditeur'] = $templateDirName . '/approbation-cp-auditeur.html.twig';
                        $data['templateData_auditeur'] = array(
                            'demandeId'             => $demandeId,
                            'civilite'              => $dataForEmail['beneficiaireCivilite'],
                            'beneficiairePrenom'    => $dataForEmail['beneficiairePrenom'],
                            'beneficiaireNom'       => $dataForEmail['beneficiaireNom']
                        );
                    }
                    break;

                /*  DEMANDE NON CONFORME */
                case Demande_statut::STATUS_6:
                case Demande_statut::STATUS_9:
                case Demande_statut::STATUS_27:
                case Demande_statut::STATUS_30:
                case Demande_statut::STATUS_33:
                case Demande_statut::STATUS_36:
                case Demande_statut::STATUS_41:
                case Demande_statut::STATUS_46:
                    if (!empty($dataForEmail['conseillerEmailAE']) && !empty($options['isFromInstructionAdministrative'])) {
                        $data['email_conseiller'] = $dataForEmail['conseillerEmailAE'];
                        $data['subject_conseiller'] = '"Chèque éco-énergie / Audit" - Demande non conforme - Dossier N°' . $demandeId  .' - ' . $dataForEmail['beneficiaireCivilite'] .
                            ' ' . $dataForEmail['beneficiaireNom'] . '/' . $dataForEmail['logementVille'];

                        $data['templatePath_conseiller'] = $templateDirName . '/demande_non_conforme.html.twig';
                        $data['templateData_conseiller'] = [
                            'demandeId'               => $demandeId,
                            'civilite'                => $dataForEmail['beneficiaireCivilite'],
                            'beneficiairePrenom'      => $dataForEmail['beneficiairePrenom'],
                            'beneficiaireNom'         => $dataForEmail['beneficiaireNom'],
                            'dataDocumentNonConforme' => $dataDocumentNonConforme
                        ];
                    }
                    break;

                /*  DEMANDE REFUSEE */
                case Demande_statut::STATUS_15:
                    $data['subject'] = 'Région Normandie - Demande de "Chèque éco-énergie / Audit" refusée';
                    $data['templatePath'] = $templateDirName . '/demande_refusee.html.twig';
                    $data['templateData']['demandeMotifRefus'] = $demandeMotifRefus;

                    if (!empty($dataForEmail['conseillerEmailAE'])) {
                        $data['email_conseiller'] = $dataForEmail['conseillerEmailAE'];
                        $data['subject_conseiller'] = 'Région Normandie - Demande de "Chèque éco-énergie / Audit" refusée - Dossier N°' . $demandeId  .' - ' . $dataForEmail['beneficiaireCivilite'] .
                            ' ' . $dataForEmail['beneficiaireNom'] . '/' . $dataForEmail['logementVille'];
                        $data['templatePath_conseiller'] = $templateDirName . '/demande_refusee.html.twig';
                        $data['templateData_conseiller'] = [
                            'demandeId'         => $demandeId,
                            'demandeTypeLabel'  => $demandeTypeLabel,
                            'demandeMotifRefus' => $demandeMotifRefus
                        ];
                    }
                    break;

                default:
                    break;
            }

        } elseif ($demandeType == Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE
            || $demandeType == Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE
        ) {

            $templateDirName =  self::BASE_TEMPLATE_PATH_DEMANDE . 'AuditNumerique/email';
            $demandeTypeLabel = Demande_::$demandeType[$demandeType];

            switch ($statutId) {
                /*  ATTENTE CP */
                case Demande_statut::STATUS_11:
                    // Pour beneficiaire
                    $data['subject'] = 'Région Normandie - Demande de "Chèque éco-énergie / ' . $demandeTypeLabel . '" en attente commission';
                    $data['templatePath'] = $templateDirName . '/attente-cp-beneficiaire.html.twig';
                    $data['templateData'] = array_merge(
                        $data['templateData'],
                        ['dateCPFormatted' => $dateCPFormatted]
                    );
                    break;

                // APPROBATION PAR LA CP
                case Demande_statut::STATUS_12:
                    // Pour beneficiaire
                    $data['subject'] = 'Région Normandie - Demande de "Chèque éco-énergie / ' . $demandeTypeLabel . '" acceptée';
                    $data['templatePath'] = $templateDirName . '/approbation-cp-beneficiaire.html.twig';

                    if ($dataForEmail['auditeurEmailAN']) {
                        $data['email_auditeur'] = $dataForEmail['auditeurEmailAN'];
                        $data['subject_auditeur'] = '"Chèque éco-énergie / ' . $demandeTypeLabel . '" accepté pour ' . $dataForEmail['beneficiaireCivilite'] .
                            ' ' . $dataForEmail['beneficiaireNom'] . '/' . $dataForEmail['logementVille'] . '/' . $demandeId;
                        $data['templatePath_auditeur'] = $templateDirName . '/approbation-cp-auditeur.html.twig';
                        $data['templateData_auditeur'] = [
                            'demandeId'          => $demandeId,
                            'demandeTypeLabel'   => $demandeTypeLabel,
                            'civilite'           => $dataForEmail['beneficiaireCivilite'],
                            'beneficiairePrenom' => $dataForEmail['beneficiairePrenom'],
                            'beneficiaireNom'    => $dataForEmail['beneficiaireNom']
                        ];
                    }
                    break;

                /*  DEMANDE REFUSEE */
                case Demande_statut::STATUS_15:
                    $data['subject'] = 'Région Normandie - Demande de "Chèque éco-énergie / ' . $demandeTypeLabel . '" refusée';
                    $data['templatePath'] = $templateDirName . '/demande_refusee.html.twig';
                    $data['templateData']['demandeMotifRefus'] = $demandeMotifRefus;

                    if (!empty($dataForEmail['conseillerEmailAN'])) {
                        $data['email_conseiller'] = $dataForEmail['conseillerEmailAN'];
                        $data['subject_conseiller'] = 'Région Normandie - Demande de "Chèque éco-énergie / ' . $demandeTypeLabel . '" refusée - Dossier N°' . $demandeId  .' - ' . $dataForEmail['beneficiaireCivilite'] .
                            ' ' . $dataForEmail['beneficiaireNom'] . '/' . $dataForEmail['logementVille'];
                        $data['templatePath_conseiller'] = $templateDirName . '/demande_refusee.html.twig';
                        $data['templateData_conseiller'] = [
                            'demandeId'         => $demandeId,
                            'demandeTypeLabel'  => $demandeTypeLabel,
                            'demandeMotifRefus' => $demandeMotifRefus
                        ];
                    }
                    break;

                //  DEMANDE COMPLETE
                default :
                    $templateDirName =  self::BASE_TEMPLATE_PATH_DEMANDE . 'AuditNumerique/email';
                    $data['subject'] = 'Région Normandie - Demande de "Chèque éco-énergie / ' . $demandeTypeLabel . '" enregistrée';
                    $data['templatePath'] = $templateDirName . '/demande_complete.html.twig';
                    break;
            }

        }  elseif ($demandeType == Demande_::DEMANDE_TRAVAUX_TYPE) {

            $templateDirName =  self::BASE_TEMPLATE_PATH_DEMANDE . 'Travaux/email';

            // CAS  DEMANDE INCOMPLETE
            if (in_array($statutId, $statutIncompletTravaux)) {
                $data['subject'] = 'Région Normandie - Demande de "Chèque éco-énergie / Travaux" à compléter';
                $data['templatePath'] = $templateDirName . '/demande_incomplete.html.twig';
                $data['templateData'] = array_merge(
                    $data['templateData'],
                    ['listDocManquant' => $dataDocumentManquant]
                );
            }

            switch ($statutId) {
                // CAS DEMANDE COMPLETE
                case Demande_statut::STATUS_19:
                    $data['subject'] = 'Région Normandie - Demande de "Chèque éco-énergie / Travaux" enregistrée';
                    $data['templatePath'] = $templateDirName . '/demande_complete.html.twig';
                    break;

                /*  ATTENTE CP */
                case Demande_statut::STATUS_11:
                    // Pour beneficiaire
                    $data['subject'] = 'Région Normandie - Demande de "Chèque éco-énergie / Travaux" en attente commission';
                    $data['templatePath'] = $templateDirName . '/attente-cp-beneficiaire.html.twig';
                    $data['templateData'] = array_merge(
                        $data['templateData'],
                        ['dateCPFormatted' => $dateCPFormatted]
                    );
                    break;

                //  APPROBATION PAR LA CP
                case Demande_statut::STATUS_12:
                    $data['subject'] = 'Région Normandie - Demande de "Chèque éco-énergie / Travaux" acceptée';
                    $data['templatePath'] = $templateDirName . '/approbation-cp-beneficiaire.html.twig';
                    break;

                /*  DEMANDE REFUSEE */
                case Demande_statut::STATUS_15:
                    $data['subject'] = 'Région Normandie - Demande de "Chèque éco-énergie / Travaux" refusée';
                    $data['templatePath'] = $templateDirName . '/demande_refusee.html.twig';
                    $data['templateData']['demandeMotifRefus'] = $demandeMotifRefus;

                    if (!empty($dataForEmail['conseillerEmailTravaux'])) {
                        $data['email_conseiller'] = $dataForEmail['conseillerEmailTravaux'];
                        $data['subject_conseiller'] = 'Région Normandie - Demande de "Chèque éco-énergie / Travaux" refusée - Dossier N°' . $demandeId  .' - ' . $dataForEmail['beneficiaireCivilite'] .
                            ' ' . $dataForEmail['beneficiaireNom'] . '/' . $dataForEmail['logementVille'];
                        $data['templatePath_conseiller'] = $templateDirName . '/demande_refusee.html.twig';
                        $data['templateData_conseiller'] = [
                            'demandeId'         => $demandeId,
                            'demandeTypeLabel'  => $demandeTypeLabel,
                            'demandeMotifRefus' => $demandeMotifRefus
                        ];
                    }
                    break;

                // DEMANDE EN ATTENTE SAISIE TECHNIQUE CONSEILLER
                case Demande_statut::STATUS_20:
                case Demande_statut::STATUS_21:
                case Demande_statut::STATUS_29:
                case Demande_statut::STATUS_30:
                case Demande_statut::STATUS_31:
                    if ($dataForEmail['conseillerEmailTravaux']) {

                        $data['email_conseiller'] = $dataForEmail['conseillerEmailTravaux'];
                        $data['subject_conseiller'] = '"Chèque éco-énergie / Travaux" - Demande en attente saisie technique Conseiller - Dossier N°' . $demandeId  .' - ' . $dataForEmail['beneficiaireCivilite'] .
                            ' ' . $dataForEmail['beneficiaireNom'] . '/' . $dataForEmail['logementVille'];

                        $data['templatePath_conseiller'] = $templateDirName . '/attente-saisie-technique-conseiller.html.twig';
                        $data['templateData_conseiller'] = [
                            'demandeId'          => $demandeId,
                            'civilite'           => $dataForEmail['beneficiaireCivilite'],
                            'beneficiairePrenom' => $dataForEmail['beneficiairePrenom'],
                            'beneficiaireNom'    => $dataForEmail['beneficiaireNom']
                        ];

                        if (Demande_statut::STATUS_30 == $statutId && !empty($options['isFromInstructionAdministrative'])) {
                            // Un autre email à envoyer pour la non conformité
                            $data['email_conseiller2'] = $dataForEmail['conseillerEmailTravaux'];
                            $data['subject_conseiller2'] = '"Chèque éco-énergie / Travaux" - Demande non conforme - Dossier N°' . $demandeId  .' - ' . $dataForEmail['beneficiaireCivilite'] .
                                ' ' . $dataForEmail['beneficiaireNom'] . '/' . $dataForEmail['logementVille'];

                            $data['templatePath_conseiller2'] = $templateDirName . '/demande_non_conforme.html.twig';
                            $data['templateData_conseiller2'] = [
                                'demandeId'               => $demandeId,
                                'civilite'                => $dataForEmail['beneficiaireCivilite'],
                                'beneficiairePrenom'      => $dataForEmail['beneficiairePrenom'],
                                'beneficiaireNom'         => $dataForEmail['beneficiaireNom'],
                                'dataDocumentNonConforme' => $dataDocumentNonConforme
                            ];
                        }
                    }
                    break;

                // DEMANDE EN ATTENTE VALIDATION CONSEILLER
                case Demande_statut::STATUS_22:
                case Demande_statut::STATUS_23:
                case Demande_statut::STATUS_32:
                case Demande_statut::STATUS_33:
                case Demande_statut::STATUS_34:
                    if ($dataForEmail['conseillerEmailTravaux']) {

                        $data['email_conseiller'] = $dataForEmail['conseillerEmailTravaux'];
                        $data['subject_conseiller'] = '"Chèque éco-énergie / Travaux" - Demande en attente Validation Conseiller - Dossier N°' . $demandeId  .' - ' . $dataForEmail['beneficiaireCivilite'] .
                            ' ' . $dataForEmail['beneficiaireNom'] . '/' . $dataForEmail['logementVille'];

                        $data['templatePath_conseiller'] = $templateDirName . '/attente-validation-conseiller.html.twig';
                        $data['templateData_conseiller'] = [
                            'demandeId'          => $demandeId,
                            'civilite'           => $dataForEmail['beneficiaireCivilite'],
                            'beneficiairePrenom' => $dataForEmail['beneficiairePrenom'],
                            'beneficiaireNom'    => $dataForEmail['beneficiaireNom']
                        ];

                        if (Demande_statut::STATUS_33 == $statutId && !empty($options['isFromInstructionAdministrative'])) {
                            // Un autre email à envoyer pour la non conformité
                            $data['email_conseiller2'] = $dataForEmail['conseillerEmailTravaux'];
                            $data['subject_conseiller2'] = '"Chèque éco-énergie / Travaux" - Demande non conforme - Dossier N°' . $demandeId  .' - ' . $dataForEmail['beneficiaireCivilite'] .
                                ' ' . $dataForEmail['beneficiaireNom'] . '/' . $dataForEmail['logementVille'];

                            $data['templatePath_conseiller2'] = $templateDirName . '/demande_non_conforme.html.twig';
                            $data['templateData_conseiller2'] = [
                                'demandeId'               => $demandeId,
                                'civilite'                => $dataForEmail['beneficiaireCivilite'],
                                'beneficiairePrenom'      => $dataForEmail['beneficiairePrenom'],
                                'beneficiaireNom'         => $dataForEmail['beneficiaireNom'],
                                'dataDocumentNonConforme' => $dataDocumentNonConforme
                            ];
                        }
                    }
                    break;

                //  En attente saisie technique auditeur
                case Demande_statut::STATUS_38:
                case Demande_statut::STATUS_39:
                case Demande_statut::STATUS_40:
                case Demande_statut::STATUS_41:
                case Demande_statut::STATUS_42:
                    // Envoi email au renovateur
                    if ($dataForEmail['renovateurEmailTravaux'] && $isEmailDemandeSelectionRenovateur) {
                        $data['email_renovateur'] = $dataForEmail['renovateurEmailTravaux'];
                        $data['subject_renovateur'] = 'Région Normandie - "Chèque éco-énergie" – Nouveau projet en tant que Rénovateur BBC';
                        $data['templatePath_renovateur'] = $templateDirName . '/demande_selection_renovateur.html.twig';
                        $adresseTravaux = $dataForEmail['logementNumeroRue'] . (($dataForEmail['logementComplementRue']) ? ' ' . $dataForEmail['logementComplementRue'] : '');
                        $adresseTravaux .= ' ' . $dataForEmail['logementAdresse'] . ' ' . $dataForEmail['logementCodePostal'] . ' ' . $dataForEmail['logementVille'];

                        $data['templateData_renovateur'] = [
                            'demandeId'          => $demandeId,
                            'civilite'           => $dataForEmail['beneficiaireCivilite'],
                            'beneficiairePrenom' => $dataForEmail['beneficiairePrenom'],
                            'beneficiaireNom'    => $dataForEmail['beneficiaireNom'],
                            'adresseTravaux'     => $adresseTravaux
                        ];
                    }

                    // Envoi email à l'auditeur
                    if ($dataForEmail['auditeurEmailTravaux']) {
                        $renovateurNomProfessionnel = '';
                        $data['email_auditeur'] = $dataForEmail['auditeurEmailTravaux'];
                        $data['subject_auditeur'] = '"Chèque éco-énergie / Travaux" dossier N°' . $demandeId  .' - ' . $dataForEmail['beneficiaireCivilite'] .
                            ' ' . $dataForEmail['beneficiaireNom'] . '/' . $dataForEmail['logementVille'] . ' - Fiche technique à compléter sur l\'extranet';

                        if ($dataForEmail['renovateurEmailTravaux'] && $isEmailDemandeSelectionRenovateur) {
                            $data['templatePath_auditeur'] = $templateDirName . '/fiche-technique-auditeur-avec-renovateur.html.twig';
                            $data['subject_auditeur'] .= ' et prise de contact avec le Rénovateur BBC';
                            $renovateurNomProfessionnel = !empty($dataForEmail['renovateurRaisonSocialeTravaux']) ? $dataForEmail['renovateurRaisonSocialeTravaux'] : '';
                        } else {
                            $data['templatePath_auditeur'] = $templateDirName . '/fiche-technique-auditeur-sans-renovateur.html.twig';
                        }

                        $adresseTravaux = $dataForEmail['logementNumeroRue'] . (($dataForEmail['logementComplementRue']) ? ' ' . $dataForEmail['logementComplementRue'] : '');
                        $adresseTravaux .= ' ' . $dataForEmail['logementAdresse'] . ' ' . $dataForEmail['logementCodePostal'] . ' ' . $dataForEmail['logementVille'];

                        $data['templateData_auditeur'] = [
                            'demandeId'                  => $demandeId,
                            'civilite'                   => $dataForEmail['beneficiaireCivilite'],
                            'beneficiairePrenom'         => $dataForEmail['beneficiairePrenom'],
                            'beneficiaireNom'            => $dataForEmail['beneficiaireNom'],
                            'adresseTravaux'             => $adresseTravaux,
                            'renovateurNomProfessionnel' => $renovateurNomProfessionnel
                        ];
                    }

                    if (!empty($dataForEmail['conseillerEmailTravaux'])
                        && Demande_statut::STATUS_41 == $statutId
                        && !empty($options['isFromInstructionAdministrative'])
                    ) {

                        $data['email_conseiller'] = $dataForEmail['conseillerEmailTravaux'];
                        $data['subject_conseiller'] = '"Chèque éco-énergie / Travaux" - Demande non conforme - Dossier N°' . $demandeId  .' - ' . $dataForEmail['beneficiaireCivilite'] .
                            ' ' . $dataForEmail['beneficiaireNom'] . '/' . $dataForEmail['logementVille'];

                        $data['templatePath_conseiller'] = $templateDirName . '/demande_non_conforme.html.twig';
                        $data['templateData_conseiller'] = [
                            'demandeId'               => $demandeId,
                            'civilite'                => $dataForEmail['beneficiaireCivilite'],
                            'beneficiairePrenom'      => $dataForEmail['beneficiairePrenom'],
                            'beneficiaireNom'         => $dataForEmail['beneficiaireNom'],
                            'dataDocumentNonConforme' => $dataDocumentNonConforme
                        ];
                    }
                    break;

                /*  DEMANDE NON CONFORME (complement) */
                case Demande_statut::STATUS_6:
                case Demande_statut::STATUS_9:
                case Demande_statut::STATUS_27:
                case Demande_statut::STATUS_36:
                case Demande_statut::STATUS_46:
                    if (!empty($dataForEmail['conseillerEmailTravaux']) && !empty($options['isFromInstructionAdministrative'])) {
                        $data['email_conseiller'] = $dataForEmail['conseillerEmailTravaux'];
                        $data['subject_conseiller'] = '"Chèque éco-énergie / Travaux" - Demande non conforme - Dossier N°' . $demandeId  .' - ' . $dataForEmail['beneficiaireCivilite'] .
                            ' ' . $dataForEmail['beneficiaireNom'] . '/' . $dataForEmail['logementVille'];

                        $data['templatePath_conseiller'] = $templateDirName . '/demande_non_conforme.html.twig';
                        $data['templateData_conseiller'] = [
                            'demandeId'               => $demandeId,
                            'civilite'                => $dataForEmail['beneficiaireCivilite'],
                            'beneficiairePrenom'      => $dataForEmail['beneficiairePrenom'],
                            'beneficiaireNom'         => $dataForEmail['beneficiaireNom'],
                            'dataDocumentNonConforme' => $dataDocumentNonConforme
                        ];
                    }
                    break;

                default:
                    break;
            }
        }

        return $data;
    }

    /**
     * @param $statutId
     * @param $demandeId
     * @param $demandeType
     * @param $ribAlt
     * @param $factureAlt
     * @param $rectoChequeAlt
     * @param $versoChequeAlt
     * @param $ficheTravauxAlt
     * @param $isBBC1
     * @param string|null $dateEmissionTitre
     * @param $remboursementId
     * @return array
     * @throws Exception
     */
    private function findDataForRemboursementEmail(
        $statutId,
        $demandeId,
        $demandeType,
        $ribAlt = null,
        $factureAlt = null,
        $rectoChequeAlt = null,
        $versoChequeAlt = null,
        $ficheTravauxAlt = null,
        $isBBC1 = null,
        string $dateEmissionTitre = null,
        $remboursementId = null
    ) {
        $demandeTypeLabel = Demande_::$demandeType[$demandeType];
        $data = array(
            'subject'      => null,
            'templatePath' => null,
            'templateData' => [
                'demandeId'        => $demandeId,
                'demandeTypeLabel' => $demandeTypeLabel
            ]
        );
        $dataDocumentManquant = array();
        $dataForEmail = array();

        /* /////////////////////////////////////////////////////////////////
                            INITIATION LIST DOC MANQUANT
        ///////////////////////////////////////////////////////////////// */
        // Pour document manquant
        $statutIncompletAuditE = array(
            Remboursement_statut::STATUS_2,
            Remboursement_statut::STATUS_6,
            Remboursement_statut::STATUS_10,
            Remboursement_statut::STATUS_16,
            Remboursement_statut::STATUS_17
        );
        $statutIncompletAuditN = array(
            Remboursement_statut::STATUS_16,
            Remboursement_statut::STATUS_24
        );
        $statutIncompletTravaux = array(
            Remboursement_statut::STATUS_16
        );

        $statutAttenteFinChantier = array(
            Remboursement_statut::STATUS_27,
            Remboursement_statut::STATUS_28,
            Remboursement_statut::STATUS_29,
            Remboursement_statut::STATUS_30
        );

        $isStatutIncomplet = false;
        if (
            in_array($statutId, $statutIncompletAuditE)
            || in_array($statutId, $statutIncompletAuditN)
            || in_array($statutId, $statutIncompletTravaux)
        ) {
            $dataDocumentManquant = $this->remboursementService->findDocumentManquant(
                $ribAlt,
                $factureAlt,
                $rectoChequeAlt,
                $versoChequeAlt,
                $ficheTravauxAlt,
                $isBBC1
            );
            $isStatutIncomplet = true;
        }

        $arrayStatutRemboursementAttenteValidationConseiller = [
            Remboursement_statut::STATUS_8,
            Remboursement_statut::STATUS_9,
            Remboursement_statut::STATUS_10,
            Remboursement_statut::STATUS_11
        ];

        if (
            $isStatutIncomplet
            || ($statutId == Remboursement_statut::STATUS_22)
            || in_array($statutId, $statutAttenteFinChantier)
            || in_array($statutId, $arrayStatutRemboursementAttenteValidationConseiller)
            || (Remboursement_statut::STATUS_20 == $statutId)
        ) {
            /* /////////////////////////////////////////////////////////////////
                INITIATION AUDITEUR, BENEFICIAIRE AND LOGEMENT DATA FOR EMAIL
            ///////////////////////////////////////////////////////////////// */
            $dataForEmail = $this->repo_demande->findDataForEmail($demandeId);
        }

        if (Remboursement_statut::STATUS_20 == $statutId) {
            /**
             * @var Remboursement_ $remboursement
             */
            $remboursement = $this->remboursementRepository->find($remboursementId);
            $remboursementMotifRefus = $remboursement->getMotifRefus();
        }

        if (Demande_::DEMANDE_AUDIT_ENERGIE_TYPE == $demandeType
            || Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE == $demandeType
        ) {

            $templateDirName =  self::BASE_TEMPLATE_PATH_REMBOURSEMENT . 'AuditEnergie/email';

            /* CAS  REMBOURSEMENT INCOMPLET */
            if (in_array($statutId, [
                Remboursement_statut::STATUS_2,
                Remboursement_statut::STATUS_6,
                Remboursement_statut::STATUS_10,
                Remboursement_statut::STATUS_16,
                Remboursement_statut::STATUS_17
            ])) {
                if ($dataForEmail['auditeurEmailAE']) {

                    $data['email_auditeur'] = $dataForEmail['auditeurEmailAE'];
                    $data['subject_auditeur'] = '"Chèque éco-énergie / Audit" - Demande de remboursement à compléter - dossier N°' . $demandeId  .' - ' . $dataForEmail['beneficiaireCivilite'] .
                        ' ' . $dataForEmail['beneficiaireNom'] . '/' . $dataForEmail['logementVille'];

                    $data['templatePath_auditeur'] = $templateDirName . '/remboursement_incomplet.html.twig';
                    $data['templateData_auditeur'] = array(
                        'demandeId'             => $demandeId,
                        'civilite'              => $dataForEmail['beneficiaireCivilite'],
                        'beneficiairePrenom'    => $dataForEmail['beneficiairePrenom'],
                        'beneficiaireNom'       => $dataForEmail['beneficiaireNom'],
                        'listDocManquant'       => $dataDocumentManquant
                    );
                }
            }

            switch ($statutId) {
                // CAS   REMBOURSEMENT TERMINE (Remboursé)
                case Remboursement_statut::STATUS_22:
                    if ($dataForEmail['auditeurEmailAE']) {

                        $data['email_auditeur'] = $dataForEmail['auditeurEmailAE'];
                        $data['subject_auditeur'] = '"Chèque éco-énergie / Audit" remboursé - dossier N°' . $demandeId  .' - ' . $dataForEmail['beneficiaireCivilite'] .
                            ' ' . $dataForEmail['beneficiaireNom'] . '/' . $dataForEmail['logementVille'];

                        $data['templatePath_auditeur'] = $templateDirName . '/remboursement_rembourse.html.twig';
                        $data['templateData_auditeur'] = array(
                            'demandeId'             => $demandeId,
                            'civilite'              => $dataForEmail['beneficiaireCivilite'],
                            'beneficiairePrenom'    => $dataForEmail['beneficiairePrenom'],
                            'beneficiaireNom'       => $dataForEmail['beneficiaireNom']
                        );
                    }
                    break;

                /*  REMBOURSEMENT REFUSEE */
                case Remboursement_statut::STATUS_20:
                    $data['subject'] = 'Région Normandie - Remboursement "Chèque éco-énergie / Audit" refusée';
                    $data['templatePath'] = $templateDirName . '/remboursement_refusee.html.twig';
                    $data['templateData']['remboursementMotifRefus'] = $remboursementMotifRefus;

                    if (!empty($dataForEmail['conseillerEmailAE'])) {
                        $data['email_conseiller'] = $dataForEmail['conseillerEmailAE'];
                        $data['subject_conseiller'] = 'Région Normandie - Remboursement "Chèque éco-énergie / Audit" refusée - Dossier N°' . $demandeId  .' - ' . $dataForEmail['beneficiaireCivilite'] .
                            ' ' . $dataForEmail['beneficiaireNom'] . '/' . $dataForEmail['logementVille'];
                        $data['templatePath_conseiller'] = $templateDirName . '/remboursement_refusee.html.twig';
                        $data['templateData_conseiller'] = [
                            'demandeId'               => $demandeId,
                            'demandeTypeLabel'        => $demandeTypeLabel,
                            'remboursementMotifRefus' => $remboursementMotifRefus
                        ];
                    }
                    break;

                default:
                    break;
            }

        } elseif (
            Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE == $demandeType
            || Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE == $demandeType
        ) {

            $templateDirName =  self::BASE_TEMPLATE_PATH_REMBOURSEMENT . 'AuditNumerique/email';

            $demandeTypeLabelAvecPrefix = $demandeTypeLabel;
            if (Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE == $demandeType) {
                $demandeTypeLabelAvecPrefix = 'la ' .  $demandeTypeLabel;
            } elseif (Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE == $demandeType) {
                $demandeTypeLabelAvecPrefix = 'l\'' .  $demandeTypeLabel;
            }

            switch ($statutId) {
                // CAS  REMBOURSEMENT INCOMPLET
                case Remboursement_statut::STATUS_16:
                case Remboursement_statut::STATUS_24:
                    if ($dataForEmail['auditeurEmailAN']) {
                        $data['email_auditeur'] = $dataForEmail['auditeurEmailAN'];
                        $data['subject_auditeur'] = '"Chèque éco-énergie / '. $demandeTypeLabel . '" - Demande de remboursement à compléter - dossier N°' . $demandeId  .' - ' . $dataForEmail['beneficiaireCivilite'] .
                            ' ' . $dataForEmail['beneficiaireNom'] . '/' . $dataForEmail['logementVille'];

                        $data['templatePath_auditeur'] = $templateDirName . '/remboursement_incomplet.html.twig';
                        $data['templateData_auditeur'] = [
                            'demandeId'                  => $demandeId,
                            'demandeTypeLabelAvecPrefix' => $demandeTypeLabelAvecPrefix,
                            'civilite'                   => $dataForEmail['beneficiaireCivilite'],
                            'beneficiairePrenom'         => $dataForEmail['beneficiairePrenom'],
                            'beneficiaireNom'            => $dataForEmail['beneficiaireNom'],
                            'listDocManquant'            => $dataDocumentManquant
                        ];
                    }
                    break;

                // CAS   REMBOURSEMENT TERMINE (Remboursé)
                case Remboursement_statut::STATUS_22:
                    if ($dataForEmail['auditeurEmailAN']) {

                        $data['email_auditeur'] = $dataForEmail['auditeurEmailAN'];
                        $data['subject_auditeur'] = '"Chèque éco-énergie / '. $demandeTypeLabel . '" remboursé - dossier N°' . $demandeId  .' - ' . $dataForEmail['beneficiaireCivilite'] .
                            ' ' . $dataForEmail['beneficiaireNom'] . '/' . $dataForEmail['logementVille'];

                        $data['templatePath_auditeur'] = $templateDirName . '/remboursement_rembourse.html.twig';
                        $data['templateData_auditeur'] = [
                            'demandeId'                  => $demandeId,
                            'demandeTypeLabelAvecPrefix' => $demandeTypeLabelAvecPrefix,
                            'civilite'                   => $dataForEmail['beneficiaireCivilite'],
                            'beneficiairePrenom'         => $dataForEmail['beneficiairePrenom'],
                            'beneficiaireNom'            => $dataForEmail['beneficiaireNom']
                        ];
                    }
                    break;

                /*  REMBOURSEMENT REFUSEE */
                case Remboursement_statut::STATUS_20:
                    $data['subject'] = 'Région Normandie - Remboursement "Chèque éco-énergie / '. $demandeTypeLabel . '" refusée';
                    $data['templatePath'] = $templateDirName . '/remboursement_refusee.html.twig';
                    $data['templateData']['remboursementMotifRefus'] = $remboursementMotifRefus;

                    if (!empty($dataForEmail['conseillerEmailAN'])) {
                        $data['email_conseiller'] = $dataForEmail['conseillerEmailAN'];
                        $data['subject_conseiller'] = 'Région Normandie - Remboursement "Chèque éco-énergie / '. $demandeTypeLabel . '" refusée - Dossier N°' . $demandeId  .' - ' . $dataForEmail['beneficiaireCivilite'] .
                            ' ' . $dataForEmail['beneficiaireNom'] . '/' . $dataForEmail['logementVille'];
                        $data['templatePath_conseiller'] = $templateDirName . '/remboursement_refusee.html.twig';
                        $data['templateData_conseiller'] = [
                            'demandeId'               => $demandeId,
                            'demandeTypeLabel'        => $demandeTypeLabel,
                            'remboursementMotifRefus' => $remboursementMotifRefus
                        ];
                    }
                    break;

                default:
                    break;
            }

        } elseif (Demande_::DEMANDE_TRAVAUX_TYPE == $demandeType) {

            $templateDirName =  self::BASE_TEMPLATE_PATH_REMBOURSEMENT . 'Travaux/email';

            switch ($statutId) {
                // CAS  REMBOURSEMENT INCOMPLET
                case Remboursement_statut::STATUS_16:
                    $data['subject'] = '"Chèque éco-énergie / Travaux" - Demande de remboursement à compléter';
                    $data['templatePath'] = $templateDirName . '/remboursement_incomplet.html.twig';
                    $data['templateData'] = array(
                        'demandeId'         => $demandeId,
                        'listDocManquant'   => $dataDocumentManquant
                    );
                    break;

                // CAS   REMBOURSEMENT TERMINE (Remboursé)
                case Remboursement_statut::STATUS_22:
                    $data['templateData'] = [
                        'demandeId' => $demandeId
                    ];
                    $carnetInformationLogementUrl = '';
                    $carnetInformationLogementToken = $this->getCarnetInformationLogementTokenByDemande(
                        $demandeId,
                        $dataForEmail['demandeTravauxDevisNiveau']
                    );

                    if (!empty($carnetInformationLogementToken)) {
                        // POUR SAVOIR ENSUITE SI ON DOIT METTRE A JOUR LA DEMANDE (CARNET INFORMATION LOGEMENT) OU PAS
                        $data['carnetInformationLogementToken'] = $carnetInformationLogementToken;

                        $data['templateData']['carnetInformationLogementEmailRedirect'] = (!empty($this->parameterBag->get('app_is_https')) ? 'https://' : 'http://')
                            . $this->parameterBag->get('url_fo')
                            . $this->router->generate('carnet_information_logement_email_redirect', [
                                    'token' => $carnetInformationLogementToken,
                                    'demandeId' => $demandeId
                                ]
                            );

                        $data['templatePath'] = $templateDirName . '/remboursement_rembourse_clea.html.twig';
                    }

                    $data['subject'] = '"Chèque éco-énergie / Travaux" remboursé';
                    $data['templatePath'] = !empty($data['templatePath']) ? $data['templatePath'] : $templateDirName . '/remboursement_rembourse.html.twig';
                    break;

                // CAS Remboursement en attente des informations de fin de chantier
                case Remboursement_statut::STATUS_27:
                case Remboursement_statut::STATUS_28:
                case Remboursement_statut::STATUS_29:
                case Remboursement_statut::STATUS_30:
                    if ($dataForEmail['renovateurEmailTravaux']) {
                        $data['email_renovateur'] = $dataForEmail['renovateurEmailTravaux'];
                        $data['subject_renovateur'] = '"Chèque éco-énergie / Travaux" - Remboursement en attente des informations de fin de chantier - dossier N°' . $demandeId  .' - ' . $dataForEmail['beneficiaireCivilite'] .
                            ' ' . $dataForEmail['beneficiaireNom'] . '/' . $dataForEmail['logementVille'];
                        $data['templatePath_renovateur'] = $templateDirName . '/remboursement_attente_fin_chantier.html.twig';
                        $adresseTravaux = $dataForEmail['logementNumeroRue'] . (($dataForEmail['logementComplementRue']) ? ' ' . $dataForEmail['logementComplementRue'] : '');
                        $adresseTravaux .= ' ' . $dataForEmail['logementAdresse'] . ' ' . $dataForEmail['logementCodePostal'] . ' ' . $dataForEmail['logementVille'];

                        $data['templateData_renovateur'] = array(
                            'demandeId'             => $demandeId,
                            'civilite'              => $dataForEmail['beneficiaireCivilite'],
                            'beneficiairePrenom'    => $dataForEmail['beneficiairePrenom'],
                            'beneficiaireNom'       => $dataForEmail['beneficiaireNom'],
                            'adresseTravaux'        => $adresseTravaux
                        );
                    }
                    break;

                /*  REMBOURSEMENT REFUSEE */
                case Remboursement_statut::STATUS_20:
                    $data['subject'] = 'Région Normandie - Remboursement "Chèque éco-énergie / Travaux" refusée';
                    $data['templatePath'] = $templateDirName . '/remboursement_refusee.html.twig';
                    $data['templateData']['remboursementMotifRefus'] = $remboursementMotifRefus;

                    if (!empty($dataForEmail['conseillerEmailTravaux'])) {
                        $data['email_conseiller'] = $dataForEmail['conseillerEmailTravaux'];
                        $data['subject_conseiller'] = 'Région Normandie - Remboursement "Chèque éco-énergie / Travaux" refusée - Dossier N°' . $demandeId  .' - ' . $dataForEmail['beneficiaireCivilite'] .
                            ' ' . $dataForEmail['beneficiaireNom'] . '/' . $dataForEmail['logementVille'];
                        $data['templatePath_conseiller'] = $templateDirName . '/remboursement_refusee.html.twig';
                        $data['templateData_conseiller'] = [
                            'demandeId'               => $demandeId,
                            'demandeTypeLabel'        => $demandeTypeLabel,
                            'remboursementMotifRefus' => $remboursementMotifRefus
                        ];
                    }
                    break;

                default:
                    break;
            }
        }

        $isAdresseUp = false;
        if (!empty($dateEmissionTitre)) {
            // On compare les dates au format Y-m-d
            $isAdresseUp = ($dateEmissionTitre < $this->parameterBag->get('app_date_us_nouvel_instructeur'));
        }

        if (!empty($data['templateData'])) {
            $data['templateData']['isAdresseUp'] = $isAdresseUp;
        }
        if (!empty($data['templateData_auditeur'])) {
            $data['templateData_auditeur']['isAdresseUp'] = $isAdresseUp;
        }
        if (!empty($data['templateData_renovateur'])) {
            $data['templateData_renovateur']['isAdresseUp'] = $isAdresseUp;
        }

        return $data;
    }

    /**
     * @param $demandeId
     * @param $demandeTravauxDevisNiveau
     * @return string|null
     * @throws Exception
     */
    private function getCarnetInformationLogementTokenByDemande($demandeId, $demandeTravauxDevisNiveau)
    {
        $rowDemandeAndRemboursementTermine = $this->remboursementRepository->findByDemandeAndRemboursementTermine(
            $demandeId,
            $this->parameterBag->get('production_travauxNiveau_BBC2')
        );

        $carnetInformationLogementToken = null;

        $demandeTravauxDevisNiveauValueSecondItem = explode(' | ', $demandeTravauxDevisNiveau)[1];
        $toEncodeUrl = !empty($demandeTravauxDevisNiveauValueSecondItem) ? ($demandeTravauxDevisNiveauValueSecondItem . $demandeId) : null;

        if (!empty($rowDemandeAndRemboursementTermine) && !empty($toEncodeUrl)) {
            $carnetInformationLogementToken = hash('sha512', $toEncodeUrl);
        }

        return $carnetInformationLogementToken;
    }
}
