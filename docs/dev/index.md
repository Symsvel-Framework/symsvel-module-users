# Users

## Fixtures temporaires du crash-test d'intégration

Ce module déclare les scopes `back`, `bo`, `app` et `front` pour le crash-test global `symsvel create-project --components=all --dev`.

- Symfony : `GET /api/users/ping` doit retourner `{"module":"users","status":"ok"}` sans données métier ni authentification propre au controller.
- SvelteKit : chaque interface fournit `src/routes/users/+page.svelte`, affichant `Page users`. Le synchroniseur copie la source sous `src/lib/modules/users/routes/` et crée le relais `src/routes/users/+page.svelte`.
- Le code métier et ses contrôles d'accès existants restent inchangés.

Ces pages et endpoints sont des sondes d'intégration, pas des interfaces métier définitives. **Limitation provisoire :** les trois scopes Svelte sont déclarés obligatoires par le manifeste ; cette fixture vise uniquement une installation avec tous les composants, pas les installations partielles.

Validation requise : tests automatisés, CI verte, puis crash-test XT (endpoints HTTP et affichage réel des pages BO/App/Front). La présence des fichiers seule ne suffit pas.

## Cohérence ORM et migrations Doctrine (novembre 2026)

Les métadonnées ORM déclarent explicitement `uniq_symsvel_users_email` sur `email` et `enabled DEFAULT true`, conformément à la migration Schema initiale immuable. Cette cohérence évite que `make:migration` propose de supprimer les contraintes ou valeurs par défaut des modules natifs dans les projets utilisateurs. Ne pas réécrire la migration initiale déjà publiée. Validation attendue : CI, puis `make:migration` sur une installation XT avec les modules migrés, en vérifiant l'absence de SQL parasite sur leurs tables.

### Visibilité des tests ignorés

La CI affiche les raisons des tests PHPUnit ignorés avec `--display-skipped` (directement ou via le script Composer QA). Un test ignoré ne devient pas bloquant pour autant : les motifs sont examinés pour distinguer prérequis absents et couverture manquante.

## Authentification App (14/11/2026)

`Auth/UserIdentityProvider` implémente les contrats `PasswordPrincipal` et `PrincipalStatus` du module Auth. Il n'accepte que le contexte `app`, consulte l'entité ORM `User`, vérifie le mot de passe Argon2id et l'état `enabled`, et renvoie un `TokenSubject('app', id)` uniquement pour un utilisateur persistant et actif. La vérification de statut rejette systématiquement le contexte BO. Le fournisseur est enregistré comme service, **sans alias global** : la composition Auth BO/App reste à réaliser dans un composant adapté, sans rendre Users obligatoire en BO seul.

Composer référence `symsvel-framework/module-auth:dev-dev` via le catalogue Satis privé, pour la phase de développement. Les tests unitaires couvrent les contrats et les rejets avant accès BDD. Un test d'intégration ORM SQLite en mémoire couvre connexion réelle, mauvais mot de passe, isolation BO/App et suppression du compte. La configuration ORM de ce test active les lazy objects natifs PHP 8.4. **Aucun endpoint ni firewall n'est ouvert ; la CI de cette passe reste à confirmer.** Les crash-tests multi-SGBD et la désactivation des comptes restent à couvrir.

## Composition des identités par contexte (14/11/2026)

`UserIdentityProvider` déclare désormais `ContextOwnedIdentity::authContext()` = `app` et est enregistré sous le tag `symsvel.auth.identity_provider`. Sa présence est facultative pour Auth et n'est pas requise dans les installations BO seules. CI de cette passe à confirmer.

## Récupération de mot de passe App (15/11/2026)

`UserPasswordResetIdentity` implémente `PasswordResetIdentity` du module Auth pour le seul contexte `app`. Il retrouve un utilisateur actif par email et remplace son empreinte de mot de passe uniquement si elle est Argon2id. L'accès DBAL utilise la connexion partagée pour permettre une transaction globale entre consommation du jeton, changement du mot de passe et révocation des sessions. Le service est enregistré sous `symsvel.auth.password_reset_identity`, sans dépendance d'Auth envers Users. Les endpoints, l'orchestration transactionnelle et les tests d'intégration complets restent à réaliser. CI de cette passe à confirmer.

