WP-Quick-Install 1.2.7.1
================

WP Quick Install est un script permettant d'installer WordPress en seul clic (téléchargement, décompression, installation de plugins, création base de données, etc...). 

Pour le mettre en place, téléchargez l'archive et rendez-vous sur le fichier *wp-quick-install/index.php*

Changelog
================

1.2.7.1
-----------

* Ajout de la balise robots avec "noindex, nofollow".

1.2.7
-----------

* Possibilité d'ajouter des extensions Premium en mettant les archives dans le dossier *plugins* de *wp-quick-install*
* Possibilité d'activer les extensions après l'installation
* Suppression automatique de l'extension Hello Dolly
* Possibilité d'ajouter un thème et de l'activer après l'installation
* Possibilité de supprimer les thèmes Tweety Eleven et Tweenty Ten

1.2.6
-----------

* Correction d'un bug JavaScript en cas de non utilisation du fichier data.ini

1.2.5
-----------

* Possibilité de supprimer le contenu ajouter par défaut après l'installation de WordPress (article, page et liens).
* Possibilité d'ajouter des pages sans passer par l'administration à partir du fichier data.ini 
* Mise à jour du fichier data.ini

1.2.4
-----------

* Ajout de deux options de debug : *Afficher les erreurs à l'écran* (WP_DEBUG_DISPLAY) et *Ecrire les erreurs dans un fichier de log* (WP_DEBUG_LOG)

1.2.3
-----------

* Correction d'un bug sur le paramètre d'activation du SEO
* Suppression automatique des fichiers licence.txt et readme.html

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
