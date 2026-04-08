<?php

namespace App\Service;

use App\Entity\Partenaire_;
use App\Entity\Partenaire_adresse;
use App\Entity\Partenaire_agence;
use App\Entity\Partenaire_contact;
use App\Entity\Partenaire_identification;
use App\Entity\Partenaire_optionAuditeur;
use App\Entity\Partenaire_optionRenovateur;
use App\Entity\Partenaire_statut;
use App\Entity\Structure_conseiller;
use App\Entity\Structure_permanence;
use App\Entity\User;
use App\Utils\DefaultUtils;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Symfony\Component\Security\Csrf\TokenGenerator\TokenGeneratorInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\SecurityEvents;

/**
 * Service d'administration pour la gestion des utilisateurs et des entités
 */
class AdminService
{

    private \Doctrine\ORM\EntityRepository $repo_partenaire;
    private \Doctrine\ORM\EntityRepository $repo_structure;
    private \Doctrine\ORM\EntityRepository $repo_structureConseiller;
    private \Doctrine\ORM\EntityRepository $repo_structurePermanence;
    private \Doctrine\ORM\EntityRepository $repo_user;

    private string $dateReferenceConvert;
    private string $appRootDossierDataSymfony;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserService $userService,
        private readonly UrlGeneratorInterface $router,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly RequestStack $requestStack,
        private readonly RoleHierarchyInterface $roleHierarchy,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly TokenGeneratorInterface $tokenGenerator,
        private readonly  MailerService $mailerService,
        string $dateReferenceConvert,
        string $appRootDossierDataSymfony,
    ) {
        $this->repo_partenaire = $this->entityManager->getRepository(Partenaire_::class);
        $this->repo_structure = $this->entityManager->getRepository(Structure_conseiller::class);
        $this->repo_structureConseiller = $this->entityManager->getRepository(Structure_conseiller::class);
        $this->repo_structurePermanence = $this->entityManager->getRepository(Structure_permanence::class);
        $this->repo_user = $this->entityManager->getRepository(User::class);
        $this->dateReferenceConvert = $dateReferenceConvert;
        $this->appRootDossierDataSymfony = $appRootDossierDataSymfony;
    }

