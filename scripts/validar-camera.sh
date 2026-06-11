#!/usr/bin/env bash
set -u
F=/var/www/html/public/assets/js/app.js
PASS=0
FAIL=0

verifica() {
  local nome="$1"; local pattern="$2"; local esp="$3"
  local cnt=$(grep -cE "$pattern" "$F")
  if [ "$cnt" -ge "$esp" ]; then
    printf "  ✅ %-50s (%d hits)\n" "$nome" "$cnt"
    PASS=$((PASS+1))
  else
    printf "  ❌ %-50s (%d hits, esperado %d)\n" "$nome" "$cnt" "$esp"
    FAIL=$((FAIL+1))
  fi
}

echo "=== 8 Fixes ==="
verifica "C1 BarcodeDetector formats: ['ean_13']"        "formats: \['ean_13'\]"                                 1
verifica "C2 caminho nativo tem votação (amostrasJanelaNativo)" "amostrasJanelaNativo"                          5
verifica "C2 VOTOS_NATIVO = 3 e JANELA_NATIVO = 5"       "VOTOS_NATIVO = 3|JANELA_NATIVO = 5"                    2
verifica "M1 callback Quagga checa cameraAtiva no início" "M1: usuário pode ter cancelado"                       1
verifica "M2 contadorLog usado com %"                    "contadorLog % 10"                                      1
verifica "M2 rate-limit de rejeição"                     "primeiraRejeicaoLogada"                                3
verifica "M3 msg dinâmica 'Lendo… (X/Y confirmações)'"   "Lendo… \(' \+ maxVotos"                                2
verifica "M4 numOfWorkers: Math.min(2,..)"               "numOfWorkers: Math.min\(2"                             1
verifica "M4 frequency: 15"                              "frequency: 15"                                         1
verifica "M5 timerRelaxar com 15000ms"                   "}, 15000\);"                                           1
verifica "M5 usa erroMaxAtual no filtro de erro"         "erroMedio > erroMaxAtual"                              1
verifica "M5 reset de erroMaxAtual em fechar"            "erroMaxAtual = 0.15"                                   2

echo
echo "=== Regressões em fecharCamera ==="
verifica "fecharCamera limpa cameraDicasTimer2"          "clearTimeout\(cameraDicasTimer2\)"                     1
verifica "fecharCamera limpa timerRelaxar"               "clearTimeout\(timerRelaxar\)"                          1
verifica "fecharCamera reseta amostrasJanelaNativo"      "amostrasJanelaNativo.length = 0"                       2
verifica "fecharCamera reseta contadorLog"               "contadorLog = 0"                                       1

echo
echo "=== Sanity: não tem readers/formats agressivos sobrando ==="
N=$(grep -cE "'code_128'|'itf'|'code_39'|'upc_a'|'upc_e'|'ean_8'" "$F")
if [ "$N" -le "0" ]; then
  printf "  ✅ formats agressivos: 0\n"
  PASS=$((PASS+1))
else
  printf "  ❌ formats agressivos AINDA aparecem: %d\n" "$N"
  grep -nE "'code_128'|'itf'|'code_39'|'upc_a'|'upc_e'|'ean_8'" "$F"
  FAIL=$((FAIL+1))
fi

echo
echo "=== Servir o arquivo via HTTPS ==="
curl -sk -o /dev/null -w "  app.js: HTTP=%{http_code} bytes=%{size_download}\n" https://localhost/assets/js/app.js

echo
echo "=== Resultado: ${PASS} OK, ${FAIL} falha(s) ==="
exit $FAIL
