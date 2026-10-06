# ADR-001 — Arquitetura

**Status:** aceita

## Contexto

O projeto precisa atender autenticação, tarefas e consulta a fontes privadas sem misturar regras de negócio com HTTP ou interface.

## Decisão

- Backend em PHP 8.1, Symfony 6, Doctrine e MySQL.
- Frontend em Angular 17, Material e Reactive Forms.
- API stateless protegida por JWT.
- Controllers tratam HTTP; Services coordenam regras; Repositories acessam o banco.
- NgRx gerencia o estado das tarefas. Os outros recursos usam serviços HTTP locais.
- O RAG usa apenas fontes selecionadas e pertencentes ao usuário.

## Consequências

A separação facilita testes e evolução, mas aumenta a quantidade de classes. A indexação permanece síncrona e a similaridade é calculada na aplicação; filas e banco vetorial ficam como opções para uma escala maior. Redis e RabbitMQ não fazem parte do fluxo atual.

Chaves, certificados, credenciais, `.env` local e arquivos enviados não devem entrar no Git.
