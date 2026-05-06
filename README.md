Community Network
____________________________________________________________________
Piattaforma di comunicazione con chat, mappa interattiva e videochiamate.

Requisiti:
    • PHP 8.0+
    • MySQL 5.7+ / MariaDB 10.3+
    • Web server (Apache/Nginx) con mod_rewrite
    • Browser moderno (Chrome/Firefox) per WebRTC
________________________________________________________________________________

Installazione

1. Copiare i file:
	Copiare l'intera cartella nella document root del web server (es. 	`/var/www/html/walker-network/`).

2. Configurare il database
	Modificare `config.php`:
		```php
		define('DB_HOST', 'localhost');
		define('DB_NAME', 'community_network');
		define('DB_USER', 'root');     // ← utente MySQL
		define('DB_PASS', '');         // ← password MySQL
		```

3. Eseguire il setup
	Aprire nel browser: `http://localhost/walker-network/setup.php`

	Questo creerà il database, le tabelle e l'account admin.

	**⚠️ IMPORTANTE: eliminare `setup.php` subito dopo!**

4. Credenziali admin default
	- Username: `admin`
	- Password: `Admin123!`
	- **Cambiare la password dal pannello admin!**

________________________________________________________________________________
________________________________________________________________________________
Struttura file applicazione

```
community-network/
├── config.php          ← Configurazione DB
├── auth.php            ← Helper autenticazione
├── setup.php           ← Setup iniziale (ELIMINA DOPO L'USO)
├── index.php           ← Redirect automatico
├── login.php           ← Pagina login
├── register.php        ← Registrazione (richiede token)
├── logout.php          ← Logout
├── dashboard.php       ← Interfaccia principale
├── admin.php           ← Pannello admin
└── api/
    ├── messages.php    ← Chat API (GET/POST)
    ├── map.php         ← Mappa API (GET/POST/DELETE)
    ├── signals.php     ← WebRTC signaling API
    ├── users.php       ← Lista utenti online
    ├── ping.php        ← Keep-alive / last_seen
    └── tokens.php      ← Gestione token (solo admin)
```
________________________________________________________________________________

Funzionalità

### Chat
	- **Chat globale**: tutti gli utenti online
	- **DM (Direct Message)**: cliccare su un utente nella sidebar → apre 	chat 	privata
	- Polling ogni 2 secondi per nuovi messaggi

### Mappa
	- Basata su **Leaflet.js + OpenStreetMap** (nessuna API key necessaria)
	- Cliccare ovunque sulla mappa per aggiungere un pin con messaggio 	opzionale
	- Tasto destro su un proprio pin → lo rimuove
	- Aggiornamento automatico ogni 15 secondi

### Videochiamate
	- **WebRTC** peer-to-peer (video + audio)
	- Cliccare 📞 su un utente nella sidebar per chiamare
	- Segnalazione via DB (polling ogni 1.5s)
	- Bottoni: muto, disattiva camera, termina chiamata
	- Usa i server STUN di Google (funziona su reti locali + internet)

### Registrazione con token
	- La registrazione richiede un **token di invito** generato dall'admin
	- L'admin genera i token da `admin.php`
	- Ogni token è monouso
	- I token usati mostrano chi li ha usati e quando

### Admin
	- Generare/eliminare token di invito
	- Lista utenti con ultimo accesso
	- Promuovere/declassare admin
	- Eliminare utenti
________________________________________________________________________________