## Adresse de récupération et contrat Auth (15/11/2026)

Le fournisseur `UserPasswordResetIdentity` implémente désormais `PasswordResetIdentity::recoveryEmail(TokenSubject): ?string`. **Il renvoie intentionnellement `null` tant qu'aucun état persistant de vérification de boîte e-mail n'existe pour les comptes App.** La présence de `email` et `enabled` ne prouve pas la propriété de la boîte. Aucun message de récupération n'est donc envoyé via cet adaptateur ; `PasswordResetDispatch` abandonne après contrôle anti-abus. Les contrats de recherche et de remplacement transactionnel de mot de passe restent inchangés.

Ce comportement temporaire est volontairement fermé et testé dans `UserPasswordResetIdentityRecoveryEmailTest`. Une évolution future devra définir et vérifier le processus de confirmation d'adresse auprès du propriétaire du compte, avec migration et tests correspondants, avant d'autoriser la délivrance des liens. Aucune route HTTP n'est ajoutée. CI et crash-tests locaux à confirmer.

### Correctif CI : provenance du contrat Auth (15/11/2026)

Le nouveau test du contrat `PasswordResetIdentity::recoveryEmail()` a échoué pendant le chargement de `UserPasswordResetIdentity` : l'interface `SymSvel\\Module\\Auth\\Identity\\PasswordResetIdentity` était absente de la dépendance Composer installée. L'interface est présente sur la branche `dev` du dépôt Auth, mais pas sur `main` au moment de l'audit. La version exacte résolue par Satis dans les jobs rouges reste à confirmer.

Pour le canal de développement, `composer.json` donne maintenant priorité au dépôt VCS Auth avant le catalogue Satis, tout en conservant `dev-dev`. La CI affiche `composer show symsvel-framework/module-auth` et vérifie explicitement l'existence de l'interface après `composer install`, avant `composer qa`. Aucun contrat métier, schéma ou service de production n'est modifié. Cette priorité VCS est limitée au travail sur `dev` : à la stabilisation, revenir à une release Auth publiée et indexée dans Satis. **CI à confirmer.**

### Correctif Composer : stabilité des dépendances transitives Auth (15/11/2026)

Après résolution d'Auth depuis sa branche `dev`, Composer a rejeté `symsvel-framework/module-mails:dev-dev` en dépendance transitive : la stabilité `dev` n'est pas autorisée implicitement par les dépendances indirectes lorsque le projet racine conserve son `minimum-stability` par défaut. Auth dépend de Mails et Settings, et Mails dépend lui-même de Settings.

Le `composer.json` du module déclare désormais explicitement, à la racine, `module-auth`, `module-mails` et `module-settings` en `dev-dev` et pointe en priorité sur leurs dépôts Git `dev`, avant le catalogue Satis. Cela autorise uniquement ces dépendances de développement, sans abaisser globalement `minimum-stability`. Le workflow CI affiche les trois versions installées et vérifie toujours la présence de `PasswordResetIdentity`.

**Portée temporaire du canal dev :** ces contraintes explicites sont à remplacer par des versions publiées cohérentes lorsque les trois modules seront stabilisés. Pas de modification du runtime ou du contrat métier. CI de cette correction à confirmer.

## Vérification persistante des e-mails (15/11/2026)

**Décision validée :** l'adresse doit avoir été confirmée avant qu'un lien de récupération du mot de passe puisse y être envoyé, y compris pour le premier administrateur créé pendant le Setup. Le propriétaire de la donnée reste le module Users ; Auth reste responsable des jetons, Mails de l'envoi.

La migration additive `Migration/Version20261115000002.php` ajoute `email_verified_at` (`datetime_immutable`, nullable) à la table du module. La migration initiale publiée n'est pas réécrite. L'entité `User` expose `getEmailVerifiedAt()` et initialise implicitement la nouvelle propriété à `null`. Les comptes existants, ainsi que les nouveaux comptes créés par le Setup ou les constructeurs, restent donc **non vérifiés** tant qu'aucun parcours de confirmation sécurisé n'a persisté une date. Aucun compte n'est vérifié automatiquement à la migration.

