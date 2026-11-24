# Users

> [!IMPORTANT]
> **Vous souhaitez utiliser SymSvel ? Rendez-vous sur [SymSvel CLI](https://github.com/Symsvel-Framework/symsvel-cli).** Ce dépôt est un **composant du framework**, pas un point d'installation autonome. Création de projets, installation des modules et mises à jour passent par la CLI. Les éventuelles commandes Composer, npm ou Symfony décrites ci-dessous concernent le travail des mainteneurs, sauf indication explicite contraire.

> [!WARNING]
> **SymSvel est en cours de développement.** Les fonctionnalités et procédures peuvent évoluer ou présenter des anomalies. **Utilisation à vos risques et périls.**

Module SymSvel (manifest schema 1).

## Intégration continue GitHub-hosted (22/11/2026)

Le workflow public `.github/workflows/qa.yml` utilise désormais **GitHub-hosted Ubuntu 24.04** au lieu des runners Docker du mainteneur. Les tests PHP 8.4 ainsi que les intégrations BO et App SvelteKit/Node.js 24 restent actifs. Les tests d'interface installent Chromium et ses dépendances système lorsque nécessaire.

Les runners auto-hébergés sont réservés à `symsvel-packages` et `symsvel-recipes`, qui restent privés. Une CI verte ne dispense pas des crash-tests locaux.


## Migration Schema portable

La migration initiale est `Migration/Version20200101000230.php`, version immuable. Elle décrit la table `symsvel_users` via Doctrine DBAL `Schema` (sans SQL PostgreSQL spécifique) et conserve les colonnes, contraintes et index de la migration précédente. Le Tooling convertit ces opérations en SQL de la plateforme configurée après le setup. Validation requise : CI, puis crash-tests PostgreSQL et MySQL sur XT. Cette branche dépend du compilateur Tooling déjà mergé.

## Licence MIT et contributions

Ce dépôt est distribué sous [licence MIT](LICENSE). Le code peut être utilisé, modifié et redistribué, y compris dans des projets commerciaux ou propriétaires, dans les conditions de cette licence (notamment la conservation de la notice de copyright et de licence).

**Contribuer :** créer un fork, puis proposer une pull request vers `dev` lorsque cette branche existe, sinon vers la branche par défaut. Expliquer la modification et fournir les tests et la documentation concernés. Les mainteneurs conservent la maîtrise des revues, des validations (CI et crash-tests), de l'intégration et des releases officielles. Une PR ne garantit pas son acceptation.

**Versions officielles :** seules les versions diffusées par l'organisation [Symsvel-Framework](https://github.com/Symsvel-Framework) sont officielles. Les forks sont libres sous MIT, mais ne doivent pas être présentés comme officiellement approuvés par les mainteneurs. Cette distinction n'ajoute aucune restriction aux droits accordés par la licence MIT sur le code.

## Participer et soutenir le projet

SymSvel est un projet en cours de développement. Pour contribuer, proposer une amélioration ou échanger avec le mainteneur : **[xavier@xavier-tezza.fr](mailto:xavier@xavier-tezza.fr)**.

SymSvel vous est utile et vous souhaitez me remercier ? **Offrez-moi une bière ! 🍺** (lien PayPal à compléter avant l'ouverture publique).

## Promotion stable (24/11/2026)

Auth `^1.1` est obligatoire pour `NewPasswordPolicy`. Les autres dépendances natives restent en `^1.0`. Le canal `dev` garde ses contraintes de développement. La CI stable doit utiliser une vraie release Auth, jamais un alias dev. Publication après indexation des dépendances et CI vertes.
