# Scali Logis

Fundação tecnológica do Scali Logis, uma plataforma SaaS multiempresa para gestão de transportadoras, desenvolvida pela Scali.dev.

Esta etapa entrega a base de autenticação, o shell da aplicação e a infraestrutura frontend. Os módulos empresariais e operacionais serão implementados em sprints posteriores.

## Stack

- Laravel 13.42
- PHP 8.5
- PostgreSQL 18 para desenvolvimento
- React 19 com TypeScript
- Inertia.js
- Vite 8
- Tailwind CSS 4
- Componentes reutilizáveis no padrão shadcn/ui
- Laravel Breeze para autenticação
- PHPUnit 12

## Requisitos

- PHP 8.5 com as extensões pdo_pgsql, pdo_sqlite e sqlite3
- Composer
- Node.js 22 ou compatível com Vite 8
- npm
- PostgreSQL 18

O PostgreSQL é o banco principal da aplicação. Os testes automatizados usam SQLite em memória e não devem apontar para o banco de desenvolvimento.

## Instalação

Clone o repositório e instale as dependências:

\`\`\`bash
git clone https://github.com/caioscali/scali.logis.git
cd scali-logis
composer install
npm install
\`\`\`

Crie o arquivo de ambiente:

\`\`\`bash
cp .env.example .env
php artisan key:generate
\`\`\`

Configure no .env as credenciais do PostgreSQL:

\`\`\`dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=scali_logis
DB_USERNAME=scali_logis
DB_PASSWORD=senha-local
\`\`\`

Execute as migrations:

\`\`\`bash
php artisan migrate
\`\`\`

Nunca versione .env ou credenciais reais.

## Execução local

Inicie o servidor Laravel:

\`\`\`bash
php artisan serve
\`\`\`

Em outro terminal, inicie o Vite em modo de desenvolvimento:

\`\`\`bash
npm run dev
\`\`\`

Abra http://localhost:8000.

O acesso ao dashboard exige autenticação e verificação de e-mail. O ambiente local usa o mailer configurado no .env, normalmente log.

## Testes e qualidade

\`\`\`bash
php artisan test
npx tsc --noEmit
npm run build
vendor/bin/pint --test
php artisan route:list
php artisan migrate:status
\`\`\`

Os testes usam:

\`\`\`xml
DB_CONNECTION=sqlite
DB_DATABASE=:memory:
\`\`\`

Essa configuração está em phpunit.xml e substitui a configuração PostgreSQL somente durante os testes.

## Estrutura inicial

Os domínios foram reservados em app/Domain:

- Platform
- Organizations
- Identity
- Registry
- Transport
- LoadingOrders
- Freight
- Finance
- Documents
- Integrations

O módulo LoadingOrders implementa o cadastro e a consulta de Ordens de Carregamento em `ordens_carregamento`. A ordem é numerada por transportadora com lock transacional, pertence à filial do contexto ativo e possui as situações `rascunho`, `emitida` e `cancelada`. Rascunhos podem ser editados; ordens emitidas ou canceladas permanecem no histórico e não podem ser excluídas fisicamente.

Clientes são pessoas globais com vínculo ativo e função `cliente`. Conjuntos precisam estar ativos e possuir motorista, transportador, veículo e conta de pagamento elegíveis na transportadora. A ordem mantém a referência ao conjunto e um snapshot dos nomes, placas e identificação mascarada da conta no momento da contratação; a identificação bancária é armazenada com cast criptografado e nunca é exibida integralmente.

O serviço de domínio separa `dadosExportaveis()['operacionais']` de `['financeiros']`; a geração documental usa exclusivamente o primeiro conjunto. Esta etapa não registra CFC e não implementa financeiro mensal, descontos ou pagamentos.

### PDF operacional da Ordem de Carregamento

A geração do PDF usa uma cópia temporária de uma planilha-modelo no Google Drive. O Scali Logis preenche essa cópia pela Google Sheets API, exporta o resultado como PDF pela Google Drive API, valida a assinatura `%PDF-`, salva o arquivo no disco privado `local` e remove a cópia temporária. O banco continua sendo a fonte oficial; a planilha não é usada como banco de dados.

Configure, apenas no ambiente de execução, as variáveis abaixo:

```dotenv
GOOGLE_APPLICATION_CREDENTIALS=/caminho/fora/publico/google-service-account.json
GOOGLE_SHEETS_TEMPLATE_ID=ID_DA_PLANILHA_MODELO
GOOGLE_DRIVE_FOLDER_ID=ID_DA_PASTA_TEMPORARIA
GOOGLE_API_TIMEOUT=15
```

Ative Google Sheets API e Google Drive API no Google Cloud. Compartilhe a planilha-modelo e, se usado, a pasta de trabalho com o e-mail da conta de serviço com permissão mínima de Editor. O JSON nunca deve ser versionado, exposto pelo servidor web ou registrado em logs. O modelo precisa ser operacional e não conter valores financeiros em células, abas, fórmulas, comentários ou metadados.

O mapeamento padrão de campos para células fica em `config/google.php` e pode ser ajustado quando o modelo oficial estiver disponível. A lista é consumida por `MapeamentoOrdem`; somente o subconjunto operacional do snapshot histórico é enviado à API. Frete, adiantamento, saldo, contratos, descontos, dados bancários e PIX não são enviados nem ocultados no PDF.

PDFs ficam em `ordens-carregamento/{transportadora}/{filial}/{numero}` no disco privado, com referência em `pdf_caminho`, `pdf_gerado_em` e `pdf_gerado_por`. A geração é autorizada apenas para administradores, gerentes e operadores no contexto ativo de uma ordem emitida. Usuários de consulta podem baixar documentos existentes autorizados. O download resolve o arquivo pelo ID da ordem, nunca por um caminho recebido do navegador; ordens canceladas preservam seu PDF histórico.

Sem credenciais ou ID de modelo, a integração permanece indisponível e a interface informa `Google Planilhas ainda não configurado para geração do PDF.`. Falhas de API, timeout, PDF inválido e armazenamento não alteram a ordem e permitem nova tentativa. A remoção de uma cópia temporária com falha é registrada sem expor credenciais.

## Autenticação

Os fluxos disponíveis são:

- Login e logout
- Registro
- Recuperação e redefinição de senha
- Verificação de e-mail
- Proteção do dashboard por auth e verified

## Administração empresarial

O cadastro inicial de grupos empresariais, transportadoras e filiais fica
protegido por \`auth\`, \`verified\` e pelo middleware administrativo provisório
\`platform.admin\`. Durante esta sprint, somente os e-mails listados em
\`PLATFORM_ADMIN_EMAILS\` podem acessar essas rotas. Essa lista será substituída
pela autorização multiempresa definitiva na Sprint 02.2.

A criação de uma transportadora é transacional e cria sua filial matriz no
mesmo fluxo. CNPJs são normalizados para somente números e validados antes de
serem persistidos. A matriz possui índice único por transportadora e registros
com dependências não podem ser excluídos.

### Usuários, transportadoras e filiais

O cadastro de usuários é único na tabela \`users\`. As tabelas
\`usuarios_transportadoras\` e \`usuarios_filiais\` representam, respectivamente,
o perfil do usuário em cada transportadora e as filiais operacionais
autorizadas nesse vínculo. Um mesmo usuário pode ter perfis diferentes em
transportadoras distintas, sem duplicar sua conta.

Os perfis atuais são \`administrador\`, \`gerente\`, \`operador\` e \`consulta\`.
Somente administradores ativos da transportadora ou administradores da
plataforma podem gerenciar vínculos. A autorização é verificada no servidor,
vínculos inativos não concedem acesso e filiais inativas não são autorizáveis.
O administrador da plataforma pode administrar vínculos, mas não recebe acesso
operacional automático a uma transportadora.

O sistema valida que a filial pertence à transportadora do vínculo, inclusive
por uma trigger PostgreSQL. A administração usa uma transportadora explícita
na rota. Convites, permissões granulares e cadastro global de pessoas ainda
não fazem parte desta etapa.

### Contexto empresarial

Após o login, o usuário seleciona uma transportadora e uma filial entre os
vínculos ativos e as autorizações de filial disponíveis para sua conta. Quando
existe somente uma opção, o contexto é selecionado automaticamente. A escolha
fica na sessão autenticada, sem criar tabelas adicionais, e é validada pelo
servidor em cada requisição operacional.

O cabeçalho permite trocar o contexto durante a navegação. Vínculos
desativados, filiais inativas e autorizações revogadas invalidam a sessão de
contexto e exigem nova seleção. Administradores da plataforma continuam com
acesso global administrativo, mas não recebem acesso operacional automático.
O middleware de contexto não é aplicado às rotas de autenticação, seleção,
administração global ou administração de vínculos.

A Sprint 02.3 prepara o mecanismo de contexto para os próximos módulos, mas
ainda não cria tabelas operacionais nem implementa filtros de cargas, ordens ou
financeiro. Cada módulo futuro deverá combinar o contexto com suas próprias
Policies e regras de propriedade.

### Cadastro único de pessoas

O domínio Registry mantém uma pessoa uma única vez na tabela `pessoas`. Os
vínculos em `transportadoras_pessoas` e as funções em `pessoas_funcoes` são
locais à transportadora ativa. Uma pessoa pode ser adotada por várias
transportadoras e exercer mais de uma função sem duplicar o cadastro global.

CPF e CNPJ são normalizados e validados antes da gravação. A pesquisa global
aceita somente o documento exato, é autenticada, limitada por rate limiting e
retorna dados mínimos quando a pessoa ainda não pertence à transportadora
ativa. A adoção reutiliza os dados existentes sem sobrescrevê-los. Nesta etapa,
as alterações de dados compartilhados ficam bloqueadas após a adoção; a edição
disponível altera somente o vínculo local e suas funções.

Usuários com perfil administrador, gerente ou operador podem cadastrar, adotar
e alterar funções no contexto ativo. Usuários de consulta somente visualizam
os vínculos autorizados. Pessoas com vínculos não podem ser excluídas e não há
exportação global ou cadastros separados de motoristas e transportadores.

### Veículos e implementos

Veículos e implementos possuem cadastros globais mínimos nas tabelas `veiculos`
e `implementos`. As tabelas `transportadoras_veiculos` e
`transportadoras_implementos` registram a adoção e a situação local de cada
equipamento. Placas são normalizadas em maiúsculas, aceitam os formatos
brasileiro antigo e Mercosul e não podem ser duplicadas dentro de sua categoria
nem usadas simultaneamente nas duas categorias.

A pesquisa exata por placa exige autenticação, contexto empresarial e rate
limiting. A adoção reutiliza o cadastro global sem modificar cidade ou placa;
somente o vínculo local pode ser ativado ou desativado. A colisão entre
veículo e implemento é protegida por bloqueio transacional no PostgreSQL, sem
criar tabela auxiliar. Equipamentos com vínculos não podem ser excluídos.

### Contas bancárias e PIX

O módulo financeiro inicial mantém o cadastro global de contas em
`contas_bancarias` e a adoção local em `transportadoras_contas_bancarias`.
Uma conta pertence a uma pessoa global existente e só pode ser adotada quando
essa pessoa estiver vinculada e ativa na transportadora atual. A mesma conta
pode ser adotada por mais de uma transportadora sem duplicar seus dados.

Agência, número da conta, dígito e chave PIX usam os casts criptografados do
Laravel e não são incluídos no compartilhamento global do Inertia. Listagens e
pesquisas exibem apenas identificações mascaradas. A exibição integral e a
cópia individual ficam restritas ao administrador empresarial; a interface
avisa quando um valor é copiado para fora da proteção da aplicação.

Nesta etapa, dados bancários globais não são editados depois da adoção. A
transportadora pode somente ativar ou desativar seu vínculo. Para correções,
deve ser cadastrada ou adotada uma nova conta. Não há consulta externa de
titularidade, exportação global, histórico de pagamentos ou seleção para
conjuntos de transporte ainda.

### Conjuntos de transporte

Um conjunto em `conjuntos_transporte` reúne, no contexto de uma transportadora,
motorista, transportador, veículo, composição, carroceria e uma conta de
pagamento. Os implementos ficam em `conjuntos_implementos`, com posição e
restrições de unicidade por conjunto. Todos os componentes precisam possuir
adoção local ativa; motorista e transportador também precisam ter as funções
correspondentes no vínculo da transportadora.

As contas elegíveis pertencem obrigatoriamente ao motorista ou ao transportador
selecionado e precisam estar adotadas e ativas. Os dados completos da conta não
são expostos na montagem do conjunto. As composições iniciais são caminhão,
cavalo + semirreboque, carreta LS, bitrem e rodotrem, com quantidades simples
de implementos definidas pelo Enum. A posição não identifica o tipo físico do
implemento nem substitui homologação regulatória.

Configurações não são editadas destrutivamente. A duplicação cria um novo
conjunto e preserva o anterior para consultas históricas; somente a situação
ativa/inativa pode ser alterada. Ordens de carregamento, contratos e pagamentos
operacionais ainda não fazem parte desta etapa.
- Regeneração e invalidação de sessão
- Rate limiting para login, registro, recuperação e envio de verificação

O dashboard inicial usa React + Inertia, com menu lateral responsivo, alternância de tema claro/escuro e componentes UI reutilizáveis.

## Git

O branch principal é main e o remote oficial é:

\`\`\`text
https://github.com/caioscali/scali.logis.git
\`\`\`

O push deve ser feito manualmente após revisar o primeiro commit:

\`\`\`bash
git push -u origin main
\`\`\`
