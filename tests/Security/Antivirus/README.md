# Analyse antivirale des dépôts — essais

Vulnérabilité 5 / recommandation 9 de l'audit ALMOND.

## Ce que couvrent les tests automatisés

Ils tournent partout, sans ClamAV, grâce à un faux service (`faux_clamd.php`) lancé dans un
processus séparé :

| Test | Ce qu'il vérifie |
|---|---|
| `ClamavScannerTest` | le dialogue avec clamd : commande INSTREAM, découpage du fichier, fichier sain, fichier infecté, réponse incomprise, service injoignable, fichier trop gros |
| `FichierSansVirusValidatorTest` | le refus du dépôt, l'alerte aux administrateurs, et le comportement quand l'analyse est impossible |
| `DepotAnalyseIntegrationTest` | la chaîne complète : contrainte posée sur l'entité → analyse → refus |
| `CouvertureDepotsTest` | qu'aucun des 33 points de dépôt n'échappe à l'analyse |

**Aucun de ces tests n'écrit la signature EICAR sur le disque** : l'antivirus du poste de
développement mettrait le fichier en quarantaine et le test échouerait. C'est le faux service qui
décide du verdict.

## Essai avec un vrai fichier EICAR, en recette

À faire une fois ClamAV installé, avant la mise en production. C'est l'essai qu'ALMOND a mené lors
de l'audit et qu'un contre-audit refera.

0. **Avant d'activer quoi que ce soit : la supervision.** `SECURITY_ANTIVIRUS_REFUSE_WHEN_UNAVAILABLE`
   vaut `true`, donc un arrêt de clamd qui passerait inaperçu bloquerait **tout dépôt de fichier**
   sur la plateforme. La sonde Nagios de l'hébergeur est en place au 06/11/2026 ; s'assurer qu'elle
   interroge le service (`PING`/`PONG`) et non la seule présence du processus, et que son alerte
   parvienne à quelqu'un qui agit.

1. **Vérifier que l'analyse est active** sur l'environnement : `SECURITY_ANTIVIRUS_ENABLED=true` et
   `SECURITY_ANTIVIRUS_DSN` renseigné dans **`.env.local`** — le serveur n'a pas de
   `.env.recette.local`, la recette tournant dans l'environnement `prod`.

2. **Vérifier que le service répond**, depuis le serveur applicatif. `socat` n'est pas installé :
   ```bash
   exec 3<>/dev/tcp/127.0.0.1/3310 && printf 'PING\n' >&3 && head -c 4 <&3; exec 3>&-
   # réponse attendue : PONG
   ```

   **Contrôler au passage le `StreamMaxLength` de clamd** : `SECURITY_ANTIVIRUS_MAX_STREAM_SIZE`
   (20 Mio) doit lui rester **strictement inférieur**, sinon clamd coupe le flux et l'analyse
   échoue — ce qui, le refus étant bloquant, se traduit par un dépôt impossible et non par un
   fichier accepté sans contrôle.
   ```bash
   grep -i StreamMaxLength /etc/clamav/clamd.conf
   ```

3. **Fabriquer le fichier de test** (chaîne officielle EICAR, inoffensive) :
   ```bash
   printf 'X5O!P%%@AP[4\\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*' > /tmp/eicar.pdf
   ```

4. **Le déposer depuis l'application**, par exemple comme avis d'imposition d'une demande d'audit
   énergétique, ou comme RIB de partenaire.

   Résultat attendu : le formulaire refuse le dépôt avec le message « Ce fichier a été reconnu
   comme dangereux par l'antivirus : son dépôt est refusé. », aucun fichier n'est écrit dans le
   dossier des données, et une ligne `Fichier infecté refusé` apparaît dans les journaux.

   **En recette, l'alerte aux administrateurs ne part pas, et c'est voulu.**
   `AlerteAntivirusMailer` emprunte la voie ordinaire — seul le code MFA passe par la voie `mfa` —
   or `MAILER_DSN=null://null` y jette le courrier. C'est heureux : le mailer parcourt les comptes
   `ROLE_SUPER_ADMIN` et `ROLE_ADMIN` de la base, qui est une copie de la production, et alerterait
   donc de vrais administrateurs sur un faux incident. **La preuve à retenir en recette est la
   ligne de journal, pas le courriel.** L'envoi de l'alerte ne se vérifie qu'en production, ou en
   local où les destinataires sont forcés sur `contact@toltech.fr`
   (`config/packages/dev/mailer.yaml`).

5. **Vérifier qu'un dépôt normal fonctionne toujours** : déposer un PDF ordinaire et contrôler
   qu'il est accepté, puis consultable.

6. **Contrôler la durée** d'un dépôt de 5 à 10 Mo : l'analyse ajoute quelques centaines de
   millisecondes. Au-delà d'une seconde, revoir le dimensionnement avec DRI.

## Si le service devient indisponible

Par défaut (`SECURITY_ANTIVIRUS_REFUSE_WHEN_UNAVAILABLE=true`), les dépôts sont refusés tant que
l'analyse est impossible, avec le message « Le dépôt est momentanément impossible ». C'est un choix
délibéré : accepter des fichiers non analysés désactiverait la mesure sans que personne ne s'en
aperçoive. L'arrêt de clamd doit donc être supervisé côté DRI.
