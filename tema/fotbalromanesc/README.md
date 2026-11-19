# Tema fotbalromanesc

Tema WordPress pentru fotbalromanesc.ro: tot fotbalul românesc, de la SuperLiga la Liga 3, de la naționale la românii din străinătate. E o temă de sine stătătoare (fără temă-părinte) și nu cere module.

## Instalare

1. În WordPress: **Aspect › Teme › Adaugă o temă › Încarcă tema** și alegi `fotbalromanesc.zip`.
2. Activezi tema. La activare se creează singure:
   - competițiile: SuperLiga, Liga 2, Liga 3 (cu Seria 1–8), Naționala (Naționala mare, U21, U19, U17, Feminin), Stranierii și Opinie;
   - pagina **Program și rezultate**.
3. **Setări › Legături permanente**: alegi „Numele articolului”.
4. **Aspect › fotbalromanesc**: depozitul GitHub cu articole, sponsorul general, Facebook, Instagram și TikTok.
5. Opțional: **Aspect › Personalizează › Identitatea site-ului** pentru logo. Fără logo, numele site-ului apare ca text, cu „.ro” în galben.

SuperLiga, Liga 2 și naționalele au date automate (LiveScore) din prima zi. Primele date apar după câteva minute, pentru că un cron le reîmprospătează la 10 minute. Dacă site-ul are puțin trafic, cronul WordPress pornește doar la vizite; pe server merită un cron real care cheamă `wp-cron.php` la 5 minute.

## Competiții (Articole › Competiții)

Fiecare competiție are câmpurile:

| Câmp | Ce face |
| --- | --- |
| Ordinea în meniu | Ordinea în meniu și în liste |
| Sursa LiveScore | Calea ligii, de exemplu `romania/liga-1` sau `romania/liga-ii`: clasament, program, rezultate, formă |
| Echipa în LiveScore (naționale) | Numele exact al echipei, de exemplu `Romania U21`: tema adună meciurile ei din toate competițiile |
| Fișier JSON cu date | Pentru Liga 3 sau alte competiții fără LiveScore (formatul e mai jos) |
| Date neoficiale | Afișează „date neoficiale” lângă clasament |
| Pe prima pagină | Meciurile intră în banda de sus, clasamentul în bara laterală |
| Sponsor, Adresa sponsorului | Sponsorul paginii competiției |

Pagina unei competiții are filele **Știri**, **Meciuri** (pe etape, cu „Înapoi / Înainte”) și **Clasament** (complet).

## Cluburi (Articole › Cluburi)

Un club leagă articolele de date. Câmpurile: **Numele în LiveScore** (de exemplu `Chindia Targoviste`), **Competiția**, **Sigla** (adresa unei imagini), **Colaboratorul clubului** (un utilizator), **Sponsor**.

Pagina clubului (`/club/<nume>/`) arată forma, următorul meci, ultimul meci cu marcatorii, golgheterii echipei, programul, clasamentul din jurul echipei, colaboratorul, ultimele articole și sponsorul.

Numele echipelor din LiveScore vin fără diacritice; tema le afișează corect pentru SuperLiga, Liga 2 și naționalele europene (`inc/nume-echipe.php`). Un club creat în admin are întâietate.

## Fișierul JSON pentru Liga 3

```json
{
  "clasament": [
    { "loc": 1, "echipa": "CSM Vaslui", "meciuri": 13, "v": 9, "e": 3, "i": 1, "gm": 30, "gp": 10, "pct": 30 }
  ],
  "meciuri": [
    { "id": "s1r14a", "data": "2026-11-20T14:00:00+02:00", "runda": "14",
      "gazde": "Aerostar Bacău", "oaspeti": "CSM Vaslui", "scor": null,
      "marcatori": { "gazde": [ { "nume": "Corban", "minut": "50", "tip": "" } ], "oaspeti": [] } }
  ]
}
```

`v`, `e`, `i`, `gm`, `gp` și `marcatori` sunt opționale; ce lipsește apare ca „—”. `scor` e `null` la meciurile care nu s-au jucat.

## Importul articolelor din GitHub

