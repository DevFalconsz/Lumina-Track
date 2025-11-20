# 📦 Guia de Deploy – Ambiente Docker com XAMPP Embutido

Este guia detalha o processo de deploy do projeto Lumina Track utilizando um ambiente Docker que contém o XAMPP completo (Apache + PHP + MariaDB) com todos os arquivos do projeto já embutidos na imagem. O objetivo é proporcionar um ambiente de execução rápido e consistente, ideal para demonstrações e desenvolvimento local.

## 🚀 1. Requisitos

Antes de iniciar, certifique-se de que possui os seguintes softwares instalados em sua máquina:

-   **Docker**
-   **Docker Compose** (opcional, mas recomendado para orquestração de múltiplos serviços)

Você precisará do arquivo `.zip` disponibilizado nas Releases do projeto, que contém:

-   `Dockerfile`
-   `lampp/` (pasta com a instalação completa do XAMPP)
-   Demais arquivos do projeto Lumina Track

## 📥 2. Como Baixar o Projeto

Baixe o arquivo `.zip` diretamente na aba `Releases` do repositório (ex: `xampp-docker-environment.zip`).

Após o download, extraia o conteúdo do arquivo:

```bash
unzip xampp-docker-environment.zip
cd xampp-docker-environment
```

Você verá uma estrutura de diretórios semelhante a esta:

```
.
├── Dockerfile
├── lampp/
│   ├── bin/
│   ├── etc/
│   ├── htdocs/  # Aqui estarão os arquivos do seu projeto Lumina Track
│   ├── phpmyadmin/
│   └── var/
└── ...
```

## 🛠️ 3. Construindo a Imagem Docker

Com o Docker instalado e no diretório `xampp-docker-environment`, execute o seguinte comando para construir a imagem Docker:

```bash
docker build -t meu-xampp:latest .
```

Este comando:

-   Cria uma imagem Docker baseada em Ubuntu com o XAMPP incorporado.
-   Copia toda a instalação do XAMPP e os arquivos do projeto para `/opt/lampp` dentro da imagem.
-   Configura as permissões necessárias para o XAMPP e o projeto.
-   Prepara o ambiente para execução automática dos serviços (Apache, PHP, MariaDB).

## ▶️ 4. Subindo o Container

Após a construção da imagem, você pode iniciar o container com o seguinte comando:

```bash
docker run -d \
  --name meu-xampp-container \
  -p 80:80 \
  -p 443:443 \
  -p 3306:3306 \
  meu-xampp:latest
```

Este comando:

-   Inicia o Apache, PHP e MariaDB (todos parte do XAMPP) em segundo plano (`-d`).
-   Mantém os logs dos serviços ativos para depuração.
-   Expõe as portas essenciais do container para o seu `localhost`:
    -   `80:80` → Servidor Web (HTTP)
    -   `443:443` → HTTPS (se o SSL estiver configurado no XAMPP)
    -   `3306:3306` → Banco de Dados MariaDB

## 🌐 5. Acessando o Sistema

Após o container estar em execução, você pode acessar o sistema e o phpMyAdmin através do seu navegador:

-   **Acesse seu projeto Lumina Track**: `http://localhost`
-   **Acesse o phpMyAdmin**: `http://localhost/phpmyadmin`

**Credenciais padrão do XAMPP (caso não tenham sido alteradas na imagem):**

-   **Usuário**: `root`
-   **Senha**: (vazia)

## 🧰 6. Logs e Depuração

Para monitorar o ambiente ou acessar o shell do container:

-   **Ver logs do container**: `docker logs -f meu-xampp-container`
-   **Entrar dentro do container**: `docker exec -it meu-xampp-container bash`

## 🔄 7. Reiniciar o Ambiente

Para reiniciar o container e todos os serviços do XAMPP:

```bash
docker restart meu-xampp-container
```

## 🗑️ 8. Parar e Remover

Para gerenciar o ciclo de vida do container e da imagem:

-   **Parar o container**: `docker stop meu-xampp-container`
-   **Remover o container**: `docker rm meu-xampp-container`
-   **Remover a imagem Docker**: `docker rmi meu-xampp:latest`

## 🏁 9. Estrutura do XAMPP Dentro do Container

Todo o ambiente XAMPP e os arquivos do projeto rodam dentro do diretório `/opt/lampp` no container, incluindo:

-   `/opt/lampp/htdocs`: Contém os arquivos do projeto Lumina Track.
-   `/opt/lampp/etc`: Arquivos de configuração do Apache, PHP e MySQL.
-   `/opt/lampp/var/mysql`: Onde os dados do banco de dados MariaDB do XAMPP são armazenados.
-   `/opt/lampp/phpmyadmin`: A instalação do phpMyAdmin para gerenciamento do banco de dados.

## 📦 10. Observações Importantes

-   Este ambiente é **imutável**: o XAMPP e os arquivos do projeto já estão empacotados dentro da imagem Docker.
-   É ideal para deploys rápidos, demonstrações e ambientes que não exigem persistência externa de dados (ou onde a persistência é gerenciada de outra forma).
