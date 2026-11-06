<?php
// Consultas do login: os valores recebidos nunca compõem o texto SQL.
function controleAtivosFindLogin($conexao, $user)
{
    $stmt = mysqli_prepare($conexao, "select ds_senha, ds_isadm from LOGIN WHERE cd_login = ?");
    if ($stmt === false) {
        return false;
    }

    $row = false;
    if (mysqli_stmt_bind_param($stmt, "s", $user)
        && mysqli_stmt_execute($stmt)
        && mysqli_stmt_store_result($stmt)
        && mysqli_stmt_bind_result($stmt, $storedPassword, $role)
        && mysqli_stmt_fetch($stmt) === true) {
        $row = array('ds_senha' => $storedPassword, 'ds_isadm' => $role);
    }
    mysqli_stmt_close($stmt);
    return $row;
}

function controleAtivosRecordAccess($conexao, $user, $accessDate)
{
    $stmt = mysqli_prepare($conexao, "INSERT INTO acesso(cd_login_acesso,dt_acesso)VALUES(?,?)");
    if ($stmt === false) {
        return false;
    }

    $recorded = mysqli_stmt_bind_param($stmt, "ss", $user, $accessDate)
        && mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $recorded;
}

function controleAtivosFindProfile($conexao, $user)
{
    $stmt = mysqli_prepare($conexao, "select DAODS.ds_nome from LOGIN inner join DAODS on ? = DAODS.cd_login");
    if ($stmt === false) {
        return false;
    }

    $profile = false;
    if (mysqli_stmt_bind_param($stmt, "s", $user)
        && mysqli_stmt_execute($stmt)
        && mysqli_stmt_store_result($stmt)
        && mysqli_stmt_bind_result($stmt, $displayName)
        && mysqli_stmt_fetch($stmt) === true) {
        $profile = array('ds_nome' => $displayName);
    }
    mysqli_stmt_close($stmt);
    return $profile;
}
