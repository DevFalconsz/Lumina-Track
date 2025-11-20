# Lumina Track - Sistema de Rastreamento de Entregas

Lumina Track é uma aplicação web leve para o rastreamento de entregas a partir de dados de Notas Fiscais Eletrônicas (NF-e). O sistema permite o upload de arquivos XML de NF-e, processa e armazena as informações de entrega, e exibe o status e a localização em uma interface simples com um mapa interativo.

## 1. Funcionalidades Principais

- **Upload de NF-e**: Ingestão de dados de entrega através do upload de arquivos XML de NF-e.
- **Rastreamento por Chave**: Consulta do status e do histórico de eventos de uma entrega usando a chave da NF-e (44 dígitos).
- **Visualização em Mapa**: Exibição da localização do destinatário em um mapa interativo (OpenStreetMap).
- **Timeline de Eventos**: Apresentação do histórico de status da entrega em uma linha do tempo clara e cronológica.
- **Painel de Métricas**: KPIs em tempo real do total de entregas, finalizadas e em andamento.
- **API RESTful**: Endpoints para integração e gerenciamento das entregas e seus eventos.

## 2. Arquitetura Técnica

O sistema foi construído com uma arquitetura cliente-servidor, utilizando PHP "vanilla" (puro) no back-end para máxima compatibilidade com ambientes legados e JavaScript "vanilla" no front-end.

### Componentes:

- **Front-end**:
  - **Tecnologias**: HTML5, CSS3, JavaScript (ES6+).
  - **Mapa**: Biblioteca [Leaflet.js](https://leafletjs.com/) consumindo tiles do [OpenStreetMap](https://www.openstreetmap.org/).
  - **Comunicação**: Realiza chamadas assíncronas (`fetch`) para a API do back-end.

- **Back-end**:
  - **Tecnologia**: PHP 5.3+ (sem frameworks).
  - **Servidor**: Roda com o servidor embutido do PHP ou em um ambiente LAMP/XAMPP.
  - **Análise de XML**: Utiliza a extensão `XMLReader` para um processamento de baixo consumo de memória, garantindo robustez com arquivos grandes.

- **Banco de Dados**:
  - **Sistema**: MySQL.
  - **Tabelas Principais**:
    - `entregas`: Armazena os dados mestres da NF-e (chave, destinatário, endereço).
    - `eventos`: Registra o histórico de status de cada entrega (ex: "Em trânsito", "Entregue").

- **APIs Externas**:
  - **Geocodificação**: [Nominatim API (OpenStreetMap)](https://nominatim.org/release-docs/develop/api/Search/).
  - **Proxy CORS**: Devido a limitações de segurança (CORS e User-Agent) e para garantir a compatibilidade do front-end, as chamadas para o Nominatim são feitas através de um proxy público (`cors-anywhere.herokuapp.com`).

### Estrutura de Arquivos

A organização dos arquivos segue o princípio de separação de responsabilidades, dividindo claramente o back-end, o front-end e os arquivos de configuração.

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

- **`backend/`**: Contém toda a lógica do lado do servidor.
  - **`config.php`**: Define as credenciais de conexão com o banco de dados MySQL.
  - **`functions.php`**: O coração da lógica de negócio. Inclui funções para analisar XML, interagir com o banco de dados e normalizar dados.
  - **`routes.php`**: Atua como o controlador da API. Recebe as requisições, identifica o endpoint e chama a função correspondente.
  - **`db.sql`**: Script SQL para a criação da estrutura inicial do banco de dados.

- **`public/`**: Diretório raiz para todos os arquivos acessíveis publicamente pelo navegador.
  - **`index.html`**: A Single Page Application (SPA). É o único arquivo HTML que carrega os assets e define a estrutura da interface.
  - **`assets/js/app.js`**: Contém toda a lógica do lado do cliente, responsável por manipular o DOM, gerenciar eventos, fazer chamadas `fetch` para a API e controlar o mapa.

- **`tests/`**: Contém uma coleção de arquivos XML de NF-e utilizados para testes manuais.

- **`.htaccess`**: Arquivo de configuração do Apache. Define regras para direcionar todas as requisições da API para `backend/routes.php`. Não é utilizado pelo servidor embutido do PHP.

- **`router.php`**: Script de roteamento que permite que o servidor embutido do PHP (`php -S`) simule o comportamento do `.htaccess`, direcionando o tráfego corretamente. É a forma recomendada para rodar o projeto localmente.

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

## 4. Estrutura do Banco de Dados

O schema do banco de dados está definido em `backend/db.sql`.

### Tabela `entregas`
Armazena as informações extraídas do XML da NF-e.

| Coluna           | Tipo          | Descrição                               |
| ---------------- | ------------- | --------------------------------------- |
| `id`             | `INT` (PK, AI)| ID único da entrega.                    |
| `nfe_key`        | `VARCHAR(44)` | Chave única da NF-e.                    |
| `dest_name`      | `VARCHAR(255)`| Nome do destinatário.                   |
| `dest_cep`       | `VARCHAR(9)`  | CEP do destinatário.                    |
| `dest_logradouro`| `VARCHAR(255)`| Logradouro do destinatário.             |
| `dest_numero`    | `VARCHAR(255)`| Número do endereço.                     |
| `dest_bairro`    | `VARCHAR(255)`| Bairro do destinatário.                 |
| `dest_municipio` | `VARCHAR(255)`| Município do destinatário.              |
| `dest_uf`        | `VARCHAR(2)`  | UF do destinatário.                     |
| `dest_lat`       | `DECIMAL(10,8)`| Latitude (pode ser `NULL`).             |
| `dest_lng`       | `DECIMAL(11,8)`| Longitude (pode ser `NULL`).            |
| `created_at`     | `TIMESTAMP`   | Data de registro.                       |

### Tabela `eventos`
Armazena o histórico de status de cada entrega.

| Coluna       | Tipo          | Descrição                               |
| ------------ | ------------- | --------------------------------------- |
| `id`         | `INT` (PK, AI)| ID único do evento.                     |
| `entrega_id` | `INT` (FK)    | Chave estrangeira para `entregas.id`.   |
| `status`     | `VARCHAR(255)`| Descrição do evento (ex: "Em trânsito").|
| `event_date` | `TIMESTAMP`   | Data e hora do evento.                  |

## 5. Instalação e Execução

### Requisitos

- PHP 5.3+
- MySQL
- Um servidor web (Apache, Nginx, ou o servidor embutido do PHP)

### Passos para Instalação

1.  **Clone o repositório:**
    ```bash
    git clone https://github.com/seu-usuario/lumina-track.git
    cd lumina-track
    ```

2.  **Banco de Dados:**
    - Crie um banco de dados no seu servidor MySQL.
    - Importe o schema inicial: `mysql -u seu_usuario -p seu_banco < backend/db.sql`.

3.  **Configuração:**
    - Renomeie `backend/config.php.example` para `backend/config.php`.
    - Edite `backend/config.php` com as credenciais do seu banco de dados.

4.  **Iniciando o Servidor (Recomendado: Servidor Embutido do PHP):**
    - O método mais simples para rodar o projeto localmente é usar o `router.php` fornecido, que simula o comportamento do `.htaccess`.
    - No diretório raiz do projeto, execute o comando:
      ```bash
      php -S localhost:8000 router.php
      ```
    - Acesse a aplicação no seu navegador em `http://localhost:8000`.

### Executando com Docker

*(Esta seção será preenchida com as instruções para um deploy simplificado via Docker.)*

---

*Documentação gerada em 20/11/2025.*