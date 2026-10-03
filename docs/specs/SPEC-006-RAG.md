# SPEC-006 — RAG fechado

Status: Implementada

## Fluxo
`POST /api/chat` recebe só `question` e `source_ids`. Não há modo automático, modo somente fontes, nem pesquisa na internet.

A pergunta vira embedding. A busca usa os chunks das fontes marcadas. Se os trechos não sustentam a resposta, o texto é:

Não encontrei informações suficientes nas fontes selecionadas para responder a essa pergunta.
Você pode adicionar novos documentos ou importar mais conteúdo pelo Web Scraping.

Se sustentam, a resposta usa só esse conteúdo e cita a página e os trechos usados. No Chat, cada importação com várias páginas aparece como um item, com a quantidade real de páginas e trechos. Dá para expandir e marcar o grupo inteiro ou só algumas páginas. O histórico do chat fica só na sessão do navegador.

## Validação em 02/10/2026
- PDF de Breaking, documento 13, “Com o que esse trabalho teve início, segundo o resumo?”: HTTP 200, `sufficient` true, `retrieval` `rag`. A resposta cita o TCC no Centro Universitário Ítalo Brasileiro. Trechos do PDF, `chunk_index` 51, 53, 46, 0 e 43. Sem “pesquisa na internet”.
- Página importada, documento 34, “Quais são os tipos de dados do PHP?”: HTTP 200, `sufficient` true, `retrieval` `rag`. A resposta lista os tipos do manual. Um trecho, `chunk_index` 0, `source_url` `https://www.php.net/manual/pt_BR/language.types.php`.
- A mesma pergunta do Everest no documento 13: HTTP 200, `sufficient` false, `retrieval` `rag`, a mensagem de informação insuficiente, sem trechos e sem pesquisa na internet.
- O log do servidor nessas conversas não tem chamada a `brave`, `search.brave` ou `api.search`.
- Página da introdução do manual, documento 44, dentro da importação de `docs.php`: HTTP 200, `sufficient` true, `retrieval` `rag`. A resposta descreve o PHP como linguagem de script de código aberto voltada à web. A fonte citada é `https://www.php.net/manual/en/introduction.php`. Sem pesquisa na internet.
- `npm run build`: exit 0. Restam avisos de orçamento de estilo, sem erro.
