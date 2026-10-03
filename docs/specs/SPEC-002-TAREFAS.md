# SPEC-002 — Tarefas
Status: Implementada no backend

## Objetivo
CRUD das tarefas do usuário autenticado, gravadas no MySQL.

## Escopo
- Listar, criar, editar e apagar somente as tarefas da conta do JWT.
- Título obrigatório e descrição opcional.
- Lista vazia sem tarefa de exemplo.
- Sem status de concluída ou pendente enquanto a tabela não tiver esse campo.

## Arquitetura
`Task`, `TaskRepository` e `TaskController` já existem. A entrega separa controller, service, DTO e repository. O Angular usa o token da SPEC-001.

## Critérios de aceite
- O usuário só vê e altera as próprias tarefas.
- Título vazio é rejeitado.
- A lista vazia permanece vazia.
- A resposta não inclui a senha do usuário.

## Validação
Exercitar o CRUD com dois usuários, depois da SPEC-001 aceita.

Em 02/10/2026, com dois JWT: listagem 200, criação 201, edição 200, exclusão 200, sem token 401, título inválido 400, tarefa ausente ou de outro usuário 404. A lista do outro usuário não continha a tarefa. `lint:container` exit 0. O Angular ainda não envia o Bearer.

## Decisão técnica
O DDL de `tasks` não tem coluna de status. Concluída e pendente ficam fora até essa coluna existir.
