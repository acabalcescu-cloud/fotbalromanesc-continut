# Vocea fotbalromanesc.ro

Fotbalromanesc.ro scrie despre tot fotbalul românesc, de la SuperLiga la Liga 3, plus naționalele și românii din străinătate. Avantajul nostru: scriem ce marile publicații ignoră (Liga 2, Liga 3) și scriem bine.

## Ton

- **Neutru față de cluburi, cald față de fotbal.** Nu ținem cu nimeni în textele redacției. Fără „ai noștri” sau „băieții noștri” la un club anume. Opinia partizană rămâne în rubrica Opinie, semnată de colaborator.
- **Respect pentru fiecare club**, de la FCSB la Bradul Putna. O echipă de Liga 3 primește aceeași seriozitate ca una de SuperLiga.
- **Viu, nu de agenție.** Spunem ce a însemnat meciul, nu doar scorul: o serie ruptă, un lider care cade, un debutant care marchează.
- Fără atacuri la persoane, arbitri sau suporteri. Fără limbaj vulgar. Fără zvonuri.
- Fără subiecte despre disputa de identitate a cluburilor (Steaua/FCSB, Universitatea Craiova/FCU, Dinamo): folosim numele oficiale din competiție, fără comentarii.

## Fapte

- **Scorul, marcatorii, minutele și clasamentul se confirmă din cel puțin două surse.** Dacă sursele se contrazic, scriem varianta sigură sau lăsăm detaliul deoparte (ex.: „înainte de minutul 30”).
- **Declarațiile** pot veni dintr-o singură sursă, cu citat scurt, exact și atribuit („a declarat pentru Monitorul de Neamț”).
- **Nimic inventat**: nici minute, nici atmosferă („tribuna a vibrat”) dacă nu apare în surse.
- **Textul e scris de la zero**: altă structură, alt unghi, alt titlu decât sursele. Nu reformulăm frază cu frază.
- La final, `<p><em>Surse:</em> …</p>` cu linkuri la articolele folosite.

## Cronica unui meci (Liga 2, SuperLiga)

1. **Titlu**: ideea meciului, nu doar scorul. Ex.: „Chindia trece de Popești-Leordeni și urcă pe primul loc”.
2. **Linia de rezultat** (paragraf separat, bold): `Gazde – Oaspeți X-Y (pauză: a-b), Liga 2, etapa N`.
3. **Deschidere**: 1–2 fraze despre ce înseamnă rezultatul.
4. **Golurile**, în ordine, cu minute și marcatori; momentele-cheie (penalty, eliminare, bară), doar dacă apar în surse.
5. **Ce înseamnă în clasament** și ce urmează pentru ambele echipe.

Lungime: 250–450 de cuvinte, după cât spun sursele.

## Rezumatul unei serii din Liga 3

1. **Titlu**: povestea rundei. Ex.: „Liga 3, Seria 1, runda 13: liderul cade acasă, Iașiul se apropie la cinci puncte”.
2. **Deschidere**: cel mai important rezultat și ce schimbă în clasament.
3. **Tabelul rezultatelor** (`<table>` cu Meci / Scor).
4. **2–3 secțiuni** (`<h2>`) cu meciurile care au o poveste: surpriza, liderul, derby-ul local.
5. **„Celelalte meciuri”**: un paragraf cu rezultatele rămase, cu marcatori unde există.
6. **Clasamentul** (primele locuri sau tot, dacă e confirmat) și **programul rundei următoare**.

Lungime: 400–700 de cuvinte. Marcatorii care apar într-o singură sursă nu se scriu.

## Știri

Doar subiectele mari: antrenori numiți sau demiși, transferuri confirmate, convocări la națională, sancțiuni. Faptele din două surse; 300–700 de cuvinte, cu context din meciurile și clasamentele pe care le avem.

## Format HTML (`continut`)

Doar `<p>`, `<strong>`, `<em>`, `<h2>`, `<h3>`, `<ul>/<li>`, `<table>` (cu `<tr>`, `<th>`, `<td>`) și `<a>`. Fără stiluri, fără imagini în text.

## Limbă

- Diacritice corecte, cu virgulă: ș, ț (nu ş, ţ).
- Numele echipelor ca pe site: „Chindia Târgoviște”, „Poli Timișoara”, „CSM Râmnicu Vâlcea”, „Ceahlăul Piatra Neamț”.
- **Virgula:** fără virgulă înainte de „și”, „sau” simple, între subiect și predicat sau înainte de „că”, „să”, „care” restrictiv. Cu virgulă înainte de „dar”, „iar”, „însă”, „ci”, în jurul incidentelor și după o condițională aflată înaintea regentei („Dacă Vasluiul câștigă, își mărește avansul”).
- Fraze sub 25 de cuvinte, de regulă.
