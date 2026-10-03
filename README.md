# Smart Tasks AI

> Gestão de tarefas com autenticação JWT e uma base de conhecimento privada formada por documentos e páginas web escolhidos pelo usuário.

O Smart Tasks AI combina uma aplicação de tarefas com upload, Web Scraping sob demanda e chat RAG. Cada conta acessa somente suas próprias tarefas e fontes. O Chat responde com base nos trechos selecionados e não realiza pesquisa automática na internet.

## Tecnologias

### Backend

- PHP 8.1 e Symfony 6;
- Doctrine ORM, Doctrine Migrations e MySQL 8;
- LexikJWTAuthenticationBundle para autenticação stateless;
- `smalot/pdfparser` para extração de PDFs;
- API da OpenAI para embeddings e geração da resposta.

### Frontend

- Angular 17 e TypeScript 5.4;
- Angular Material, SCSS e Reactive Forms;
- NgRx Store e Effects para o estado do CRUD de tarefas;
- interceptor HTTP para envio do Bearer Token;
- RxJS e SweetAlert2.

## Funcionalidades implementadas

- cadastro e login com senha protegida e emissão de JWT;
- rotas privadas no Angular e API protegida, exceto cadastro e login;
- CRUD de tarefas isolado por usuário, com título obrigatório e descrição opcional;
- dashboard e interface responsiva para tarefas;
- upload de PDF e TXT, listagem, exclusão e reprocessamento de falhas;
- extração, divisão em chunks, geração de embeddings e persistência no MySQL;
- importação de uma página web ou navegação por links do mesmo site;
- agrupamento das páginas de uma importação;
- chat RAG com seleção explícita de documentos e páginas;
- resposta com fontes, trechos e indicação de insuficiência de contexto;
- exclusão de documentos, páginas e grupos pertencentes ao usuário.

## Arquitetura

```text
Angular 17
   |
   | HTTP + JWT
   v
Controllers REST do Symfony
   |
   v
Services de aplicação
   |
   +--> Repositories / Doctrine --> MySQL
   +--> extração e chunking
   +--> OpenAI (embeddings e chat)
```

O backend separa Controllers, DTOs, Services, Repositories e Entities. Os Controllers validam a entrada HTTP e delegam as regras; os Services coordenam autenticação, tarefas, upload, indexação, scraping e RAG; os Repositories concentram o acesso ao Doctrine.

As entidades persistidas são `User`, `Task`, `Document`, `DocumentChunk` e `SourceImport`. Consultas e alterações sempre consideram o usuário autenticado. Recursos de outra conta são tratados como não encontrados.

No frontend, o NgRx controla as tarefas. Autenticação, documentos, fontes e chat usam serviços HTTP específicos; o token fica no `localStorage` e é anexado pelo interceptor apenas às chamadas da API local.

## RAG fechado

O endpoint `POST /api/chat` recebe somente `question` e `source_ids`. O fluxo é:

1. validar a pergunta, a propriedade e o status das fontes;
2. gerar o embedding da pergunta com o mesmo modelo usado na indexação;
3. percorrer todos os chunks das fontes selecionadas em lotes;
4. calcular similaridade de cosseno e manter os cinco melhores trechos;
5. descartar candidatos abaixo do limiar configurado;
6. enviar ao LLM somente a pergunta e os trechos recuperados;
7. devolver resposta, fontes e excertos utilizados.

O prompt proíbe conhecimento geral, links inventados e instruções encontradas dentro do conteúdo recuperado. Quando os trechos não sustentam a resposta, a API retorna:

> Não encontrei informações suficientes nas fontes selecionadas para responder a essa pergunta.
>
> Você pode adicionar novos documentos ou importar mais conteúdo pelo Web Scraping.

**O Chat não possui provedor de busca web e não faz pesquisa automática na internet.** Uma página só entra na base depois que o usuário solicita sua importação.

## Upload e Web Scraping

### Upload

