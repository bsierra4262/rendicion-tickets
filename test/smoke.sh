#!/usr/bin/env bash
# ============================================================
#  Smoke tests HTTP contra el sitio en produccion.
#  Uso: bash test/smoke.sh   (se corre en el servidor)
# ============================================================
B="https://intellihelp.tech/tickets"
pass=0; fail=0
chk(){ if [ "$3" = "$2" ]; then echo "  [OK] $1 -> $3"; pass=$((pass+1)); else echo "  [XX] $1 (esperado $2, obtuvo $3)"; fail=$((fail+1)); fi; }
code(){ curl -s -o /dev/null -w '%{http_code}' "$1"; }

chk "login 200"                 200 "$(code "$B/login")"
chk "home 302 -> login"         302 "$(code "$B/")"
chk "dashboard sin sesion 302"  302 "$(code "$B/dashboard")"
chk "css 200"                   200 "$(code "$B/assets/css/app.css")"
chk "app/ bloqueado 403"        403 "$(code "$B/app/bootstrap.php")"
g=$(curl -s "$B/login" | grep -c 'Ingresar con Google Workspace')
chk "boton Google presente"     1   "$g"
np=$(curl -s "$B/login" | grep -c 'name="password"')
chk "sin campo contrasena"      0   "$np"

echo "SMOKE: $pass ok, $fail fail"
exit $fail
