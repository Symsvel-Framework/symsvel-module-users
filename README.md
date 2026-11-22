# Users

Module SymSvel (manifest schema 1).

## CI (10/11/2026)

`.github/workflows/qa.yml` exécute Composer et les tests du module sur le runner Docker ARM64 d'organisation (`self-hosted`, `linux`, `ARM64`, `symsvel`), avec PHP 8.4 préinstallé. La CI GitHub-hosted originale est conservée et désactivée dans `.github/workflows/qa.yml.stub`. Pour revenir aux runners GitHub, restaurer ce `.stub` sous le nom `qa.yml`. Les jobs locaux nécessitent que le Mac et Docker soient disponibles ; runner réservé aux contributions de confiance. Validation finale soumise à une CI verte.

## Migration Schema portable

La migration initiale est `Migration/Version20200101000230.php`, version immuable. Elle décrit la table `symsvel_users` via Doctrine DBAL `Schema` (sans SQL PostgreSQL spécifique) et conserve les colonnes, contraintes et index de la migration précédente. Le Tooling convertit ces opérations en SQL de la plateforme configurée après le setup. Validation requise : CI, puis crash-tests PostgreSQL et MySQL sur XT. Cette branche dépend du compilateur Tooling déjà mergé.

## Licence MIT et contributions

Ce dépôt est distribué sous [licence MIT](LICENSE). Le code peut être utilisé, modifié et redistribué, y compris dans des projets commerciaux ou propriétaires, dans les conditions de cette licence (notamment la conservation de la notice de copyright et de licence).

**Contribuer :** créer un fork, puis proposer une pull request vers `dev` lorsque cette branche existe, sinon vers la branche par défaut. Expliquer la modification et fournir les tests et la documentation concernés. Les mainteneurs conservent la maîtrise des revues, des validations (CI et crash-tests), de l'intégration et des releases officielles. Une PR ne garantit pas son acceptation.

**Versions officielles :** seules les versions diffusées par l'organisation [Symsvel-Framework](https://github.com/Symsvel-Framework) sont officielles. Les forks sont libres sous MIT, mais ne doivent pas être présentés comme officiellement approuvés par les mainteneurs. Cette distinction n'ajoute aucune restriction aux droits accordés par la licence MIT sur le code.
