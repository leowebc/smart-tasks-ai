# Guia de entrevista

Etapa A da SPEC-001 concluída. Cadastro, JWT e Angular não foram implementados nesta etapa.

## O que existe

O Symfony 6.4.9 sobe no PHP 8.4.23 local, sem Docker. `security.yaml` usa `password_hashers`, `json_login` com username e o autenticador `jwt`. As rotas de login, cadastro e tarefas carregam sem duplicidade. A tela de login do Angular continua enviando username e senha.

## O que está planejado

A SPEC-001 corrige a subida do Symfony e o JWT. A SPEC-002 fecha o CRUD por usuário. A SPEC-003 unifica o visual. As SPECs 004, 005 e 006 cobrem upload, scraping e resposta com contexto.

## Testes

Em 02/10/2026, no backend, com código 0: `php bin/console about`, `debug:router` e `lint:container`. Cadastro e login não foram chamados.
