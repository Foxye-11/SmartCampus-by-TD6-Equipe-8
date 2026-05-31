# SmartCampus-by-TD6-Equipe-8
# Configuration de la Base de données
Le fichier ScriptSQL.txt contient l'ensemble du script SQL pour créer la base de données, avec ses tables, leurs attributs et leurs contraintes. Il crée également le premier administrateur du site.  
Le fichier donnees_demo.sql constitue le script SQL pour insérer des administrateurs, des professeurs et des élèves pour une démonstration.  
## Connexion de test
Si vous avez utilisé les scripts SQL de ce répertoire, alors les login de l'administrateur sont :  
Email : admin@smartcampus.fr  
Mot de passe : Admin1234!  
Pour les étudiants, il y en a 300 générés par défaut avec comme login :  
Email : etudiant(un nombre entre 1 et 300)@etu.smartcampus.fr  
Mot de passe : 1234  
Pour les professeurs, 5 sont générés par défaut :  
Email : prof(nombre entre 1 et 5)@smartcampus.fr  
Mot de passe : 1234
# Configuration du serveur d'hébergement
Sur notre serveur, nous avons un réseau docker interne dédié à ce projet, vous trouverez ci-dessous les différentes composantes/instances de ce réseau
## Docker-compose.yml
Il s'agit du fichier de configuration des containers docker présent sur le réseau interne. Il regroupe les configurations des containers de la base de données (mysql), d'apache et php (image officiel, équivalent à phpmyadmin).
## Fichiers serveurs de l'application
Le code de ce répertoire (à l'intérieur du dossier www) a été fait pour qu'il soit compatible avec localhost. Toutefois vous avez les fichiers serveurs (docker-compose.yml et dockerFile) de l'application. Voici la configuration pour utilisé cette application sur un serveur et non en localhost :

### 1. database.php
Dans www/config/database.php, modifier les macros de connexion à la base de données par :  
define('DB_HOST', getenv('MYSQL_HOST'));  
define('DB_NAME', getenv('MYSQL_DATABASE'));  
define('DB_USER', 'root');  
define('DB_PASS', getenv('MYSQL_ROOT_PASSWORD'));  
define('DB_CHARSET', getenv('MYSQL_CHARSET'));  

### 2. Dossier .env
Créer un dossier au même endroit que le docker-compose.yml (~/smartCampus/ si vous avez clone le git à la racine) appelé .env. Dans ce dossier vous rentrerez les variables d'environnements comme ci-dessous.  
Choississez vos mots de passe et le nom de la base de données (il faut que ce soit le même que celui dans le script SQL de création de la base de données).  
Le nom du conteneur Docker est défini dans votre docker-compose.yml (impératif de mettre le même nom).
  
MYSQL_ROOT_PASSWORD=mon_mdp_secret  
MYSQL_DATABASE=nomDB  
MYSQL_CHARSET=encodage (nécessaire pour être en HTTPS)  
MYSQL_HOST=nom du container de la BDD  

## Nom de domaine
Le nom de domaine a été créé sur le site duckdns.org
## Requête HTTPS
Sur le serveur, nous avons un proxy et reverse proxy avec un container NGINX. Dans la configuration du proxy NGINX, vous devrez inclure le token de votre compte duckdns avec le nom de domaine. Nous avons également un container certbot pour avoir le certificat HTTPS.

Si c'est un serveur à domicile, vous devrez créer des règles sur votre routeur wifi.
### 1. Règle HTTP
Protocole : TCP  
Port interne : 80  
Port externe : 80  
IP externe : Toutes  
Equipement : l'IP du serveur d'hébergement  
### 2. Règle HTTPS
Protocole : TCP  
Port interne : 443  
Port externe : 443  
IP externe : Toutes  
Equipement : l'IP du serveur d'hébergement  
