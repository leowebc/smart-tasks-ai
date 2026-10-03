# SPEC-001 — Autenticação

**Status:** Em andamento

## Objetivo
Inicializar o backend Symfony 6 e implementar cadastro e login JWT, integrados ao Angular.

## Escopo
- **A:** Corrigir a inicialização do Symfony 6.4 e sua configuração de segurança.
- **B:** Implementar cadastro público e login com `username` e senha.
- **C:** Integrar o Angular ao JWT e proteger as rotas privadas.

## Decisões técnicas
- PHP 8.1, Symfony 6, Doctrine, MySQL e LexikJWT.
- Utilizar `json_login` com os handlers do Lexik; remover o login manual duplicado.
- Separar Controller, DTO, Service e Repository conforme a responsabilidade.
- Utilizar o hasher do Symfony para senhas.
- Manter chaves JWT e segredos fora do Git.
- Utilizar HTTP Interceptor para enviar o Bearer Token no Angular.
- Executar o ambiente localmente, sem depender de Docker.

## Critérios de aceite
- Symfony inicia sem erros de configuração.
- Cadastro válido persiste usuário com senha protegida por hash.
- Login válido retorna JWT; credenciais inválidas retornam 401.
- Cadastro e login são públicos; tarefas exigem autenticação.
- Angular armazena o token real e o envia às rotas protegidas.

## Validação
Testar a inicialização do Symfony, cadastro, login e acesso com/sem JWT. Registrar somente resultados efetivamente executados.

## Pendências
B2 concluída em 02/10/2026. Login válido HTTP 200 com `token`; senha incorreta HTTP 401; `GET /api/tasks` sem Bearer HTTP 401; com Bearer o firewall aceita e `TaskController::getTasks` responde 500 (`getDoctrine`). `lint:container` e `debug:router` exit 0. A integração Angular do JWT fica na SPEC-003. Próxima etapa, após autorização: SPEC-002.