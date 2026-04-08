<?php

namespace App\Service;

use App\Entity\ANAHCritere;
use App\Entity\Beneficiaire;
use App\Entity\Demande_;
use App\Entity\Demande_statut;
use App\Entity\Demande_travaux_devis;
use App\Entity\EPCI_;
use App\Entity\Historique_;
use App\Entity\Orientation;
use App\Entity\Remboursement_;
use App\Entity\Structure_;
use App\Entity\User;
use App\Repository\BeneficiaireRepository;
use App\Repository\Demande_Repository;
use App\Repository\Demande_statutRepository;
use App\Repository\Demande_travaux_devisRepository;
use App\Repository\EPCI_Repository;
use App\Repository\Historique_Repository;
use App\Repository\OrientationRepository;
use App\Repository\Structure_Repository;
use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;


class DemandeServiceBO
{
    /**
     * @var EntityManagerInterface
     */
    private $EM;

    /**
     * @var HistoriqueService
     */
    private $historiqueService;

    /**
     * @var TokenStorageInterface
     */
    private $tokenStorage;

    /**
     * @var ANAHService
     */
    private $ANAHService;

    /**
     * @var ParameterBagInterface
     */
    private $parameterBag;

    /**
     * @var DemandeServiceFO
     */
    private $demandeServiceFO;

    /**
     * @var EPCI_Repository
     */
    private $repo_EPCI;

    /**
     * @var Historique_Repository
     */
    private $historiqueRepository;

    /**
     * @var OrientationRepository
     */
    private $repo_orientation;

    /**
     * @var Structure_Repository
     */
    private $repo_structure;

    /**
     * @var Demande_Repository
     */
    private $repo_demande;

    /**
     * @var Demande_statutRepository
     */
    private $demandeStatutRepository;

    /**
     * @var Demande_travaux_devisRepository
     */
    private $repo_demande_travaux_devis;

    /**
     * @var BeneficiaireRepository
     */
    private $repo_beneficiaire;

    public function __construct(
        EntityManagerInterface $em,
        TokenStorageInterface $tokenStorage,
        HistoriqueService $historiqueService,
        ANAHService $ANAHService,
        ParameterBagInterface $parameterBag
    ) {
        $this->EM = $em;
        $this->tokenStorage = $tokenStorage;
        $this->historiqueService = $historiqueService;
        $this->ANAHService = $ANAHService;
        $this->parameterBag = $parameterBag;
        $this->historiqueRepository = $this->EM->getRepository(Historique_::class);
        $this->repo_EPCI = $this->EM->getRepository(EPCI_::class);
        $this->repo_orientation = $this->EM->getRepository(Orientation::class);
        $this->repo_structure = $this->EM->getRepository(Structure_::class);
        $this->repo_demande = $this->EM->getRepository(Demande_::class);
        $this->demandeStatutRepository = $this->EM->getRepository(Demande_statut::class);
        $this->repo_demande_travaux_devis = $this->EM->getRepository(Demande_travaux_devis::class);
        $this->repo_beneficiaire = $this->EM->getRepository(Beneficiaire::class);
    }

    public function setDemandeServiceFO(DemandeServiceFO $demandeServiceFO): void
    {
        $this->demandeServiceFO = $demandeServiceFO;
    }

    /* *****************************************************************
    ********************************************************************
                    P U B L I C   F U N C T I O N
    ********************************************************************
    *******************************************************************/

    /**
     * @param $handle
     * @param $option
     * @param $whereFormFilter
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    public function fputTitleAndContentExport(
        &$handle,
        $option,
        $whereFormFilter
    ) {
        $roles = $option['roles'];

        $fieldsHeader = [
            'numeroDossier'                     => 'Numéro dossier',
            'thematique'                        => 'Thématique',
            'nom'                               => 'Nom',
            'prenom'                            => 'Prénom',
            'codePostalTravaux'                 => 'Code postal travaux',
            'villeTravaux'                      => 'Ville travaux',
            'EPCI'                              => 'EPCI',
            'departement'                       => 'Département',
            'dateDemande'                       => 'Date demande',
            'statut'                            => 'Statut demande',
            'dateInstructionDemande'            => 'Date Instruction demande d\'aide',
            'dateCommission'                    => 'Date commission',
            'statutRemboursement'               => 'Statut Remboursement',
            'remboursementDateInstruction'      => 'Date Instruction Remboursement',
            'remboursementDateInstructionSuppl' => 'Date Instruction Remboursement supplémentaire',
            'dateRMH'                           => 'Date RMH',
            'revenuFiscal'                      => 'Revenu fiscal',
            'situationFamiliale'                => 'Situation familiale',
            'nombrePersonnesFoyer'              => 'Nombre de personnes du foyer',
            'typeMenage'                        => 'Type de ménage',
            'email'                             => 'Email',
            'telephone1'                        => 'Téléphone1',
            'telephone2'                        => 'Téléphone2',
            'codePostalBeneficiaire'            => 'Code postal habitat',
            'villeBeneficiaire'                 => 'Ville habitat',
            'situation'                         => 'Situation PO/PB',
            'structure'                         => 'Structure MAR',
            'conseiller'                        => 'Conseiller',
            'audit'                             => 'Audit (oui/non)',
            'carnetNumerique'                   => 'Souhait carnet numérique (oui/non)',
            'nomProfessionnel'                  => 'Nom du professionnel',
            'anneeConstruction'                 => 'Année construction',
            'typeHabitation'                    => 'Type habitation',
            'descriptionProjet'                 => 'Description du projet du particulier',
            'montantTotal'                      => 'Montant des devis',
            'montantFacture'                    => 'Montant des factures',
            'aideRegion'                        => 'Aide Région',
            'aideDepartement'                   => 'Aide Département',
            'aideDepartementOrigine'            => 'Origine Aide Département',
            'aideIntercommunalite'              => 'Aide Intercommunalité',
            'aideIntercommunaliteOrigine'       => 'Origine Aide Intercommunalité',
            'aideANAH'                          => 'Aide Anah',
            'aideHabitezMieux'                  => 'MaPrimeRénov’ Sérénité',
            'typeMaPrimeRenovSerenite'          => 'Type MaPrimeRénov’ Sérénité',
            'creditImpot'                       => 'MaPrimeRénov’',
            'typeMaPrimeRenov'                  => 'Type MaPrimeRénov’',
            'CEE'                               => 'CEE',
            'EcoPTZ'                            => 'EcoPTZ',
            'fondsPropres'                      => 'Fonds propres',
            'autresAides'                       => 'Autres aides',
            'autrePret'                         => 'Autre prêt',
            'banqueEcoPTZ'                      => 'Banque EcoPTZ',
            'banqueAutrePret'                   => 'Banque autre prêt',
            'surfaceHabitat'                    => 'Surface habitable',
            'CEPDepart'                         => 'CEP départ',
            'CEPBBC'                            => 'CEP BBC',
            'CEPTravaux'                        => 'CEP travaux',
            'CEPGain'                           => 'Gain CEP',
            'CEPFinChantier'                    => 'CEP fin de chantier',
            'GESDepart'                         => 'GES départ',
            'GESTravaux'                        => 'GES travaux',
            'GESFinChantier'                    => 'GES fin de chantier',
            'UbatDepart'                        => 'Ubât départ',
            'UbatBBC'                           => 'Ubât BBC',
            'UbatTravaux'                       => 'Ubât travaux',
            'UbatFinChantier'                   => 'Ubât fin de chantier',
            'etiquetteEnergetiqueDepart'        => 'Etiquette départ',
            'etiquetteEnergetiqueTravaux'       => 'Etiquette travaux',
            'etiquetteEnergetiqueFinChantier'   => 'Etiquette fin de chantier',
            'NbSautsEiquettes'                  => 'Nb sauts d\'étiquettes',
            'Q4FinChantier'                     => 'Q4 fin de chantier',
            'informationControleurChantier'     => 'Contrôleur fin de chantier',
            'AValoriser'                        => 'A valoriser'
        ];

        if (!in_array(User::PARAM_ROLE_ADMIN, $roles)) {
            $fieldsKey = $this->getFieldsKeyExportByRole($roles);
            $fieldsHeader = array_intersect_key($fieldsHeader, array_flip($fieldsKey));
        }

        fputcsv($handle, $fieldsHeader, ';');

        /**
         * @var Statement $statement
         */
        $statement = $this->repo_demande->findDataExportStatement(
            $option,
            $whereFormFilter
        );
        $result = $statement->execute();

