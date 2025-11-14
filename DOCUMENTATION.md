# Documentação Técnica Inicial - Sistema de Rastreamento de Entregas

## 1. Visão Geral do Projeto

O objetivo deste projeto é desenvolver um mini sistema de rastreamento de entregas. O sistema deverá ser capaz de ingerir dados de Nota Fiscal Eletrônica (NF-e) via upload de arquivo XML, receber atualizações de status de entrega através de um webhook, persistir esses dados em um banco de dados MySQL e exibir as informações em um front-end com um mapa interativo.

## 2. Stack de Tecnologia Obrigatória

*   **Back-end:** PHP 5.2/5.3 (sem uso de frameworks)
*   **Banco de Dados:** MySQL
*   **Front-end:** HTML, CSS, JavaScript (vanilla)
*   **Mapas:** Leaflet.js com OpenStreetMap

## 3. Arquitetura e Estrutura de Diretórios

A estrutura de diretórios sugerida para organizar o projeto é a seguinte:

```
/
|-- backend/
|   |-- config.php         # Configurações (ex: conexão com DB)
|   |-- functions.php      # Funções reutilizáveis (parsing, geocodificação, etc.)
|   |-- routes.php         # Roteador para tratar as requisições da API
|   |-- db.sql             # Schema inicial do banco de dados
|
|-- public/
|   |-- index.html         # Página principal da aplicação
|   |-- assets/
|       |-- css/
|       |   |-- styles.css # Folhas de estilo
|       |-- js/
|           |-- app.js     # Lógica do front-end (chamadas API, mapa)
|
|-- tests/                 # Testes (opcional)
|-- README.md              # Instruções de deploy e descrição do projeto
```

## 4. Banco de Dados

O banco de dados será em MySQL. O schema mínimo deve conter duas tabelas principais: `entregas` e `eventos`.

### Tabela: `entregas`

Armazena as informações principais da NF-e.

| Coluna          | Tipo         | Descrição                               |
| --------------- | ------------ | --------------------------------------- |
| `id`            | INT (PK, AI) | Identificador único da entrega.         |
| `nfe_key`       | VARCHAR(44)  | Chave da NF-e (única).                  |
| `dest_name`     | VARCHAR(255) | Nome do destinatário.                   |
| `dest_cep`      | VARCHAR(9)   | CEP do destinatário.                    |
| `dest_lat`      | DECIMAL(10,8)| Latitude do endereço de entrega.        |
| `dest_lng`      | DECIMAL(11,8)| Longitude do endereço de entrega.       |
| `created_at`    | TIMESTAMP    | Data de criação do registro.            |

### Tabela: `eventos`

Armazena os eventos de rastreamento associados a uma entrega.

| Coluna          | Tipo         | Descrição                               |
| --------------- | ------------ | --------------------------------------- |
| `id`            | INT (PK, AI) | Identificador único do evento.          |
| `entrega_id`    | INT (FK)     | Chave estrangeira para a tabela `entregas`. |
| `status`        | VARCHAR(255) | Descrição do status (ex: "Em trânsito").|
| `event_date`    | TIMESTAMP    | Data e hora do evento.                  |

**SQL para criação das tabelas (`db.sql`):**

```sql
CREATE TABLE entregas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nfe_key VARCHAR(44) NOT NULL UNIQUE,
    dest_name VARCHAR(255) NOT NULL,
    dest_cep VARCHAR(9) NOT NULL,
    dest_lat DECIMAL(10, 8),
    dest_lng DECIMAL(11, 8),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE eventos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entrega_id INT NOT NULL,
    status VARCHAR(255) NOT NULL,
    event_date TIMESTAMP NOT NULL,
    FOREIGN KEY (entrega_id) REFERENCES entregas(id) ON DELETE CASCADE
);
```

## 5. Back-end (PHP)

O back-end será responsável por toda a lógica de negócio e exposição dos dados via API REST.

### 5.1. Ingestão de NF-e (XML)

