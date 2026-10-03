# SPEC-005 — Web Scraping

Status: Implementada

## Objetivo
O Web Scraping só entra conhecimento quando o usuário pede. Não roda durante o Chat.

Uma URL pública vira texto, chunks, embeddings e registro no MySQL, no pipeline já usado pelo upload. A URL final fica gravada na fonte. HTML de script, estilo, navegação, rodapé e linhas repetidas é descartado. O texto útil vem de `main` ou `article` quando esse bloco tem conteúdo.

A importação percorre os links do mesmo domínio e para no mesmo teto do outro sistema, 200 páginas, para a requisição concluir. Não há campo de limite na tela. O conjunto entra numa única importação. A lista e o chat mostram essa importação, não cada página. Páginas já salvas do mesmo host entram nela sem reindexar, e no chat ela conta como uma fonte. Cada página guarda a própria URL. Há robots.txt e a mesma proteção contra SSRF nos redirecionamentos. Documentos já gravados não são reindexados.

## Validação em 02/10/2026
- `POST /api/sources` de `https://www.php.net/manual/pt_BR/language.types.php`, sem links internos: HTTP 201, documento 34, título `PHP: Tipos - Manual`, status `ready`, 1 chunk, `source_url` igual à URL pedida.
- O PDF de Breaking (documento 13) continuou `ready`, com 67 chunks e 67 linhas em `document_chunks`, `import_id` nulo.
- `POST /api/sources` de `https://www.php.net/docs.php` com links internos: HTTP 200, uma importação (id 1), 10 páginas e 36 trechos. A página já existente (documento 35) manteve os 3 trechos; só recebeu `import_id` 1. Outro usuário não vê o grupo e o DELETE responde 404.