`UserPasswordResetIdentity::recoveryEmail()` consulte la connexion DBAL partagée et retourne l'adresse uniquement si le contexte vaut `app`, le compte est actif (`enabled`) **et** `email_verified_at IS NOT NULL`. Sinon, il retourne `null` sans envoi. La recherche du sujet et le remplacement transactionnel du mot de passe ne changent pas.

Tests : `EmailVerificationMigrationTest` vérifie les mutations `Schema` et leur annulation sans toucher aux colonnes préexistantes ; `UserPasswordResetIdentityVerifiedMailboxTest` couvre le refus sans vérification, l'isolation du contexte, le passage à un état vérifié simulé en base puis le refus du compte désactivé.

**Limites du chantier :** la confirmation d'adresse par jeton à usage unique, la persistance sécurisée de la confirmation et la gestion d'un changement d'adresse ne sont **pas encore développées**. Aucun endpoint de demande de récupération n'est ouvert. La CI des nouveaux commits et les crash-tests locaux de la compilation/exécution multi-SGBD restent à confirmer. Ne jamais renseigner `email_verified_at` à partir d'une simple saisie d'adresse ou d'une authentification locale.

## Confirmation de boîte mail App : propriétaire du compte (15/11/2026)

`UserPasswordResetIdentity` implémente également `EmailVerificationIdentity` et est enregistré sous `symsvel.auth.email_verification_identity`. `confirmEmail(subject, emailHash, now)` vérifie le contexte `app`, l'empreinte SHA-256 de l'adresse **actuellement enregistrée**, l'activation du compte et l'absence de confirmation antérieure. La mise à jour DBAL est conditionnelle pour empêcher qu'un changement d'adresse soit validé par un ancien jeton.

Auth consomme le jeton dans la même transaction que cette mise à jour. Aucun compte User n'est confirmé par défaut et le module n'est pas exigé pour une installation BO seule. `UserEmailVerificationTest` vérifie contexte, empreinte, succès unique, désactivation et changement d'adresse.

L'envoi de mail, les endpoints et les crash-tests multi-SGBD restent à développer ; CI à confirmer.

## Correctif CI des tests de confirmation (15/11/2026)

Le test `UserPasswordResetIdentityRecoveryEmailTest` crée désormais sa table `symsvel_users` dans SQLite en mémoire avant la lecture DBAL du fournisseur. Le test de migration utilise `Table::getPrimaryKey()` au lieu de la méthode inexistante `hasPrimaryKey()`. `composer.json` déclare explicitement `doctrine/migrations:^3.9`, indispensable pour charger les migrations du module (`AbstractMigration`) lors des tests. Cette dépendance ne change pas le modèle d'authentification.

Les tests restent isolés de toute base de projet et ne présupposent plus une table absente. Aucune migration publiée ni logique métier de vérification n'a été modifiée dans cette passe. CI et crash-tests locaux à confirmer.

## Adresse App éligible à la demande de confirmation (15/11/2026)

`UserPasswordResetIdentity` expose désormais `EmailVerificationIdentity::pendingVerificationEmail()` pour un utilisateur `app` actif, avec `email_verified_at IS NULL`. Le service interne Auth dispose ainsi de l'adresse réellement enregistrée, sans jamais faire confiance à une adresse fournie par le navigateur. Les comptes déjà vérifiés et les contextes BO sont rejetés. Tests de l'éligibilité avant/après confirmation et de l'isolation des contextes ajoutés.

Users reste facultatif en installation BO seule, sans route App supplémentaire. CI et crash-tests de cette passe à confirmer.

## Diagnostic des accès GitHub privés dans les CI (15/11/2026)

Le workflow `qa.yml` fournit explicitement le secret GitHub Actions `SYMSVEL_READ_TOKEN` à Composer via `COMPOSER_AUTH` (`github-oauth`). Avant installation, il refuse un secret absent et contrôle en lecture, par l'API GitHub, l'accès aux dépôts privés `symsvel-module-auth`, `symsvel-module-mails` et `symsvel-module-settings`. En cas d'absence, d'expiration ou de permissions insuffisantes, la CI échoue avec un diagnostic explicite **avant** `composer install` plutôt qu'un message générique de Composer. La valeur du jeton n'est jamais affichée. Les runners auto-hébergés ne doivent pas dépendre d'une authentification Composer laissée dans leur cache local.

