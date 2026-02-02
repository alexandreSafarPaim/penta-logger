# Penta Logger

[![Latest Version on Packagist](https://img.shields.io/packagist/v/alexandresafarpaim/penta-logger.svg?style=flat-square)](https://packagist.org/packages/alexandresafarpaim/penta-logger)
[![Total Downloads](https://img.shields.io/packagist/dt/alexandresafarpaim/penta-logger.svg?style=flat-square)](https://packagist.org/packages/alexandresafarpaim/penta-logger)
[![License](https://img.shields.io/packagist/l/alexandresafarpaim/penta-logger.svg?style=flat-square)](https://packagist.org/packages/alexandresafarpaim/penta-logger)
[![PHP Version](https://img.shields.io/packagist/php-v/alexandresafarpaim/penta-logger.svg?style=flat-square)](https://packagist.org/packages/alexandresafarpaim/penta-logger)

> **[Read in English](intl/README_EN.md)**

Dashboard de streaming de logs em tempo real para aplicações Laravel. Monitore requisições, erros, APIs externas, jobs e tarefas agendadas - tudo em um só lugar, sem configuração.

## Funcionalidades

- **Logs de Requisições**: Método HTTP, endpoint, headers, corpo da requisição/resposta, status, duração
- **Logs de Erros**: Detalhes de exceções com stack trace otimizado (apenas seu código)
- **Logs de APIs Externas**: Rastreie todas as chamadas HTTP client com dados completos
- **Logs de Jobs**: Monitore jobs da fila - status, duração, tentativas, payload e exceções
- **Logs de Schedules**: Acompanhe tarefas agendadas - comando, expressão cron, duração, output
- **Dashboard em Tempo Real**: Server-Sent Events (SSE) para atualizações instantâneas
- **Filtros Avançados**: Filtre por método, status, endpoint, IP, body, nome do job, comando do schedule e intervalo de datas
- **Zero Configuração**: Funciona imediatamente, sem banco de dados ou setup
- **Seguro**: Desabilitado em produção por padrão, mascaramento automático de dados sensíveis

## Requisitos

- PHP 8.0+
- Laravel 8.65+ ou 9

> **Laravel 10+?** Use a versão mais recente: `composer require alexandresafarpaim/penta-logger:^1.0`

## Instalação

```bash
composer require alexandresafarpaim/penta-logger:^0.1
```

> **Dica**: Use `--dev` se quiser instalar apenas em desenvolvimento. Para uso em HML/produção, instale sem a flag.

### Versões

| Versão | Laravel | PHP |
|--------|---------|-----|
| `^1.0` | 10, 11, 12 | ^8.1 |
| `^0.1` (legacy) | 8.65+, 9 | ^8.0 |

Pronto! Acesse `http://sua-app.test/_penta-logger` para ver o dashboard.

## O Que é Registrado

### Requisições
Todas as requisições HTTP para sua aplicação com:
- Endereço IP, método HTTP, URL e path
- Headers e corpo da requisição
- Headers e corpo da resposta
- Código de status e duração

### Erros
Todas as exceções com:
- Classe e mensagem da exceção
- Arquivo e número da linha
- Stack trace filtrado (apenas seu código, não vendor)
- Contexto da requisição

### APIs Externas
Todas as chamadas HTTP client (`Http::get()`, etc.) com:
- URL e método
- Headers e corpo da requisição
- Status, headers e corpo da resposta
- Duração

### Jobs
Todos os jobs da fila com:
- Nome da classe e ID do job
- Nome da fila e conexão
- Número da tentativa e máximo de tentativas
- Payload/dados do job
- Duração e status (completed/failed)
- Detalhes da exceção se falhou

### Tarefas Agendadas
Todos os comandos agendados com:
- Comando ou closure
- Expressão cron
- Duração e status
- Output (se disponível)
- Detalhes da exceção se falhou

## Configuração (Opcional)

Publique o arquivo de configuração para personalizar:

```bash
php artisan vendor:publish --tag=penta-logger-config
```

### Variáveis de Ambiente

```env
# Configurações gerais
PENTA_LOGGER_ENABLED=true
PENTA_LOGGER_ROUTE_PREFIX=_penta-logger
PENTA_LOGGER_ALLOW_PRODUCTION=false

# Autenticação (opcional)
PENTA_LOGGER_USER=admin
PENTA_LOGGER_PASSWORD=secret

# Limite de logs por tipo (opcional, padrão: 500)
PENTA_LOGGER_MAX_REQUESTS=500
PENTA_LOGGER_MAX_ERRORS=500
PENTA_LOGGER_MAX_EXTERNAL_API=500
PENTA_LOGGER_MAX_JOBS=500
PENTA_LOGGER_MAX_SCHEDULES=500
```

### Opções de Configuração

```php
// config/penta-logger.php

return [
    // Habilitar/desabilitar o pacote
    'enabled' => env('PENTA_LOGGER_ENABLED', true),

    // Autenticação do dashboard
    'auth' => [
        'user' => env('PENTA_LOGGER_USER'),
        'password' => env('PENTA_LOGGER_PASSWORD'),
    ],

    // Prefixo da URL do dashboard
    'route_prefix' => '_penta-logger',

    // Middleware das rotas
    'middleware' => ['web'],

    // Máximo de logs por tipo (use 0 para desabilitar um tipo)
    'max_logs' => [
        'request' => env('PENTA_LOGGER_MAX_REQUESTS', 500),
        'error' => env('PENTA_LOGGER_MAX_ERRORS', 500),
        'external_api' => env('PENTA_LOGGER_MAX_EXTERNAL_API', 500),
        'job' => env('PENTA_LOGGER_MAX_JOBS', 500),
        'schedule' => env('PENTA_LOGGER_MAX_SCHEDULES', 500),
    ],

    // Habilitar em produção (requer autenticação)
    'allow_production' => false,

    // Paths a ignorar (suporta wildcards)
    'ignore_paths' => [
        '_penta-logger/*',
        'telescope/*',
        'horizon/*',
    ],

    // Headers a mascarar
    'mask_headers' => [
        'Authorization',
        'Cookie',
        'X-API-Key',
    ],

    // Campos a mascarar (correspondência parcial)
    'mask_fields' => [
        'password',
        'credit_card',
        'cvv',
        'token',
        'secret',
    ],
];
```

## Uso em Produção

Por padrão, o Penta Logger está **desabilitado em produção**. Para habilitá-lo com segurança:

1. Configure as credenciais de autenticação:

```env
PENTA_LOGGER_USER=admin
PENTA_LOGGER_PASSWORD=sua-senha-segura
```

2. Habilite o modo de produção:

```env
PENTA_LOGGER_ALLOW_PRODUCTION=true
```

## Segurança

- Desabilitado em produção por padrão
- Autenticação básica opcional para o dashboard
- Headers sensíveis (Authorization, Cookie, etc.) são mascarados
- Campos sensíveis (password, credit_card, token, etc.) são mascarados
- Stack traces mostram apenas arquivos da sua aplicação (não vendor)
- Corpos de resposta grandes são truncados

## Arquitetura

```
┌─────────────────────────────────────────────────────────────┐
│                    Sua Aplicação Laravel                     │
├─────────────────────────────────────────────────────────────┤
│  Middleware          │  Exception Handler  │  Event Listeners│
│  (Requisições)       │  (Erros)            │  (APIs/Jobs)    │
└──────────┬───────────┴─────────┬───────────┴────────┬────────┘
           │                     │                    │
           └─────────────────────┼────────────────────┘
                                 ▼
                    ┌────────────────────────┐
                    │     LogCollector       │
                    │   (Arquivo JSON Lines) │
                    └───────────┬────────────┘
                                │
                    ┌───────────┴────────────┐
                    │                        │
              ┌─────▼─────┐          ┌───────▼───────┐
              │ Dashboard │◄────SSE──│ StreamController│
              │  (HTML)   │          │  (Tempo Real)  │
              └───────────┘          └────────────────┘
```

## Licença

MIT