*   **Endpoint:** `POST /upload`
*   **Descrição:** Recebe um arquivo XML de NF-e. O script PHP deve fazer o parsing do arquivo, extrair as informações relevantes (chave da NF-e, dados do destinatário), geocodificar o CEP para obter as coordenadas e salvar os dados na tabela `entregas`.
*   **Validações:**
    *   Verificar se o arquivo é um XML válido.
    *   Verificar se uma NF-e com a mesma chave já não existe no banco.

### 5.2. Webhook de Eventos

*   **Endpoint:** `POST /webhook`
*   **Descrição:** Recebe um payload JSON com atualizações de status de entrega. O script deve validar o JSON, encontrar a entrega correspondente pela chave da NF-e e inserir um novo registro na tabela `eventos`.
*   **Exemplo de Payload JSON:**
    ```json
    {
      "nfe_key": "CHAVE_DA_NFE_AQUI",
      "status": "Entrega realizada com sucesso",
      "event_date": "2025-11-14T10:30:00Z"
    }
    ```

### 5.3. Integração de Geocodificação

*   **Serviço:** ViaCEP para consulta de CEP e Nominatim (OpenStreetMap) para geocodificação (ou um serviço de mock).
*   **Fluxo:** Ao receber uma nova NF-e, o sistema consulta o ViaCEP com o CEP do destinatário. Com o endereço obtido, consulta o Nominatim para obter as coordenadas de latitude e longitude.
*   **Contingência:** Se a geocodificação falhar, as coordenadas podem ser armazenadas como `NULL`.

### 5.4. APIs REST

As seguintes rotas devem ser implementadas em `routes.php`:

*   **`GET /entregas`**: Lista todas as entregas com paginação.
*   **`GET /entregas/{nfe_key}`**: Busca uma entrega específica pela chave da NF-e.
*   **`GET /rastreamento/{nfe_key}`**: Retorna a entrega e todos os seus eventos de rastreamento associados (timeline).
*   **`GET /metricas`**: Retorna métricas/KPIs, como total de entregas, entregas em andamento, etc.

## 6. Front-end (HTML/CSS/JS)

O front-end será uma Single Page Application (SPA) simples para interagir com o back-end.

### 6.1. Componentes da Interface

*   **Formulário de Upload:** Um formulário para o usuário enviar o arquivo XML da NF-e.
*   **Campo de Busca:** Um campo de input para o usuário buscar uma entrega pela chave da NF-e.
*   **Área de Resultados:**
    *   **Timeline:** Exibe os eventos de rastreamento em ordem cronológica.
    *   **Mapa (Leaflet):** Mostra um marcador na localização do destinatário.
    *   **KPIs:** Exibe as métricas retornadas pela API.

### 6.2. Lógica (app.js)

*   **Requisições AJAX (Fetch API):**
    *   `POST` para `/upload` com o arquivo XML.
    *   `GET` para `/rastreamento/{nfe_key}` ao realizar uma busca.
    *   `GET` para `/metricas` para popular a área de KPIs.
*   **Manipulação do DOM:**
    *   Atualizar a timeline e o mapa com os dados recebidos da API.
    *   Exibir mensagens de sucesso ou erro para o usuário.
*   **Mapa Interativo:**
    *   Inicializar o mapa Leaflet.
    *   Adicionar/atualizar o marcador no mapa com as coordenadas da entrega.

## 7. Critérios de Avaliação a serem Observados

*   **Corretude:** As APIs devem funcionar conforme especificado, o parsing do XML deve ser preciso e a persistência dos dados correta.
*   **Qualidade de Código:** O código deve ser bem organizado, seguro (prevenir SQL Injection, XSS) e compatível com PHP 5.2/5.3.
*   **Modelo de Dados:** As tabelas devem estar normalizadas e com índices adequados (ex: na coluna `nfe_key`).
*   **Front-end/UX:** A interface deve ser responsiva e intuitiva.
*   **Integrações:** O consumo das APIs de geocodificação deve ser tratado corretamente.
*   **Desempenho:** Evitar loops desnecessários e implementar paginação na listagem de entregas.

---

Esta documentação serve como um ponto de partida. Ajustes podem ser necessários conforme o desenvolvimento avança.