**Configuration à maintenir :** secret `SYMSVEL_READ_TOKEN` lisible dans chacun des trois dépôts GitHub Actions (ou via une configuration de secrets autorisée), avec droits de lecture sur les dépôts privés requis. Un `GITHUB_TOKEN` standard limité au dépôt du workflow ne suffit pas pour ces dépendances inter-dépôts. Un jeton révoqué ou expiré doit être remplacé dans les secrets GitHub, pas en dur dans `composer.json` ou les logs. CI de ce correctif à confirmer ; aucune modification du runtime.

## Décision de vérification e-mail App (16/11/2026)

La confirmation de l'adresse reste **obligatoire** pour les comptes Users/App. Un changement d'adresse rend la nouvelle adresse non vérifiée jusqu'à confirmation. La dispense consentie au développeur lors du Setup local ne concerne que l'unique administrateur initial `ROLE_DEV` du BO et **ne s'applique jamais aux Users**. Les protections de récupération de mot de passe fondées sur `email_verified_at` demeurent actives. Cette règle est validée ; la validation opérationnelle des parcours App demeure distincte des CI unitaires.

## Inscription et activation obligatoirement vérifiée (16/11/2026)

`POST /apiopen/users/register` (Users) reçoit une adresse, un mot de passe et une langue FR/EN, sans session. `UserRegistration` gère la création en DBAL sur `symsvel_users` avec e-mail normalisé, mot de passe Argon2id (12 à 1024 octets), `email_verified_at = NULL` et `enabled = true`. Il consomme avant écriture le quota de `PasswordResetLimiter::allowRegistration` fourni par Auth ; les doublons et requêtes limitées ne modifient jamais les comptes existants. L'API retourne uniformément `202 {status:accepted}` pour toute entrée valide, sans révéler l'existence du compte.

Si un compte vient d'être créé, `UserRegistrationController` déclenche `EmailVerificationDispatch` d'Auth pour émettre le jeton à usage unique et envoyer le mail via Mails à l'adresse **enregistrée** ; un échec de transport après insertion laisse un compte en attente. Le parcours existant de renvoi `POST /apiopen/auth/email-verification/request` permet de récupérer la situation sans remettre à zéro le mot de passe. La confirmation se fait par `POST /apiopen/auth/email-verification/complete`, qui consomme le jeton et persiste la confirmation dans une transaction DBAL commune.

`UserIdentityProvider` refuse désormais **l'authentification ainsi que la validité des sessions** tant que `email_verified_at` est nul. Cette règle s'applique aussi aux comptes existants non vérifiés : aucune connexion par simple possession de mot de passe. Le contexte BO est toujours rejeté. Tests : insertion/normalisation/Argon2id/doublons/limiteur SQLite, requêtes invalides, et contrôle réel de la connexion avant et après confirmation.

Les écrans App et la configuration HTTPS de `auth.app_verify_email_url` seront validés dans les passes correspondantes. Aucun compte App n'est vérifié lors de l'inscription ni du Setup BO. Les CT finaux restent nécessaires ; mail SMTP, CORS et migrations multi-SGBD n'ont pas encore été crash-testés sur une installation.

### Correction de tests (16/11/2026)

La réflexion du chemin Route s'effectue par l'argument de l'attribut (Symfony 8), pas via une méthode getPath inexistante sur l'attribut.

### Interface d'inscription du module Users (16/11/2026)

Users fournit désormais `app/src/routes/register/+page.svelte`, synchronisé vers `/register` par Tooling sur une installation App. Le formulaire envoie `POST /apiopen/users/register` au moyen du **client natif App** `api(...,{public:true})`, sans jeton ni cookie. La saisie confirme le mot de passe et applique les limites 12 à 1024 octets, validées une seconde fois côté serveur. Le message de succès est volontairement générique pour les comptes existants et les requêtes limitées.

