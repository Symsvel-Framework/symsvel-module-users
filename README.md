# Users

Module SymSvel (manifest schema 1).

## CI (10/11/2026)

`.github/workflows/qa.yml` exécute Composer et les tests du module sur le runner Docker ARM64 d'organisation (`self-hosted`, `linux`, `ARM64`, `symsvel`), avec PHP 8.4 préinstallé. La CI GitHub-hosted originale est conservée et désactivée dans `.github/workflows/qa.yml.stub`. Pour revenir aux runners GitHub, restaurer ce `.stub` sous le nom `qa.yml`. Les jobs locaux nécessitent que le Mac et Docker soient disponibles ; runner réservé aux contributions de confiance. Validation finale soumise à une CI verte.

## Migration Schema portable

La migration initiale est `Migration/Version20200101000230.php`, version immuable. Elle décrit la table `symsvel_users` via Doctrine DBAL `Schema` (sans SQL PostgreSQL spécifique) et conserve les colonnes, contraintes et index de la migration précédente. Le Tooling convertit ces opérations en SQL de la plateforme configurée après le setup. Validation requise : CI, puis crash-tests PostgreSQL et MySQL sur XT. Cette branche dépend du compilateur Tooling déjà mergé.
