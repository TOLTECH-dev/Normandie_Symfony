<?php

namespace App\Command;

use App\Entity\User;
use App\Security\Password\PasswordExpirationPolicy;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'normandie:expirePasswords',
    description: 'Impose le renouvellement immédiat des mots de passe (compromission suspectée ou avérée)',
)]
class ExpirePasswordsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PasswordExpirationPolicy $passwordExpirationPolicy
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp(<<<'HELP'
Reco-15 / ANSSI PG-078 R26 : en cas de compromission suspectée ou avérée, rend immédiatement
expirés les mots de passe des comptes ciblés. Les comptes soumis à l'expiration périodique
(paramètre security_password_expiration_roles) devront choisir un nouveau mot de passe à leur
prochaine requête sur le back-office.

Les rôles sont recherchés tels qu'enregistrés sur les comptes, sans la hiérarchie de security.yaml :
--role=ROLE_CLIENT ne cible pas les administrateurs.

Sans --force, la commande affiche seulement le nombre de comptes concernés.

  php bin/console normandie:expirePasswords --role=ROLE_ADMIN --role=ROLE_CLIENT
  php bin/console normandie:expirePasswords --user=identifiant --force
HELP)
            ->addOption('role', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Rôle ciblé (option répétable)')
            ->addOption('user', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Identifiant ciblé (option répétable)')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Applique réellement l\'expiration');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $roles = $input->getOption('role');
        $usernames = $input->getOption('user');

        if ([] === $roles && [] === $usernames) {
            $io->error('Préciser au moins une option --role ou --user.');

            return Command::INVALID;
        }

        $users = $this->findTargetedUsers($roles, $usernames);
        $subjectUsers = array_filter($users, fn (User $user): bool => $this->passwordExpirationPolicy->appliesTo($user));

        $io->table(['Comptes ciblés', 'Soumis à l\'expiration périodique'], [[\count($users), \count($subjectUsers)]]);

        if (\count($subjectUsers) < \count($users)) {
            $io->note('Les comptes non soumis à l\'expiration périodique ne seront pas contraints : pour eux, recourir à une réinitialisation forcée.');
        }

        if (!$input->getOption('force')) {
            $io->warning('Simulation : relancer avec --force pour appliquer.');

            return Command::SUCCESS;
        }

        foreach ($subjectUsers as $user) {
            $user->setPasswordChangedAt(null);
        }
        $this->entityManager->flush();

        $io->success(sprintf('%d mot(s) de passe expiré(s).', \count($subjectUsers)));

        return Command::SUCCESS;
    }

    /**
     * @param string[] $roles
     * @param string[] $usernames
     *
     * @return User[]
     */
    private function findTargetedUsers(array $roles, array $usernames): array
    {
        $queryBuilder = $this->entityManager->getRepository(User::class)->createQueryBuilder('u');
        $conditions = $queryBuilder->expr()->orX();

        // Rôles sérialisés (type Doctrine "array") : le nom entre guillemets évite qu'un rôle en
        // cible un autre dont il est le préfixe (ROLE_INSTRUCTEUR / ROLE_INSTRUCTEUR_UP).
        foreach (array_values($roles) as $index => $role) {
            $conditions->add($queryBuilder->expr()->like('u.roles', ':role' . $index));
            $queryBuilder->setParameter('role' . $index, '%"' . strtoupper($role) . '"%');
        }

        if ([] !== $usernames) {
            $conditions->add($queryBuilder->expr()->in('u.username', ':usernames'));
            $queryBuilder->setParameter('usernames', $usernames);
        }

        return $queryBuilder->where($conditions)->getQuery()->getResult();
    }
}