Les libellés sont externalisés dans `app/src/lib/modules/users/registration.ts`, en FR/EN avec repli EN. Après inscription, l'utilisateur passe par `/verify-email` et `/login` (pages natives de l'App). Aucun compte non vérifié n'obtient de session App. Tests Vitest de la langue et des mots de passe inclus.

**Compatibilité des installations partielles :** l'ancien manifeste Users imposait `front` pour une simple page de ping. Or Users est nécessaire pour App, tandis que Front reste facultatif (`symsvel create-project --components=app`). Le scope `front` a donc été supprimé du manifeste pour ne pas rendre Front obligatoire sans raison ; Back, BO et App restent disponibles.

La CI Users ajoute un job indépendant qui assemble son écran dans le dépôt `symsvel-app/dev`, puis valide TypeScript/Svelte, Prettier, le build statique et Vitest. Cette validation ne remplace pas les crash-tests HTTP/SMTP/multi-SGBD locaux.

### Test d'intégration complet App sans navigateur (16/11/2026)

`AppRegistrationVerificationFlowTest` utilise les vrais services DBAL/ORM Auth, Users, Settings et Mails sur SQLite en mémoire. Il vérifie l'inscription avec réponse uniforme, l'émission d'un mail à l'adresse persistée, le refus de connexion avant confirmation, la confirmation à usage unique, la connexion, la vérification `/api/auth/me`, la rotation du refresh, la déconnexion et le rejet des anciens jetons. Un nouvel enregistrement du même e-mail ne doit modifier ni le compte ni les messages envoyés. Ce test n'utilise pas un serveur HTTP réel ni les domaines de déploiement ; il complète, mais ne remplace pas, les CT.

### Tests Playwright du formulaire d'inscription (16/11/2026)

Le test `app/tests/registration.e2e.ts` est copié dans l'App assemblée par le job CI Users. Chromium vérifie que le formulaire `/register` envoie une requête `/apiopen/users/register` sans Authorization, présente un accusé de réception uniforme et bloque localement les mots de passe discordants avant tout appel réseau. Le serveur est simulé dans ces deux scénarios ; le test de bout en bout PHP SQLite reste la preuve de l'intégration serveur, et les CT HTTP réels restent nécessaires.


### Confidentialité des réponses publiques lorsque SMTP échoue (16/11/2026)

Après insertion d'un compte, un échec du transport Mails ou de sa configuration ne doit **jamais** transformer `POST /apiopen/users/register` en réponse HTTP 500 propre aux seuls comptes nouvellement créés. Le contrôleur renvoie la même réponse `202 {"status":"accepted"}` que pour les doublons et quotas atteints ; il consigne l'exception dans les logs serveur, sans adresse dans le message applicatif. Le compte créé reste `email_verified_at = NULL` et ne peut pas se connecter ; il peut demander un nouveau lien via Auth après expiration du quota anti-abus.

Le test DBAL/ORM intégré `AppRegistrationVerificationFlowTest` simule une panne SMTP et vérifie la réponse uniforme sur les trois demandes publiques App : inscription Users, renvoi de confirmation Auth, et récupération du mot de passe Auth. Il assure également que l'inscription reste persistée mais non vérifiée. Cette vérification automatisée ne remplace pas les crash-tests SMTP, HTTP et multi-SGBD en projet assemblé. Les requêtes de confirmation restent distinctes de la réalisation du changement d'adresse e-mail, qui n'est pas encore implémenté.

### Confirmation App depuis le domaine Setup (16/11/2026)

Le test intégré `AppRegistrationVerificationFlowTest` utilise désormais les métadonnées `local_domain` du Setup pour construire le lien HTTPS `/verify-email`. Il ne configure plus `auth.app_verify_email_url` dans Settings. Le vrai service Auth `EmailVerificationUrlResolver` lit `.symsvel/project.json` à la racine du workspace, et ne consulte jamais le domaine d'une requête. Les quatre étapes inscription, confirmation, login et session restent exercées par le test, avec conservation des garanties SMTP et anti-énumération. Le domaine local doit être servi en HTTPS pour que le lien soit navigable. Vérifier la CI Users sur Auth/dev, puis le crash-test SMTP/HTTP XT.


## CRUD Users depuis le BO (16/11/2026)

