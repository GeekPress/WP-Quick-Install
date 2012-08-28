WP-Quick-Install 1.2.2
================

WP Quick Install est un script permettant d'installer WordPress en seul clic (téléchargement, décompression, installation de plugins, création base de données, etc...). 

Pour le mettre en place, téléchargez l'archive et rendez-vous sur le fichier *wp-quick-install/index.php*

Changelog
================

1.2.2
-----------

* Suppression de toutes les fonctions exec()
* Dézip de WordPress et des plugins avec la class ZipArchive de PHP
* Utilisation des fonctions scandir() et rename() pour déplacer les fichiers de WordPress

1.2.1
-----------

* Vérification des droits d'écriture sur le dossier parent
* Ajout d'un lien vers l'admin et le site en cas de succès

1.2
-----------

* Possibilité de pré-configuré le formulaire à l'aide d'un fichier data.ini


1.1
-----------

* Optimisation diverses du code


1.0
-----------

* Commit Initial
