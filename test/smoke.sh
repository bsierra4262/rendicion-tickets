#!/usr/bin/env bash
# ============================================================
#  Smoke tests HTTP contra el sitio en produccion.
#  Uso: bash test/smoke.sh   (BASE=https://... para otra instalacion)
#  Con ITHub configurado, /login no tiene pantalla: manda a ithub-login y de
#  ahi a ITHub con PKCE. La pantalla (con «Ingresar con ITHub») se ve con
#  ?manual=1 y el aviso de cierre rechaza tokens invalidos (400).
# ============================================================
B="${BASE:-https://intellihelp.tech/comprobantes}"
pass=0; fail=0
chk(){ if [ "$3" = "$2" ]; then echo "  [OK] $1 -> $3"; pass=$((pass+1)); else echo "  [XX] $1 (esperado $2, obtuvo $3)"; fail=$((fail+1)); fi; }
code(){ curl -s -o /dev/null -w '%{http_code}' "$1"; }

chk "home 302 -> login"         302 "$(code "$B/")"
chk "dashboard sin sesion 302"  302 "$(code "$B/dashboard")"
chk "css 200"                   200 "$(code "$B/assets/css/app.css")"
chk "app/ bloqueado 403"        403 "$(code "$B/app/bootstrap.php")"

red=$(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' "$B/login")
case "$red" in
    30*ithub-login*)
        echo "  [OK] el login manda a la sesion unica de ITHub ($red)"; pass=$((pass+1))
        aut=$(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' "$B/ithub-login")
        case "$aut" in
            30*/oauth/authorize\?client_id=*code_challenge_method=S256*)
                echo "  [OK] ithub-login pide autorizacion a ITHub con PKCE"; pass=$((pass+1)) ;;
            *)  echo "  [XX] ithub-login no manda a /oauth/authorize con PKCE -> $aut"; fail=$((fail+1)) ;;
        esac
        chk "pantalla de ingreso 200"   200 "$(code "$B/login?manual=1")"
        g=$(curl -s "$B/login?manual=1" | grep -c 'Ingresar con ITHub')
        chk "boton ITHub presente"      1   "$g"
        chk "aviso de cierre invalido 400" 400 "$(curl -s -o /dev/null -w '%{http_code}' -X POST -d 'logout_token=x.y.z' "$B/ithub-logout")"
        np=$(curl -s "$B/login?manual=1" | grep -c 'name="password"')
        chk "sin campo contrasena"      0   "$np"
        ;;
    *)
        chk "login 200"                 200 "$(code "$B/login")"
        g=$(curl -s "$B/login" | grep -c 'Ingresar con Google Workspace')
        chk "boton Google presente"     1   "$g"
        np=$(curl -s "$B/login" | grep -c 'name="password"')
        chk "sin campo contrasena"      0   "$np"
        ;;
esac

echo "SMOKE: $pass ok, $fail fail"
exit $fail
