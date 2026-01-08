Este é o documento mestre. Ele contém a **Especificação Técnica Unificada** (ETU). Você pode copiar este conteúdo e entregar diretamente ao **Google Antigravity** (ou qualquer agente de IA), pois ele traduz a lógica dos seus arquivos antigos (`sync_anuncios.php`, `dashboard.php`, etc.) para a nova arquitetura que definimos.

---

# 📄 Documento Mestre de Engenharia: Projeto "Novo 360 Analisador"

## 1. Visão Geral e Objetivo

O objetivo é criar uma **Aplicação Web (SaaS)** para gestão de anúncios do Mercado Livre, fundindo funcionalidades de dois sistemas legados ("360" e "Analisador").
O sistema deve identificar oportunidades logísticas (Full/Flex) e saúde dos anúncios (Anúncios parados/sem venda), rodando em infraestrutura de **Hospedagem Compartilhada (Hostinger)** com banco de dados externo **(Supabase/PostgreSQL)**.

## 2. Arquitetura da Solução (O "Anti-Gravity Stack")

Para contornar as limitações de processamento e memória da hospedagem compartilhada, utilizaremos uma arquitetura híbrida:

* **Frontend (Cliente):** Single Page Application (SPA) em **React + TypeScript (Vite)**.
* *Obs:* Compilado localmente e enviado como estático (`html/js/css`) para a pasta `public_html`.


* **Backend (Servidor):** API RESTful em **PHP 8.2+**.
* Responsável pela autenticação OAuth e execução dos Cron Jobs.
* Não utiliza frameworks pesados (Laravel), apenas PHP puro ou um micro-router para performance máxima na Hostinger.


* **Banco de Dados:** **Supabase (PostgreSQL)**.
* Toda a carga de *queries* complexas e armazenamento fica fora da hospedagem compartilhada.



---

## 3. Engenharia de Dados (Schema do Banco)

O banco de dados deve unificar os campos do *360* (Foco Logístico) com o *Analisador* (Foco em Vendas).

**Tabela: `items` (Tabela Mestra de Anúncios)**
Deve conter as seguintes colunas obrigatórias:

| Coluna | Tipo | Origem | Descrição |
| --- | --- | --- | --- |
| `id` | UUID | Sistema | Chave primária (Supabase). |
| `ml_id` | VARCHAR | ML API | ID do anúncio (MLB...). |
| `account_id` | UUID | Sistema | Vínculo com a conta do vendedor. |
| `title` | TEXT | ML API | Título do anúncio. |
| `price` | NUMERIC | ML API | Preço atual. |
| `status` | VARCHAR | ML API | `active`, `paused`, `closed`. |
| `permalink` | TEXT | ML API | Link do anúncio. |
| `thumbnail` | TEXT | ML API | Foto principal. |
| **Campos do Analisador** |  |  | *Lógica de performance de vendas* |
| `date_created` | TIMESTAMP |  | Data de criação do anúncio. |
| `last_sale_date` | TIMESTAMP |  | **Crítico:** Data da última venda (extraída via API de Orders). |
| `sold_quantity` | INT |  | Total vendido na vida do anúncio. |
| `days_without_sale` | INT | Calculado | `NOW() - last_sale_date`. |
| **Campos do 360** |  |  | *Lógica logística e qualitativa* |
| `shipping_mode` | VARCHAR |  | `me2`, `not_specified`, `custom`. |
| `logistic_type` | VARCHAR |  | `cross_docking`, `fulfillment`, `self_service`, `drop_off`. |
| `free_shipping` | BOOLEAN |  | Se oferece frete grátis. |
| `tags` | JSONB |  | Array de tags (ex: `dragontail`, `good_quality_picture`). |

---

## 4. O "Motor de Sincronização" (Lógica Backend PHP)

Esta é a parte mais complexa. O agente deve replicar a lógica do arquivo legado `sync_anuncios.php`, adaptando para PDO PostgreSQL.

### Algoritmo de Sincronização ("Self-Healing Cron")

Como a hospedagem mata processos longos, o script deve rodar em ciclos curtos (ex: a cada minuto), processando pequenos lotes.

**Fluxo do Script (`sync.php`):**

1. **Verificação de Lock:** Checa se já existe um processo rodando para evitar duplicidade.
2. **Fase 1: Coleta de IDs (Scan)**
* Usa o endpoint `/users/{id}/items/search?search_type=scan`.
* Pagina usando `scroll_id` até buscar todos os IDs da conta.
* Insere apenas os `ml_id` no banco com status "pendente".


3. **Fase 2: Enriquecimento (A Fusão)**
* Seleciona 50 itens do banco que estão pendentes ou desatualizados.
* **Chamada 1:** `GET /items?ids=...` (Multiget) para pegar Título, Preço, Logística (Dados do 360) e `sold_quantity`.
* **Chamada 2 (Condicional - Lógica do Analisador):**
* Para cada item onde `sold_quantity > 0`:
* Fazer chamada em `GET /orders/search?item={id}&limit=1&sort=date_desc`.
* Extrair `date_closed` da venda mais recente.
* *Motivo:* A API de itens não fornece a data da última venda, necessária para calcular se o anúncio está "encalhado".


* **Upsert:** Salva todos os dados combinados na tabela `items` do Supabase.



---

## 5. Especificações do Frontend (React/TypeScript)

O painel deve ser visualmente limpo, substituindo o antigo `dashboard.php`.

**Tecnologias:**

* Vite + React + TypeScript.
* Framework UI: TailwindCSS + shadcn/ui.
* Gerenciamento de Estado Server-Side: TanStack Query (React Query).

**Requisitos da Tela "Inventário":**

1. **Data Table:** Tabela com paginação server-side (Supabase).
2. **Indicadores Visuais (Tags):**
* Ícone de Raio Amarelo para `logistic_type = 'fulfillment'` (Full).
* Ícone de Caminhão para `shipping_mode = 'me2'` (Mercado Envios).


3. **Lógica de Alerta (Herdada do Analisador):**
* Se `days_without_sale > 60` E `available_quantity > 0`: Pintar a linha de **Vermelho Claro** (Alerta de Estoque Parado).
* Se `days_without_sale > 30`: Pintar de **Amarelo Claro**.


4. **Filtros Avançados:**
* "Mostrar apenas Full".
* "Mostrar parados há +60 dias".
* "Mostrar sem vendas".



---

## 6. Instruções para o Agente de Desenvolvimento

**Passo a Passo de Execução:**

1. **Setup:** Inicialize o projeto Vite com o template React-TS. Crie a estrutura de pastas `/src/services`, `/src/pages`, `/src/components`.
2. **Database:** Gere o script SQL para criar as tabelas no Supabase conforme a seção 3 deste documento.
3. **Backend Legacy-Bridge:**
* Crie o script `db.php` usando `PDO` com driver `pgsql` para conectar ao Supabase.
* Reescreva a lógica de `sync_anuncios.php` para extrair os dados de logística (360) e datas (Analisador) em um único loop eficiente.


4. **Frontend Integration:**
* Conecte o React diretamente ao Supabase para **LEITURA** (Selects).
* Conecte o React ao PHP apenas para **ESCRITA/AÇÕES** (Gatilho de Sync, Login OAuth).


5. **Build:** Configure o `vite.config.ts` para gerar a build de produção pronta para a pasta `public_html`.