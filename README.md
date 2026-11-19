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

## Pe site

În WordPress, **Aspect › fotbalromanesc**: depozitul `acabalcescu-cloud/fotbalromanesc-continut`, ramura `main`. Atât: importul pornește singur, iar seriile Liga 3 își iau clasamentul și meciurile din `date/`.
