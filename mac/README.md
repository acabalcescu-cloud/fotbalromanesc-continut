# Rutinele fotbalromanesc.ro pe Mac Studio

Trei verificări automate, fără inteligență artificială. Claude Code pornește doar când găsesc ceva de scris, scrie articolele în depozit și face commit pe `main`. Site-ul le preia singur în cel mult 15 minute.

| Rutina | Când | Ce face |
| --- | --- | --- |
| Liga 2 (`mac/liga2.py`) | la 10 minute | Meciurile terminate de 2–36 de ore fără cronică → câte o cronică pe meci |
| Liga 3 (`mac/liga3.py`) | sâmbătă 19:37, duminică 10:37 și 18:37, luni 10:37 | Seriile fără rezumat pentru weekendul curent → câte o sesiune pe serie: articolul și `date/liga-3-seria-N.json` |
| Știri (`mac/stiri.py`) | zilnic la 20:47 | Titluri noi din Google News pe temele mari → știri, doar dacă subiectul e confirmat |

Promptul fiecărei rutine e în `mac/prompt-*.md`; regulile de scris sunt în `STIL.md` și `INSTRUCTIUNI.md`.

## Instalare (o singură dată)

Pe Mac Studio sunt deja instalate, pentru Poveștile Științei: Homebrew, git, Claude Code (logat) și accesul la GitHub (`gh auth login`). Dacă lipsește ceva, vezi `mac/README.md` din depozitul Poveștile Științei.

1. **Depozitul**, lângă cel al Poveștilor Științei:
   ```
   cd ~
   gh repo clone acabalcescu-cloud/fotbalromanesc-continut
   cd fotbalromanesc-continut
   ```
2. **Accesul la GitHub**: contul de pe Mac trebuie să aibă drept de scriere în `acabalcescu-cloud/fotbalromanesc-continut` (e același proprietar, deci de obicei îl are deja).
3. **Instalarea**: `bash mac/instaleaza.sh`

## Verificare

- `python3 mac/liga2.py --doar-lista` – meciurile din Liga 2 care ar primi acum cronică.
- `python3 mac/liga3.py --doar-lista` – seriile din Liga 3 fără rezumat pentru weekendul curent.
- `python3 mac/stiri.py --doar-lista` – titlurile noi găsite.
- `python3 mac/liga3.py --serie 1` – rulează acum rezumatul Seriei 1 (cu Claude).
- `cat .stare-mac/jurnal.log` – ce s-a întâmplat la fiecare rulare.
- `cat .stare-mac/ultimul-raport-liga2.txt` (sau `-liga3`, `-stiri`) – raportul ultimei sesiuni Claude.

La fiecare articol publicat apare o notificare pe Mac cu titlul lui.

## De știut

- Mac-ul trebuie să fie pornit. Dacă doarme, rularea se face la trezire.
- Nu e nevoie de parola WordPress: publicarea trece prin GitHub.
- Rutinele Poveștilor Științei nu sunt atinse; cele două seturi rulează independent.
- Oprire: `bash mac/instaleaza.sh --sterge`.
