# SPEC-004 — Upload
Status: Pipeline parcial

## Objetivo
Gravar documentos do usuário autenticado e listar apenas o que foi persistido.

## Escopo
- Área para arrastar arquivos e botão para selecioná-los.
- Lista "Documentos enviados" da conta do JWT.
- Estado vazio quando não houver arquivo.
- Falha de envio sem aparência de sucesso.

## Arquitetura
Symfony 6, Doctrine e MySQL para o registro. O arquivo fica fora do Git. A página entra no shell da SPEC-003. Ainda não há endpoint de upload.

## Critérios de aceite
- O arquivo enviado continua na lista depois de recarregar.
- Outro usuário não vê esse arquivo.
- A lista vazia não tem item de exemplo.
- Requisição sem JWT é recusada.

## Validação
Enviar um arquivo, recarregar e repetir com outro usuário.

Em 02/10/2026: migration ok; arquivo inválido 400; sem JWT 401; outro usuário 404. TXT e PDF foram gravados como `failed` porque `OPENAI_API_KEY` está vazia. Embeddings não foram gerados. `npm run build` exit 0.

## Decisão técnica
Tipo, tamanho máximo e pasta de armazenamento ficam para a autorização desta SPEC.