`UserAdministration` porte les mutations de comptes depuis le BO, séparées du quota anti-abus de l'inscription anonyme. La création conserve un hash Argon2id, `email_verified_at = NULL` et `enabled = true`, puis Auth tente d'envoyer la confirmation. Le contrôleur protégé `BoUsersController` expose `GET/POST /apibo/users`, `PATCH/DELETE /apibo/users/{id}` avec les ACL `users:list/create/update/delete` fournies par `BoAccessGate` dans ACLs (implémentation Admins). Le BO peut modifier l'adresse et l'activation ; un changement d'adresse annule sa confirmation et toutes les sessions App, puis déclenche une nouvelle confirmation. La suppression révoque les sessions et efface les jetons de confirmation/récupération dans une transaction commune. Les réponses ne contiennent pas de hash ni de secrets ; un envoi SMTP impossible est journalisé et visible seulement par `confirmation_sent=false` pour cet administrateur authentifié.

Les éventuels rôles Users/App restent hors de ce CRUD (aucun rôle User dédié dans cette entité). Les tests/CI et le crash-test XT restent obligatoires.

### Écran BO Users (16/11/2026)

La route BO `/users` consomme la nouvelle API via `boApi` et contrôle `currentBo` avant affichage. Elle fournit la création avec mot de passe initial, la liste, l'édition de l'adresse ou du statut, et la suppression confirmée. Les nouveaux comptes restent non vérifiés et l'UI indique les échecs d'envoi de confirmation, sans exposer d'exception SMTP. La limite du mot de passe est de 12 à 1024 octets et aucune session App n'est créée par le BO. Les textes sont externalisés en FR/EN avec tests Vitest dans `bo/tests/users.test.ts` ; CI d'assemblage et CT local requis.

### Indicateur de mot de passe sur App Register et BO Users (16/11/2026)

Les formulaires de création importent `passwordStrength()` depuis les clients App et BO natifs, affichent un indicateur progressif et les limites exactes validées par le serveur (12 à 1024 octets UTF-8, pas de NUL). Le score indique la diversité, sans imposer une nouvelle règle de composition. Les messages sont traduits dans les modules, et les contrôles finaux existants restent appliqués avant POST. L'attribut HTML `minlength=12` a été retiré car il comptait les caractères JS et rejetait certains mots de passe Unicode pourtant valides en octets. CI assemblées et CT navigateur requis.

### Intégrité des liens lors d'un changement d'adresse (16/11/2026)

Le service `UserAdministration` supprime, dans la même transaction DBAL que le changement d'adresse, les jetons App de confirmation et de récupération de mot de passe encore associés au compte. Ils ne peuvent ainsi pas être utilisés après une modification, même si l'ancienne adresse est réattribuée plus tard. Les sessions restent toutes révoquées et `email_verified_at` repasse à `NULL`. `UserAdministrationTest` utilise les vraies tables Auth en SQLite pour vérifier création Argon2id, absence de vérification implicite, nettoyage et suppression. CI et CT XT requis.

### Transactions uniques Auth / Users (16/11/2026)

Les mutations BO de rôle, d'état, d'adresse ou de suppression utilisent désormais `TokenSessionRevoker::revokeWith()` comme **unique propriétaire de transaction** : modification du compte et révocation des sessions dans le même `DbalTokenStore`. Cela évite une transaction DBAL extérieure au verrou SQLite du TokenStore, tout en conservant le rollback sur erreur et les contrôles ACL. Le contrat public `revokeAll()` demeure inchangé. Les crash-tests sur les quatre moteurs et la CI de l'assemblage sont nécessaires avant clôture.

### Déclaration de navigation BO (16/11/2026)

Le module fournit `bo/src/lib/modules/users/navigation.ts`, qui décrit sa page `/users` avec libellés FR/EN et sa permission `users:list`. Le dashboard du BO hôte importe les déclarations des modules effectivement installés ; il n'affiche cette entrée que si `GET /apibo/users` réussit via `boApi`, ce qui vérifie le droit côté PHP avant toute navigation. Aucune route Symfony centrale ni règle d'accès Svelte parallèle n'est créée. Le test `bo/tests/users.test.ts` valide la déclaration, et la CI de l'assemblage BO doit confirmer l'import. CT XT après mise à jour du BO et des modules.


