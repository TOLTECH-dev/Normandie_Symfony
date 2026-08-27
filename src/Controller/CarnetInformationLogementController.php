<?php

namespace App\Controller;

use App\Service\HistoriqueService;
use App\Utils\DefaultServiceUtils;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\Remboursement_;
use App\Entity\Demande_;
use App\Entity\User;
use App\Service\CarnetInformationLogementService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\PhpExecutableFinder;

class CarnetInformationLogementController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private HistoriqueService $historiqueService;

    public function __construct(EntityManagerInterface $entityManager, HistoriqueService $historiqueService)
    {
        $this->entityManager = $entityManager;
        $this->historiqueService = $historiqueService;
    }

    public function confirmCreation(Request $request, string $demandeId, ?string $token): Response
    {
        $formConfirmCarnetInformationLogementCreation = $this->createFormBuilder()->getForm();

        if (!empty($token)) {
            $demandeRepository = $this->entityManager->getRepository(Demande_::class);
            /**
             * @var Demande_ $demande
             */
            $demande = $demandeRepository->findOneBy([
                'carnetInformationToken' => $token
            ]);
        }

        if ($demande->getId() == $demandeId
            && $request->isMethod('POST')
            && $formConfirmCarnetInformationLogementCreation->handleRequest($request)->isValid()
            && DefaultServiceUtils::checkItemValidityByDate(
                $demande->getCarnetInformationRequestedAt(),
                CarnetInformationLogementService::EXPIRATION_SECONDS_BEFORE_VALIDATE_CLEA_CREATE
            )
            && empty($demande->getCarnetInformationValidatedAt())
        ) {

            $demandeRepository = $this->entityManager->getRepository(Demande_::class);
            /**
             * @var Demande_ $demande
             */
            $demande = $demandeRepository->find($demandeId);

            if (!empty($demande)) {
                $demande->setCarnetInformationValidatedAt(new \DateTime());
                $this->entityManager->flush();

                $remboursementRepository = $this->entityManager->getRepository(Remboursement_::class);
                $rowDemandeAndRemboursementTermine = $remboursementRepository->findByDemandeAndRemboursementTermine($demande->getId(), $this->getParameter('production_travauxNiveau_BBC2'));

                /* /////////////////////////////////////////////////////////////////
                                        FILL UP HISTORIQUE
                ///////////////////////////////////////////////////////////////// */
                $this->historiqueService->save(
                    $demande->getId(),
                    $rowDemandeAndRemboursementTermine['remboursementStatutId'],
                    $demande->getType(),
                    [User::PARAM_ROLE_AUTOMATE],
                    false,
                    'Confirmation demande de création du Carnet d\'information CLÉA',
                    null,
                    null,
                    null,
                    null,
                    null,
                    false,
                    $rowDemandeAndRemboursementTermine['remboursementId']
                );

                // COMMANDE DE CREATION DU CARNET CLEA
                // Asynchronous command
                // Chemin absolu vers le binaire PHP : php-fpm tourne avec clear_env=yes,
                // donc un 'php' nu est introuvable dans le PATH vidé du process enfant (sortie 127 silencieuse).
                $phpBinaryPath = (new PhpExecutableFinder())->find() ?: 'php';
                $process = new Process([
                    $phpBinaryPath,
                    'bin/console',
                    'normandie:carnetLogement',
                    '--demandeId=' . $demande->getId(),
                    '--env=' . $this->getParameter('kernel.environment')
                ]);

                $process->setWorkingDirectory($this->getParameter('kernel.project_dir'));
                $process->run();

                $this->addFlash('success', 'Votre demande de création du Carnet d\'information CLÉA a bien été envoyée.');

            }
        }

        return $this->redirectToRoute('web_homepage');
    }

    public function emailRedirect(string $demandeId, ?string $token): Response
    {
        // Vérification de la demande et du token
        if (!empty($token)) {

            $demandeRepository = $this->entityManager->getRepository(Demande_::class);
            /**
             * @var Demande_ $demande
             */
            $demande = $demandeRepository->findOneBy([
                'id' => $demandeId,
                'carnetInformationToken' => $token
            ]);

            if (empty($demande)
                || !DefaultServiceUtils::checkItemValidityByDate(
                    $demande->getCarnetInformationRequestedAt(),
                    CarnetInformationLogementService::EXPIRATION_SECONDS_BEFORE_VALIDATE_CLEA_CREATE
                )
                || !empty($demande->getCarnetInformationValidatedAt())
            ) {
                $this->addFlash('danger', 'Token expiré');

                return $this->redirectToRoute('web_homepage');
            }
        }

        // Crée un formulaire vide pour la modal
        $formConfirmCarnetInformationLogementCreation = $this->createFormBuilder()->getForm();

        return $this->render(
            'Main/carnet_information_logement/email_redirect.html.twig',
            [
                'demandeId' => $demandeId,
                'token' => $token,
                'formConfirmCarnetInformationLogementCreation' => $formConfirmCarnetInformationLogementCreation->createView(),
            ]
        );
    }
}
