<?php
require __DIR__ . '/inc/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    encerrar_sessao();
    voltar(url('login.php'));
}
voltar(url());
