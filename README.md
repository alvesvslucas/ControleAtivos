# Inventário de Ativos de TI

Sistema em PHP para controle de ativos de TI, com acesso por perfis de administrador, leitura e leitura/escrita.

## Estrutura

- `index.php`: encaminha para `login.html`.
- `login.html` e `Home.php`: formulário e processamento do acesso.
- `formAdmin.php`, `formRead.php` e `formWriteRead.php`: interfaces por perfil.
- `control/`, `model/` e `bootstrap/`: apoio ao sistema e estilos.

## Ambiente local

O código utiliza PHP com a extensão `mysqli` e banco MySQL. A versão compatível do PHP e o processo de criação do banco ainda precisam ser confirmados no ambiente de destino.

1. Clone esta cópia:

```sh
git clone https://github.com/alvesvslucas/ControleAtivos.git
cd ControleAtivos
```

2. Prepare um banco de teste com o esquema exigido pela aplicação e configure a conexão local usada por `control/conecta_mysql.inc`.
3. Com PHP disponível, sirva a raiz apenas para desenvolvimento:

```sh
php -S 127.0.0.1:8080 -t .
```

4. Abra `http://127.0.0.1:8080/login.html`. O login depende dos usuários existentes no banco; este documento não fornece credenciais.

## Verificações e limites

As três consultas do login (usuário, registro de acesso e perfil) usam parâmetros SQL. O workflow [Regressão do login](.github/workflows/login-regression.yml) exercita o fluxo de `Home.php` em PHP 8.2 e MySQL 8 descartável, com tabelas temporárias e dependências de teste. A regressão cobre seis contas legítimas, onze entradas recusadas e os três perfis, incluindo aspas, Unicode, senha incorreta, entradas malformadas e tentativas de injeção SQL.

A [regressão na branch principal](https://github.com/alvesvslucas/ControleAtivos/actions/runs/42284679704) passou com esses controles; a [verificação de sintaxe](https://github.com/alvesvslucas/ControleAtivos/actions/runs/42284679715) passou em 22 arquivos PHP reais.

O workflow [Sintaxe PHP](.github/workflows/php-syntax.yml) verifica os arquivos PHP sem executar as páginas ou conectar ao banco da aplicação.

As senhas continuam no formato legado Base64; a migração para hashing seguro permanece pendente e precisa ser concluída antes de disponibilizar uma demonstração com dados reais ou acesso externo. A instalação do banco, os demais módulos e o ambiente de produção ainda exigem validação própria. Esta revisão não constitui auditoria completa e não implantou o sistema no servidor de produção.

## Créditos

O rodapé de `Home.php` registra **Renan Cunha / 2016**. Esta cópia mantém esse crédito; a organização do repositório não implica autoria exclusiva do código original.
