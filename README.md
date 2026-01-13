# 🐭 RatControl - Sistema de Gestão para Agências e Consultores

> Uma plataforma completa, estilo SaaS, para gestão de tempo, projetos, financeiro e relacionamento com clientes. Desenvolvido em PHP Nativo e MySQL.

![License](https://img.shields.io/badge/license-MIT-blue.svg)
![PHP](https://img.shields.io/badge/php-8.0%2B-777BB4)
![MySQL](https://img.shields.io/badge/mysql-8.0-4479A1)
![Bootstrap](https://img.shields.io/badge/bootstrap-5.3-7952B3)

## 📋 Sobre o Projeto

O **RatControl** foi desenvolvido para resolver a dor de freelancers e pequenas agências: controlar exatamente quanto tempo é gasto em cada cliente e se esse tempo está sendo lucrativo.

Diferente de sistemas complexos, ele foca na agilidade: um Timer global sempre acessível, geração rápida de propostas e um portal onde o cliente acompanha o progresso em tempo real.

### ✨ Funcionalidades Principais

* **⏱️ Time Tracking Inteligente:** Cronômetro global (anti-drift) com suporte a pausas, retomadas e inserção manual.
* **📋 Gestão de Projetos (Kanban):** Quadro visual (To Do, Doing, Done) com checklists e arrastar-e-soltar.
* **💰 Financeiro & Relatórios:** Análise de lucro por projeto (Custo Hora vs. Valor Cobrado), despesas e receitas multi-moeda.
* **📄 Propostas & Contratos:** Gerador de orçamentos em PDF com cálculo automático de horas/valor.
* **🤝 Portal do Cliente:** Área externa segura (via Token) para o cliente aprovar tarefas, visualizar cronogramas e trocar mensagens/arquivos.
* **🔐 Controle de Acesso:** Níveis de permissão (Admin/User) e sistema de login seguro.

---

## 🚀 Tecnologias Utilizadas

* **Backend:** PHP 8+ (PDO, MVC Pattern simplificado).
* **Database:** MySQL / MariaDB.
* **Frontend:** Bootstrap 5, FontAwesome 6, Chart.js.
* **Libs JS:** Select2 (para selects pesquisáveis), SortableJS (para o Kanban).
* **PDF:** DomPDF (para geração de invoices e propostas).

---

## 📦 Instalação e Configuração

Siga os passos abaixo para rodar o projeto localmente:

### 1. Clone o repositório
```bash
git clone [https://github.com/SEU-USUARIO/ratcontrol.git](https://github.com/SEU-USUARIO/ratcontrol.git)
cd ratcontrol
2. Configure o Banco de DadosCrie um banco de dados MySQL (ex: ratcontrol_db).Em seguida, renomeie o arquivo de configuração e edite com suas credenciais:Renomeie config/db.php.example para config/db.php (ou crie um novo).Edite o arquivo:PHP<?php
$host = 'localhost';
$db   = 'ratcontrol_db';
$user = 'root';
$pass = ''; // Sua senha

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage());
}
?>
3. Instalação Automática (Migration)O sistema possui um script de auto-reparo e instalação. Basta acessar via navegador:http://localhost/ratcontrol/reparar_banco.php
Isso criará todas as tabelas necessárias e um usuário administrador padrão:Login: admin@admin.comSenha: 123456Nota: Apague ou proteja o arquivo reparar_banco.php após a instalação em produção.🖼️ ScreenshotsDashboardTimerKanbanPortal do Cliente🛠️ Estrutura de Pastasratcontrol/
├── api.php              # Central de requisições AJAX (Timer, Kanban, Chat)
├── config/              # Conexão com banco de dados
├── includes/            # Header, Footer, Auth, Mailer
├── assets/              # CSS, JS, Uploads, Imagens
├── login.php            # Autenticação
├── timer.php            # Lógica principal de contagem de tempo
├── kanban.php           # Quadro de tarefas
├── portal.php           # Área externa do cliente
└── ...
🤝 ContribuiçãoContribuições são bem-vindas! Se você tiver uma ideia de melhoria:Faça um Fork do projeto.Crie uma Branch para sua Feature (git checkout -b feature/IncrivelFeature).Faça o Commit (git commit -m 'Add some IncrivelFeature').Faça o Push (git push origin feature/IncrivelFeature).Abra um Pull Request.📄 LicençaEste projeto está sob a licença MIT. Veja o arquivo LICENSE para mais detalhes.Feito com 💙 por Pedro Lopes