# Prompt: crea un nuovo stack containerizzato per la VPS PM-GEST

Copia il blocco qui sotto, **sostituisci i placeholder `<...>`** e incollalo a Claude Code
(aprendo la cartella del progetto da dockerizzare, oppure questa cartella `vps-ops`).

---

```text
Devi preparare un nuovo stack Docker da deployare sulla mia VPS PM-GEST, che usa
Nginx Proxy Manager come reverse proxy. NON hai accesso SSH alla VPS: il tuo output
deve essere (1) i FILE dello stack dentro il repo del progetto e (2) la LISTA ESATTA
di comandi che eseguirò io sulla VPS, più le azioni manuali (DNS, SSL su NPM).

PRIMA di tutto leggi il file `vps-ops/README.md` (architettura e convenzioni della VPS)
e i template in `vps-ops/templates/`. Rispetta le convenzioni alla lettera:
- naming con prefisso ${PROJECT_NAME} su container, immagini, volumi, reti
- nessuna porta pubblicata (instrada NPM)
- rete privata ${PROJECT_NAME}-network + rete esterna `proxy` solo sul container web
- volumi persistenti per DB e contenuti/upload
- web-container con depends_on db condition: service_healthy
- gestione https dietro proxy (X-Forwarded-Proto)

Dati del nuovo progetto:
- PROJECT_NAME (kebab-case, univoco): <mariorossi>
- Tipo: <wordpress | laravel | statico | node | altro>
- Codice sorgente esistente (path locale, se c'è): <es. /Applications/XAMPP/xamppfiles/htdocs/MarioRossi>
- Dominio/i: <mariorossi.it, www.mariorossi.it>
- Note (versione PHP, DB dump da importare, ecc.): <...>

Produci:
1. I file dello stack nel repo (Dockerfile se serve, docker-compose.prod.yml,
   .env.prod.example, conf nginx se serve, install.sh/update.sh), partendo dal
   template vps-ops/templates/<tipo>.
2. Un blocco di comandi copia-incolla per me che, sulla VPS:
   a. crea /opt/docker/stacks/${PROJECT_NAME}/ con dentro app/ (clone del repo),
      il .env compilato e docker-compose.yml (copia del template prod)
   b. genera i segreti mancanti (password DB, APP_KEY se Laravel)
   c. builda e avvia lo stack e verifica lo stato
3. I valori ESATTI da inserire nel Proxy Host di NPM
   (Domain, Forward Hostname = ${PROJECT_NAME}-<web>, Port 80, scheme http; SSL LE + Force SSL).
4. Il record DNS da creare sul registrar: A <dominio> -> 5.249.150.209
   e il promemoria che il certificato SSL su NPM va richiesto SOLO dopo la propagazione DNS.

Se sto importando un sito esistente (es. WordPress già sviluppato):
- includi come copiare/mettere i file esistenti nel container o nel volume
- includi come importare il dump SQL nel container db
- ricordami di aggiornare WP_HOME/WP_SITEURL (o config equivalente) al nuovo dominio.

Non dare per scontato nulla: elenca ogni comando in ordine, idempotente, commentato.
```

---

## Come lo userò (esempio reale)

> "C'è un progetto dentro la cartella htdocs che si chiama `MarioRossi`, è un WordPress.
> Entra, dockerizzalo e preparami tutto per deployarlo sotto la VPS come stack `mariorossi`,
> dominio `mariorossi.it`. Segui `vps-ops/README.md`."

Claude legge le convenzioni, genera i file dallo stack template WordPress, e mi restituisce
i comandi VPS + i valori per NPM + il record DNS. Io eseguo solo quei comandi.
