#!/usr/bin/env bash
set -u
echo "=== PHP LINT ==="
ERR=0
for f in /var/www/html/api/*.php /var/www/html/src/Domain/Entity/*.php /var/www/html/src/Domain/Service/*.php /var/www/html/src/Application/*.php /var/www/html/src/Infrastructure/Adapter/Out/Persistence/*.php /var/www/html/src/Infrastructure/Adapter/Out/IsbnProvider/*.php /var/www/html/bootstrap/container.php /var/www/html/scripts/*.php /var/www/html/public/index.php /var/www/html/public/lista.php; do
  out=$(php -l "$f" 2>&1)
  if ! echo "$out" | grep -q "No syntax errors"; then
    echo "FAIL: $f"
    echo "$out"
    ERR=$((ERR+1))
  fi
done
echo "Lint ERR=$ERR"

echo
echo "=== ENDPOINTS HTTPS ==="
echo -n "GET categorias: "
curl -sk -o /dev/null -w "HTTP=%{http_code} bytes=%{size_download}\n" https://localhost/api/categorias.php
echo -n "GET livros:     "
curl -sk -o /dev/null -w "HTTP=%{http_code} bytes=%{size_download}\n" https://localhost/api/livros.php
echo -n "GET exportar?ids=1: "
curl -sk -o /dev/null -w "HTTP=%{http_code} type=%{content_type}\n" "https://localhost/api/exportar.php?ids=1"
echo -n "GET index.php:  "
curl -sk -o /dev/null -w "HTTP=%{http_code} bytes=%{size_download}\n" https://localhost/
echo -n "GET lista.php:  "
curl -sk -o /dev/null -w "HTTP=%{http_code} bytes=%{size_download}\n" https://localhost/lista.php
echo
echo "=== REDIRECT :80 -> :443 ==="
curl -s -o /dev/null -w "HTTP=%{http_code} location=%{redirect_url}\n" http://localhost/

echo
echo "=== HEADERS HTTPS ==="
curl -sk -I https://localhost/ | grep -iE "permissions-policy|strict-transport|content-type" | head -5

echo
echo "=== CATEGORIAS — sanity ==="
curl -sk https://localhost/api/categorias.php > /tmp/cat.json
php -r 'require "/var/www/html/vendor/autoload.php"; $d = json_decode(file_get_contents("/tmp/cat.json"), true); $raiz = array_filter($d, fn($c)=>$c["parent_id"]===null); echo "count=" . count($d) . " raiz=" . count($raiz) . " primeiro=" . $d[0]["indice"] . " ultimo=" . end($d)["indice"] . "\n";'

echo
echo "=== LIVROS — payload check ==="
curl -sk https://localhost/api/livros.php > /tmp/livros.json
php -r '$d = json_decode(file_get_contents("/tmp/livros.json"), true); if(empty($d["livros"])) { echo "lista vazia\n"; exit; } $item = $d["livros"][0]; $tem = []; foreach(["exportado_em","exportado_em_br","qtd_baixas","quantidade"] as $k) { $tem[$k] = array_key_exists($k, $item) ? "ok" : "FALTA"; } echo "total=" . $d["total"] . " primeiro_item_id=" . $item["id"] . " campos: " . json_encode($tem) . "\n";'

echo
echo "=== JS: existem funções esperadas? ==="
echo -n "app.js: "
grep -c -E "iniciarCameraEDecodificar|validarIsbn13|fecharCamera|carregarCategorias|preencherHyb|coletarHyb" /var/www/html/public/assets/js/app.js
echo -n "lista.js: "
grep -c -E "abrirModalExport|aplicarFiltros|atualizarContador|btn-gerar-xlsx" /var/www/html/public/assets/js/lista.js

echo
echo "=== HTML index.php: elementos esperados? ==="
curl -sk https://localhost/ | grep -oE "id=\"(btn-camera|overlay-camera|video-camera|campo-categoria|campo-quantidade|btn-salvar-e-baixar)\"" | sort -u

echo
echo "=== HTML lista.php: modal e botão? ==="
curl -sk https://localhost/lista.php | grep -oE "id=\"(btn-abrir-modal-export|modal-export|tabela-modal-export|btn-gerar-xlsx)\"" | sort -u

echo
echo "=== FIM ==="
