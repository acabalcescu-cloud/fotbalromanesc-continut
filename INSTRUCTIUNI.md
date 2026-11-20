# Rutinele automate – instrucțiuni pentru sesiunile programate

Scopul: articolele și datele de pe fotbalromanesc.ro apar fără intervenție manuală. O sesiune scrie fișierele în acest depozit și face commit pe `main`; site-ul le preia singur în cel mult 15 minute.

Toate textele urmează `STIL.md`. Orele sunt în ora României.

## Formatul unui articol

`articole/<id>.json`, cu `<id>` = `AAAA-LL-ZZ-<subiect-fara-diacritice>`:

```json
{
  "id": "2026-11-15-liga3-seria1-runda13",
  "titlu": "…",
  "rezumat": "1–2 fraze pentru liste și Facebook",
  "continut": "<p>…</p>",
  "competitie": "liga-3-seria-1",
  "cluburi": ["Bradul Putna", "Ceahlăul Piatra Neamț"],
  "etichete": ["Liga 3", "Seria 1", "2026/2027"],
  "hashtaguri": ["Bradul Putna", "Ceahlăul", "Ionel Stoian", "Liga 3", "surpriza etapei"],
  "imagine": "<id>.jpg",
  "data_publicare": "2026-11-15 10:00"
}
```

