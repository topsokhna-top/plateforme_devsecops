# Portfolio Cybersécurité — Guide de démarrage

## Structure
```
cybersecurite/
├── index.html          → toute la page (sections ancrées)
├── css/style.css        → styles + thèmes clair/sombre
├── js/script.js          → toggle thème, menu mobile, effet de déchiffrement, formulaire
├── php/contact.php       → traitement du formulaire de contact
└── assets/images/       → photos/images
```

## 1. Personnaliser le contenu
Cherche `[` dans `index.html` pour retrouver tout le texte à remplacer.

Sections à compléter :
- **Hero** : nom, rôle (le texte qui se "déchiffre"), phrase d'accroche
- **Profil** : parcours, infos clés (formation, spécialisation, dispo)
- **Compétences** : ajuste le statut de chaque item (`dot-done` / `dot-progress` / `dot-planned`
  dans `index.html`, sur la classe de l'icône `<i class="dot ...">`)
- **Certifications** : liste réelle, avec badge `badge-done` / `badge-progress` / `badge-planned`
- **Projets & CTF** : remplace les 3 cartes ; le badge du haut (`badge-done`/`badge-progress`)
  indique le statut du challenge
- **Parcours** : le "journal de bord" façon trace de signal
- **Contact** : email, LinkedIn, GitHub, profil TryHackMe/HackTheBox

## 2. Ajouter une photo
Dépose l'image dans `assets/images/profile-placeholder.jpg`.

## 3. Configurer le formulaire de contact
Mêmes réglages que le portfolio développeur — voir `php/contact.php` :
remplace `ton.email@example.com` et `no-reply@tondomaine.com`.

## 4. Tester en local
```bash
cd cybersecurite
php -S localhost:8000
```
Puis ouvre `http://localhost:8000`.

## Sécurité — ce qui a été corrigé et à savoir avant déploiement

Le formulaire de contact et le serveur ont été durcis :

- **CSRF (double-submit cookie)** : un jeton aléatoire est généré côté JS, déposé
  dans un cookie et dans un champ caché du formulaire. `contact.php` vérifie que
  les deux correspondent avant de traiter la requête.
- **Rate limiting par IP** (fichier, sans base de données) : 5 envois max par
  tranche de 15 minutes, et 15 secondes minimum entre deux envois. Les compteurs
  sont stockés dans `php/storage/ratelimit/` (protégé par son propre `.htaccess`,
  inaccessible depuis le web).
- **Anti-injection d'en-têtes email** : tout caractère de contrôle (`\r`, `\n`, etc.)
  dans les champs nom/email/sujet fait rejeter la requête — empêche qu'un
  attaquant détourne le formulaire pour envoyer du spam via ton adresse.
- **Nettoyage sans casser le texte** : les champs sont débarrassés des balises
  HTML et caractères de contrôle, mais plus sur-échappés (`&amp;` n'apparaîtra
  plus dans les emails reçus).
- **En-têtes de sécurité HTTP** (`​.htaccess` à la racine) : Content-Security-Policy,
  X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy,
  HSTS, et redirection automatique HTTP → HTTPS.
- **Anti-listing de dossier** (`Options -Indexes`) et blocage direct des fichiers
  `.json` / `.log` / `.md`.

### ⚠️ À faire après l'upload chez l'hébergeur

1. Vérifie que le dossier `php/storage/ratelimit/` est accessible en écriture
   par le serveur web (`chmod 755` généralement suffisant ; certains hébergeurs
   mutualisés demandent `775`). Si ce n'est pas le cas, le formulaire continue
   de fonctionner mais sans rate limiting (fail-open volontaire pour ne pas
   bloquer le site).
2. Confirme que le certificat HTTPS est actif **avant** de garder la redirection
   forcée dans `.htaccess`, sinon le site deviendrait inaccessible.
3. Si ton hébergeur n'autorise pas les fichiers `.htaccess` (rare), demande-lui
   d'appliquer les mêmes en-têtes au niveau de la configuration serveur.
4. Un `.htaccess` suppose un serveur **Apache**. Si tu déploies sur Nginx,
   dis-le-moi : les mêmes règles s'écrivent différemment (bloc `server {}`).

## Ce qui différencie ce portfolio du premier (développeur)
- Palette et typographie différentes (Space Grotesk / IBM Plex, accent cyan-signal)
- Eyebrows de section en offsets hexadécimaux (`0x00`, `0x01`...) plutôt qu'en commentaires de code
- Effet de **déchiffrement** du rôle dans le hero (texte qui se décode depuis des caractères aléatoires)
- Section Compétences façon **scan report** avec statuts (maîtrisé / en cours / à venir)
- Section **Certifications** dédiée avec badges de statut
- Projets présentés comme des **fiches d'incident/CTF** plutôt que des fichiers de code
- Parcours en **trace de signal** plutôt qu'en "log de commits"

La structure de fichiers et les conventions (CSS variables, JS modulaire, PHP) restent
les mêmes que le premier portfolio pour rester cohérent si les deux sont dans le même dépôt.
