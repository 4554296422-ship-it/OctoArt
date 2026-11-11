<?php
require_once __DIR__ . '/includes/auth.php';
$user = require_login();
if (is_admin($user)) {
    header('Location: dashboard.php?tab=perfil');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php?tab=perfil');
    exit;
}
// A confirmação é feita pelo diálogo do formulário e pela senha da conta.
$senha = (string)($_POST['senha_confirmacao'] ?? '');
if ($senha === '' || !password_verify($senha, (string)$user['senha'])) {
    $_SESSION['flash'] = 'Senha incorreta. A conta não foi excluída.';
    header('Location: dashboard.php?tab=perfil');
    exit;
}

$pdo = db();
$uid = (int)$user['id_usuario'];
try {
    $pdo->beginTransaction();
    $st = $pdo->prepare('SELECT id_perfil FROM perfis_artistas WHERE id_usuario=?');
    $st->execute([$uid]);
    $profileIds = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));

    // Remover primeiro relações sem ON DELETE CASCADE para evitar erros de chave estrangeira.
    $pdo->prepare('DELETE FROM mensagens WHERE id_remetente=? OR id_destinatario=?')->execute([$uid, $uid]);
    $pdo->prepare('DELETE FROM favoritos WHERE id_cliente=?')->execute([$uid]);
    foreach ($profileIds as $pid) {
        $pdo->prepare('DELETE FROM favoritos WHERE id_artista=?')->execute([$pid]);
        $pdo->prepare('DELETE FROM avaliacoes WHERE id_avaliador=? OR id_avaliado=?')->execute([$uid, $pid]);
        $pdo->prepare('DELETE FROM pedidos WHERE id_cliente=? OR id_artista=?')->execute([$uid, $pid]);
        $pdo->prepare('DELETE FROM propostas WHERE id_artista=?')->execute([$pid]);
    }
    $pdo->prepare('DELETE FROM avaliacoes WHERE id_avaliador=?')->execute([$uid]);
    $pdo->prepare('DELETE FROM pedidos WHERE id_cliente=?')->execute([$uid]);

    // Pedidos que dependem de propostas em projetos pertencentes ao usuário.
    $pdo->prepare('DELETE FROM pedidos WHERE id_proposta IN (SELECT id_proposta FROM propostas WHERE id_projeto IN (SELECT id_projeto FROM projetos WHERE id_usuario=?))')->execute([$uid]);
    // Propostas associadas a projetos pertencentes ao usuário.
    $pdo->prepare('DELETE pr FROM propostas pr INNER JOIN projetos pj ON pj.id_projeto=pr.id_projeto WHERE pj.id_usuario=?')->execute([$uid]);
    // Tabelas relacionadas aos projetos normalmente têm cascata, mas removemos explicitamente os dados do dono.
    $pdo->prepare('DELETE FROM comentarios_projeto WHERE id_usuario=? OR id_projeto IN (SELECT id_projeto FROM projetos WHERE id_usuario=?)')->execute([$uid, $uid]);
    $pdo->prepare('DELETE FROM curtidas_projeto WHERE id_usuario=? OR id_projeto IN (SELECT id_projeto FROM projetos WHERE id_usuario=?)')->execute([$uid, $uid]);
    $pdo->prepare('DELETE FROM denuncias WHERE id_denunciante=? OR id_projeto IN (SELECT id_projeto FROM projetos WHERE id_usuario=?)')->execute([$uid, $uid]);
    $pdo->prepare('DELETE FROM projetos WHERE id_usuario=?')->execute([$uid]);
    $pdo->prepare('DELETE FROM perfis_artistas WHERE id_usuario=?')->execute([$uid]);
    $pdo->prepare('DELETE FROM usuarios WHERE id_usuario=?')->execute([$uid]);
    $pdo->commit();

    // Remove a sessão e encerra o acesso imediatamente.
    logout_user();
    header('Location: login.php?conta_excluida=1');
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Erro ao excluir conta OctoArt: ' . $e->getMessage());
    $_SESSION['flash'] = 'Não foi possível excluir a conta agora. Nenhum dado foi removido; tente novamente.';
    header('Location: dashboard.php?tab=perfil');
    exit;
}