## Coque BO partagée (16/11/2026)

La page /users conserve uniquement ses formulaires,
tableaux et appels `boApi`. La session, la sidebar, la topbar et la
navigation active sont gérées par le layout hôte de `symsvel-bo`.
La route générée par Tooling reste inchangée et les ACL restent dans les
contrôleurs PHP. Voir `symsvel-bo/docs/bo-ui.md`.


## Composants génériques SymSvel BO (16/11/2026)

Les écrans réutilisent maintenant `Button` (primaire et secondaire) et
`Alert` (succès et erreur) du BO hôte via
`src/lib/components/`. La liste des utilisateurs avec édition en ligne conserve le tableau HTML natif, adapté aux formulaires intégrés.
Les modules fournissent les libellés, états, callbacks et appels `boApi`.
Aucune logique d'ACL n'est déplacée vers les composants.
Voir `symsvel-bo/docs/bo-ui.md`.


## Compatibilité des balises Svelte (16/11/2026)

L'indicateur natif de robustesse du mot de passe utilise la balise HTML non vide
`<meter></meter>`. Une balise autofermante `<meter />` génère un avertissement
Svelte 5 et est interdite dans les routes BO. Le changement ne modifie
ni la validation du mot de passe ni l'API.


## Invitations BO des utilisateurs App (16/11/2026)

Le formulaire BO ne choisit plus de mot de passe pour autrui :
`POST /apibo/users` accepte `email` et `locale`, crée une identité
non vérifiée avec un hash Argon2id aléatoire inaccessible, puis Auth envoie
un mail d'invitation avec jeton séparé et lien de création du vrai mot de passe.
Le frontend ne transmet jamais de mot de passe initial à l'administrateur.
La réponse `invitation_sent` indique un éventuel échec de transport sans
supprimer silencieusement le compte créé.

`POST /apibo/users/{id}/invitations/resend` exige l'ACL BO `users:update`
et une identité active non vérifiée ; il renvoie le lien au mail stocké,
jamais à une adresse fournie par le navigateur. `UserInvitationIdentity`
fournit le contrat optionnel d'Auth et réutilise les écritures transactionnelles
de `UserPasswordResetIdentity` pour confirmer l'email puis remplacer le hash.
Une ancienne adresse invalide automatiquement les jetons App lors de la
modification du compte ; la suppression purge également ces jetons.

L'inscription autonome `POST /apiopen/users/register` et la récupération
de mot de passe des utilisateurs déjà vérifiés restent inchangées.
Le provider App et le mot de passe final demeurent indépendants du BO.
Les CI, l'assemblage Svelte et les CT XT SMTP et multi-SGBD sont requis.


### Écran App d'activation par invitation (16/11/2026)

La route module `app/src/routes/activate/+page.svelte`, synchronisée
vers `/activate`, accepte un jeton opaque de 43 caractères reçu par
e-mail. Elle le retire immédiatement de l'historique de navigation,
impose le mot de passe choisi et sa confirmation (12 à 1024 octets),
puis appelle `POST /apiopen/auth/invitation/complete` sans session.
L'API Auth et Users assurent une consommation unique et la confirmation
simultanée de l'adresse. Le formulaire efface le secret et les mots de
passe après succès. Le login App reste distinct ; aucune session
n'est créée silencieusement. Les textes FR/EN et la validation cliente
sont testés dans `app/tests/invitation.test.ts`.


### UX BO : modal et tableau paginé (16/11/2026)

L'écran Users utilise `Modal` et `DataTable` du BO hôte. La création
ne demande que l'email et envoie une invitation à usage unique. Le mot de
passe n'est jamais choisi par l'administrateur ; l'utilisateur le définit
dans l'App. L'édition est également présentée en modal ; la liste offre
recherche, tri et pagination 10/20/50/100, ainsi que le renvoi du mail pour
un compte actif dont l'adresse n'est pas encore confirmée.

Comme le contrat GET actuel contient au plus 200 lignes, cette pagination
s'exécute côté client comme dans XCore et ne doit pas être confondue avec
une pagination serveur complète. CI et CT XT nécessaires avant validation.
