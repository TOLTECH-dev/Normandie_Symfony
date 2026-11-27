<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Demande_auditEnergie;
use App\Entity\Demande_travaux;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Obligation du mandat donné au conseiller (D26013).
 *
 * La règle tient en une phrase, mais elle a deux faces, et c'est la seconde
 * qui est fragile : le mandat est exigé pour DÉPOSER une demande, et il ne
 * doit JAMAIS l'être pour en modifier une qui existe déjà.
 *
 * Les dossiers déposés avant la mise en service n'en portent pas. Les
 * soumettre à la même exigence rendrait toute reprise impossible : un
 * instructeur ne pourrait plus enregistrer la moindre correction sur un
 * dossier déposé régulièrement. Si le second test de ce fichier tombe, c'est
 * tout le parc antérieur qui se bloque, sans message compréhensible pour
 * l'utilisateur.
 *
 * La validation est demandée au validateur réel du projet, et non simulée :
 * c'est le branchement de la contrainte qui est vérifié autant que sa logique.
 */
class MandatObligatoireTest extends KernelTestCase
{
    private ValidatorInterface $validateur;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->validateur = self::getContainer()->get('validator');
    }

    /**
     * Les violations qui portent sur le mandat, à l'exclusion de toutes les
     * autres : une demande vide en déclenche beaucoup, qui ne nous disent
     * rien ici.
     *
     * @return list<string>
     */
    private function violationsSurLeMandat(object $demande): array
    {
        $messages = [];

        foreach ($this->validateur->validate($demande) as $violation) {
            if ('mandat' === $violation->getPropertyPath()) {
                $messages[] = (string) $violation->getMessage();
            }
        }

        return $messages;
    }

    /**
     * Pose un identifiant, ce que le code applicatif ne fait jamais : il n'y a
     * pas de setId, l'identifiant venant de la base. C'est pourtant la seule
     * différence entre un dépôt et une modification.
     */
    private function avecIdentifiant(object $demande, int $id): object
    {
        $propriete = new \ReflectionProperty($demande, 'id');
        $propriete->setAccessible(true);
        $propriete->setValue($demande, $id);

        return $demande;
    }

    /**
     * @return array<string, array{0: class-string}>
     */
    public static function lesDeuxTypesDeDemande(): array
    {
        return [
            'travaux' => [Demande_travaux::class],
            'audit énergétique' => [Demande_auditEnergie::class],
        ];
    }

    /**
     * @dataProvider lesDeuxTypesDeDemande
     *
     * @param class-string $classe
     */
    public function testLeMandatEstExigeAuDepotDUneNouvelleDemande(string $classe): void
    {
        $violations = $this->violationsSurLeMandat(new $classe());

        self::assertCount(1, $violations, $classe . ' : le dépôt sans mandat doit être refusé.');
        self::assertStringContainsString('obligatoire', $violations[0]);
    }

    /**
     * @dataProvider lesDeuxTypesDeDemande
     *
     * @param class-string $classe
     */
    public function testLeMandatNEstPasExigeSurUneDemandeDejaEnregistree(string $classe): void
    {
        $demande = $this->avecIdentifiant(new $classe(), 4321);

        self::assertSame(
            [],
            $this->violationsSurLeMandat($demande),
            $classe . " : un dossier déposé avant la mise en service doit rester modifiable."
        );
    }

    /**
     * @dataProvider lesDeuxTypesDeDemande
     *
     * @param class-string $classe
     */
    public function testUnMandatDejaDeposeSatisfaitLObligation(string $classe): void
    {
        // Le dépôt effectif ne laisse dans l'entité que l'EXTENSION du
        // fichier, dans mandat_url : c'est la convention du projet, et c'est
        // donc elle que la contrainte doit savoir lire.
        $demande = new $classe();
        $demande->setMandatUrl('pdf');
        $demande->setMandatAlt('mandat-signe.pdf');

        self::assertSame(
            [],
            $this->violationsSurLeMandat($demande),
            $classe . ' : un mandat déjà déposé doit satisfaire l\'obligation.'
        );
    }
}