        while ($row = $result->fetchAssociative()) {

            $situation = (!empty($row['situation'])) ? explode(' | ', $row['situation'])[1] : '';
            $situation_famille = (!empty($row['situation_famille'])) ? explode(' | ', $row['situation_famille'])[1] : '';
            $annee_construction = (!empty($row['annee_construction'])) ? explode(' | ',$row['annee_construction'])[1] : '';
            $typeHabitation = (!empty($row['typeHabitation'])) ? explode(' | ',$row['typeHabitation'])[1] : '';
            $date_commission = (!empty($row['commissionDate'])) ? date_format(date_create($row['commissionDate']), 'Y-m-d') : '';
            $date_RMH = (!empty($row['remboursementDateRMH'])) ? date_format(date_create($row['remboursementDateRMH']), 'Y-m-d') : '';
            $instructionDateCreation = (!empty($row['instructionDateCreation'])) ? date_format(date_create($row['instructionDateCreation']), 'Y-m-d') : '';
            $remboursementDateInstruction = (!empty($row['remboursementDateInstruction'])) ? date_format(date_create($row['remboursementDateInstruction']), 'Y-m-d') : '';
            $remboursementDateInstructionSuppl = (!empty($row['remboursementDateInstructionSuppl'])) ? date_format(date_create($row['remboursementDateInstructionSuppl']), 'Y-m-d') : '';
            $nbSautsEiquettes = '-';

            if (!empty($row['etiquetteEnergetique_depart']) && !empty($row['etiquetteEnergetique_fin_de_chantier'])) {
                $nbSautsEiquettes = abs(ord($row['etiquetteEnergetique_fin_de_chantier']) - ord($row['etiquetteEnergetique_depart']));
            }

            $fieldsData = [
                'numeroDossier'                     => $row['demandeId'],
                'thematique'                        => $row['demandeType'],
                'nom'                               => $row['beneficiaireNom'],
                'prenom'                            => $row['beneficiairePrenom'],
                'codePostalTravaux'                 => $row['logementCodePostal'],
                'villeTravaux'                      => $row['logementVille'],
                'EPCI'                              => $row['epci'],
                'departement'                       => $row['departement'],
                'dateDemande'                       => date_format(date_create($row['demandeDate']), 'Y-m-d'),
                'statut'                            => $row['demandeStatutSlug'],
                'dateInstructionDemande'            => $instructionDateCreation,
                'dateCommission'                    => $date_commission,
                'statutRemboursement'               => $row['remboursementStatutSlug'],
                'remboursementDateInstruction'      => $remboursementDateInstruction,
                'remboursementDateInstructionSuppl' => $remboursementDateInstructionSuppl,
                'dateRMH'                           => $date_RMH,
                'revenuFiscal'                      => $row['revenu_fiscal_ref'],
                'situationFamiliale'                => $situation_famille,
                'nombrePersonnesFoyer'              => $row['nb_pers_foyer'],
                'typeMenage'                        => (!empty($row['typeMenage']) ? ANAHCritere::$TYPE_MENAGE[$row['typeMenage']] : ''),
                'email'                             => $row['email'],
                'telephone1'                        => $row['tel_1'],
                'telephone2'                        => $row['tel_2'],
                'codePostalBeneficiaire'            => $row['code_postal'],
                'villeBeneficiaire'                 => $row['ville'],
                'situation'                         => $situation,
                'structure'                         => $row['structure'],
                'conseiller'                        => $row['conseiller'],
                'audit'                             => $row['audit'],
                'carnetNumerique'                   => $row['carnet_numerique'],
                'nomProfessionnel'                  => $row['professionnel'],
                'anneeConstruction'                 => $annee_construction,
                'typeHabitation'                    => $typeHabitation,
                'descriptionProjet'                 => $row['description_projet'],
                'montantTotal'                      => $row['total_devis'],
                'montantFacture'                    => $row['montantFacture'],
                'aideRegion'                        => $row['aide_region'],
                'aideDepartement'                   => $row['aide_departement'],
                'aideDepartementOrigine'            => $row['aide_departement_origine'],
                'aideIntercommunalite'              => $row['aide_intercommunalite'],
                'aideIntercommunaliteOrigine'       => $row['aide_intercommunalite_origine'],
                'aideANAH'                          => $row['aide_anah'],
                'aideHabitezMieux'                  => $row['aide_habiter_mieux'],
                'typeMaPrimeRenovSerenite'          => $row['type_ma_prime_renov_serenite'],
                'creditImpot'                       => $row['credit_impot'],
                'typeMaPrimeRenov'                  => $row['type_ma_prime_renov'],
                'CEE'                               => $row['CEE'],
                'EcoPTZ'                            => $row['EcoPTZ'],
                'fondsPropres'                      => $row['fonds_propres'],
                'autresAides'                       => $row['autre_aide'],
                'autrePret'                         => $row['autre_pret'],
                'banqueEcoPTZ'                      => $row['EcoPTZ_banque'],
                'banqueAutrePret'                   => $row['autre_pret_banque'],
                'surfaceHabitat'                    => str_replace('.', ',', $row['surface_habitable']),
                'CEPDepart'                         => str_replace('.', ',', $row['CEP_depart']),
                'CEPBBC'                            => str_replace('.', ',', $row['CEP_bbc']),
                'CEPTravaux'                        => str_replace('.', ',', $row['CEP_prescription']),
                'CEPGain'                           => str_replace('.', ',', $row['CEP_gain']),
                'CEPFinChantier'                    => str_replace('.', ',', $row['CEP_fin_de_chantier']),
                'GESDepart'                         => str_replace('.', ',', $row['GES_depart']),
                'GESTravaux'                        => str_replace('.', ',', $row['GES_prescription']),
                'GESFinChantier'                    => str_replace('.', ',', $row['GES_fin_de_chantier']),
                'UbatDepart'                        => str_replace('.', ',', $row['CEP_ubatdepart']),
                'UbatBBC'                           => str_replace('.', ',', $row['CEP_ubatbbc']),
                'UbatTravaux'                       => str_replace('.', ',', $row['CEP_ubatprescription']),
                'UbatFinChantier'                   => str_replace('.', ',', $row['ubat_fin_de_chantier']),
                'etiquetteEnergetiqueDepart'        => str_replace('.', ',', $row['etiquetteEnergetique_depart']),
                'etiquetteEnergetiqueTravaux'       => str_replace('.', ',', $row['etiquetteEnergetique_prescription']),
                'etiquetteEnergetiqueFinChantier'   => str_replace('.', ',', $row['etiquetteEnergetique_fin_de_chantier']),
                'NbSautsEiquettes'                  => $nbSautsEiquettes,
                'Q4FinChantier'                     => str_replace('.', ',', $row['Q4_fin_de_chantier']),
                'informationControleurChantier'     => $row['information_controleur_chantier'],
                'AValoriser'                        => $row['is_valoriser_renovation']
            ];

            if (!in_array(User::PARAM_ROLE_ADMIN, $roles)) {
                $fieldsKey = $this->getFieldsKeyExportByRole($roles);
                $fieldsData = array_intersect_key($fieldsData, array_flip($fieldsKey));
            }
            fputcsv($handle, $fieldsData, ';');
        }