- aceita arquivos PDF e TXT de 1 byte a 10 MB;
- valida extensão e MIME type;
- exige texto UTF-8 em TXT;
- extrai somente texto selecionável de PDF; não há OCR;
- armazena os arquivos em `backend/var/documents`, fora do Git;
- usa chunks de até 800 caracteres, com sobreposição de 150;
- só marca o documento como `ready` após validar todos os embeddings;
- mantém falhas com mensagem segura e permite reprocessamento.

### Web Scraping

- aceita apenas URLs HTTP ou HTTPS sem credenciais;
- bloqueia hosts e endereços IP privados ou reservados para reduzir SSRF;
- revalida os endereços durante redirecionamentos;
- exige resposta HTML, limita cada página a 1 MB e aceita até três redirecionamentos;
- consulta as regras de `robots.txt` quando o arquivo está disponível;
- remove scripts, navegação e outros elementos de interface;
- preserva títulos, listas, tabelas, definições e blocos de código como texto estruturado;
- pela tela, segue links do mesmo site até 50 páginas; a API aceita limites entre 1 e 200;
- não reindexa uma URL já cadastrada para o mesmo usuário;
- registra a URL final na fonte e nos metadados de cada chunk.

## Estrutura do repositório

```text
backend/                 API Symfony
  config/                segurança, Doctrine e injeção de dependências
  migrations/            evolução do schema de documentos e fontes
  src/Controller/        endpoints REST
  src/Service/           regras de aplicação, indexação, scraping e RAG
frontend/                SPA Angular 17
database/task_app_ddl.sql schema base de usuários e tarefas
docs/specs/              decisões e validações das entregas
docs/adr/                registro de arquitetura
```

## Instalação

### Pré-requisitos

- PHP 8.1 ou compatível, Composer e extensões `curl`, `dom`, `fileinfo`, `mbstring` e `pdo_mysql`;
- MySQL 8;
- Node.js compatível com Angular 17 e npm;
- OpenSSL para as chaves JWT;
- chave de API da OpenAI para indexação e chat.

### Banco de dados

Em um MySQL vazio, crie o banco e execute `database/task_app_ddl.sql`. Depois, no diretório `backend`, com `DATABASE_URL` nesse banco, rode `php bin/console doctrine:migrations:migrate --no-interaction`.

Exemplo sem credenciais reais, executado a partir da raiz:

```bash
mysql -u SEU_USUARIO -p -e "CREATE DATABASE task_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u SEU_USUARIO -p task_app < database/task_app_ddl.sql
```

Essa ordem foi validada em 02/10/2026 num banco temporário: as três migrations criaram `documents`, `document_chunks` e `source_imports` sobre `users` e `tasks`. O banco `task_app` local não foi alterado durante essa validação.

### Backend

```bash
cd backend
cp .env.example .env.local
composer install
php bin/console lexik:jwt:generate-keypair
php bin/console doctrine:migrations:migrate --no-interaction
php -S 127.0.0.1:9000 -t public public/index.php
```

Antes de gerar as chaves ou executar migrations, preencha `backend/.env.local`. A pasta `backend/config/jwt` e o arquivo local de ambiente são ignorados pelo Git.

### Frontend

Em outro terminal:

```bash
cd frontend
npm ci
npm start
```

A aplicação fica em `http://localhost:4200` e os serviços Angular apontam para `http://localhost:9000/api`. O CORS do backend permite as origens locais nas portas configuradas.

## Deploy de teste no Render

### Backend

Crie um Web Service Docker usando `backend` como diretório raiz. O container usa Apache, serve `backend/public` e escuta a porta informada pelo Render em `PORT`. O ambiente do container já assume `APP_ENV=prod` e `APP_DEBUG=0`.

Cadastre no painel do Render, sem salvar valores reais no repositório:

