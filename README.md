# Lumina Track: Sistema de Rastreamento de Entregas por NF-e
![PHP Version](https://img.shields.io/badge/PHP-5.3+-777BB4?style=for-the-badge&logo=php)
![Database](https://img.shields.io/badge/MySQL-4479A1?style=for-the-badge&logo=mysql)
![Frontend](https://img.shields.io/badge/JavaScript-F7DF1E?style=for-the-badge&logo=javascript)

Lumina Track é uma aplicação web leve para o rastreamento de entregas a partir de dados de Notas Fiscais Eletrônicas (NF-e). O sistema permite o upload de arquivos XML, processa e armazena as informações, e exibe o status e a localização em uma interface simples com um mapa interativo.

## 📜 Sumário

- [1. Visão Geral e Funcionalidades](#1-visão-geral-e-funcionalidades)
- [2. Arquitetura e Tecnologias](#2-arquitetura-e-tecnologias)
  - [Componentes da Stack](#componentes-da-stack)
  - [Estrutura de Arquivos](#estrutura-de-arquivos)
- [3. API Endpoints](#3-api-endpoints)
- [4. Schema do Banco de Dados](#4-schema-do-banco-de-dados)
- [5. Guia de Instalação e Execução](#5-guia-de-instalação-e-execução)
  - [Pré-requisitos](#pré-requisitos)
  - [Passos para Instalação](#passos-para-instalação)
- [6. Documentação Adicional](#6-documentação-adicional)

---

## 1. Visão Geral e Funcionalidades

O projeto foi desenhado para ser uma solução de rastreamento ponta a ponta, focada na simplicidade e compatibilidade com ambientes de servidor legados.

-   **Upload de NF-e**: Ingestão de dados de entrega através do upload de arquivos XML.
-   **Rastreamento por Chave**: Consulta do status e do histórico de eventos de uma entrega usando a chave da NF-e (44 dígitos).
-   **Visualização em Mapa**: Exibição da localização do destinatário em um mapa interativo (OpenStreetMap).
-   **Timeline de Eventos**: Apresentação do histórico de status da entrega em uma linha do tempo clara e cronológica.
-   **Painel de Métricas**: KPIs em tempo real do total de entregas, finalizadas e em andamento.
-   **API RESTful**: Endpoints para integração e gerenciamento das entregas e seus eventos.

## 2. Arquitetura e Tecnologias

O sistema foi construído com uma arquitetura cliente-servidor, utilizando PHP "vanilla" (puro) no back-end e JavaScript "vanilla" no front-end.

### Componentes da Stack

-   **Front-end**:
    -   **Tecnologias**: HTML5, CSS3, JavaScript (ES6+).
    -   **Mapa**: Biblioteca [Leaflet.js](https://leafletjs.com/) consumindo tiles do [OpenStreetMap](https://www.openstreetmap.org/).
    -   **Comunicação**: Realiza chamadas assíncronas (`fetch`) para a API do back-end.

-   **Back-end**:
    -   **Tecnologia**: PHP 5.3+ (sem frameworks).
    -   **Servidor**: Compatível com o servidor embutido do PHP, Apache ou Nginx.
    -   **Análise de XML**: Utiliza a extensão `XMLReader` para um processamento de baixo consumo de memória, garantindo robustez com arquivos grandes.

-   **Banco de Dados**:
    -   **Sistema**: MySQL.
    -   **Tabelas**: `entregas` (dados da NF-e) e `eventos` (histórico de status).

-   **APIs Externas**:
    -   **Geocodificação**: [Nominatim API (OpenStreetMap)](https://nominatim.org/release-docs/develop/api/Search/).
    -   **Proxy CORS**: As chamadas para o Nominatim são feitas através de um proxy público (`cors-anywhere.herokuapp.com`) para garantir a compatibilidade do front-end.

### Estrutura de Arquivos

A organização dos arquivos segue o princípio de separação de responsabilidades:

```
lumina-track/
├── backend/
│   ├── config.php         # Configurações de ambiente (banco de dados)
│   ├── functions.php      # Lógica de negócio principal (parsing, DB, etc.)
│   ├── routes.php         # Roteador da API que processa as requisições
│   └── db.sql             # Schema do banco de dados
├── public/
│   ├── index.html         # Ponto de entrada do front-end (SPA)
│   └── assets/
│       ├── css/styles.css # Estilização visual
│       └── js/app.js      # Lógica do cliente (chamadas API, mapa)
├── tests/                 # Arquivos XML para testes de upload
├── .htaccess              # Regras de reescrita de URL para Apache
├── router.php             # Roteador para o servidor embutido do PHP
└── README.md              # Esta documentação
```

## 3. API Endpoints

O roteamento é centralizado no arquivo `backend/routes.php`.

| Método | Rota                       | Descrição                                                                 |
| :----- | :------------------------- | :------------------------------------------------------------------------ |
| `POST` | `/upload`                  | Recebe um arquivo `nfe_xml` para processar e registrar uma nova entrega.  |
| `POST` | `/webhook`                 | Endpoint para registrar novos eventos de status para uma entrega existente. |
| `GET`  | `/entregas`                | Lista todas as entregas (suporta paginação com `?page=` e `?limit=`).     |
| `GET`  | `/entregas/{nfe_key}`      | Retorna os detalhes de uma entrega específica.                            |
| `GET`  | `/rastreamento/{nfe_key}`  | Retorna os dados da entrega e sua timeline de eventos completa.           |
| `GET`  | `/metricas`                | Retorna um JSON com as métricas de entregas totais, em andamento e finalizadas. |

## 4. Schema do Banco de Dados

O schema do banco de dados está definido em `backend/db.sql`. As tabelas principais são `entregas` e `eventos`, relacionadas por `entrega_id`.

## 5. Guia de Instalação e Execução

### Pré-requisitos

-   PHP 5.3+
-   MySQL
-   Um servidor web (Apache, Nginx, ou o servidor embutido do PHP)

### Passos para Instalação

1.  **Clone o repositório:**
    ```bash
    git clone https://github.com/DevFalconsz/Lumina-Track.git
    cd Lumina-Track
    ```

2.  **Configure o Banco de Dados:**
    -   Crie um banco de dados no seu servidor MySQL.
    -   Importe o schema inicial: `mysql -u seu_usuario -p seu_banco < backend/db.sql`.

3.  **Configure o Ambiente:**
    -   Renomeie `backend/config.php.example` para `backend/config.php`.
    -   Edite `backend/config.php` com as credenciais do seu banco de dados.

4.  **Inicie o Servidor (Método Recomendado):**
    -   O `router.php` simula o comportamento do `.htaccess` para o servidor embutido do PHP.
    -   No diretório raiz do projeto, execute:
        ```bash
        php -S localhost:8000 router.php
        ```
    -   Acesse a aplicação em `http://localhost:8000`.

## 6. Documentação Adicional

Para mais detalhes sobre o uso e o deploy, consulte os seguintes documentos:

-   **[📖 Manual do Usuário](USER_MANUAL.md)**: Um guia passo a passo sobre como usar a interface do sistema.
-   **[📦 Guia de Deploy com Docker](DEPLOYMENT.md)**: Instruções detalhadas para executar o projeto em um ambiente Docker containerizado.
