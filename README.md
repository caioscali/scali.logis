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

Até o momento, esses diretórios são apenas fronteiras arquiteturais. Não foram criadas tabelas multiempresa ou tabelas operacionais.

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
na rota; a seleção de contexto operacional e o seletor global ficam para a
Sprint 02.3. Convites, permissões granulares e cadastro global de pessoas ainda
não fazem parte desta etapa.
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