- `competitie`: slugul de pe site: `superliga`, `liga-2`, `liga-3-seria-1` … `liga-3-seria-8`, `nationala-mare`, `nationala-u21`, `nationala-u19`, `nationala-u17`, `nationala-feminin`, `stranierii`, `opinie`.
- `cluburi`: numele exact ca pe site (cu diacritice). Un club nou se creează singur la import.
- `hashtaguri`: 4–10 nume scrise normal, din contextul articolului: oamenii despre care e vorba (marcatori, antrenori, cine a făcut declarații), echipele, competiția și 1–3 teme („derby”, „promovare”, „debut”, „lider nou”). Site-ul le transformă în hashtag-uri pentru Facebook și Instagram și adaugă singur pe cele ale site-ului (#fotbalromanesc #fotbalromania #fotbal), competiția și cluburile.
- `importanta` (opțional, 1–3): cât de mare e subiectul. 3 = știrea zilei (subiectul cel mai important al zilei din SuperLiga sau Naționale), 2 = important, 1 sau lipsă = obișnuit. Pe prima pagină, sus stă articolul din SuperLiga/Naționale din ultimele 36 de ore cu importanța cea mai mare; sub el, cel mai nou din Liga 2 și cel mai nou din Liga 3.
- **Fixarea în prima pagină:** `presa/fixat.json` = `{"id": "<id articol>", "subiect": "…", "acoperire": 9, "publicatii": […], "fixat_la": "2026-11-20 10:00", "pana_la": "2026-11-21 06:00"}`. Site-ul fixează articolul și îl desprinde singur la `pana_la` (cel mult 24 de ore). Subiectul se alege după acoperirea în presa națională: `python3 -I unelte/acoperire.py "<cuvinte cheie>"`.
- `imagine` e opțională (fișierul stă lângă JSON, în `articole/`). `"status": "draft"` = ciornă.
- `data_publicare` niciodată în viitor.
- Articolul se adaugă **la începutul** listei `articole` din `index.json` (maximum 300 de intrări).

## Formatul datelor unei serii din Liga 3

`date/liga-3-seria-N.json` (și `"liga-3-seria-N"` trecut în `date/index.json` → `{"fisiere": [...]}`):

```json
{
  "actualizat": "2026-11-15 10:00",
  "surse": ["https://…"],
  "clasament": [ { "loc": 1, "echipa": "CSM Vaslui", "meciuri": 13, "pct": 30, "gm": 30, "gp": 10 } ],
  "meciuri": [
    { "id": "s1r13b", "data": "2026-11-14T14:00:00+02:00", "runda": "13",
      "gazde": "Bradul Putna", "oaspeti": "Ceahlăul Piatra Neamț", "scor": [4, 2],
      "marcatori": { "gazde": [ { "nume": "Hopulele", "minut": "1", "tip": "" } ], "oaspeti": [] } }
  ]
}
```

`v`, `e`, `i`, `gm`, `gp`, `marcatori` sunt opționale: scriem doar ce e confirmat. `scor` = `null` pentru meciurile nejucate. Numele echipelor sunt aceleași în clasament, în meciuri și în articole.

## Unelte

- Google News: `curl -s "https://news.google.com/rss/search?q=<termeni>+when:3d&hl=ro&gl=RO&ceid=RO:ro"`.
- Adresa reală a unui link Google News: `python3 -I unelte/gndecode.py <link>`.
- Textul unui articol: `python3 -I unelte/citeste.py <url>`.
- LiveScore (SuperLiga, Liga 2): `https://prod-public-api.livescore.com/v1/api/app/stage/soccer/romania/liga-ii/0?MD=1` (clasament și meciuri, orele în UTC), marcatorii: `…/incidents/soccer/<Eid>?locale=en` (IT 36 = gol, 37 = penalty, 39 = autogol; `Nm` 1 = gazde, 2 = oaspeți).
- Evidența surselor: `presa/vazute.json` = `{ "<url>": "<id-ul articolului nostru sau „ignorat”>" }`. Un URL deja trecut nu se mai citește.

## Rutina 1 – Liga 3, rezumatul fiecărei serii (sâmbătă seara și duminică dimineața)

Pentru fiecare serie 1–8 care a avut rundă vineri sau sâmbătă și nu are încă articol pentru runda respectivă:

1. **Meciurile rundei**: din `date/liga-3-seria-N.json` (programul) sau, dacă lipsesc, din presă.
2. **Faptele**: caută fiecare meci în Google News (ambele echipe + „Liga 3”), apoi citește articolele presei locale. Scorul și marcatorii trebuie confirmați de **două surse**; ce apare într-una singură nu se scrie.
3. **Clasamentul**: dintr-o sursă care îl publică întreg (ex.: Deșteptarea pentru Seria 1). Verificare obligatorie: punctele fiecărei echipe = punctele de după runda trecută + rezultatul din această rundă. Dacă nu se potrivește, scrii doar primele locuri confirmate.
4. **Programul rundei următoare**: din aceeași sursă sau din presa locală (ora oficială e 14:00, cu excepțiile anunțate).
5. **Fișierele**: articolul `articole/AAAA-LL-ZZ-liga3-seriaN-rundaR.json` (`competitie`: `liga-3-seria-N`, `cluburi`: echipele cu poveste în text) și datele `date/liga-3-seria-N.json` actualizate (rezultatele rundei, clasamentul, programul următor).
6. Dacă o serie are prea puține surse (sub jumătate din meciuri confirmate), nu scrii articolul: actualizezi doar scorurile confirmate în `date/` și reîncerci la următoarea rulare.

## Rutina 2 – Liga 2, cronica fiecărui meci (la 2–3 ore după fiecare meci)

1. Din LiveScore (`romania/liga-ii`): meciurile terminate azi și ieri; pentru fiecare, scorul, scorul la pauză (`Trh1`, `Trh2`) și marcatorii.
2. Dacă `articole/AAAA-LL-ZZ-<gazde>-<oaspeti>.json` există deja, sari peste meci.
3. Caută meciul în Google News și în presa locală pentru context (declarații, momente-cheie). Marcatorii din LiveScore se confirmă cu cel puțin o sursă de presă.
4. Scrie cronica după `STIL.md` (250–450 de cuvinte): `competitie`: `liga-2`, `cluburi`: cele două echipe.
5. Dacă presa nu a scris încă nimic despre meci, publici o cronică scurtă doar din date (scor, marcatori, clasament, următorul meci) și o completezi a doua zi (câmpul `actualizat` + calea în `actualizate` din `index.json`).

## Rutina 3 – Știri mari (o dată pe zi, seara)

Google News pentru „Liga 2”, „Liga 3”, „naționala României”, „antrenor demis”, „transfer” + numele cluburilor din Liga 2. Doar subiectele mari (vezi `STIL.md`), confirmate din două surse. `competitie` după subiect.

## Rutina 4 – Profilurile cluburilor (de două ori pe zi, câte 5 cluburi)

Lista vine din `.stare-mac/cluburi-de-facut.json`. Promptul: `mac/prompt-cluburi.md`.

## Profilurile cluburilor

Fișierul `date/cluburi/<slug>.json` (slug-ul paginii clubului de pe site, de exemplu `chindia-targoviste`). Site-ul îl citește singur, în cel mult 12 ore. Toate câmpurile sunt opționale: ce nu e confirmat lipsește.

```json
{
  "nume": "Chindia Târgoviște",
  "oras": "Târgoviște",
  "fondat": 2010,
  "culori": "alb-roșu",
  "site": "https://…",
  "stadion": { "nume": "…", "capacitate": 0, "inaugurat": 0, "maps": "https://maps.google.com/?q=…" },
  "antrenor": { "nume": "…", "nationalitate": "România", "varsta": 45 },
  "lot": [ { "nr": 1, "nume": "…", "post": "portar", "varsta": 24, "nationalitate": "România" } ],
  "stema": "https://upload.wikimedia.org/…/stema.png",
  "actualizat": "2026-11-20",
  "surse": [ "https://…" ]
}
```

- `post`: `portar`, `fundas`, `mijlocas` sau `atacant`.
- `stema`: doar dacă clubul nu are încă stemă pe site (cluburile din Liga 3); PNG sau JPG, adresă directă.
- Commit: `Cluburi: <nume>, <nume>`.

## Commit

- Mesaj: `Liga 3: Seria 1 runda 13` / `Liga 2: Chindia – Popesti 4-1` / `Stiri: <subiecte>` / `Date: Liga 3 Seria N`.
- Nimic nou → fără commit.
- Înainte de commit: JSON valid (`python3 -c "import json,sys; json.load(open(sys.argv[1]))" <fișier>`), numele echipelor identice în date și articole, nicio dată în viitor.
- Raport scurt la final: ce s-a publicat, ce n-a putut fi confirmat.
