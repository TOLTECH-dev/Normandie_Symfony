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

1. **Vérifier que l'analyse est active** sur l'environnement :
   `SECURITY_ANTIVIRUS_ENABLED=true` et `SECURITY_ANTIVIRUS_DSN` renseigné dans `.env.recette.local`.

2. **Vérifier que le service répond**, depuis le serveur applicatif :
   ```bash
   echo -n "PING" | socat - UNIX-CONNECT:/var/run/clamav/clamd.ctl   # ou TCP:127.0.0.1:3310
   # réponse attendue : PONG
   ```

3. **Fabriquer le fichier de test** (chaîne officielle EICAR, inoffensive) :
   ```bash
   printf 'X5O!P%%@AP[4\\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*' > /tmp/eicar.pdf
   ```

4. **Le déposer depuis l'application**, par exemple comme avis d'imposition d'une demande d'audit
   énergétique, ou comme RIB de partenaire.

   Résultat attendu : le formulaire refuse le dépôt avec le message « Ce fichier a été reconnu
   comme dangereux par l'antivirus », aucun fichier n'est écrit dans le dossier des données, une
   ligne « Fichier infecté refusé » apparaît dans les journaux, et les administrateurs reçoivent
   l'alerte.

5. **Vérifier qu'un dépôt normal fonctionne toujours** : déposer un PDF ordinaire et contrôler
   qu'il est accepté, puis consultable.

6. **Contrôler la durée** d'un dépôt de 5 à 10 Mo : l'analyse ajoute quelques centaines de
   millisecondes. Au-delà d'une seconde, revoir le dimensionnement avec DRI.

## Si le service devient indisponible

Par défaut (`SECURITY_ANTIVIRUS_REFUSE_WHEN_UNAVAILABLE=true`), les dépôts sont refusés tant que
l'analyse est impossible, avec le message « Le dépôt est momentanément impossible ». C'est un choix
délibéré : accepter des fichiers non analysés désactiverait la mesure sans que personne ne s'en
aperçoive. L'arrêt de clamd doit donc être supervisé côté DRI.
