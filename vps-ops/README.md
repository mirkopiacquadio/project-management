# VPS PM-GEST — architettura e convenzioni

Fonte di verità per deployare qualsiasi progetto containerizzato su questa VPS.
Quando chiedi a Claude di preparare un nuovo stack, **fagli leggere prima questo file**.

> Claude non ha (più) accesso SSH alla VPS. Il suo compito è: generare i **file**
> dello stack nel repo del progetto e produrre i **comandi** che esegui tu.

---

## La macchina

- **Host**: `PM-GEST` — **IP pubblico**: `5.249.150.209`
- Accesso: `ssh root@5.249.150.209` (gestito da te; password/chiave tue)
- Reverse proxy + SSL: **Nginx Proxy Manager** (NPM), UI su `http://5.249.150.209:81`
- I singoli container **non pubblicano porte**: è NPM che instrada per hostname e
  fa i certificati Let's Encrypt.

## Accessi infrastruttura

Servizi di gestione (girano come stack `infra-*` in `/opt/docker/infrastructure/`):

| Servizio | URL | Note |
|---|---|---|
| **Nginx Proxy Manager** (routing + SSL) | `http://5.249.150.209:81` | admin UI; le porte 80/443 servono il traffico pubblico |
| **Portainer** (gestione Docker via web) | `https://5.249.150.209:9443` | UI su HTTPS (self-signed: accetta l'avviso) |
| **Uptime Kuma** (monitoring/uptime) | `http://5.249.150.209:3001` | stack presente in `infrastructure/uptime-kuma`; avvialo se spento (`docker compose up -d` nella sua cartella) |
| **backup** | `/opt/docker/infrastructure/backup` | script/cron di backup |

Le credenziali di questi pannelli le custodisci tu (non le conosco).
Da **Portainer** puoi fare quasi tutto via web: vedere log, riavviare container,
ispezionare volumi/reti — comodo se non vuoi usare SSH.

## Layout su disco (`/opt`)

```
/opt/docker/
├── infrastructure/            # servizi condivisi
│   ├── nginx-proxy-manager/   # il reverse proxy (crea la rete `proxy`)
│   ├── portainer/
│   └── uptime-kuma/
├── shared/
└── stacks/                    # un progetto = una cartella qui
    ├── pmvvf/                 # es. gestionale Laravel (questo repo)
    │   ├── app/               # il repo git dell'applicazione
    │   ├── docker-compose.yml # copia di app/deploy/docker-compose.prod.yml
    │   └── .env               # variabili d'ambiente (segreti)
    └── <nuovo-stack>/
/opt/backups   /opt/logs   /opt/scripts
```

## Convenzioni di uno stack (`/opt/docker/stacks/<PROJECT_NAME>/`)

| Elemento | Regola |
|---|---|
| `PROJECT_NAME` | kebab-case, univoco (es. `pmvvf`, `mariorossi`). Prefissa **tutto**. |
| `app/` | il repo git dell'app (contiene Dockerfile + `deploy/` con i template) |
| `docker-compose.yml` | copia di `app/deploy/docker-compose.prod.yml` (va risincronizzato agli update) |
| `.env` | segreti e config; **mai** committato nel repo |
| Container | `${PROJECT_NAME}-<servizio>` (es. `pmvvf-app`, `pmvvf-db`) |
| Immagini | `${PROJECT_NAME}-<servizio>:latest` |
| Volumi | `${PROJECT_NAME}-<nome>` (es. `pmvvf-db-data`, `pmvvf-storage`) |
| Reti | privata `${PROJECT_NAME}-network` + `proxy` (esterna) solo sul container web |

## Rete e instradamento

- Esiste **una** rete Docker esterna condivisa: **`proxy`** (creata da NPM).
- Ogni stack ha la **sua rete privata** `${PROJECT_NAME}-network` per app↔db↔queue.
- **Solo** il container che serve HTTP (nginx / apache / wordpress) si attacca
  **anche** alla rete `proxy`, così NPM lo raggiunge per nome.
- In NPM crei un **Proxy Host**: `Domain = <dominio>`, `Forward Hostname = ${PROJECT_NAME}-<container-web>`,
  `Port = 80`, `Scheme = http`, poi tab SSL → Let's Encrypt + Force SSL + HTTP/2.
- **DNS**: sul registrar (es. Aruba) crea un record **A** del dominio → `5.249.150.209`.
  Il certificato LE si emette **solo dopo** che il DNS risolve alla VPS.

Se `proxy` non esistesse ancora:
```bash
docker network create proxy
```

## Regole di deploy (valide per ogni stack)

1. Nessuna porta pubblicata sui container (tranne NPM/infra).
2. Volumi persistenti per **DB**, **upload/contenuti** e ciò che non deve morire al rebuild.
3. `restart: unless-stopped` su tutti i servizi long-running.
4. Il web-container dipende dal DB **con `condition: service_healthy`** (niente 500 durante i riavvii del DB).
5. Dietro NPM (SSL terminato dal proxy) l'app deve fidarsi degli header `X-Forwarded-Proto`
   (Laravel: `trustProxies`; WordPress: snippet `HTTP_X_FORWARDED_PROTO` in wp-config).
6. Idempotenza: install/update rilanciabili senza rompere nulla.

## Stack di riferimento

- **Laravel/Filament** → questo repo (`pmvvf`). Template canonici:
  `app/Dockerfile` (multi-stage: `app-base`→`assets`→`app`→`nginx`),
  `app/deploy/docker-compose.prod.yml`, `app/deploy/{install,update}.sh`,
  `app/deploy/.env.prod.example`.
- **WordPress** → `vps-ops/templates/wordpress/`.

Vedi `PROMPT-nuovo-stack.md` per far generare a Claude un nuovo stack,
e `RUNBOOK.md` per la manutenzione quotidiana.