- `APP_SECRET`;
- `DATABASE_URL`, no formato `mysql://USUARIO:SENHA@HOST:3306/BANCO?serverVersion=8.0&charset=utf8mb4`;
- `JWT_PASSPHRASE`;
- `JWT_PRIVATE_KEY_BASE64` e `JWT_PUBLIC_KEY_BASE64`;
- `OPENAI_API_KEY`;
- opcionalmente, `EMBEDDING_MODEL` e `CHAT_MODEL`.

Para transportar as chaves JWT como variáveis de ambiente, gere o par localmente e codifique cada arquivo. Os resultados devem ser copiados diretamente para os campos secretos do Render:

```bash
cd backend
php bin/console lexik:jwt:generate-keypair
base64 -w 0 config/jwt/private.pem
base64 -w 0 config/jwt/public.pem
```

O entrypoint decodifica as chaves somente dentro do container. Também é possível usar arquivos secretos e sobrescrever `JWT_SECRET_KEY` e `JWT_PUBLIC_KEY` com seus caminhos absolutos. Não execute migrations automaticamente ao iniciar cada instância.

### Frontend

A URL da API fica centralizada em:

- `frontend/src/environments/environment.ts` para desenvolvimento;
- `frontend/src/environments/environment.production.ts` para produção.

A configuração de produção usa `/api` como fallback para uma implantação na mesma origem. No Static Site do Render, cadastre `API_URL` com a URL pública completa do backend, incluindo `/api`; o script `postbuild` grava essa configuração somente no bundle gerado, sem alterar os fontes Angular. O CORS do backend autoriza o frontend em `https://smart-tasks-ai.onrender.com`.

```bash
cd frontend
npm ci
npm run build
```

Para um Static Site no Render, publique `frontend/dist/frontend/browser` e configure uma regra de rewrite de `/*` para `/index.html`, necessária para as rotas do Angular.

## SQL e Doctrine Migrations

O arquivo `database/task_app_ddl.sql` cria as tabelas base:

- `users`;
- `tasks`.

As migrations devem ser aplicadas depois do DDL:

- `Version20261002180000`: cria `documents` e `document_chunks`;
- `Version20261002210000`: adiciona `source_url` aos documentos;
- `Version20261002223000`: cria `source_imports` e relaciona suas páginas.

Na primeira preparação do MySQL remoto, aplique o DDL base uma única vez a partir da raiz do projeto:

```bash
mysql -h HOST -P 3306 -u USUARIO -p BANCO < database/task_app_ddl.sql
```

Depois, no Shell do Web Service do Render, confira e execute as migrations com as variáveis de produção já configuradas:

```bash
php bin/console doctrine:migrations:status --env=prod
php bin/console doctrine:migrations:migrate --no-interaction --env=prod
```

Os embeddings são armazenados em JSON junto com o modelo e os metadados da origem. Arquivos enviados permanecem no filesystem e não são gravados no repositório.

## Variáveis de ambiente

Use `backend/.env.example` como modelo e mantenha os valores reais somente em `backend/.env.local`.

- `APP_ENV`: ambiente do Symfony;
- `APP_DEBUG`: ativa ou desativa o modo de depuração;
- `APP_SECRET`: segredo interno da aplicação;
- `JWT_SECRET_KEY`: caminho da chave privada JWT;
- `JWT_PUBLIC_KEY`: caminho da chave pública JWT;
- `JWT_PASSPHRASE`: senha das chaves JWT;
- `JWT_PRIVATE_KEY_BASE64`: chave privada codificada para o container;
- `JWT_PUBLIC_KEY_BASE64`: chave pública codificada para o container;
- `DATABASE_URL`: conexão com o MySQL;
- `OPENAI_API_KEY`: credencial usada por embeddings e chat;
- `EMBEDDING_MODEL`: modelo de embeddings;
- `CHAT_MODEL`: modelo de chat.
- `API_URL`: URL pública da API incorporada ao build de produção do frontend.

Não versione `.env`, `.env.local`, chaves PEM, credenciais ou arquivos enviados.

