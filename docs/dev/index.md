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
