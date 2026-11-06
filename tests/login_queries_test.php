<?php
// Apenas MySQL descartável de CI. Não inclui a conexão/configuração da aplicação.
if (getenv('LOGIN_TEST_DB') !== 'controle_ativos_test') {
    throw new RuntimeException('Use exclusivamente LOGIN_TEST_DB=controle_ativos_test.');
}
foreach (array('LOGIN_TEST_HOST', 'LOGIN_TEST_USER', 'LOGIN_TEST_PASSWORD', 'LOGIN_BASELINE_PATH') as $variable) {
    if (getenv($variable) === false || getenv($variable) === '') {
        throw new RuntimeException('Variável de teste ausente: ' . $variable);
    }
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conexao = mysqli_connect(
    getenv('LOGIN_TEST_HOST'),
    getenv('LOGIN_TEST_USER'),
    getenv('LOGIN_TEST_PASSWORD'),
    getenv('LOGIN_TEST_DB'),
    3306
);
mysqli_set_charset($conexao, 'utf8mb4');
$GLOBALS['loginTestConnection'] = $conexao;
ini_set('session.use_cookies', '0');
session_cache_limiter('');
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

function check($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function makeHomeFixture($homeSource, $helperSource)
{
    $directory = sys_get_temp_dir() . '/controle-login-' . bin2hex(random_bytes(8));
    mkdir($directory . '/control', 0700, true);
    // Home.php é copiado sem alterar nenhum byte. Só dependências externas são fixtures.
    file_put_contents($directory . '/Home.php', $homeSource);
    file_put_contents($directory . '/control/conecta_mysql.inc',
        '<?php $conexao = $GLOBALS["loginTestConnection"];');
    if ($helperSource !== null) {
        file_put_contents($directory . '/control/login_queries.php', $helperSource);
    }
    foreach (array('formAdmin.php', 'formRead.php', 'formWriteRead.php') as $roleFile) {
        file_put_contents($directory . '/' . $roleFile, '[ROLE:' . $roleFile . ']');
    }
    return $directory;
}

function renderHome($directory, $post)
{
    $previousDirectory = getcwd();
    $_POST = $post;
    chdir($directory);
    ob_start();
    try {
        include $directory . '/Home.php';
        return ob_get_contents();
    } finally {
        ob_end_clean();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
            session_id('');
        }
        chdir($previousDirectory);
    }
}

function removeHomeFixture($directory)
{
    foreach (array('Home.php', 'formAdmin.php', 'formRead.php', 'formWriteRead.php',
        'control/conecta_mysql.inc', 'control/login_queries.php') as $path) {
        if (is_file($directory . '/' . $path)) {
            unlink($directory . '/' . $path);
        }
    }
    rmdir($directory . '/control');
    rmdir($directory);
}

function auditCount($conexao)
{
    $result = mysqli_query($conexao, 'SELECT COUNT(*) FROM acesso');
    $count = mysqli_fetch_row($result);
    mysqli_free_result($result);
    return (int) $count[0];
}

function originalLookup($conexao, $original, $user)
{
    $start = strpos($original, '$res = mysqli_query(');
    $end = strpos($original, 'if($linha != 0)', $start);
    check($start !== false && $end !== false, 'Bloco original de consulta não encontrado.');
    // Executa o bloco exato da fonte original; não reimplementa a consulta vulnerável.
    eval(substr($original, $start, $end - $start));
    mysqli_free_result($res);
    return $row;
}

$original = file_get_contents(getenv('LOGIN_BASELINE_PATH'));
check(sha1('blob ' . strlen($original) . "\0" . $original)
    === '0b1d426826492dd9d3d38ef750db4dbfe1f4cd65', 'Blob original inesperado.');
$current = file_get_contents(dirname(__DIR__) . '/Home.php');
$helper = file_get_contents(dirname(__DIR__) . '/control/login_queries.php');
$baselineDirectory = makeHomeFixture($original, null);
$candidateDirectory = makeHomeFixture($current, $helper);

try {
    // Fixture mínima, baseada nos nomes usados pela fonte; não é migração/esquema de produção.
    // TEMPORARY garante que nenhuma tabela permanente seja criada, apagada ou modificada.
    mysqli_query($conexao, 'CREATE TEMPORARY TABLE LOGIN
        (cd_login VARCHAR(255), ds_senha VARCHAR(255), ds_isadm VARCHAR(64))
        CHARACTER SET utf8mb4 COLLATE utf8mb4_bin');
    mysqli_query($conexao, 'CREATE TEMPORARY TABLE DAODS
        (cd_login VARCHAR(255), ds_nome VARCHAR(255))
        CHARACTER SET utf8mb4 COLLATE utf8mb4_bin');
    mysqli_query($conexao, 'CREATE TEMPORARY TABLE acesso
        (cd_login_acesso VARCHAR(255), dt_acesso VARCHAR(32))
        CHARACTER SET utf8mb4 COLLATE utf8mb4_bin');

    $accounts = array(
        array('admin', 'senha-admin', 'Administrador', 'formAdmin.php', 'Admin Teste'),
        array('leitor', 'senha-leitor', 'Apenas leitura', 'formRead.php', 'Leitor Teste'),
        array('editor', 'senha-editor', 'Leitura/Escrita', 'formWriteRead.php', 'Editor Teste'),
        array("O'Neil", "senha'com-aspas", 'Apenas leitura', 'formRead.php', 'Nome Teste'),
        array('usuário-ação', 'senha-acentuação', 'Leitura/Escrita', 'formWriteRead.php', 'Pessoa Teste'),
        array('', '', 'Apenas leitura', 'formRead.php', 'Conta Legada')
    );
    $insertLogin = mysqli_prepare($conexao, 'INSERT INTO LOGIN VALUES (?, ?, ?)');
    $insertProfile = mysqli_prepare($conexao, 'INSERT INTO DAODS VALUES (?, ?)');
    foreach ($accounts as $account) {
        list($name, $password, $role, $roleFile, $displayName) = $account;
        $encoded = base64_encode($password);
        mysqli_stmt_bind_param($insertLogin, 'sss', $name, $encoded, $role);
        mysqli_stmt_execute($insertLogin);
        mysqli_stmt_bind_param($insertProfile, 'ss', $name, $displayName);
        mysqli_stmt_execute($insertProfile);
    }
    mysqli_stmt_close($insertLogin);
    mysqli_stmt_close($insertProfile);

    $control = renderHome($baselineDirectory, array('nome' => 'admin', 'senha' => 'senha-admin'));
    check(strpos($control, '[ROLE:formAdmin.php]') !== false, 'Controle original legítimo falhou.');
    mysqli_query($conexao, 'DELETE FROM acesso');

    $quotedFailure = false;
    try {
        renderHome($baselineDirectory, array('nome' => "O'Neil", 'senha' => "senha'com-aspas"));
    } catch (mysqli_sql_exception $error) {
        $quotedFailure = $error->getCode() === 1064;
    }
    check($quotedFailure, 'A fonte original não reproduziu o erro SQL com aspas.');

    $injectedPassword = base64_encode('attacker-pass');
    $union = "inexistente-union' UNION ALL SELECT 'attacker', '" . $injectedPassword . "', 'Administrador' -- ";
    $injectedRow = originalLookup($conexao, $original, $union);
    check($injectedRow['ds_senha'] === $injectedPassword
        && $injectedRow['ds_isadm'] === 'Administrador',
        'A consulta original não reproduziu a alteração SQL na fixture.');
    // Prova somente da consulta de autenticação na fixture de 3 colunas, não de acesso completo.

    foreach ($accounts as $account) {
        list($name, $password, $role, $roleFile, $displayName) = $account;
        $before = auditCount($conexao);
        $output = renderHome($candidateDirectory, array('nome' => $name, 'senha' => $password));
        check(strpos($output, '[ROLE:' . $roleFile . ']') !== false, 'Roteamento de perfil mudou.');
        check(strpos($output, 'Perfil: <b>' . $role . '</b>') !== false, 'Perfil exibido mudou.');
        check(strpos($output, 'Bem vindo, ' . $displayName . ' !') !== false, 'Nome exibido mudou.');
        check(auditCount($conexao) === $before + 1, 'Acesso legítimo não foi registrado.');
        $audit = mysqli_prepare($conexao, 'SELECT cd_login_acesso, dt_acesso FROM acesso WHERE cd_login_acesso = ?');
        mysqli_stmt_bind_param($audit, 's', $name);
        mysqli_stmt_execute($audit);
        mysqli_stmt_store_result($audit);
        mysqli_stmt_bind_result($audit, $recordedName, $recordedDate);
        check(mysqli_stmt_fetch($audit) === true, 'Registro de acesso ausente.');
        $last = array('cd_login_acesso' => $recordedName, 'dt_acesso' => $recordedDate);
        mysqli_stmt_close($audit);
        check($last['cd_login_acesso'] === $name, 'Nome foi alterado no registro de acesso.');
        check(preg_match('/^\\d{2}\\.\\d{2}\\.\\d{4}, \\d{2}:\\d{2}:\\d{2}$/D',
            $last['dt_acesso']) === 1, 'Formato do horário de acesso mudou.');
    }

    $rejected = array(
        array('nome' => 'admin', 'senha' => 'incorreta'),
        array('nome' => 'inexistente', 'senha' => 'senha-admin'),
        array('nome' => "' OR 1=1 -- ", 'senha' => 'senha-admin'),
        array('nome' => $union, 'senha' => 'attacker-pass'),
        array('nome' => "x\\' OR 1=1 -- ", 'senha' => 'senha-admin'),
        array('nome' => "x\0' OR 1=1 -- ", 'senha' => 'senha-admin'),
        array('nome' => array('admin'), 'senha' => 'senha-admin'),
        array('nome' => 'admin', 'senha' => array('senha-admin')),
        array('nome' => 'admin'),
        array('senha' => 'senha-admin'),
        array()
    );
    foreach ($rejected as $post) {
        $before = auditCount($conexao);
        $output = renderHome($candidateDirectory, $post);
        check(strpos($output, '[ROLE:') === false, 'Entrada inválida alcançou módulo de perfil.');
        check(strpos($output, 'USER INVALIDO') !== false || strpos($output, 'ACESSO NEGADO') !== false,
            'Entrada inválida não recebeu a resposta existente de recusa.');
        check(auditCount($conexao) === $before, 'Entrada recusada criou registro de acesso.');
    }
    echo "PASS: fonte original falha com aspas e permite alteração da consulta na fixture.\n";
    echo "PASS: Home.php real corrigido aceita 6 controles e rejeita 11 entradas inválidas.\n";
    echo "PASS: 3 perfis, Base64, nome, registro literal e formato de data preservados.\n";
} finally {
    removeHomeFixture($baselineDirectory);
    removeHomeFixture($candidateDirectory);
    mysqli_close($conexao);
}
