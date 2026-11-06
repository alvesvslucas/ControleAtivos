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

## Estado do projeto

A execução e a instalação do banco não foram validadas nesta revisão documental. O login legado em `Home.php` contém SQL interpolado e comparação de senha em Base64; esses pontos precisam ser corrigidos antes de disponibilizar uma demonstração com dados ou acesso externo.

## Créditos

O rodapé de `Home.php` registra **Renan Cunha / 2016**. Esta cópia mantém esse crédito; a organização do repositório não implica autoria exclusiva do código original.
