<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ArchiveLot;
use App\Entity\ArchiveLotBeneficiaire;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ArchiveLotBeneficiaire>
 */
class ArchiveLotBeneficiaireRepository extends ServiceEntityRepository
{
    /** Lignes par instruction INSERT. */
    private const TAILLE_PAQUET = 500;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ArchiveLotBeneficiaire::class);
    }

    /**
     * Insère les comptes d'un lot en masse.
     *
     * Par DBAL et non par l'ORM : une campagne porte plusieurs milliers de
     * comptes, et autant d'entités en mémoire pour des lignes qui ne seront
     * relues que par la commande suivante n'apporterait rien.
     *
     * @param list<array{user_id: int, declencheur: string, date_eligibilite: ?string, nb_dossiers: int}> $comptes
     *
     * @return int le nombre de lignes insérées
     *
     * @throws DBALException
     */
    public function insererComptes(ArchiveLot $lot, array $comptes): int
    {
        if ([] === $comptes) {
            return 0;
        }

        $connexion = $this->getEntityManager()->getConnection();
        $lotId = $lot->getId();

        if (null === $lotId) {
            throw new \LogicException('Le lot doit être enregistré avant que ses comptes le soient.');
        }

        $inserees = 0;

        foreach (array_chunk($comptes, self::TAILLE_PAQUET) as $paquet) {
            $valeurs = [];
            $parametres = [];

            foreach ($paquet as $i => $compte) {
                $valeurs[] = sprintf('(:lot%1$d, :user%1$d, :decl%1$d, :date%1$d, :nb%1$d, :etat%1$d)', $i);
                $parametres['lot' . $i] = $lotId;
                $parametres['user' . $i] = $compte['user_id'];
                $parametres['decl' . $i] = $compte['declencheur'];
                $parametres['date' . $i] = $compte['date_eligibilite'];
                $parametres['nb' . $i] = $compte['nb_dossiers'];
                $parametres['etat' . $i] = ArchiveLotBeneficiaire::ETAT_A_PRODUIRE;
            }

            $sql = 'INSERT INTO archive_lot_beneficiaire '
                . '(lot_id, user_id, declencheur, date_eligibilite, nb_dossiers, etat) VALUES '
                . implode(', ', $valeurs);

            $inserees += (int) $connexion->executeStatement($sql, $parametres);
        }

        return $inserees;
    }

    /**
     * Les comptes d'un lot dans l'état donné.
     *
     * @return list<ArchiveLotBeneficiaire>
     */
    public function findParLotEtEtat(ArchiveLot $lot, string $etat): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.lot = :lot')
            ->andWhere('b.etat = :etat')
            ->setParameter('lot', $lot)
            ->setParameter('etat', $etat)
            ->orderBy('b.userId', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Les identifiants de compte d'un lot, dans l'état donné.
     *
     * @return list<int>
     */
    public function findUserIdsParLotEtEtat(ArchiveLot $lot, string $etat): array
    {
        $lignes = $this->createQueryBuilder('b')
            ->select('b.userId')
            ->where('b.lot = :lot')
            ->andWhere('b.etat = :etat')
            ->setParameter('lot', $lot)
            ->setParameter('etat', $etat)
            ->orderBy('b.userId', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $ligne): int => (int) $ligne['userId'], $lignes);
    }

    /**
     * Comptage par état, pour afficher l'avancement d'un lot.
     *
     * @return array<string, int>
     */
    public function compterParEtat(ArchiveLot $lot): array
    {
        $lignes = $this->createQueryBuilder('b')
            ->select('b.etat AS etat, COUNT(b.id) AS nombre')
            ->where('b.lot = :lot')
            ->setParameter('lot', $lot)
            ->groupBy('b.etat')
            ->getQuery()
            ->getArrayResult();

        $parEtat = [];

        foreach ($lignes as $ligne) {
            $parEtat[(string) $ligne['etat']] = (int) $ligne['nombre'];
        }

        return $parEtat;
    }
}
