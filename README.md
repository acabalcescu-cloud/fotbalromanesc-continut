# fotbalromanesc.ro – conținut automat

Articolele și datele pe care site-ul [fotbalromanesc.ro](https://fotbalromanesc.ro) le preia singur, la 15 minute (tema `fotbalromanesc`, din `tema/`).

| Ce | Unde |
| --- | --- |
| Lista articolelor pe care site-ul le importă | `index.json` → `{ "articole": [ "articole/<id>.json", … ] }` |
| Articolele (JSON + copertă) | `articole/<id>.json`, `articole/<id>.jpg` |
| Datele competițiilor fără LiveScore (Liga 3 pe serii) | `date/<slug-competitie>.json`, listate în `date/index.json` |
| Tema WordPress | `tema/fotbalromanesc/` (sursa) și `tema/fotbalromanesc.zip` |
| Vocea și regulile de scris | `STIL.md` |
| Pașii rutinelor automate | `INSTRUCTIUNI.md` |
| Unelte (Google News, citirea articolelor) | `unelte/` |

SuperLiga, Liga 2 și naționalele nu au nevoie de fișiere aici: tema le ia direct de la LiveScore.

## Versiune nouă a temei

1. `Version` în `tema/fotbalromanesc/style.css` și `FR_VERSIUNE` în `functions.php`.
2. `cd tema && rm -f fotbalromanesc.zip && zip -qr fotbalromanesc.zip fotbalromanesc && sha256sum fotbalromanesc.zip | cut -d' ' -f1 > sha256.txt`, apoi versiunea în `versiune.txt`.
3. Commit pe `main`. Site-ul o instalează singur (sau imediat, cu `POST /wp-json/fr/v1/tema` și antetul `X-Fr-Key`).

## Pe site

În WordPress, **Aspect › fotbalromanesc**: depozitul `acabalcescu-cloud/fotbalromanesc-continut`, ramura `main`. Atât: importul pornește singur, iar seriile Liga 3 își iau clasamentul și meciurile din `date/`.
