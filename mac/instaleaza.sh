#!/bin/bash
# Instalează pe Mac (launchd) rutinele fotbalromanesc.ro:
#   - Liga 2: la 10 minute (mac/liga2.py), cronica fiecărui meci la 2 ore după final;
#   - Liga 3: sâmbătă 19:37, duminică 10:37 și 18:37, luni 10:37 (mac/liga3.py), rezumatul fiecărei serii;
#   - Știri: zilnic la 09:47 și 20:47 (mac/stiri.py), inclusiv știrea zilei din SuperLiga/Naționale;
#   - Cluburi: zilnic la 04:17 și 14:17 (mac/cluburi.py), profilurile (stadion, antrenor, lot), câte 5 cluburi.
# Claude pornește doar când verificarea (fără AI) găsește ceva de scris.
# Rulare, din folderul depozitului:  bash mac/instaleaza.sh
# Dezinstalare:                       bash mac/instaleaza.sh --sterge
set -e
DEPOZIT="$(cd "$(dirname "$0")/.." && pwd)"
AGENTI="$HOME/Library/LaunchAgents"
PLIST_L2="$AGENTI/ro.fotbalromanesc.liga2.plist"
PLIST_L3="$AGENTI/ro.fotbalromanesc.liga3.plist"
PLIST_ST="$AGENTI/ro.fotbalromanesc.stiri.plist"
PLIST_CL="$AGENTI/ro.fotbalromanesc.cluburi.plist"

if [ "$1" = "--sterge" ]; then
  for p in "$PLIST_L2" "$PLIST_L3" "$PLIST_ST" "$PLIST_CL"; do
    launchctl bootout "gui/$(id -u)" "$p" 2>/dev/null || true
    rm -f "$p"
  done
  echo "Rutinele fotbalromanesc.ro au fost oprite si sterse."
  exit 0
fi

lipsa=0
for p in git python3 curl claude; do
  if ! command -v "$p" >/dev/null 2>&1; then echo "Lipseste: $p"; lipsa=1; fi
done
if [ $lipsa = 1 ]; then
  echo "Instaleaza ce lipseste (vezi mac/README.md), apoi ruleaza din nou."
  exit 1
fi
python3 -c "import zoneinfo" 2>/dev/null || { echo "python3 e prea vechi (trebuie 3.9+). Vezi mac/README.md."; exit 1; }

cd "$DEPOZIT"
echo "Verific accesul la GitHub..."
git fetch -q origin main || { echo "Nu pot accesa depozitul pe GitHub. Vezi mac/README.md, pasul 2."; exit 1; }
git push --dry-run -q origin HEAD:main 2>/dev/null || echo "Atentie: nu pot verifica dreptul de scriere (git push). Vezi mac/README.md, pasul 2."

grep -qx ".stare-mac/" .gitignore 2>/dev/null || echo ".stare-mac/" >> .gitignore
mkdir -p .stare-mac "$AGENTI"

PY="$(command -v python3)"
CALE="$(dirname "$(command -v claude)"):$(dirname "$PY"):/usr/local/bin:/opt/homebrew/bin:/usr/bin:/bin:/usr/sbin:/sbin"

# scrie_plist <fisier> <eticheta> <script> <programare-xml> <jurnal>
scrie_plist() {
  cat > "$1" <<PLIST
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
  <key>Label</key><string>$2</string>
  <key>ProgramArguments</key>
  <array>
    <string>$PY</string>
    <string>$DEPOZIT/mac/$3</string>
  </array>
  <key>WorkingDirectory</key><string>$DEPOZIT</string>
  <key>EnvironmentVariables</key>
  <dict><key>PATH</key><string>$CALE</string></dict>
  $4
  <key>StandardOutPath</key><string>$DEPOZIT/.stare-mac/$5</string>
  <key>StandardErrorPath</key><string>$DEPOZIT/.stare-mac/$5</string>
</dict>
</plist>
PLIST
}

ora() { echo "<dict><key>Weekday</key><integer>$1</integer><key>Hour</key><integer>$2</integer><key>Minute</key><integer>37</integer></dict>"; }

scrie_plist "$PLIST_L2" ro.fotbalromanesc.liga2 liga2.py \
  '<key>StartInterval</key><integer>600</integer>' launchd-liga2.log
scrie_plist "$PLIST_L3" ro.fotbalromanesc.liga3 liga3.py \
  "<key>StartCalendarInterval</key><array>$(ora 6 19)$(ora 0 10)$(ora 0 18)$(ora 1 10)</array>" launchd-liga3.log
scrie_plist "$PLIST_ST" ro.fotbalromanesc.stiri stiri.py \
  '<key>StartCalendarInterval</key><array><dict><key>Hour</key><integer>9</integer><key>Minute</key><integer>47</integer></dict><dict><key>Hour</key><integer>20</integer><key>Minute</key><integer>47</integer></dict></array>' launchd-stiri.log
scrie_plist "$PLIST_CL" ro.fotbalromanesc.cluburi cluburi.py \
  '<key>StartCalendarInterval</key><array><dict><key>Hour</key><integer>4</integer><key>Minute</key><integer>17</integer></dict><dict><key>Hour</key><integer>14</integer><key>Minute</key><integer>17</integer></dict></array>' launchd-cluburi.log

for p in "$PLIST_L2" "$PLIST_L3" "$PLIST_ST" "$PLIST_CL"; do
  launchctl bootout "gui/$(id -u)" "$p" 2>/dev/null || true
  launchctl bootstrap "gui/$(id -u)" "$p"
done

echo
echo "Gata."
echo "Liga 2: verificare la 10 minute; cronica la 2 ore dupa final."
echo "Liga 3: sambata 19:37, duminica 10:37 si 18:37, luni 10:37."
echo "Stiri: zilnic la 09:47 si 20:47 (stirea zilei din SuperLiga/Nationale)."
echo "Cluburi: zilnic la 04:17 si 14:17, cate 5 profiluri (stadion, antrenor, lot)."
echo
echo "Teste fara Claude:"
echo "  python3 mac/liga2.py --doar-lista"
echo "  python3 mac/liga3.py --doar-lista"
echo "  python3 mac/stiri.py --doar-lista"
echo "  python3 mac/cluburi.py --doar-lista"
echo "Jurnal: cat .stare-mac/jurnal.log"