/* *****************************************************************
 ********************************************************************
                 P U B L I C   F U N C T I O N
 ********************************************************************
 *******************************************************************/
    /**
     * Crée un nouvel utilisateur
     *
     * @param int $type Type d'utilisateur (-1 pour manuel, 0=auditeur, 1=renovateur, 2=conseiller, 3=epci)
     * @param int $id ID de l'entité liée
     * @param string $prenom Prénom de l'utilisateur
     * @param string $nom Nom de l'utilisateur
     * @param string $email Email de l'utilisateur
     * @param string|null $username Nom d'utilisateur personnalisé
     * @param array $roles Rôles de l'utilisateur
     * @return bool
     */
    public function createUser(int $type, ?int $id, string $prenom, string $nom, string $email, ?string $username = null, array $roles = []): bool
    {
        $user = new User();
        if (-1 !== $type) {
            $initial = match ($type) {
                0 => 'A',
                1 => 'R',
                2 => 'C',
                3 => 'E',
                default => ''
            };

            if ($type === 0) {
                $roles[] = User::PARAM_ROLE_AUDITEUR;
            } elseif ($type === 1) {
                $roles[] = User::PARAM_ROLE_RENOVATEUR;
            } elseif ($type === 2) {
                $roles[] = User::PARAM_ROLE_CONSEILLER;
            } elseif ($type === 3) {
                $roles[] = User::PARAM_ROLE_EPCI;
            }

            $username = $initial . DefaultUtils::strPadCustom((string)$id, 5, "0", STR_PAD_LEFT);
        } else {
            if (empty($roles)) {
                $roles[] = User::PARAM_ROLE_MEMBER;
            }
        }

        $userObject = $this->repo_user->findOneBy(['username' => $username]);

        if ($userObject) {
            $this->updateUser(
                $type,
                $id,
                $prenom,
                $nom,
                $email,
                $userObject->isEnabled(),
                $userObject->getDateInactif()
            );
        } else {
            $user->setUsername($username);
            $user->setFirstname($prenom);
            $user->setLastname($nom);
            $user->setEmail($email);
            $user->setRoles($roles);

            if (-1 === $id) {
                // Utilisateur FranceConnect
                $user->setEnabled(true);
                $user->setDateInactif(null);
                $user->setAuteurCreation('Automate');
                $user->setAuteurModif('Automate');
                $user->setIsFranceConnect(true);
                $this->authenticateManually($user);

                $this->entityManager->persist($user);
                $this->entityManager->flush();
            } else {
                // Utilisateur normal - désactivé en attente d'activation
                $user->setEnabled(false);
                $user->setDateInactif(new \DateTime());
                $user->setIsFranceConnect(false);

                $user->setConfirmationToken($this->tokenGenerator->generateToken());
                $user->setPasswordRequestedAt(new \Datetime());

                $this->userService->updateUserWithPassword($user);

                $this->sendEmail(
                    $type,
                    $user->getUsername(),
                    $user->getConfirmationToken(),
                    $user->getEmail(),
                    $roles
                );
            }
        }

        return true;
    }

    /**
     * Met à jour un utilisateur existant
     *
     * @param int $type Type d'utilisateur
     * @param int $id ID de l'entité liée
     * @param string $prenom Prénom de l'utilisateur
     * @param string $nom Nom de l'utilisateur
     * @param string $email Email de l'utilisateur
     * @param bool $enabled État d'activation
     * @param \DateTime|null $dateInactif Date d'inactivation
     * @param array $roles Rôles de l'utilisateur
     */
    public function updateUser(int $type, int $id, string $prenom, string $nom, string $email, bool $enabled, ?\DateTime $dateInactif, array $roles = []): void
    {
        if (-1 !== $type) {
            $initial = '';
            if (0 == $type) $initial = 'A';
            elseif (1 == $type) $initial = 'R';
            elseif (2 == $type) $initial = 'C';
            elseif (3 == $type) $initial = 'E';
            elseif (4 == $type) $initial = 'B';

            $username = $initial . DefaultUtils::strPadCustom((string)$id, 5, "0", STR_PAD_LEFT);
            $userObject = $this->repo_user->findOneBy(['username' => $username]);

            if ($userObject) {
                $emailDatabase = trim($userObject->getEmail());

                $userObject->setFirstname(trim($prenom));
                $userObject->setLastname(trim($nom));
                $userObject->setEmail(trim($email));

                if (trim($email) !== $emailDatabase) {
                    $userObject->setEnabled(false);
                    $userObject->setDateInactif(new \DateTime());

                    $userObject->setConfirmationToken($this->tokenGenerator->generateToken());
                    $userObject->setPasswordRequestedAt(new \Datetime());
                } else {
                    $userObject->setEnabled($enabled);
                }

                if (!$enabled && in_array($type, [0, 1, 2, 3])) {
                    $userObject->setDateInactif($dateInactif ?? new \DateTime());
                }

                $this->entityManager->persist($userObject);
                $this->entityManager->flush();

                if (trim($email) !== $emailDatabase) {
                    $this->sendEmail(
                        $type,
                        $userObject->getUsername(),
                        $userObject->getConfirmationToken(),
                        $userObject->getEmail(),
                        $roles
                    );
                }
            } else {
                $this->createUser($type, $id, $prenom, $nom, $email, null, $roles);
            }
        } elseif (-1 === $type) {
            $userObject = $this->repo_user->find($id);
            if ($userObject) {
                $userObject->setFirstname($prenom);
                $userObject->setLastname($nom);
                $userObject->setEmail($email);
                $userObject->setRoles($roles);

                $this->entityManager->persist($userObject);
                $this->entityManager->flush();
            }
        }
    }

    /**
     * @param string $role
     * @param $user
     * @return bool
     */
    public function isGranted(string $role, $user): bool
    {
        // Get all user roles expanded by the hierarchy
        $reachableRoles = $this->roleHierarchy->getReachableRoleNames($user->getRoles());

        // Check if the requested role is in the expanded roles
        return in_array($role, $reachableRoles, true);
    }


    /**
     * @param $userId_session
     * @param $userId_param
     */
    public function checkAdmin($userId_session, $userId_param): void
    {
        if ($userId_session != $userId_param) {
            throw new AccessDeniedHttpException("Unauthorized access. Please contact the administrator.");
        }
    }

    /**
     * @param $importId
     * @param $type
     * @return void
     */
    public function persistImport($importId, $type): void
    {
        $typeKey = explode(' | ', $type);

        if ('0' == $typeKey[0]) {
            $arrayTypePartenaire = array(
                '0' => '0 | auditeur',
                '1' => '1 | renovateur',
                ''  => ''
            );

            $arrayCivilite = array(
                '0' => '0 | madame',
                '1' => '1 | monsieur',
                ''  => ''
            );

            $arrayTypeActeur = array(
                '0' => '0 | architecte',
                '1' => '1 | moe',
                '2' => '2 | entreprise',
                '3' => '3 | artisan',
                '4' => '4 | cooperative',
                '5' => '5 | autre',
                ''  => ''
            );

            $listPartenaire = $this->repo_partenaire->findAllCustom();
            $arrayListPartenaire = array_map('current', $listPartenaire);
        } elseif ('1' == $typeKey[0]) {
            $listConseiller = $this->repo_structureConseiller->findAllCustom();
            $arrayListConseiller = array_map('current', $listConseiller);
        } elseif ('2' == $typeKey[0]) {
            $listPermanence = $this->repo_structurePermanence->findAllCustom();
            $arrayListPermanence = array_map('current', $listPermanence);
        }

        $dateReference = $this->dateReferenceConvert;
        $flag = true;
        $object = array();
        $row = 0;

        if (($handle = fopen($this->appRootDossierDataSymfony . "import/" . $importId . "_" . $typeKey[1] . ".txt", "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
                if ($flag) {
                    $flag = false;
                    continue;
                }
                $col = count($data);
                $row++;
                for ($i = 0; $i < $col; $i++) {
                    if ('0' == $typeKey[0]) {
                        if ('0' == $data[21]) $dateInactifPartenaire =  new \DateTime();
                        else $dateInactifPartenaire = \DateTime::createFromFormat('d/m/Y', $dateReference);

                        $object[$row] = array(
                            "type"              => $arrayTypePartenaire[trim($data[1])],
                            "raison_sociale"    => trim($data[2]),
                            "thematique"        => $arrayTypePartenaire[trim($data[1])],
                            "siret"             => trim($data[3]),
                            "domicile_bancaire" => trim($data[4]),
                            "titulaire"         => trim($data[5]),
                            "iban"              => trim($data[6]),
                            "bic"               => trim($data[7]),
                            "type_acteur"       => $arrayTypeActeur[trim($data[8])],
                            "complement"        => trim($data[9]),
                            "adresse1"          => trim($data[10]),
                            "adresse2"          => trim($data[11]),
                            "code_postal"       => trim($data[12]),
                            "ville"             => trim($data[13]),
                            "departement"       => trim($data[14]),
                            "tel_fixe"          => trim($data[15]),
                            "tel_mobile"        => trim($data[16]),
                            "email"             => trim($data[17]),
                            "site_internet"     => trim($data[18]),
                            "adresse_complement" => trim($data[19]),
                            "date_rattachement" => \DateTime::createFromFormat('d/m/Y', $data[20]),
                            "enabled"           => trim($data[21]),
                            "date_inactif"      => $dateInactifPartenaire,
                            "contact1"          => array(
                                "civilite"  => $arrayCivilite[trim($data[22])],
                                "nom"       => trim($data[23]),
                                "prenom"    => trim($data[24]),
                                "titre"     => trim($data[25]),
                                "telephone" => trim($data[26]),
                                "email"     => trim($data[27])
                            ),
                            "contact2"          => array(
                                "civilite"  => $arrayCivilite[trim($data[28])],
                                "nom"       => trim($data[29]),
                                "prenom"    => trim($data[30]),
                                "titre"     => trim($data[31]),
                                "telephone" => trim($data[32]),
                                "email"     => trim($data[33])
                            ),
                            "agence1"          => array(
                                "nom"           => trim($data[34]),
                                "adresse"       => trim($data[35]),
                                "code_postal"   => trim($data[36]),
                                "ville"         => trim($data[37]),
                                "telephone"     => trim($data[38]),
                                "email"         => trim($data[39])
                            ),
                            "agence2"          => array(
                                "nom"           => trim($data[40]),
                                "adresse"       => trim($data[41]),
                                "code_postal"   => trim($data[42]),
                                "ville"         => trim($data[43]),
                                "telephone"     => trim($data[44]),
                                "email"         => trim($data[45])
                            ),
                            "agence3"          => array(
                                "nom"           => trim($data[46]),
                                "adresse"       => trim($data[47]),
                                "code_postal"   => trim($data[48]),
                                "ville"         => trim($data[49]),
                                "telephone"     => trim($data[50]),
                                "email"         => trim($data[51])
                            )
                        );
                    } elseif ('1' == $typeKey[0]) {
                        $dateInactifConseiller = \DateTime::createFromFormat('d/m/Y', $dateReference);

                        $object[$row] = array(
                            "structure_id"      => trim($data[0]),
                            "nom"               => trim($data[1]),
                            "prenom"            => trim($data[2]),
                            "telephone"         => trim($data[3]),
                            "email"             => trim($data[4]),
                            "intervention"      => array(
                                "14"    => trim($data[5]),
                                "27"    => trim($data[6]),
                                "50"    => trim($data[7]),
                                "61"    => trim($data[8]),
                                "76"    => trim($data[9])
                            ),
                            "enabled"           => '1',
                            "date_inactif"      => $dateInactifConseiller
                        );
                    } elseif ('2' == $typeKey[0]) {
                        $object[$row] = array(
                            "structure_id"      => trim($data[0]),
                            "nom"               => trim($data[1]),
                            "adresse"           => trim($data[2]),
                            "code_postal"       => trim($data[3]),
                            "ville"             => trim($data[4]),
                            "telephone"         => trim($data[5]),
                            "email"             => trim($data[6]),
                            "jour_ouverture"    => trim($data[7]),
                            "horaire"           => trim($data[8])
                        );
                    }
                }
            }
            fclose($handle);

            $object = array_values($object);
            foreach ($object as $item) {
                if ('0' == $typeKey[0]) {
                    // Remove doublon
                    if (!in_array(mb_strtolower($item["raison_sociale"]), $arrayListPartenaire)) {
                        $partenaire = new Partenaire_();
                        $partenaire->setType($item["type"]);

                        $partenaireIdentification = new Partenaire_identification();
                        $partenaireIdentification->setRaisonSociale($item["raison_sociale"]);
                        $partenaireIdentification->setThematique($item["thematique"]);
                        $partenaireIdentification->setSiret($item["siret"]);
                        $partenaire->setPartenaireIdentification($partenaireIdentification);

                        if ('0 | auditeur' == $item["type"]) {
                            $partenaireOptionAuditeur = new Partenaire_optionAuditeur();
                            $partenaireOptionAuditeur->setDomicileBancaire($item["domicile_bancaire"]);
                            $partenaireOptionAuditeur->setTitulaire($item["titulaire"]);
                            $partenaireOptionAuditeur->setIban($item["iban"]);
                            $partenaireOptionAuditeur->setBic($item["bic"]);
                            $partenaire->setPartenaireOptionAuditeur($partenaireOptionAuditeur);
                        } elseif ('1 | renovateur' == $item["type"]) {
                            $partenaireOptionRenovateur = new Partenaire_optionRenovateur();
                            $partenaireOptionRenovateur->setTypeActeur($item["type_acteur"]);
                            $partenaireOptionRenovateur->setComplement($item["complement"]);
                            $partenaire->setPartenaireOptionRenovateur($partenaireOptionRenovateur);
                        }

                        $partenaireAdresse = new Partenaire_adresse();
                        $partenaireAdresse->setAdresse1($item["adresse1"]);
                        $partenaireAdresse->setAdresse2($item["adresse2"]);
                        $partenaireAdresse->setCodePostal($item["code_postal"]);
                        $partenaireAdresse->setVille($item["ville"]);
                        $partenaireAdresse->setDepartement($item["departement"]);
                        $partenaireAdresse->setTelFixe($item["tel_fixe"]);
                        $partenaireAdresse->setTelMobile($item["tel_mobile"]);
                        $partenaireAdresse->setEmail($item["email"]);
                        $partenaireAdresse->setSiteInternet($item["site_internet"]);
                        $partenaireAdresse->setComplement($item["adresse_complement"]);
                        $partenaire->setPartenaireAdresse($partenaireAdresse);

                        $partenaireStatut = new Partenaire_statut();
                        $partenaireStatut->setDateRattachement($item["date_rattachement"]);
                        $partenaireStatut->setEnabled($item["enabled"]);
                        $partenaireStatut->setDateInactif($item["date_inactif"]);
                        $partenaire->setPartenaireStatut($partenaireStatut);

                        if (!empty($item["contact1"])) {
                            if (
                                "" != $item["contact1"]["nom"]
                                && "" != $item["contact1"]["titre"]
                            ) {
                                $partenaireContact1 = new Partenaire_contact();
                                $partenaireContact1->setCivilite($item["contact1"]["civilite"]);
                                $partenaireContact1->setNom($item["contact1"]["nom"]);
                                $partenaireContact1->setPrenom($item["contact1"]["prenom"]);
                                $partenaireContact1->setTitre($item["contact1"]["titre"]);
                                $partenaireContact1->setTelephone($item["contact1"]["telephone"]);
                                $partenaireContact1->setEmail($item["contact1"]["email"]);
                                $partenaire->addPartenaireContact($partenaireContact1);
                            }
                        }

                        if (!empty($item["contact2"])) {
                            if (
                                "" != $item["contact2"]["nom"]
                                && "" != $item["contact2"]["titre"]
                            ) {
                                $partenaireContact2 = new Partenaire_contact();
                                $partenaireContact2->setCivilite($item["contact2"]["civilite"]);
                                $partenaireContact2->setNom($item["contact2"]["nom"]);
                                $partenaireContact2->setPrenom($item["contact2"]["prenom"]);
                                $partenaireContact2->setTitre($item["contact2"]["titre"]);
                                $partenaireContact2->setTelephone($item["contact2"]["telephone"]);
                                $partenaireContact2->setEmail($item["contact2"]["email"]);
                                $partenaire->addPartenaireContact($partenaireContact2);
                            }
                        }

                        if (!empty($item["agence1"])) {
                            if (
                                "" != $item["agence1"]["adresse"]
                                && "" != $item["agence1"]["code_postal"]
                                && "" != $item["agence1"]["ville"]
                            ) {
                                $partenaireAgence1 = new Partenaire_agence();
                                $partenaireAgence1->setNom($item["agence1"]["nom"]);
                                $partenaireAgence1->setAdresse($item["agence1"]["adresse"]);
                                $partenaireAgence1->setCodePostal($item["agence1"]["code_postal"]);
                                $partenaireAgence1->setVille($item["agence1"]["ville"]);
                                $partenaireAgence1->setTelephone($item["agence1"]["telephone"]);
                                $partenaireAgence1->setEmail($item["agence1"]["email"]);
                                $partenaire->addPartenaireAgence($partenaireAgence1);
                            }
                        }

                        if (!empty($item["agence2"])) {
                            if (
                                "" != $item["agence2"]["adresse"]
                                && "" != $item["agence2"]["code_postal"]
                                && "" != $item["agence2"]["ville"]
                            ) {
                                $partenaireAgence2 = new Partenaire_agence();
                                $partenaireAgence2->setNom($item["agence2"]["nom"]);
                                $partenaireAgence2->setAdresse($item["agence2"]["adresse"]);
                                $partenaireAgence2->setCodePostal($item["agence2"]["code_postal"]);
                                $partenaireAgence2->setVille($item["agence2"]["ville"]);
                                $partenaireAgence2->setTelephone($item["agence2"]["telephone"]);
                                $partenaireAgence2->setEmail($item["agence2"]["email"]);
                                $partenaire->addPartenaireAgence($partenaireAgence2);
                            }
                        }

                        if (!empty($item["agence3"])) {
                            if (
                                "" != $item["agence3"]["adresse"]
                                && "" != $item["agence3"]["code_postal"]
                                && "" != $item["agence3"]["ville"]
                            ) {
                                $partenaireAgence3 = new Partenaire_agence();
                                $partenaireAgence3->setNom($item["agence3"]["nom"]);
                                $partenaireAgence3->setAdresse($item["agence3"]["adresse"]);
                                $partenaireAgence3->setCodePostal($item["agence3"]["code_postal"]);
                                $partenaireAgence3->setVille($item["agence3"]["ville"]);
                                $partenaireAgence3->setTelephone($item["agence3"]["telephone"]);
                                $partenaireAgence3->setEmail($item["agence3"]["email"]);
                                $partenaire->addPartenaireAgence($partenaireAgence3);
                            }
                        }

                        $this->entityManager->persist($partenaire);
                        try {
                            $this->entityManager->flush();
                        } catch (\Doctrine\ORM\Exception\ORMException $e) {
                            echo $e->getMessage();
                        }

                        if ('0 | auditeur' == $partenaire->getType()) {
                            $this->createUser(
                                0,
                                $partenaire->getId(),
                                '',
                                $partenaire->getPartenaireIdentification()->getRaisonSociale(),
                                $partenaire->getPartenaireAdresse()->getEmail()
                            );
                        } elseif ('1 | renovateur' == $partenaire->getType()) {
                            $this->createUser(
                                1,
                                $partenaire->getId(),
                                '',
                                $partenaire->getPartenaireIdentification()->getRaisonSociale(),
                                $partenaire->getPartenaireAdresse()->getEmail()
                            );
                        }
                    }
                } elseif ('1' == $typeKey[0]) {
                    // Remove doublon
                    if (!in_array(mb_strtolower($item["nom"] . $item["prenom"]), $arrayListConseiller)) {
                        $structure = $this->repo_structure->find($item["structure_id"]);

                        if ($structure) {
                            $conseiller = new Structure_conseiller();
                            $conseiller->setNom($item["nom"]);
                            $conseiller->setPrenom($item["prenom"]);
                            $conseiller->setTelephone($item["telephone"]);
                            $conseiller->setEmail($item["email"]);
                            $conseiller->setEnabled($item["enabled"]);
                            $conseiller->setDateInactif($item["date_inactif"]);

                            $structure->addStructureConseiller($conseiller);
                            $this->entityManager->persist($structure);
                            try {
                                $this->entityManager->flush();
                            } catch (\Doctrine\ORM\Exception\ORMException $e) {
                                echo $e->getMessage();
                            }

                            $list_conseiller = $structure->getStructureConseiller();
                            foreach ($list_conseiller as $row) {
                                $this->createUser(
                                    2,
                                    $row->getId(),
                                    $row->getPrenom(),
                                    $row->getNom(),
                                    $row->getEmail()
                                );
                            }
                        }
                    }
                } elseif ('2' == $typeKey[0]) {
                    // Remove doublon
                    if (!in_array(mb_strtolower($item["nom"]), $arrayListPermanence)) {
                        $structure = $this->repo_structure->find($item["structure_id"]);

                        if ($structure) {
                            $permanence = new Structure_permanence();
                            $permanence->setNom($item["nom"]);
                            $permanence->setAdresse($item["adresse"]);
                            $permanence->setCodePostal($item["code_postal"]);
                            $permanence->setVille($item["ville"]);
                            $permanence->setTelephone($item["telephone"]);
                            $permanence->setEmail($item["email"]);
                            $permanence->setJourOuverture($item["jour_ouverture"]);
                            $permanence->setHoraire($item["horaire"]);

                            $structure->addStructurePermanence($permanence);
                            $this->entityManager->persist($structure);
                            try {
                                $this->entityManager->flush();
                            } catch (\Doctrine\ORM\Exception\ORMException $e) {
                                echo $e->getMessage();
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Authentifie manuellement un utilisateur (pour FranceConnect)
     */
    public function authenticateManually(User $user): void
    {
        $currentToken = $this->tokenStorage->getToken();
        $identity = [
            'gender'    => $currentToken->getAttribute('gender'),
            'address'   => $currentToken->getAttribute('address') ?? null
        ];
        $this->requestStack->getSession()->set('identity', $identity);

        //Handle getting or creating the user entity likely with a posted form
        // The third parameter "main" can change according to the name of your firewall in security.yml
        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
        $this->tokenStorage->setToken($token);

        // If the firewall name is not main, then the set value would be instead:
        $this->requestStack->getSession()->set('_security_main', serialize($token));

        // Fire the login event manually
        $event = new InteractiveLoginEvent($this->requestStack->getCurrentRequest(), $token);
        $this->eventDispatcher->dispatch($event, SecurityEvents::INTERACTIVE_LOGIN);
    }

    /* *****************************************************************
    ********************************************************************
                    P R I V A T E   F U N C T I O N
    ********************************************************************
    *******************************************************************/
    /**
     * Envoie un email d'activation de compte
     *
     * @param int $type Type d'utilisateur
     * @param string $username Nom d'utilisateur
     * @param string $userToken Token de confirmation
     * @param string $adminEmail Email du destinataire
     * @param array $roles Rôles de l'utilisateur
     * @return void
     */
    private function sendEmail(int $type, string $username, string $userToken, string $adminEmail, array $roles = []): void
    {
        $userType = match ($type) {
            -1 => match ($roles[0] ?? '') {
                'ROLE_MEMBER' => 'Bénéficiaire',
                'ROLE_CONSEILLER' => 'Conseiller',
                'ROLE_INSTRUCTEUR' => 'Instructeur',
                'ROLE_INSTRUCTEUR_UP' => 'Instructeur UP',
                'ROLE_AUDITEUR' => 'Auditeur',
                'ROLE_RENOVATEUR' => 'Rénovateur',
                'ROLE_CLIENT' => 'Client',
                'ROLE_EPCI' => 'EPCI',
                'ROLE_ADMIN' => 'Administrateur',
                'ROLE_AUTOMATE' => 'Automate',
                'ROLE_TECHNIQUE' => 'Technique',
                default => 'Bénéficiaire'
            },
            0 => 'Auditeur',
            1 => 'Rénovateur',
            2 => 'Conseiller',
            3 => 'EPCI',
            default => 'Bénéficiaire'
        };

        $confirmationUrl = $this->router->generate('user_resetting_reset', [
            'token' => $userToken
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $context = [
            'username' => $username,
            'type' => $userType,
            'confirmationUrl' => $confirmationUrl
        ];

        $this->mailerService->sendTemplateEmail(
            trim($adminEmail),
            'Activation de votre nouveau compte',
            'BackOffice/Admin/email/activation.html.twig',
            $context
        );
    }
}