La 15 minute, tema citește `index.json` din depozit și importă articolele noi, cu același format ca la Poveștile Științei (cheia `cronici` e acceptată):

```json
{ "articole": [ "articole/2026-11-15-liga3-seria1-runda13.json" ], "actualizate": [] }
```

Fiecare articol: `id`, `titlu`, `rezumat`, `continut` (HTML), `competitie` (slug sau „Liga 3 › Seria 1”), `cluburi`, `etichete`, `imagine` (lângă fișierul JSON) sau `imagine_url`, `autor` (`login`, `nume`), `data_publicare`, `status` (`draft` = ciornă). Un articol deja importat se înlocuiește dacă are `actualizat` mai nou și apare la `actualizate`.

Starea importului: **Aspect › fotbalromanesc** sau `GET /wp-json/fr/v1/import` (administratori). `POST` pe aceeași adresă rulează importul imediat.

## Facebook și Instagram

Articolele noi se postează automat, la ~2 minute după publicare:

- **Facebook** (pagina): titlul, rezumatul, hashtag-urile și linkul; previzualizarea linkului folosește coperta generată 1200×630.
- **Instagram** (contul profesional legat de pagină): coperta generată 1080×1350 și descrierea, cu „link în bio”.

Coperțile se generează din titlu și competiție (cu fotografia articolului, dacă există), în `uploads/fr-social/`. Articolele mai vechi de 2 zile nu se postează.

Conectarea: în **Aspect › fotbalromanesc › Facebook și Instagram** lipești un token Meta (de utilizator, cu termen lung, sau de pagină) cu permisiunile `pages_show_list`, `pages_read_engagement`, `pages_manage_posts`, `instagram_basic`, `instagram_content_publish`. Tema găsește singură pagina, tokenul paginii și contul de Instagram. Pentru postare automată bifezi cele două căsuțe.

Verificare: `GET /wp-json/fr/v1/social` (starea, fără token), `GET /wp-json/fr/v1/social/<id>/previzualizare` (imaginile și textele, fără publicare), `POST /wp-json/fr/v1/social/<id>?retea=fb|ig` (postează acum). Toate cer antetul `X-Fr-Key` sau un administrator.

## Actualizarea temei

Tema se actualizează singură din depozit (`tema/versiune.txt`, `tema/sha256.txt`, `tema/fotbalromanesc.zip`):

- WordPress vede versiunea nouă la **Panou › Actualizări** și o instalează automat, la verificarea lui obișnuită (de două ori pe zi).
- `POST /wp-json/fr/v1/tema` cu antetul `X-Fr-Key: <cheia din Aspect › fotbalromanesc>` o instalează imediat. `GET` pe aceeași adresă arată versiunea instalată și pe cea din depozit.
- Arhiva se instalează doar dacă amprenta SHA-256 e cea din `tema/sha256.txt`.

O versiune nouă înseamnă: `Version` din `style.css` și `FR_VERSIUNE` din `functions.php`, arhiva refăcută, `sha256.txt` și `versiune.txt` actualizate, totul pe `main`.

## Pentru dezvoltatori

- `inc/date.php` – sursele de date, cache-ul (ultima copie bună rămâne dacă sursa cade), forma și marcatorii. `GET /wp-json/fr/v1/date/<id-competiție>` dă datele unei competiții.
- `inc/import.php` – importul. Filtrul `fr_repo_baza` schimbă serverul de unde se citesc fișierele.
- `inc/sabloane.php` – bucățile de afișare și meta-datele Open Graph și NewsArticle (dezactivate automat când e activ Yoast, Rank Math, SEOPress sau AIOSEO).
- Zonele de widgeturi: **Bara laterală** și **Articol – după text**. Meniurile: **Meniul principal** (fără meniu definit apar competițiile) și **Meniul din subsol**.
- Pe telefon: meniul de jos (Acasă, Meciuri, Ligi, Caută) și clasamentele restrânse.

Datele LiveScore vin dintr-un flux public, nedocumentat; poate să se schimbe fără anunț. Dacă se întâmplă, site-ul păstrează ultimele date bune, iar pagina de setări arată când s-au actualizat ultima dată.
