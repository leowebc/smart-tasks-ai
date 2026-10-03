# ADR-001 — Arquitetura

Status: aceito como guia. Não autoriza implementação.

Stack: PHP 8.1, Symfony 6, Doctrine, MySQL, JWT, Angular 17, Material, Reactive Forms e NgRx.

O backend separa controller, service, DTO e repository. O NgRx guarda sessão e tarefas. O guard protege a tela. A API exige JWT.

A SPEC-001 corrige o `security.yaml` antes do cadastro. Ele usa chaves do Symfony 5, e o lock traz o security-bundle 6.4.9. O `POST /api/login` fica com um único tratamento.

Upload, scraping e resposta com contexto são as SPECs 004 a 006. Sem API, a tela fica vazia. A geração só roda com trechos dos documentos e URLs da própria conta.

Redis e RabbitMQ estão no Compose e ficam de fora. Chaves e `.env` não entram no Git.
