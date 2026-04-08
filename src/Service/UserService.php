<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * User Service for managing user operations
 * Replaces FOSUserBundle functionality in Symfony 5.4+
 */
class UserService
{
    public function __construct(
        private readonly EntityManagerInterface      $entityManager,
        private readonly UserRepository              $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly RequestStack                $requestStack,
    )
    {
    }

    /**
     * Create a new user with hashed password
     */
    public function createUser(
        string $username,
        string $email,
        string $plainPassword,
        array  $roles = [User::PARAM_ROLE_MEMBER]
    ): User
    {
        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setRoles($roles);

        $hashedPassword = $this->hashPassword($user, $plainPassword);
        $user->setPassword($hashedPassword);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    /**
     * Update user password
     */
    public function updateUserWithPassword(User $user): void
    {
        $hashedPassword = $this->hashPassword($user, $user->getUsername());
        $user->setPassword($hashedPassword);
        $user->setDateModif(new \DateTime());

        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    /**
     * Hash password for a user
     */
    public function hashPassword(User $user, string $plainPassword): string
    {
        return $this->passwordHasher->hashPassword($user, $plainPassword);
    }

    /**
     * Check if password is valid for user
     */
    public function isPasswordValid(User $user, string $plainPassword): bool
    {
        return $this->passwordHasher->isPasswordValid($user, $plainPassword);
    }

    /**
     * Find user by username (active users only)
     */
    public function findByUsername(string $username): ?User
    {
        return $this->userRepository->findOneByUsername($username);
    }

    /**
     * Find user by email (active users only)
     */
    public function findActiveByEmail(string $email): ?User
    {
        return $this->userRepository->findActiveByEmail($email);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->userRepository->findOneBy(['email' => $email]);
    }

    /**
     * Find all users with a specific role
     *
     * @return User[]
     */
    public function findByRole(string $role): array
    {
        return $this->userRepository->findByRole($role);
    }

    /**
     * Find all active users
     *
     * @return User[]
     */
    public function findAllActive(): array
    {
        return $this->userRepository->findAllActive();
    }

    /**
     * Add role to user
     */
    public function addRole(User $user, string $role): void
    {
        $user->addRole($role);
        $user->setDateModif(new \DateTime());
        $this->entityManager->flush();
    }

    /**
     * Remove role from user
     */
    public function removeRole(User $user, string $role): void
    {
        $user->removeRole($role);
        $user->setDateModif(new \DateTime());
        $this->entityManager->flush();
    }

    /**
     * Deactivate user
     */
    public function deactivateUser(User $user): void
    {
        $user->setEnabled(false);
        $user->setDateInactif(new \DateTime());
        $user->setDateModif(new \DateTime());
        $this->entityManager->flush();
    }

    /**
     * Activate user
     */
    public function activateUser(User $user): void
    {
        $user->setEnabled(true);
        $user->setDateModif(new \DateTime());
        $this->entityManager->flush();
    }

    /**
     * Increment failed login attempts
     */
    public function incrementFailedConnection(User $user, int $maxAttempts = 5): bool
    {
        $count = ($user->getCountFailedConnection() ?? 0) + 1;
        $user->setCountFailedConnection($count);

        if ($count >= $maxAttempts) {
            $this->deactivateUser($user);
            return true; // User deactivated
        }

        $this->entityManager->flush();
        return false;
    }

    /**
     * Reset failed login attempts
     */
    public function resetFailedConnection(User $user): void
    {
        $user->setCountFailedConnection(0);
        $user->setLastLogin(new \DateTime());
        $this->entityManager->flush();
    }

    /**
     * Delete user
     */
    public function deleteUser(User $user): void
    {
        $this->entityManager->remove($user);
        $this->entityManager->flush();
    }

    /**
     * Save user without password hashing
     */
    public function saveUser(User $user): void
    {
        $user->setDateModif(new \DateTime());
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    /**
     * @param string $token
     * @return User|null
     */
    public function findByConfirmationToken(string $token): ?User
    {
        return $this->userRepository->findOneBy(['confirmationToken' => $token]);
    }

    public function registerUser(User $user, string $plainPassword): void
    {
        $hashedPassword = $this->hashPassword($user, $plainPassword);
        $user->setPassword($hashedPassword);
        $user->setEmailCanonical($user->getEmail());
        $user->setUsernameCanonical($user->getUsername());
        $user->setEnabled(false);
        $user->setIsFranceConnect(false);
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    public function confirmUser(User $user): void
    {
        $user->setConfirmationToken(null);
        $user->setEnabled(true);
        $this->entityManager->flush();
    }

    public function handlePasswordReset(User $user): void
    {
        $user->setPassword($this->passwordHasher->hashPassword($user, $user->getPassword()));
        $user->setConfirmationToken(null);
        $user->setPasswordRequestedAt(null);
        $user->setEnabled(true);
        $this->entityManager->flush();
    }

    /**
     * Process AJAX data for user list
     */

    public function getDataForListAjax(): array
    {
        if (!empty($_POST)) {

            $dataSQL['param'] = [];
            $columnWhere = "";
            $arrayColumnWhere = [];

            /* START of $_POST variables coming from datatable */
            $draw = $_POST["draw"]; //Counter used by DataTables to ensure that the Ajax returns from server-side processing requests are drawn in sequence
            $orderByColumnIndex = $_POST['order'][0]['column']; //Index of the sorting column (0 index based)
            $orderBy = $_POST['columns'][$orderByColumnIndex]['data']; //Get name of the sorting column from its index
            $orderType = $_POST['order'][0]['dir']; //ASC or DESC
            $start = $_POST["start"]; //Paging first record indicator
            $length = $_POST['length']; //Number of records that the table can display in the current draw
            /* END of $_POST variables */


            /* START INIT of column search */
            $columnWhereTmp = [];
            for ($i = 0; $i < count($_POST['columns']); $i++) {
                if ('' != ($_POST['columns'][$i]['search']['value'])) {
                    $columnWhereTmp[] = $_POST['columns'][$i]['search']['value'];
                }
            }
            /* END INIT of column search */

            /* START of search */
            if (!empty($_POST['search']['value'])) {

                for ($i = 0; $i < count($_POST['columns']); $i++) {
                    $globalSearch = $_POST['columns'][$i]['data'];
                    if ('action' != $globalSearch) {
                        $dataSQL['param'][$globalSearch] = "%" . $_POST['search']['value'] . "%";

                        if ($globalSearch == 'last_login') {
                            $arrayColumnWhere[] =
                                "DATE_FORMAT(u.last_login, '%d/%m/%Y') LIKE :" . $globalSearch;
                        } else {
                            $arrayColumnWhere[] =
                                "u." . $globalSearch . " LIKE :" . $globalSearch;
                        }
                    }
                }

                $columnWhere = implode(" OR ", $arrayColumnWhere);

            } elseif (!empty($columnWhereTmp)) {

                for ($i = 0; $i < count($_POST['columns']); $i++) {

                    if ('' != ($_POST['columns'][$i]['search']['value'])) {

                        $columnSearch = $_POST['columns'][$i]['data'];
                        $dataSQL['param'][$columnSearch] = "%" . $_POST['columns'][$i]['search']['value'] . "%";

                        if ($columnSearch == 'lastLogin') {
                            $arrayColumnWhere[] =
                                "DATE_FORMAT(u.lastLogin, '%d/%m/%Y') LIKE :" . $columnSearch;
                        } else {
                            $arrayColumnWhere[] =
                                "u." . $columnSearch . " LIKE :" . $columnSearch;
                        }
                    }
                }

                if (!empty($arrayColumnWhere)) {
                    $columnWhere = implode(" AND ", $arrayColumnWhere);
                } else {
                    $columnWhere = "";
                }

            }
            /* END of search */

            return [
                'draw' => $draw,
                'orderBy' => $orderBy,
                'orderType' => $orderType,
                'start' => $start,
                'length' => $length,
                "dataSQL" => $dataSQL,
                'columnWhereTmp' => $columnWhereTmp,
                'columnWhere' => $columnWhere
            ];
        }

        return [];
    }


    /**
     * @param $userIdConnected
     * @param $userIdParam
     * @return void
     */
    public function checkUserSecurity($userIdConnected, $userIdParam): void
    {
        $session = $this->requestStack->getSession();
        $userIdSession = $session->get('login')?->getId();

        if (($userIdConnected != $userIdSession) || ($userIdConnected != $userIdParam) || ($userIdSession != $userIdParam)) {
            throw new AccessDeniedHttpException("Unauthorized access. Please contact the administrator.");
        }
    }

    /**
     * @param $repoUser
     * @param $emailTo
     * @param array $roles
     * @return array
     */
    public function getEmailBcc($repoUser, $emailTo, $roles = array())
    {
        $listEmailBcc = array();
        $listUser = $repoUser->getList($roles, true);

        foreach ($listUser as $item) {
            if ($emailTo != $item->getEmail()) {
                $listEmailBcc[] = $item->getEmail();
            }
        }

        return $listEmailBcc;
    }
}
