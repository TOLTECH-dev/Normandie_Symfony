<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\FicheTechniqueField;
use App\Entity\Demande_;
use App\Repository\Demande_Repository;
use App\Repository\FicheTechniqueFieldRepository;
use App\Utils\DefaultUtils;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateFicheTechniqueSurfaceHabitableCommand extends Command
{

    private EntityManagerInterface $em;

    private LoggerInterface $loggerUpdateFicheTechnique;

    private Demande_Repository $demandeRepository;

    private FicheTechniqueFieldRepository $ficheTechniqueFieldRepository;

    public function __construct(
        EntityManagerInterface        $em,
        Demande_Repository            $demandeRepository,
        FicheTechniqueFieldRepository $ficheTechniqueFieldRepository,
        LoggerInterface               $loggerUpdateFicheTechnique,
        string                        $name = null
    )
    {
        $this->em = $em;
        $this->demandeRepository = $demandeRepository;
        $this->ficheTechniqueFieldRepository = $ficheTechniqueFieldRepository;
        $this->loggerUpdateFicheTechnique = $loggerUpdateFicheTechnique;
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this
            ->setName('normandie:updateFicheTechniqueSurfaceHabitable')
            ->setDescription('Récupère toutes les fiches techniques ayant des valeurs non numeriques pour champ surface habitable (dernière colonne fiche technique) et leur redonne la valeur numerique correpondante (si possible).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->loggerUpdateFicheTechnique->info('--- UpdateFicheTechniqueSurfaceHabitableCommand: Start ---', []);
        $this->runCustom();
        $this->loggerUpdateFicheTechnique->info('--- UpdateFicheTechniqueSurfaceHabitableCommand: End ---', []);

        return Command::SUCCESS;
    }

    /* *****************************************************************
    ********************************************************************
                            PRIVATE FUNCTION
    ********************************************************************
    *******************************************************************/
    private function runCustom(): void
    {
        /* /////////////////////////////////////////////////////////////////////////////////////
                                      GET LIST FICHE TECHNIQUE A TRAITER
        ///////////////////////////////////////////////////////////////////////////////////// */
        $ficheTechniqueRows = $this->demandeRepository->findForUpdateFicheTechniqueSurfaceHabitableCommand();

        if (empty($ficheTechniqueRows)) {
            $this->loggerUpdateFicheTechnique->info('Aucune fiche technique à traiter', []);
        } else {
            $this->loggerUpdateFicheTechnique->info('Nombre de résultats: ' . count($ficheTechniqueRows), []);

            foreach ($ficheTechniqueRows as $ficheTechniqueRow) {
                $this->formatSurfaceHabitableNumeric($ficheTechniqueRow);
            }
        }

        $this->em->clear();
    }

    private function formatSurfaceHabitableNumeric(array $row): void
    {
        $newNumericValue = null;
        $ficheTechniqueFieldId = null;
        $detailMessage = null;
        $contextLogger = [
            'IsSuccess' => null,
            'ErrorMessage' => '',
            'DetailMessage' => '',
        ];

        if (!empty($row['rtftId'])
            && !empty($row['rtftSurfaceHabitableFinChantier'])
            && ('0' === $row['rtftIsNumericFinChantier'])
            && (in_array($this->formatString($row['rtftSurfaceHabitableFinChantier']), $this->getFormattedInchangeList()))
        ) {

            // On traite la fiche remboursement travaux => fiche technique 'fin de chantier'
            $detailMessage = 'DemandeId: ' . $row['demandeId'] . ' / Remboursement Travaux colonne \'FIN DE CHANTIER\'' .
                ' / ancienne valeur: \'' . $row['rtftSurfaceHabitableFinChantier'] . '\'';

            if ('1' === $row['rtftIsNumericPrescription']) {
                $newNumericValue = $row['rtftSurfaceHabitablePrescription'];
            } elseif (!empty($row['rtftSurfaceHabitablePrescription'])
                && in_array($this->formatString($row['rtftSurfaceHabitablePrescription']), $this->getFormattedInchangeList())
            ) {
                if ('1' === $row['rtftIsNumericBBC']) {
                    $newNumericValue = $row['rtftSurfaceHabitableBBC'];
                } elseif (!empty($row['rtftSurfaceHabitableBBC'])
                    && in_array($this->formatString($row['rtftSurfaceHabitableBBC']), $this->getFormattedInchangeList())
                ) {
                    if ('1' === $row['rtftIsNumericInitial']) {
                        $newNumericValue = $row['rtftSurfaceHabitableInitial'];
                    }
                }
            }
            if (empty($newNumericValue)) {
                $newNumericValue = '-1';
            }

            if (!empty($newNumericValue)) {
                $ficheTechniqueFieldId = $row['rtftFinChantierId'];
            }

        } elseif (
            !empty($row['dtftId'])
            && !empty($row['dtftSurfaceHabitablePrescription'])
            && ('0' === $row['dtftIsNumericPrescription'])
            && (in_array($this->formatString($row['dtftSurfaceHabitablePrescription']), $this->getFormattedInchangeList()))
        ) {
            // On traite la demande travaux => fiche technique 'Prescription' (TRAVAUX CORRESPONDANT AUX DEVIS)
            $detailMessage = 'DemandeId: ' . $row['demandeId'] . ' / Demande Travaux colonne \'TRAVAUX CORRESPONDANT AUX DEVIS\'' .
                ' / ancienne valeur: \'' . $row['dtftSurfaceHabitablePrescription'] . '\'';

            if ('1' === $row['dtftIsNumericBBC']) {
                $newNumericValue = $row['dtftSurfaceHabitableBBC'];
            } elseif (!empty($row['dtftSurfaceHabitableBBC'])
                && in_array($this->formatString($row['dtftSurfaceHabitableBBC']), $this->getFormattedInchangeList())
            ) {
                if ('1' === $row['dtftIsNumericInitial']) {
                    $newNumericValue = $row['dtftSurfaceHabitableInitial'];
                }
            }

            if (empty($newNumericValue)) {
                $newNumericValue = '-1';
            }

            if (!empty($newNumericValue)) {
                $ficheTechniqueFieldId = $row['dtftPrescriptionId'];
            }
        } elseif (
            !empty($row['rtftId'])
            && !empty($row['rtftSurfaceHabitableFinChantier'])
            && ('0' === $row['rtftIsNumericFinChantier'])
        ) {
            // On traite la fiche remboursement travaux => fiche technique 'fin de chantier'
            $detailMessage = 'DemandeId: ' . $row['demandeId'] . ' / Remboursement Travaux colonne \'FIN DE CHANTIER\'' .
                ' / ancienne valeur: \'' . $row['rtftSurfaceHabitableFinChantier'] . '\'';
            $ficheTechniqueFieldId = '-1';
            $newNumericValue = '-1';
        } elseif (
            !empty($row['dtftId'])
            && !empty($row['dtftSurfaceHabitablePrescription'])
            && ('0' === $row['dtftIsNumericPrescription'])
        ) {
            // On traite la demande travaux => fiche technique 'Prescription' (TRAVAUX CORRESPONDANT AUX DEVIS)
            $detailMessage = 'DemandeId: ' . $row['demandeId'] . ' / Demande Travaux colonne \'TRAVAUX CORRESPONDANT AUX DEVIS\'' .
                ' / ancienne valeur: \'' . $row['dtftSurfaceHabitablePrescription'] . '\'';
            $ficheTechniqueFieldId = '-1';
            $newNumericValue = '-1';
        }

        if (!empty($ficheTechniqueFieldId) && !empty($newNumericValue)) {
            if ($newNumericValue === '-1') {
                $detailMessage .= ' et nouvelle valeur: PAS PU ETRE RECUPEREE';

                $contextLogger['IsSuccess'] = false;
                $contextLogger['DetailMessage'] = $detailMessage;
                $this->loggerUpdateFicheTechnique->info('', $contextLogger);
            } else {
                try {

                    $newNumericValue = str_replace(',', '.', $newNumericValue);
                    $detailMessage .= ' et nouvelle valeur: \'' . $newNumericValue . '\'';

                    /**
                     * @var FicheTechniqueField $ficheTechniqueField
                     */
                    $ficheTechniqueField = $this->ficheTechniqueFieldRepository->find($ficheTechniqueFieldId);

                    $ficheTechniqueField->setSurfaceHabitable($newNumericValue);
                    $this->em->persist($ficheTechniqueField);
                    $this->em->flush();

                    $contextLogger['IsSuccess'] = true;
                    $contextLogger['DetailMessage'] = $detailMessage;
                    $this->loggerUpdateFicheTechnique->info('', $contextLogger);
                } catch (Exception $e) {
                    $contextLogger['IsSuccess'] = false;
                    $contextLogger['ErrorMessage'] = 'Erreur mise à jour fiche technique : ' . $e->getMessage();
                    $contextLogger['DetailMessage'] = $detailMessage;
                    $this->loggerUpdateFicheTechnique->error('', $contextLogger);
                }
            }
        }
    }

    /**
     * @return array<string>
     */
    private function getFormattedInchangeList(): array
    {
        return [
            'INCHANGEE',
            'INCHANGE',
            'INCHANGES',
            'INCHANGEES'
        ];
    }

    private function formatString(string $string): string
    {
        return (string)preg_replace('/\s/', '', DefaultUtils::formatString($string));
    }
}
