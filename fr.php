<?php
if(!defined('HT_ACCESS') or !defined('DATABASE')) { exit; }

/* ---- auth.php ----- */
const L_AUTH_TITLE			= 'Mise en place authentification';
const L_ERROR_FOUND			= 'Une erreur a eu lieu';
const L_MYLOGIN				= 'Utilisateur';
const L_SECRET1				= 'Mot de passe 1';
const L_SECRET2				= 'Mot de passe 2';
const L_SECRET2_PLACEHOLDER	= 'Retapez le mot de passe ci-dessus';
const L_PASSWORDS_MISMATCH	= 'Les deux mots de passe sont différents\nRecommencez';
const L_REALM				= 'Utilisation de Unzip-Me';
/* ---- index.php ---- */
const L_BAD_CONTENT_TYPE	= 'Mauvais Content-Type';
const L_BAD_ZIP				= 'Archive zip corrompue';
const L_FOLDERS_LIST		= 'Liste dossiers';
const L_INDEX_TITLE			= 'Dézippe-moi';
const L_MISSING_TARGET		= 'Le dossier cible n\'existe pas';
const L_MISSING_ZIP_MODULE	= 'Module ZipArchive absent';
const L_NO_TEMPFILE			= 'Impossible d\'ouvrir un fichier temporaire';
const L_NO_ZIP				= 'Pas d\'archive zip';
const L_SUBMIT				= 'Exécuter';
const L_SUCCESS				= 'Archive "%s" dézippée';
const L_TARGET				= 'Dossier cible';
const L_TARGET_PLACEHOLDER	= 'Un chemin depuis la racine de serveur';
const L_TIMEOUT				= 'Délai expiré. Recommencez';
const L_UNWRITABLE_FOLDER	= "Impossible d'écrire dans le dossier :\n%s";
const L_URI					= 'URI archive zip';
const L_URI_PLACEHOLDER		= 'Doit débuter /, http, ftp et finir par .zip'
# vim:ts=4
?>
