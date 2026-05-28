# SmartCampus-by-TD6-Equipe-8

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
