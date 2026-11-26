# Compatibilité Composer

La branche Git `dev` (`dev-dev` pour Composer) déclare l'alias `1.1.x-dev`, correspondant à la série publiée 1.1, afin de satisfaire les contraintes SemVer des modules consommateurs. Les projets du canal dev exigent explicitement `dev-dev` ; les contraintes de release restent inchangées. Ne pas supprimer cet alias lors d'une promotion sur main. Vérifier la résolution conjointe de tous les modules natifs avant publication (CI Back et CT XT).

Le dépôt Composer de distribution stable déclaré par le manifeste est `https://packages.symsvel.com`. Les sources VCS et les alias de développement restent inchangés.
