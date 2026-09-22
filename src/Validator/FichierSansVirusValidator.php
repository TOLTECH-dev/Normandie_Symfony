<?php

declare(strict_types=1);

namespace App\Validator;

use App\Security\Antivirus\AlerteAntivirus;
use App\Security\Antivirus\AntivirusIndisponibleException;
use App\Security\Antivirus\AntivirusScanner;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Analyse le fichier déposé et refuse le dépôt si l'antivirus y reconnaît une menace.
 *
 * L'analyse porte sur le fichier temporaire, avant tout enregistrement : un fichier infecté n'est
 * jamais écrit dans le dossier des données.
 *
 * Si l'analyse ne peut pas être menée (service arrêté ou injoignable), le dépôt est refusé par
 * défaut : accepter un fichier non analysé reviendrait à désactiver la mesure sans que personne ne
 * s'en aperçoive. Ce comportement est réglable par environnement.
 */
final class FichierSansVirusValidator extends ConstraintValidator
{
    public function __construct(
        private readonly AntivirusScanner $antivirusScanner,
        private readonly AlerteAntivirus $alerteMailer,
        private readonly LoggerInterface $logger,
        private readonly bool $refuserSiIndisponible
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof FichierSansVirus) {
            throw new UnexpectedTypeException($constraint, FichierSansVirus::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        $chemin = $this->chemin($value);
        if (null === $chemin) {
            return;
        }

        $libelle = $constraint->libelle ?? (string) $this->context->getPropertyName();

        try {
            $resultat = $this->antivirusScanner->analyser($chemin);
        } catch (AntivirusIndisponibleException $indisponible) {
            $this->logger->error('Analyse antivirale impossible', [
                'champ' => $libelle,
                'motif' => $indisponible->getMessage(),
            ]);

            if ($this->refuserSiIndisponible) {
                $this->context->buildViolation($constraint->messageIndisponible)->addViolation();
            }

            return;
        }

        if ($resultat->estSain()) {
            return;
        }

        $this->logger->warning('Fichier infecté refusé', [
            'champ' => $libelle,
            'menace' => $resultat->menace(),
            'nom_client' => $value instanceof UploadedFile ? $value->getClientOriginalName() : null,
        ]);

        $this->alerteMailer->signalerFichierInfecte((string) $resultat->menace(), $libelle);

        $this->context->buildViolation($constraint->message)->addViolation();
    }

    /**
     * Chemin du fichier à analyser, ou null si la valeur n'en désigne aucun (dépôt incomplet :
     * la contrainte Assert\File signale déjà ces cas).
     */
    private function chemin(mixed $value): ?string
    {
        if ($value instanceof UploadedFile) {
            return $value->isValid() ? $value->getPathname() : null;
        }

        if ($value instanceof File) {
            return $value->getPathname();
        }

        if (\is_string($value) && is_file($value)) {
            return $value;
        }

        return null;
    }
}