        $statement = null;
    }

    /**
     * dateReference format => '2021-04-06'
     *
     * @param $filePath
     * @param $dateReference
     * @return false|int
     * @throws \Doctrine\DBAL\Exception
     */
    public function fputCSVContentExportADEMEA03($filePath, $dateReference): bool|int
    {
        $successDataPuts = true;
        $handle = fopen($filePath, 'w+');
        $separateur = "|;|";

        $dateUSBegin = date('Y-m-d', strtotime($dateReference . ' first day of -3 month'));
        $dateUSEnd = date('Y-m-d', strtotime($dateReference . ' last day of -1 month'));
        $arrayDataToWrite = [];

        /*
                $rowFieldsHeader = [
                    'numeroDossier'                   => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Identifiant unique de l\'acte'),
                    'structure'                       => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Structure'),
                    'conseiller'                      => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Conseiller'),
                    'typeActe'                        => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Type Acte'),
                    'dateActe'                        => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Date Acte'),
                    'dureeActe'                       => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Durée Acte'),
                    'uniteDureeActe'                  => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Unité Durée Acte'),
                    'typePublic'                      => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Type de public'),
                    'nom'                             => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Nom'),
                    'prenom'                          => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Prénom'),
                    'raisonSociale'                   => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Raison sociale'),
                    'SIRETEntreprise'                 => iconv("UTF-8", "Windows-1252//TRANSLIT", 'SIRET de l\'entreprise'),
                    'eligibiliteAuxAidesAnah'         => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Eligibilité aux aides Anah'),
                    'email'                           => iconv("UTF-8", "Windows-1252//TRANSLIT", 'E-mail'),
                    'telephone'                       => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Téléphone'),
                    'typeLogement'                    => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Type de logement'),
                    'nombreLogements'                 => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Nombre de logements'),
                    'logementCodePostal'              => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Code Postal'),
                    'logementCommune'                 => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Commune'),
                    'logementAdresse'                 => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Adresse'),
                    'StatutOccupation'                => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Statut d\'occupation'),
                    'TypeInformation'                 => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Type de l\'information'),
                    'NatureInformation'               => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Nature de l\'information'),
                    'NatureInformationTechnique'      => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Nature de l\'information technique'),
                    'questionPoseeParDemandeur'       => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Question (posée par le demandeur)'),
                    'reponseApporteeParConseiller'    => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Réponse (apportée par le conseiller)'),
                    'poursuiteServiceEnvisagee'       => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Poursuite de service envisagée'),
                    'rapportAuditDTGRemisAuDemandeur' => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Rapport d\'Audit / DTG remis au demandeur'),
                    'visaConseiller'                  => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Visa conseiller'),
                    'dateDemarrageTravaux'            => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Date de démarrage des travaux'),
                    'dateBilanFinTravaux'             => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Date du bilan de fin de travaux'),
                    'dateAbandonAccompagnement'       => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Date abandon de l\'accompagnement'),
                    'datePremiereVisite'              => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Date de 1ere visite'),
                    'datePremierDevis'                => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Date du 1er devis'),
                    'dateBilanConsommation'           => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Date du Bilan de consommation'),
                    'dateTestEtancheiteAir'           => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Date du test d\'étanchéité à l\'air'),
                    'datePriseEnMainFinale'           => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Date de Prise en main finale'),
                    'dateMiseAJourTechnique'          => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Date mise à jour Technique'),
                    'codeInsee'                       => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Code Insee')
                ];

                $arrayDataToWrite[] = implode($separateur, $rowFieldsHeader);
        */

        /**
         * @var Demande_Repository $demande_Repository
         */
        $demande_Repository = $this->EM->getRepository(Demande_::class);

        $data = $demande_Repository->findDataExportADEMEA03($dateUSBegin, $dateUSEnd);

        foreach ($data as $row) {

            $eligibiliteAuxAidesAnah = '';

            if (!empty($row['demandeNbPersFoyer']) && !empty($row['demandeRevenuFiscalRef'])) {
                $revenuFiscalRef = (integer)$row['demandeNbPersFoyer'];
                $nbPersFoyer = (integer)$row['demandeRevenuFiscalRef'];
            } else {
                $revenuFiscalRef = (integer)$row['beneficiaireRevenuFiscalRef'];
                $nbPersFoyer = (integer)$row['beneficiaireNbPersFoyer'];
            }

            if ($nbPersFoyer) {
                $ANAH = $this->ANAHService->findPlafond($nbPersFoyer);
                $eligibiliteAuxAidesAnah = ((integer)$revenuFiscalRef < (integer)$ANAH) ? 'OUI' : 'NON';
            }

            $rowFieldsBodyData = [
                'numeroDossier'                   => $row['numeroDossier'],
                'structure'                       => $this->parameterBag->get('app_export_ADEME_A03_structure'),
                'conseiller'                      => '',
                'typeActe'                        => $this->parameterBag->get('app_export_ADEME_A03_type_acte'),
                'dateActe'                        => $row['dateActe'],
                'dureeActe'                       => '',
                'uniteDureeActe'                  => '',
                'typePublic'                      => $row['typePublic'],
                'nom'                             => iconv("UTF-8", "Windows-1252//TRANSLIT", $row['beneficiaireNom']),
                'prenom'                          => iconv("UTF-8", "Windows-1252//TRANSLIT", $row['beneficiairePrenom']),
                'raisonSociale'                   => '',
                'SIRETEntreprise'                 => '',
                'eligibiliteAuxAidesAnah'         => $eligibiliteAuxAidesAnah,
                'email'                           => iconv("UTF-8", "Windows-1252//TRANSLIT", $row['email']),
                'telephone'                       => iconv("UTF-8", "Windows-1252//TRANSLIT", $row['beneficiaireTel']),
                'typeLogement'                    => $this->parameterBag->get('app_export_ADEME_A03_type_logement'),
                'nombreLogements'                 => '',
                'logementCodePostal'              => iconv("UTF-8", "Windows-1252//TRANSLIT", $row['logementCodePostal']),
                'logementCommune'                 => iconv("UTF-8", "Windows-1252//TRANSLIT", $row['logementCommune']),
                'logementAdresse'                 => iconv("UTF-8", "Windows-1252//TRANSLIT", $row['logementAdresse']),
                'StatutOccupation'                => '',
                'TypeInformation'                 => '',
                'NatureInformation'               => '',
                'NatureInformationTechnique'      => '',
                'questionPoseeParDemandeur'       => '',
                'reponseApporteeParConseiller'    => '',
                'poursuiteServiceEnvisagee'       => '',
                'rapportAuditDTGRemisAuDemandeur' => $this->parameterBag->get('app_export_ADEME_A03_rapport_audit_DTG_remis'),
                'visaConseiller'                  => '',
                'dateDemarrageTravaux'            => '',
                'dateBilanFinTravaux'             => '',
                'dateAbandonAccompagnement'       => '',
                'datePremiereVisite'              => '',
                'datePremierDevis'                => '',
                'dateBilanConsommation'           => '',
                'dateTestEtancheiteAir'           => '',
                'datePriseEnMainFinale'           => '',
                'dateMiseAJourTechnique'          => '',
                'codeInsee'                       => iconv("UTF-8", "Windows-1252//TRANSLIT", $row['codeInsee']),
                'natureEntreprise'                => '',
                'trancheEffectifEntreprise'       => ''
            ];

            $arrayDataToWrite[] = implode($separateur, $rowFieldsBodyData);
        }

        // POUR CHACUNE DES LIGNES ON AJOUTE LE FLAG DE FIN LIGNE

        if (!empty($arrayDataToWrite)) {
            $successDataPuts = file_put_contents($filePath, implode(PHP_EOL, $arrayDataToWrite));
        }

        fclose($handle);

        return $successDataPuts;
    }

    /**
     * dateReference format => '2021-04-06'
     *
     * @param $filePath
     * @param $dateReference
     * @return false|int
     */
    public function fputCSVContentExportADEMEA05($filePath, $dateReference)
    {
        $successDataPuts = true;
        $handle = fopen($filePath, 'w+');
        $separateur = "|;|";

        $dateUSBegin = date('Y-m-d', strtotime($dateReference . ' first day of -3 month'));
        $dateUSEnd = date('Y-m-d', strtotime($dateReference . ' last day of -1 month'));
        $arrayDataToWrite = [];

        /*
                $rowFieldsHeader = [
                    'numeroDossier'                   => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Identifiant unique de l\'acte'),
                    'structure'                       => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Structure'),
                    'conseiller'                      => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Conseiller'),
                    'typeActe'                        => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Type Acte'),
                    'dateActe'                        => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Date Acte'),
                    'dureeActe'                       => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Durée Acte'),
                    'uniteDureeActe'                  => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Unité Durée Acte'),
                    'typePublic'                      => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Type de public'),
                    'nom'                             => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Nom'),
                    'prenom'                          => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Prénom'),
                    'raisonSociale'                   => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Raison sociale'),
                    'SIRETEntreprise'                 => iconv("UTF-8", "Windows-1252//TRANSLIT", 'SIRET de l\'entreprise'),
                    'eligibiliteAuxAidesAnah'         => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Eligibilité aux aides Anah'),
                    'email'                           => iconv("UTF-8", "Windows-1252//TRANSLIT", 'E-mail'),
                    'telephone'                       => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Téléphone'),
                    'typeLogement'                    => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Type de logement'),
                    'nombreLogements'                 => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Nombre de logements'),
                    'logementCodePostal'              => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Code Postal'),
                    'logementCommune'                 => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Commune'),
                    'logementAdresse'                 => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Adresse'),
                    'StatutOccupation'                => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Statut d\'occupation'),
                    'TypeInformation'                 => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Type de l\'information'),
                    'NatureInformation'               => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Nature de l\'information'),
                    'NatureInformationTechnique'      => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Nature de l\'information technique'),
                    'questionPoseeParDemandeur'       => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Question (posée par le demandeur)'),
                    'reponseApporteeParConseiller'    => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Réponse (apportée par le conseiller)'),
                    'poursuiteServiceEnvisagee'       => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Poursuite de service envisagée'),
                    'rapportAuditDTGRemisAuDemandeur' => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Rapport d\'Audit / DTG remis au demandeur'),
                    'visaConseiller'                  => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Visa conseiller'),
                    'dateDemarrageTravaux'            => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Date de démarrage des travaux'),
                    'dateBilanFinTravaux'             => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Date du bilan de fin de travaux'),
                    'dateAbandonAccompagnement'       => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Date abandon de l\'accompagnement'),
                    'datePremiereVisite'              => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Date de 1ere visite'),
                    'datePremierDevis'                => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Date du 1er devis'),
                    'dateBilanConsommation'           => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Date du Bilan de consommation'),
                    'dateTestEtancheiteAir'           => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Date du test d\'étanchéité à l\'air'),
                    'datePriseEnMainFinale'           => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Date de Prise en main finale'),
                    'dateMiseAJourTechnique'          => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Date mise à jour Technique'),
                    'codeInsee'                       => iconv("UTF-8", "Windows-1252//TRANSLIT", 'Code Insee')
                ];

                $arrayDataToWrite[] = implode($separateur, $rowFieldsHeader);
        */

        /**
         * @var Demande_Repository $demande_Repository
         */
        $demande_Repository = $this->EM->getRepository(Demande_::class);

        $data = $demande_Repository->findDataExportADEMEA05(
            $dateUSBegin,
            $dateUSEnd,
            $this->parameterBag->get('production_travauxNiveau1_2'),
            $this->parameterBag->get('production_travauxNiveau_BBC2')
        );

        foreach ($data as $row) {

            $eligibiliteAuxAidesAnah = '';

            if (!empty($row['demandeNbPersFoyer']) && !empty($row['demandeRevenuFiscalRef'])) {
                $revenuFiscalRef = (integer)$row['demandeNbPersFoyer'];
                $nbPersFoyer = (integer)$row['demandeRevenuFiscalRef'];
            } else {
                $revenuFiscalRef = (integer)$row['beneficiaireRevenuFiscalRef'];
                $nbPersFoyer = (integer)$row['beneficiaireNbPersFoyer'];
            }

            if ($nbPersFoyer) {
                $ANAH = $this->ANAHService->findPlafond($nbPersFoyer);
                $eligibiliteAuxAidesAnah = ((integer)$revenuFiscalRef < (integer)$ANAH) ? 'OUI' : 'NON';
            }

            $rowFieldsBodyData = [
                'numeroDossier'                   => $row['numeroDossier'],
                'structure'                       => $this->parameterBag->get('app_export_ADEME_A05_structure'),
                'conseiller'                      => '',
                'typeActe'                        => $this->parameterBag->get('app_export_ADEME_A05_type_acte'),
                'dateActe'                        => $row['dateActe'],
                'dureeActe'                       => '',
                'uniteDureeActe'                  => '',
                'typePublic'                      => $row['typePublic'],
                'nom'                             => iconv("UTF-8", "Windows-1252//TRANSLIT", $row['beneficiaireNom']),
                'prenom'                          => iconv("UTF-8", "Windows-1252//TRANSLIT", $row['beneficiairePrenom']),
                'raisonSociale'                   => '',
                'SIRETEntreprise'                 => '',
                'eligibiliteAuxAidesAnah'         => $eligibiliteAuxAidesAnah,
                'email'                           => iconv("UTF-8", "Windows-1252//TRANSLIT", $row['email']),
                'telephone'                       => iconv("UTF-8", "Windows-1252//TRANSLIT", $row['beneficiaireTel']),
                'typeLogement'                    => $this->parameterBag->get('app_export_ADEME_A05_type_logement'),
                'nombreLogements'                 => '',
                'logementCodePostal'              => iconv("UTF-8", "Windows-1252//TRANSLIT", $row['logementCodePostal']),
                'logementCommune'                 => iconv("UTF-8", "Windows-1252//TRANSLIT", $row['logementCommune']),
                'logementAdresse'                 => iconv("UTF-8", "Windows-1252//TRANSLIT", $row['logementAdresse']),
                'StatutOccupation'                => '',
                'TypeInformation'                 => '',
                'NatureInformation'               => '',
                'NatureInformationTechnique'      => '',
                'questionPoseeParDemandeur'       => '',
                'reponseApporteeParConseiller'    => '',
                'poursuiteServiceEnvisagee'       => '',
                'rapportAuditDTGRemisAuDemandeur' => $this->parameterBag->get('app_export_ADEME_A05_rapport_audit_DTG_remis'),
                'visaConseiller'                  => '',
                'dateDemarrageTravaux'            => '',
                'dateBilanFinTravaux'             => '',
                'dateAbandonAccompagnement'       => '',
                'datePremiereVisite'              => '',
                'datePremierDevis'                => '',
                'dateBilanConsommation'           => '',
                'dateTestEtancheiteAir'           => '',
                'datePriseEnMainFinale'           => '',
                'dateMiseAJourTechnique'          => '',
                'codeInsee'                       => iconv("UTF-8", "Windows-1252//TRANSLIT", $row['codeInsee']),
                'natureEntreprise'                => '',
                'trancheEffectifEntreprise'       => ''
            ];

            $arrayDataToWrite[] = implode($separateur, $rowFieldsBodyData);
        }

        // POUR CHACUNE DES LIGNES ON AJOUTE LE FLAG DE FIN LIGNE
        if (!empty($arrayDataToWrite)) {
            $successDataPuts = file_put_contents($filePath, implode(PHP_EOL, $arrayDataToWrite));
        }

        fclose($handle);

        return $successDataPuts;
    }

    /**
     * @param $filterFields
     * @return string
     */
    public function getWhereFormFilter($filterFields)
    {
        $columnWhere = [];
        $columnWhereStr = '';

        foreach ($filterFields as $k    => $v) {
            $columnSearch = $v ? $k : null;

            switch ($columnSearch) {
                case 'demandeId':
                    $columnWhere[] = "d.id LIKE \"%" . $v . "%\"";
                    break;
                case 'demandeType':
                    $searchValue = $v;
                    $columnDemandeType = 'd.type';
                    $columnDemandeNiveauSubstring = 'SUBSTRING(dtd.niveau, 1, 1)';

                    if (isset($searchValue)) {
                        switch ($searchValue) {
                            case Demande_::DEMANDE_AUDIT_ENERGIE_TYPE:
                                $columnDemandeType = $columnDemandeType . " = " . Demande_::DEMANDE_AUDIT_ENERGIE_TYPE;
                                $columnDemandeNiveau = " dtd.niveau IS NULL ";
                                break;
                            case Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE:
                                $columnDemandeType = $columnDemandeType . " = " . Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE;
                                $columnDemandeNiveau = " dtd.niveau IS NULL ";
                                break;
                            case Demande_::DEMANDE_TRAVAUX_TYPE:
                                $columnDemandeType = $columnDemandeType . " = " . Demande_::DEMANDE_TRAVAUX_TYPE;
                                $columnDemandeNiveau = " dtd.niveau IS NULL ";
                                break;
                            case Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE:
                                $columnDemandeType = $columnDemandeType . " = " . Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE;
                                $columnDemandeNiveau = " dtd.niveau IS NULL ";
                                break;
                            case Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE:
                                $columnDemandeType = $columnDemandeType . " = " . Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE;
                                $columnDemandeNiveau = " dtd.niveau IS NULL ";
                                break;
                            case Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_1_CODE:
                                $columnDemandeType = $columnDemandeType . " = " . Demande_::DEMANDE_TRAVAUX_TYPE;
                                $columnDemandeNiveau = $columnDemandeNiveauSubstring . " = '0'";
                                break;
                            case Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_2_CODE:
                                $columnDemandeType = $columnDemandeType . " = " . Demande_::DEMANDE_TRAVAUX_TYPE;
                                $columnDemandeNiveau = $columnDemandeNiveauSubstring . " = '1'";
                                break;
                            case Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_2_RENOVATEUR_CODE:
                                $columnDemandeType = $columnDemandeType . " = " . Demande_::DEMANDE_TRAVAUX_TYPE;
                                $columnDemandeNiveau = $columnDemandeNiveauSubstring . " = '2'";
                                break;
                            case Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_BBC_RENOVATEUR_CODE:
                                $columnDemandeType = $columnDemandeType . " = " . Demande_::DEMANDE_TRAVAUX_TYPE;
                                $columnDemandeNiveau = $columnDemandeNiveauSubstring . " = '3'";
                                break;
                            case Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_BBC_BIOSOURCE_CODE:
                                $columnDemandeType = $columnDemandeType . " = " . Demande_::DEMANDE_TRAVAUX_TYPE;
                                $columnDemandeNiveau = $columnDemandeNiveauSubstring . " = '4'";
                                break;
                            case Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_SORTIE_PASSOIRE_CODE:
                                $columnDemandeType = $columnDemandeType . " = " . Demande_::DEMANDE_TRAVAUX_TYPE;
                                $columnDemandeNiveau = $columnDemandeNiveauSubstring . " = '6'";
                                break;
                            case Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_ETAPE1_BBC_RGE_CODE:
                                $columnDemandeType = $columnDemandeType . " = " . Demande_::DEMANDE_TRAVAUX_TYPE;
                                $columnDemandeNiveau = $columnDemandeNiveauSubstring . " = '7'";
                                break;
                            case Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_ETAPE1_BBC_RENOVATEUR_CODE:
                                $columnDemandeType = $columnDemandeType . " = " . Demande_::DEMANDE_TRAVAUX_TYPE;
                                $columnDemandeNiveau = $columnDemandeNiveauSubstring . " = '8'";
                                break;
                            case Demande_travaux_devis::DEMANDE_TRAVAUX_NIVEAU_RENOVATION_GLOBALE_BBC_CODE:
                                $columnDemandeType = $columnDemandeType . " = " . Demande_::DEMANDE_TRAVAUX_TYPE;
                                $columnDemandeNiveau = $columnDemandeNiveauSubstring . " = '9'";
                                break;
                            default:
                                $columnDemandeNiveau = '';
                                $columnDemandeType = '';
                                break;
                        }
                    }
                    if ($columnDemandeType != '' && $columnDemandeNiveau != '') {
                        $columnWhere[] = "(" . $columnDemandeType . " AND " .  $columnDemandeNiveau . ")";
                    }
                    break;
                case 'beneficiaireIdentifiant':
                    $columnWhere[] =
                        "(b.nom LIKE \"%" . $v . "%\"" .
                        " OR " .
                        "b.prenom LIKE \"%" . $v . "%\"" .
                        " OR " .
                        "b.nom_SCI LIKE \"%" . $v . "%\")"
                    ;
                    break;
                case 'logement':
                    $columnWhere[] =
                        "(l.code_postal LIKE \"%" . $v . "%\"" .
                        " OR " .
                        "l.ville LIKE \"%" . $v . "%\")"
                    ;
                    break;
                case 'demandeDateCreation':
                    $columnWhere[] = "DATE_FORMAT(d.date_creation, '%d/%m/%Y') LIKE \"%" . $v . "%\"";
                    break;
                case 'demandeStatutSlug':
                    $columnWhere[] =
                        "(ds.slug LIKE \"%" . $v . "%\"".
                        " OR " .
                        "rs.slug LIKE \"%" . $v . "%\")"
                    ;
                    break;
                case 'structureConseiller':
                    $columnWhere[] =
                        "(si_dae.nom LIKE \"%" . $v . "%\"" .
                        " OR " .
                        "sc_dae.nom LIKE \"%" . $v . "%\"" .
                        " OR " .
                        "si_dan.nom LIKE \"%" . $v . "%\"" .
                        " OR " .
                        "sc_dan.nom LIKE \"%" . $v . "%\"" .
                        " OR " .
                        "si_dt.nom LIKE \"%" . $v . "%\"" .
                        " OR " .
                        "sc_dt.nom LIKE \"%" . $v . "%\")"
                    ;
                    break;
                case 'partenaire':
                    $columnWhere[] =
                        "(pi_dae.raison_sociale LIKE \"%" . $v . "%\"" .
                        " OR " .
                        "pi_dan.raison_sociale LIKE \"%" . $v . "%\"" .
                        " OR " .
                        "pi_dtd.raison_sociale LIKE \"%" . $v . "%\")"
                    ;
                    break;
                case 'commissionDate':
                    $columnWhere[] = "DATE_FORMAT(dCP.date_CP, '%d/%m/%Y') LIKE \"%" . $v . "%\"";
                    break;
                case 'remboursementDate':
                    $columnWhere[] = "DATE_FORMAT(dRMH.date_RMH, '%d/%m/%Y') LIKE \"%" . $v . "%\"";
                    break;
            }
        }

        if (!empty($columnWhere)) {
            $columnCopy = implode(" AND ", $columnWhere);
            $columnWhereStr = " AND " . $columnCopy;
        }

        return $columnWhereStr;
    }

    /**
     * @param Demande_ $demande_
     * @param $option
     */
    public function checkAccesByRole(Demande_ $demande_, $option)
    {
        $roles = $option['roles'];
        $username = $option['username'];

        $adminId = (int)substr($username, 1);
        $isAccessRight = false;
        if (is_int($adminId)) {
            if (in_array(User::PARAM_ROLE_CONSEILLER, $roles)) {

                $structure_id = $this->repo_structure->findByConseillerId($adminId);
                $user_id_current = $structure_id['id'];

                $beneficiaire = $this->repo_beneficiaire->find($demande_->getBeneficiaireId());
                if ($beneficiaire->getStructureRattachementId() == $user_id_current) {
                    $isAccessRight = true;
                }
            } elseif (in_array(User::PARAM_ROLE_AUDITEUR, $roles)) {
                switch ($demande_->getType()) {
                    case Demande_::DEMANDE_AUDIT_ENERGIE_TYPE: // Cas Audit Energie
                    case Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE: // Cas Audit Energie Region Normandie
                        if($adminId == $demande_->getDemandeAuditEnergie()->getAuditeurId()) {
                            $isAccessRight = true;
                        }
                        break;
                    case Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE: // Cas Audit Numérique
                    case Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE: // Cas Mise à jour Audit énergétique et scénarios
                        if($adminId == $demande_->getDemandeAuditNumerique()->getAuditeurId()) {
                            $isAccessRight = true;
                        }
                        break;
                    case Demande_::DEMANDE_TRAVAUX_TYPE: // Cas Travaux
                        if ($demande_->getDemandeTravaux()->getTravauxDevisId()) {
                            $demandeTravauxDevis = $this->repo_demande_travaux_devis->find($demande_->getDemandeTravaux()->getTravauxDevisId());
                            if($adminId == $demandeTravauxDevis->getAuditeurId() && $demande_->getDemandeTravaux()->getAudit() == '1') {
                                $isAccessRight = true;
                            }
                        }
                        break;
                    default:
                        break;
                }
            } elseif (in_array(User::PARAM_ROLE_RENOVATEUR, $roles)) {
                // Cas Travaux
                if (Demande_::DEMANDE_TRAVAUX_TYPE == $demande_->getType()) {
                    if ($demande_->getDemandeTravaux()->getTravauxDevisId()) {
                        $demandeTravauxDevis = $this->repo_demande_travaux_devis->find($demande_->getDemandeTravaux()->getTravauxDevisId());
                        if($adminId == $demandeTravauxDevis->getRenovateurId()) {
                            $isAccessRight = true;
                        }
                    }
                }
            }  elseif (in_array(User::PARAM_ROLE_EPCI, $roles)) {
                $EPCI_id = $this->repo_EPCI->findByContactId($adminId);
                $user_id_current = $EPCI_id['id'];

                $orientationEPCIId = $this->repo_orientation->searchEPCIIdByLogement($demande_->getLogementId());
                if ($orientationEPCIId == $user_id_current) {
                    $isAccessRight = true;
                }
            } elseif (in_array(User::PARAM_ROLE_TECHNIQUE, $roles)) {
                // Cas Travaux
                if (Demande_::DEMANDE_TRAVAUX_TYPE == $demande_->getType()) {
                    $demandeTravaux = $demande_->getDemandeTravaux();
                    if (
                        Demande_statut::STATUS_15 != $demande_->getStatutId()
                        && (!empty($demandeTravaux->getFicheTechniqueId()) || !empty($demandeTravaux->getTravauxDevisId()))
                    ) {
                        $isAccessRight = true;
                    }
                }
            }
        }

        if (
            !in_array(User::PARAM_ROLE_INSTRUCTEUR, $roles)
            && !in_array(User::PARAM_ROLE_CLIENT, $roles)
            && !in_array(User::PARAM_ROLE_ADMIN, $roles)
            && !$isAccessRight
        ) {
            throw new AccessDeniedHttpException();
        }
    }

    /**
     * @param $demandeStatutId
     * @return void
     */
    public function checkAccesFicheTechniqueByStatut($demandeStatutId)
    {
        $listStatutForFicheTechniqueAccess = $this->getListStatutForFicheTechniqueAccess();

        if (!in_array($demandeStatutId, $listStatutForFicheTechniqueAccess)) {
            throw new AccessDeniedHttpException();
        }
    }

    /**
     * @return array
     */
    public function getListStatutForFicheTechniqueAccess()
    {
        return [
            Demande_statut::STATUS_3,
            Demande_statut::STATUS_4,
            Demande_statut::STATUS_5,
            Demande_statut::STATUS_8,
            Demande_statut::STATUS_10,
            Demande_statut::STATUS_20,
            Demande_statut::STATUS_21,
            Demande_statut::STATUS_22,
            Demande_statut::STATUS_23,
            Demande_statut::STATUS_29,
            Demande_statut::STATUS_30,
            Demande_statut::STATUS_31,
            Demande_statut::STATUS_32,
            Demande_statut::STATUS_33,
            Demande_statut::STATUS_34,
            Demande_statut::STATUS_38,
            Demande_statut::STATUS_39,
            Demande_statut::STATUS_40,
            Demande_statut::STATUS_41,
            Demande_statut::STATUS_42
        ];
    }

    /**
     * @return bool
     */
    public function checkIsOkDemandeCreateActionByDate()
    {
        /**
         * @var User $user
         */
        $user = !empty($this->tokenStorage->getToken()?->getUser()) ? $this->tokenStorage->getToken()->getUser() : null;
        $dateDuJour = (new \DateTime())->format('Y-m-d');
        $dateLimiteCreationDemande = $this->parameterBag->get('app_date_limite_creation_demande_member');
        if (!empty($user) && $user instanceof User) {
            $roles = $user->getRoles();

            if (in_array(User::PARAM_ROLE_ADMIN, $roles)
                || in_array(User::PARAM_ROLE_CONSEILLER, $roles)
            ) {
                $dateLimiteCreationDemande = $this->parameterBag->get('app_date_limite_creation_demande_conseiller');
            } elseif (in_array(User::PARAM_ROLE_MEMBER, $roles)) {
                $dateLimiteCreationDemande = $this->parameterBag->get('app_date_limite_creation_demande_member');
            } else {
                return false;
            }
        }

        return ($dateDuJour < $dateLimiteCreationDemande);
    }

    /**
     * @param $demandeId
     * @param $devisId
     * @param Request $request
     * @param $formTravauxDevisUpdateNiveauAide
     * @param $userRoles
     * @return void
     */
    public function saveUpdateNiveauAideTravauxDevisAction(
        $demandeId,
        $devisId,
        Request &$request,
        $formTravauxDevisUpdateNiveauAide,
        $userRoles
    ) {
        /**
         * @var Demande_ $demande
         */
        $demande = $this->repo_demande->find($demandeId);

        if ($demande->getType() == Demande_::DEMANDE_TRAVAUX_TYPE) {

            $demandeTravaux = $demande->getDemandeTravaux();
            $demandeTravauxDevisId = ($demandeTravaux) ? $demandeTravaux->getTravauxDevisId() : null;

            if (!empty($demandeTravauxDevisId) && $demandeTravauxDevisId == $devisId) {

                $demande->setDateModif(new \DateTime())
                    ->setAuteurModif($_SESSION['login']->getUsername());

                $niveau = ($formTravauxDevisUpdateNiveauAide['niveau']->getData()) ? $formTravauxDevisUpdateNiveauAide['niveau']->getData() : null;
                $renovateurId = ($formTravauxDevisUpdateNiveauAide['renovateur_id']->getData()) ? $formTravauxDevisUpdateNiveauAide['renovateur_id']->getData() : null;

                /**
                 * @var Demande_travaux_devis $demandeTravauxDevis
                 */
                $demandeTravauxDevis = $this->repo_demande_travaux_devis->find($demandeTravauxDevisId);
                $demandeTravauxDevis->setNiveau($niveau)
                    ->setRenovateurId($renovateurId);

                /* /////////////////////////////////////////////////////////////////////////////////////////////////////
                    RECALCUL DES MONTANTS (AIDE REGION - AVEC OU SANS BONIFICATION, ET TOTAL PLAN DE FINANCEMENT)
                ///////////////////////////////////////////////////////////////////////////////////////////////////// */
                $aideRegionAndTotalPlanByNiveauAide = $this->getAideRegionAndTotalPlanByNiveauAide($demandeTravauxDevis);
                $demandeTravauxDevis->setAideRegion($aideRegionAndTotalPlanByNiveauAide['aideRegion'])
                    ->setTotalPlan($aideRegionAndTotalPlanByNiveauAide['totalPlan']);

                $this->EM->persist($demande);
                $this->EM->persist($demandeTravauxDevis);
                $this->EM->flush();

                /* /////////////////////////////////////////////////////////////////
                            FILL UP HISTORIQUE DEMANDE
                ///////////////////////////////////////////////////////////////// */

                /* /////////////////////////////////////////////////////////////////
                                        GET REMBOURSEMENT
                ///////////////////////////////////////////////////////////////// */
                $repo_remboursement = $this->EM->getRepository(Remboursement_::class);
                $remboursement = $repo_remboursement->findOneBy([
                    'demande_id' => $demandeId
                ]);

                if ($remboursement) {
                    $this->historiqueService->save(
                        $demandeId,
                        $remboursement->getStatutId(),
                        $demande->getType(),
                        $userRoles,
                        false,
                        'Modification du niveau d\'aide',
                        null,
                        null,
                        null,
                        null,
                        null,
                        false,
                        $remboursement->getId()
                    );

                } else {
                    $this->historiqueService->save(
                        $demandeId,
                        $demande->getStatutId(),
                        $demande->getType(),
                        $userRoles,
                        false,
                        'Modification du niveau d\'aide'
                    );
                }

                $request->getSession()->getFlashBag()->add(
                    'success',
                    'Le niveau d\'aide (dont le montant aide région et le total plan de financement) a bien été mis à jour.'
                );
            }
        } else {
            $request->getSession()->getFlashBag()->add(
                'warning',
                'Le niveau d\'aide n\'a pas pu être mis à jour: aucun devis n\'est associé à la demande ' . $demandeId
            );
        }
    }

    /**
     * @param Demande_travaux_devis $demandeTravauxDevis
     * @return array
     */
    public function getAideRegionAndTotalPlanByNiveauAide(Demande_travaux_devis $demandeTravauxDevis)
    {
        $aideRegionAvecOuSansBonification = 0;
        if (false !== ($keyMontant = array_search($demandeTravauxDevis->getNiveau(), Demande_travaux_devis::$arrayDemandeTypeNiveauForForm))) {
            $aideRegion = Demande_travaux_devis::$arrayDemandeTypeNiveauForFormMontant[$keyMontant];
            $aideRegionAvecOuSansBonification = (!empty($aideRegion) && $demandeTravauxDevis->getIsBonificationAide()) ? $aideRegion + Demande_travaux_devis::BONIFICATION_SUPPLEMENT_AIDE_REGION_MONTANT : $aideRegion;
        }

        $arrayMontantAides = [
            $aideRegionAvecOuSansBonification,
            $demandeTravauxDevis->getAideDepartement(),
            $demandeTravauxDevis->getAideIntercommunalite(),
            $demandeTravauxDevis->getCreditImpot(),
            $demandeTravauxDevis->getCEE(),
            $demandeTravauxDevis->getEcoPTZ(),
            $demandeTravauxDevis->getFondsPropres(),
            $demandeTravauxDevis->getAutrePret(),
            $demandeTravauxDevis->getAutreAide()
        ];

        $revenuTotal = 0;
        foreach ($arrayMontantAides as $valRevenu) {
            if (!empty($valRevenu)) {
                $revenuTotal += (integer)$valRevenu;
            }
        }

        return [
            'aideRegion' => $aideRegionAvecOuSansBonification,
            'totalPlan'  => $revenuTotal
        ];
    }

    /**
     * @param Demande_ $demande
     * @param array                                         $userRoles
     * @return bool
     */
    public function savePreviousStatutAndHistoriqueAfterReactivation(Demande_ &$demande, array  $userRoles) {
        // search historique
        $historiques = $this->historiqueRepository->findBy(
            [
                'demande_id' => $demande->getId()
            ],
            [
                'id' => 'DESC'
            ]
        );

        if (count($historiques) > 1 && (Demande_statut::LABEL_SLUG_DEMANDE_REFUSEE === $historiques[0]->getStatutSlug())) {
            if (!empty($historiques[1]) && !empty($historiques[1]->getStatutSlug())) {

                $demandeStatut = $this->historiqueService->findPreviousDemandeStatutAfterReactivationDemande($historiques);
                if (!empty($demandeStatut)) {
                    $demande->setStatutId($demandeStatut->getId());
                    $demande->setMotifRefus(null);
                    $this->EM->persist($demande);
                    $this->EM->flush();

                    // MISE A JOUR DEMANDE STATUT DESCRIPTION
                    $demande->setStatutDescription($this->demandeServiceFO->findStatutDescriptionByDemande($demande->getId()));
                    $this->EM->persist($demande);
                    $this->EM->flush();

                    // search statut id
                    $this->historiqueService->save(
                        $demande->getId(),
                        $demande->getStatutId(),
                        $demande->getType(),
                        $userRoles,
                        false,
                        'Réactivation de la demande'
                    );
                    return true;
                }
            }
        }

        return false;
    }

    /* *****************************************************************
    ********************************************************************
                    P R I V A T E   F U N C T I O N
    ********************************************************************
    *******************************************************************/

    /**
     * @param $roles
     * @return array|string[]
     */
    private function getFieldsKeyExportByRole($roles)
    {
        $fieldsKey = [];

        if (in_array(User::PARAM_ROLE_CLIENT, $roles)) {
            $fieldsKey = [
                'numeroDossier',
                'thematique',
                'nom',
                'prenom',
                'codePostalTravaux',
                'villeTravaux',
                'EPCI',
                'departement',
                'dateDemande',
                'statut',
                'dateInstructionDemande',
                'dateCommission',
                'statutRemboursement',
                'remboursementDateInstruction',
                'remboursementDateInstructionSuppl',
                'dateRMH',
                'revenuFiscal',
                'nombrePersonnesFoyer',
                'typeMenage',
                'email',
                'situation',
                'structure',
                'conseiller',
                'audit',
                'nomProfessionnel',
                'anneeConstruction',
                'typeHabitation',
                'montantTotal',
                'montantFacture',
                'aideRegion',
                'aideDepartement',
                'aideDepartementOrigine',
                'aideIntercommunalite',
                'aideIntercommunaliteOrigine',
                'aideANAH',
                'aideHabitezMieux',
                'typeMaPrimeRenovSerenite',
                'creditImpot',
                'typeMaPrimeRenov',
                'CEE',
                'EcoPTZ',
                'fondsPropres',
                'autresAides',
                'autrePret',
                'banqueEcoPTZ',
                'banqueAutrePret',
                'surfaceHabitat',
                'CEPDepart',
                'CEPTravaux',
                'CEPGain',
                'CEPFinChantier',
                'GESDepart',
                'GESTravaux',
                'GESFinChantier',
                'UbatDepart',
                'UbatTravaux',
                'UbatFinChantier',
                'etiquetteEnergetiqueDepart',
                'etiquetteEnergetiqueTravaux',
                'etiquetteEnergetiqueFinChantier',
                'NbSautsEiquettes',
                'Q4FinChantier',
                'informationControleurChantier',
                'AValoriser'
            ];

        } elseif (in_array(User::PARAM_ROLE_CONSEILLER, $roles)) {
            $fieldsKey = [
                'numeroDossier',
                'thematique',
                'nom',
                'prenom',
                'codePostalTravaux',
                'villeTravaux',
                'EPCI',
                'dateDemande',
                'statut',
                'dateCommission',
                'typeMenage',
                'email',
                'telephone1',
                'telephone2',
                'structure',
                'conseiller',
                'audit',
                'carnetNumerique',
                'nomProfessionnel',
                'anneeConstruction',
                'typeHabitation',
                'descriptionProjet',
                'montantTotal',
                'aideRegion',
                'aideDepartement',
                'aideDepartementOrigine',
                'aideIntercommunalite',
                'aideIntercommunaliteOrigine',
                'aideANAH',
                'aideHabitezMieux',
                'typeMaPrimeRenovSerenite',
                'creditImpot',
                'typeMaPrimeRenov',
                'CEE',
                'EcoPTZ',
                'fondsPropres',
                'autresAides',
                'autrePret',
                'banqueEcoPTZ',
                'banqueAutrePret',
                'surfaceHabitat',
                'CEPDepart',
                'CEPBBC',
                'CEPTravaux',
                'CEPFinChantier',
                'UbatDepart',
                'UbatBBC',
                'UbatTravaux',
                'UbatFinChantier',
                'Q4FinChantier',
                'informationControleurChantier'
            ];

        } else if (in_array(User::PARAM_ROLE_AUDITEUR, $roles)
            or in_array(User::PARAM_ROLE_RENOVATEUR, $roles)
        ) {
            $fieldsKey = [
                'numeroDossier',
                'thematique',
                'nom',
                'prenom',
                'codePostalTravaux',
                'villeTravaux',
                'dateDemande',
                'statut',
                'dateCommission',
                'email',
                'telephone1',
                'telephone2',
                'structure',
                'conseiller',
                'audit',
                'carnetNumerique',
                'nomProfessionnel',
                'anneeConstruction',
                'typeHabitation',
                'descriptionProjet',
                'surfaceHabitat',
                'CEPDepart',
                'CEPBBC',
                'CEPTravaux',
                'CEPFinChantier',
                'UbatDepart',
                'UbatBBC',
                'UbatTravaux',
                'UbatFinChantier',
                'Q4FinChantier',
                'informationControleurChantier'
            ];

        } else if (in_array(User::PARAM_ROLE_EPCI, $roles)) {
            $fieldsKey = [
                'numeroDossier',
                'thematique',
                'codePostalTravaux',
                'villeTravaux',
                'dateDemande',
                'statut',
                'dateCommission',
                'revenuFiscal',
                'situationFamiliale',
                'nombrePersonnesFoyer',
                'typeMenage',
                'codePostalBeneficiaire',
                'villeBeneficiaire',
                'situation',
                'structure',
                'conseiller',
                'audit',
                'nomProfessionnel',
                'anneeConstruction',
                'typeHabitation',
                'descriptionProjet',
                'montantTotal',
                'aideRegion',
                'aideDepartement',
                'aideDepartementOrigine',
                'aideIntercommunalite',
                'aideIntercommunaliteOrigine',
                'aideANAH',
                'aideHabitezMieux',
                'typeMaPrimeRenovSerenite',
                'creditImpot',
                'typeMaPrimeRenov',
                'CEE',
                'EcoPTZ',
                'fondsPropre',
                'autresAides',
                'autrePret',
                'banqueEcoPTZ',
                'banqueAutrePret',
                'surfaceHabitat',
                'CEPDepart',
                'CEPTravaux',
                'CEPGain',
                'UbatDepart',
                'UbatTravaux'
            ];

        } else if (in_array(User::PARAM_ROLE_INSTRUCTEUR, $roles)) {
            $fieldsKey = [
                'numeroDossier',
                'thematique',
                'dateDemande',
                'statut',
                'dateInstructionDemande',
                'dateCommission',
                'statutRemboursement',
                'remboursementDateInstruction',
                'remboursementDateInstructionSuppl',
                'dateRMH'
            ];
        }

        return $fieldsKey;
    }

    /**
     * @param Demande_ $demande
     * @param array                                         $userRoles
     * @param                                               $demandeStatutDescription
     * @return bool
     */
    public function findPreviousStatutAfterReactivationAndSaveHistorique(
        Demande_ &$demande,
        array $userRoles,
                 $demandeStatutDescription = null
    ) {
        // search historique
        $historiques = $this->historiqueRepository->findBy(
            [
                'demande_id' => $demande->getId()
            ],
            [
                'id' => 'DESC'
            ]
        );

        if (count($historiques) > 1 && (Demande_statut::LABEL_SLUG_DEMANDE_REFUSEE === $historiques[0]->getStatutSlug())) {
            if (!empty($historiques[1]) && !empty($historiques[1]->getStatutSlug())) {
                $demandeStatut = $this->demandeStatutRepository->findOneBy([
                    'slug' => $historiques[1]->getStatutSlug()
                ]);
                if (!empty($demandeStatut)) {
                    $demande->setStatutId($demandeStatut->getId());
                    $demande->setMotifRefus(null);
                    $demande->setStatutDescription(!empty($demandeStatutDescription ? $demandeStatutDescription : null));
                    $this->EM->persist($demande);
                    $this->EM->flush();

                    // search statut id
                    $this->historiqueService->save(
                        $demande->getId(),
                        $demande->getStatutId(),
                        $demande->getType(),
                        $userRoles,
                        false,
                        'Réactivation de la demande'
                    );
                    return true;
                }
            }
        }

        return false;
    }
}