## REST API

`POST /api/register` e `POST /api/login` são públicos. Todos os demais endpoints exigem `Authorization: Bearer <jwt>`.

### Autenticação

- `POST /api/register` — cria uma conta com `username` e `password`;
- `POST /api/login` — autentica pelo `json_login` e devolve o JWT.

### Tarefas

- `GET /api/tasks` — lista as tarefas da conta;
- `POST /api/tasks` — cria uma tarefa;
- `PUT /api/tasks/{id}` — altera título e descrição;
- `DELETE /api/tasks/{id}` — exclui uma tarefa.

### Documentos

- `GET /api/documents` — lista uploads;
- `POST /api/documents` — recebe `multipart/form-data` no campo `file`;
- `GET /api/documents/{id}` — detalha um documento;
- `DELETE /api/documents/{id}` — exclui um upload;
- `POST /api/documents/{id}/retry` — reprocessa um upload com falha.

### Fontes web

- `GET /api/sources` — lista páginas avulsas e importações agrupadas;
- `POST /api/sources` — importa `url` e aceita `follow_links`;
- `DELETE /api/sources/{id}` — exclui uma página;
- `POST /api/sources/{id}/retry` — reprocessa uma página com falha;
- `DELETE /api/source-imports/{id}` — exclui uma importação inteira.

### Chat

- `POST /api/chat` — responde a `question` usando exclusivamente os `source_ids` selecionados.

## Validações executadas

Os resultados detalhados estão em `docs/specs`.

- `php bin/console lint:container`: concluído sem erros;
- `php bin/console debug:router`: as 17 rotas sob `/api` foram carregadas;
- `npm run build`: concluído; restaram avisos de orçamento de estilos e dependência CommonJS;
- autenticação: login válido `200`, senha inválida `401` e rota protegida sem JWT `401`;
- tarefas: CRUD exercitado com duas contas, incluindo isolamento, título inválido `400` e recurso alheio `404`;
- upload: arquivo inválido `400`, ausência de JWT `401` e acesso por outra conta `404`;
- scraping: página do manual do PHP importada com URL e embeddings persistidos; uma página validada gerou 49 chunks de 1536 dimensões;
- RAG: uma importação com 198 páginas e 1.997 chunks recuperou conteúdo além dos primeiros 300 chunks;
- insuficiência: pergunta sem suporte retornou `sufficient: false`, sem fontes ou trechos e sem chamada a busca web.

Não há, neste repositório, uma suíte automatizada completa de integração do backend ou testes end-to-end. Os arquivos `*.spec.ts` do Angular cobrem componentes e serviços básicos, mas o histórico de validação não registra uma execução completa do Karma.

## Limitações conhecidas

- embeddings e respostas dependem da OpenAI e de conectividade externa;
- indexação e scraping são síncronos; importações grandes mantêm a requisição aberta;
- embeddings ficam em JSON no MySQL e a similaridade é calculada em PHP, sem banco vetorial;
- PDFs digitalizados sem texto selecionável exigiriam OCR, que não foi implementado;
- o build de produção precisa receber `API_URL` quando a API usar um domínio diferente do padrão;
- o histórico do Chat não é persistido e se perde ao recarregar a aplicação;
- tarefas não possuem estado de concluída porque o DDL original não inclui essa coluna;
- as telas “Bases” e “Configurações” são placeholders;
- Redis e RabbitMQ permanecem no Compose, mas não participam do fluxo da aplicação;
- o Compose não entrega o frontend nem substitui o fluxo local validado;
- não existe busca automática na internet durante uma conversa.

## Skeletons do desafio

Este projeto partiu dos skeletons de backend PHP/Symfony e frontend Angular fornecidos pelo avaliador para o teste técnico de Desenvolvedor AGU. Eles serviram somente como base inicial.

Este repositório contém a evolução independente solicitada pelo desafio. Os repositórios originais não são remotos, submódulos ou dependências da solução.
