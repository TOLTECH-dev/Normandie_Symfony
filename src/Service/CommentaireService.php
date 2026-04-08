<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Beneficiaire;
use App\Entity\Demande_;
use App\Repository\Demande_Repository;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Environment;

class CommentaireService
{
    private Demande_Repository $demande_repository;
    private MailerService $mailerService;
    private string $emailRegionPaiement;
    private string $emailRegionInstruction;
    private string $emailPrestataireInstruction;
    private string $mailerAddressFrom;

    public const AUDITEUR = 'Auditeur';
    public const RENOVATEUR = 'Rénovateur';
    public const CONSEILLER = 'Conseiller H&E';
    public const REGION_PAIEMENT = 'Région Paiement';
    public const REGION_INSTRUCTION = 'Région Instruction';
    public const PRESTATAIRE_INSTRUCTION = 'Prestataire Instruction';

    private const RECIPIENT_CODE_REGION = 1;
    private const RECIPIENT_CODE_PRESTATAIRE_INSTRUCTION = 2;

    public function __construct(
        Demande_Repository $demande_repository,
        MailerService $mailerService,
        string $emailRegionPaiement,
        string $emailRegionInstruction,
        string $emailPrestataireInstruction,
        string $mailerAddressFrom,
    ) {
        $this->demande_repository = $demande_repository;
        $this->mailerService = $mailerService;
        $this->emailRegionPaiement = $emailRegionPaiement;
        $this->emailRegionInstruction = $emailRegionInstruction;
        $this->emailPrestataireInstruction = $emailPrestataireInstruction;
        $this->mailerAddressFrom = $mailerAddressFrom;
    }

    /**
     * Get form list of recipients for comments
     */
    public function searchRecipientFormList(int $demandeId, int $demandeType): array
    {
        $recipients = $this->findAllRecipient(
            $demandeId,
            $demandeType,
            $this->emailRegionPaiement,
            $this->emailRegionInstruction,
            $this->emailPrestataireInstruction
        );

        $list = [];

        if (!empty($recipients['auditeurEmail'])) {
            $list[self::AUDITEUR] = $recipients['auditeurEmail'];
        }
        if (!empty($recipients['renovateurEmail'])) {
            $list[self::RENOVATEUR] = $recipients['renovateurEmail'];
        }
        if (!empty($recipients['conseillerEmail'])) {
            $list[self::CONSEILLER] = $recipients['conseillerEmail'];
        }
        if (!empty($recipients['regionPaiement'])) {
            $list[self::REGION_PAIEMENT] = $this->emailRegionPaiement;
        }
        if (!empty($recipients['regionInstruction'])) {
            $list[self::REGION_INSTRUCTION] = $this->emailRegionInstruction;
        }
        if (!empty($recipients['prestataireInstruction'])) {
            $list[self::PRESTATAIRE_INSTRUCTION] = $this->emailPrestataireInstruction;
        }

        return $list;
    }

    /**
     * Get email data for comment sending
     */
    public function findEmailData(
        int $demandeId,
        int $demandeType,
        ?Beneficiaire $beneficiaire,
        string $fromMailerAddress
    ): array {
        if (!$demandeId || !$demandeType || !$beneficiaire) {
            return [];
        }

        $typeLabel = Demande_::$demandeType[$demandeType] ?? '';

        $from = $fromMailerAddress;
        $subject = 'Demande ' . $typeLabel . ' n°' . $demandeId . ' – '
            . strtoupper($beneficiaire->getNom()) . ' ' . ucfirst($beneficiaire->getPrenom()) . ' : commentaire';
        $contentType = 'text/plain';
        $contentFooter = 'Merci de bien vouloir répondre en vous connectant sur la plateforme "Chèque éco-énergie Normandie"';

        return [
            'from' => $from,
            'subject' => $subject,
            'contentType' => $contentType,
            'contentFooter' => $contentFooter
        ];
    }

    /**
     * Send email with comment
     */
    public function sendEmailComment(string $content, array $recipient, array $emailData): void
    {
        $subject = $emailData['subject'];
        $contentType = $emailData['contentType'];
        $content .= ($emailData['contentFooter'] ?? '') ? "\r\n\r\n" . $emailData['contentFooter'] : '';

        foreach ($recipient as $item) {
            $this->mailerService->sendGeneriqueEmail(
                subject: $subject,
                body: $content,
                from: $this->mailerAddressFrom,
                address: $item,
                contentType: $contentType
            );
        }
    }
    private function findAllRecipient(
        int $demandeId,
        int $demandeType,
        string $regionPaiementEmail,
        string $regionInstructionEmail,
        string $prestataireInstructionEmail
    ): array {
        $recipient = [];
        $recipientRegions = $this->findRecipientByType(
            self::RECIPIENT_CODE_REGION,
            $regionPaiementEmail,
            $regionInstructionEmail,
            $prestataireInstructionEmail
        );
        $recipientInstructeur = [];

        if (!$demandeId || !$demandeType) {
            return $recipient;
        }

        switch ($demandeType) {
            case Demande_::DEMANDE_AUDIT_ENERGIE_TYPE:
            case Demande_::DEMANDE_AUDIT_ENERGIE_REGION_TYPE:
            case Demande_::DEMANDE_MISE_A_JOUR_AUDIT_ENERGIE_TYPE:
                $recipient = $this->demande_repository->findRecipientForAudit($demandeId, $demandeType);
                $recipientInstructeur = $this->findRecipientByType(
                    self::RECIPIENT_CODE_PRESTATAIRE_INSTRUCTION,
                    $regionPaiementEmail,
                    $regionInstructionEmail,
                    $prestataireInstructionEmail
                );
                break;
            case Demande_::DEMANDE_AUDIT_NUMERIQUE_TYPE:
                $recipient = $this->demande_repository->findRecipientForAudit($demandeId, $demandeType);
                break;
            case Demande_::DEMANDE_TRAVAUX_TYPE:
                $recipient = $this->demande_repository->findRecipientForTravaux($demandeId);
                $recipientInstructeur = $this->findRecipientByType(
                    self::RECIPIENT_CODE_PRESTATAIRE_INSTRUCTION,
                    $regionPaiementEmail,
                    $regionInstructionEmail,
                    $prestataireInstructionEmail
                );
                break;
            default:
                break;
        }

        return array_merge($recipient, $recipientRegions, $recipientInstructeur);
    }

    /**
     * Find recipients by type
     */
    private function findRecipientByType(
        int $type,
        string $regionPaiementEmail,
        string $regionInstructionEmail,
        string $prestataireInstructionEmail
    ): array {
        $recipient = [];

        if (self::RECIPIENT_CODE_REGION === $type) {
            $recipient = [
                'regionPaiement' => $regionPaiementEmail,
                'regionInstruction' => $regionInstructionEmail,
            ];
        } elseif (self::RECIPIENT_CODE_PRESTATAIRE_INSTRUCTION === $type) {
            $recipient = [
                'prestataireInstruction' => $prestataireInstructionEmail
            ];
        }

        return $recipient;
    }
}
