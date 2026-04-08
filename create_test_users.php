<?php
/**
 * Script de creation des comptes de test - A executer sur le serveur de recette
 * Usage: php create_test_users.php
 *
 * IMPORTANT: Supprimer ce fichier apres utilisation
 */

$users = [
    // gtiger
    ['username' => 'gtiger_client',       'password' => 'dZqzkN8PMqtvCUaEB8Gu@',  'email' => 'gtiger+client@almond.eu',       'role' => 'ROLE_CLIENT',        'firstname' => 'GTiger', 'lastname' => 'Client'],
    ['username' => 'gtiger_instructeur',  'password' => 'XLyB12Ahsb3GQMAziokz!',  'email' => 'gtiger+instructeur@almond.eu',  'role' => 'ROLE_INSTRUCTEUR',   'firstname' => 'GTiger', 'lastname' => 'Instructeur'],
    ['username' => 'gtiger_conseiller',   'password' => '2bPAlwqaRjcsDjZxtZyM&',  'email' => 'gtiger+conseiller@almond.eu',   'role' => 'ROLE_CONSEILLER',    'firstname' => 'GTiger', 'lastname' => 'Conseiller'],
    ['username' => 'gtiger_auditeur',     'password' => 'tnLA4XdjgnvikKZn8ci0_',  'email' => 'gtiger+auditeur@almond.eu',     'role' => 'ROLE_AUDITEUR',      'firstname' => 'GTiger', 'lastname' => 'Auditeur'],
    ['username' => 'gtiger_renovateur',   'password' => 'SY92992DM4T9wCQBZ6uF{',  'email' => 'gtiger+renovateur@almond.eu',   'role' => 'ROLE_RENOVATEUR',    'firstname' => 'GTiger', 'lastname' => 'Renovateur'],
    ['username' => 'gtiger_technique',    'password' => '4uiqH9jZGubwl6dx8IOh}',  'email' => 'gtiger+technique@almond.eu',    'role' => 'ROLE_TECHNIQUE',     'firstname' => 'GTiger', 'lastname' => 'Technique'],
    ['username' => 'gtiger_beneficiaire', 'password' => 'RtvQOlFfA8bNpYzh3LYg$',  'email' => 'gtiger+beneficiaire@almond.eu', 'role' => 'ROLE_MEMBER',        'firstname' => 'GTiger', 'lastname' => 'Beneficiaire'],

    // ggauvrit
    ['username' => 'ggauvrit_client',       'password' => 'g3hPjWGfa4QuVVSk1nb4@',  'email' => 'ggauvrit+client@almond.eu',       'role' => 'ROLE_CLIENT',        'firstname' => 'GGauvrit', 'lastname' => 'Client'],
    ['username' => 'ggauvrit_instructeur',  'password' => 'mCFkIaxifuXBXDF3zyee!',  'email' => 'ggauvrit+instructeur@almond.eu',  'role' => 'ROLE_INSTRUCTEUR',   'firstname' => 'GGauvrit', 'lastname' => 'Instructeur'],
    ['username' => 'ggauvrit_conseiller',   'password' => '1mSwSFp5YFqxspgKc11y&',  'email' => 'ggauvrit+conseiller@almond.eu',   'role' => 'ROLE_CONSEILLER',    'firstname' => 'GGauvrit', 'lastname' => 'Conseiller'],
    ['username' => 'ggauvrit_auditeur',     'password' => '9VhgeGlI6FeQxBFa7HpP_',  'email' => 'ggauvrit+auditeur@almond.eu',     'role' => 'ROLE_AUDITEUR',      'firstname' => 'GGauvrit', 'lastname' => 'Auditeur'],
    ['username' => 'ggauvrit_renovateur',   'password' => '1VocSuYKlZg8ZCh9gR3f{',  'email' => 'ggauvrit+renovateur@almond.eu',   'role' => 'ROLE_RENOVATEUR',    'firstname' => 'GGauvrit', 'lastname' => 'Renovateur'],
    ['username' => 'ggauvrit_technique',    'password' => 'TzWAxYYaYmfrQPhct4qb}',  'email' => 'ggauvrit+technique@almond.eu',    'role' => 'ROLE_TECHNIQUE',     'firstname' => 'GGauvrit', 'lastname' => 'Technique'],
    ['username' => 'ggauvrit_beneficiaire', 'password' => 'N8xNwt2XYQZ7BWjoKAh6$',  'email' => 'ggauvrit+beneficiaire@almond.eu', 'role' => 'ROLE_MEMBER',        'firstname' => 'GGauvrit', 'lastname' => 'Beneficiaire'],
];

echo "-- Script de creation des comptes de test pour normandie_symfony_rec\n";
echo "-- Genere le " . date('Y-m-d H:i:s') . "\n";
echo "-- IMPORTANT: verifier que les username n'existent pas deja avant d'executer\n\n";

foreach ($users as $u) {
    $hash = password_hash($u['password'], PASSWORD_BCRYPT, ['cost' => 13]);
    $roles = serialize([$u['role']]);
    $now = date('Y-m-d H:i:s');

    $sql = sprintf(
        "INSERT INTO user (username, username_canonical, email, email_canonical, password, salt, roles, enabled, firstname, lastname, date_creation, auteur_creation, date_modif, auteur_modif, date_inactif, is_france_connect, count_failed_connection) VALUES ('%s', '%s', '%s', '%s', '%s', NULL, '%s', 1, '%s', '%s', '%s', 'Script recette', '%s', 'Script recette', NULL, 0, 0);",
        $u['username'],
        $u['username'],
        $u['email'],
        $u['email'],
        $hash,
        $roles,
        $u['firstname'],
        $u['lastname'],
        $now,
        $now
    );

    echo $sql . "\n";
}

echo "\n-- Fin du script\n";
