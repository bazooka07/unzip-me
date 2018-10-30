# Présentation

*It's a small helper for unzipping zip files directly on a server. The files may be located on the server or on the Cloud*.

Unzip-me est un petit utilitaire en PHP qui permet de dézipper directement sur un serveur une archive Zip.
L'archive doit être soit déjà présente sur le serveur ou pouvoir être téléchargée directement depuis un autre serveur.

# Authentification
A la première utilisation, un contrôle d'authentification est installé, basé sur un module du serveur Apache2.
Un nom d'utilisateur et un mot de passe sont réclamés. Le mot de passe est sauvegardé crypté sauf si le serveur est hébergé chez free.fr.

# Utilisation

Après authentification, une boîte de dialogue est affichée pour saisir le chemin de l'archive Zip et le dossier de destination.

Une liste des dossiers de destination autorisés en écriture peut être affiché en cliquant sur le bouton 'Liste dossiers'.
En même, une liste de suggestions de chemins pour les archives Zip est installé.

# Installation

Téléverser l'ensemble du dossier sur le serveur. Le dossier doit être accessible en écriture.

Le dossier doit contenir au minimum les trois fichiers suivants :

* index.php
* auth.php
* en.php

*Feel free to push requests if your native language is not french*.
