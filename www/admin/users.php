<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_once 'includes/auth.php';

// 用户管理页面只允许管理员访问
require_admin_permission('admin');

// 获取数据库连接
$db = Database::getInstance()->getConnection();

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($_POST['user_action']) {
        case 'add':
            if ($_POST['password'] === $_POST['confirm_password']) {
                $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
                $stmt->execute([
                    $_POST['username'],
                    $_POST['email'],
                    $password_hash,
                    $_POST['role']
                ]);
                header('Location: users.php?message=用户添加成功');
                exit;
            }
            break;

        case 'edit':
            // 只允许修改密码，不允许修改用户名和邮箱
            if (!empty($_POST['password']) && $_POST['password'] === $_POST['confirm_password']) {
                $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([
                    password_hash($_POST['password'], PASSWORD_DEFAULT),
                    $_POST['user_id']
                ]);
                header('Location: users.php?message=密码修改成功');
                exit;
            } else {
                header('Location: users.php?error=密码不能为空或两次输入不一致');
                exit;
            }
            break;

        case 'delete':
            $user_id = (int)$_POST['user_id'];
            $current_user_id = (int)$_SESSION['user_id'];
            
            // 检查是否尝试删除自己
            if ($user_id === $current_user_id) {
                header('Location: users.php?error=不能删除当前登录用户');
                exit;
            }
            
            // 检查用户是否存在
            $check_stmt = $db->prepare("SELECT id FROM users WHERE id = ?");
            $check_stmt->execute([$user_id]);
            if (!$check_stmt->fetch()) {
                header('Location: users.php?error=用户不存在');
                exit;
            }
            
            // 执行删除操作
            $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND id != ?");
            $result = $stmt->execute([$user_id, $current_user_id]);
            
            if ($result && $stmt->rowCount() > 0) {
                header('Location: users.php?message=用户删除成功');
            } else {
                header('Location: users.php?error=删除失败，请重试');
            }
            exit;
            break;
    }
}

// 获取搜索参数
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';

// 构建查询条件
$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(username LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($role_filter !== '') {
    $where[] = "role = ?";
    $params[] = $role_filter;
}

$where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// 获取用户列表
$sql = "SELECT * FROM users $where_clause ORDER BY username";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// 检查并添加last_login字段（如果不存在）
try {
    $db->query("SELECT last_login FROM users LIMIT 1");
} catch (PDOException $e) {
    // 如果字段不存在，添加它
    $db->exec("ALTER TABLE users ADD COLUMN last_login DATETIME DEFAULT NULL");
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户管理 - <?php echo escape(get_setting('site_title', '我的博客')); ?></title>
    <link href="https://cdn.bootcdn.net/ajax/libs/twitter-bootstrap/5.2.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.bootcdn.net/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/sidebar.css" rel="stylesheet">
    <link href="assets/dashboard.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <!-- 侧边栏 -->
    <nav class="sidebar">
        <a href="index.php" class="sidebar-brand">
            <i class="fas fa-blog"></i>
            HM管理系统
        </a>
        <ul class="nav nav-tabs flex-column">
            <li class="nav-item">
                <a class="nav-link" href="index.php">
                    <i class="fas fa-home"></i> 仪表盘
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="posts.php">
                    <i class="fas fa-file-alt"></i> 文章管理
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="categories.php">
                    <i class="fas fa-folder"></i> 分类管理
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="comments.php">
                    <i class="fas fa-comments"></i> 评论管理
                </a>
            </li>
            <?php if (is_admin()): ?>
            <li class="nav-item">
                <a class="nav-link active" href="users.php">
                    <i class="fas fa-users"></i> 用户管理
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="settings.php">
                    <i class="fas fa-cog"></i> 系统设置
                </a>
            </li>
            <?php endif; ?>
            <li class="nav-item">
                <a class="nav-link" href="logout.php">
                    <i class="fas fa-sign-out-alt"></i> 退出登录
                </a>
            </li>
        </ul>
    </nav>

    <!-- 主内容区域 -->
    <main class="main-content">
        <div class="container-fluid">
            <div class="page-header animate-fade-in mb-4">
                <h1 class="page-title">用户管理</h1>
            </div>

            <?php if (isset($_GET['message'])): ?>
            <div class="alert alert-success animate-fade-in">
                <?php echo escape($_GET['message']); ?>
            </div>
            <?php endif; ?>

            <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger animate-fade-in">
                <?php echo escape($_GET['error']); ?>
            </div>
            <?php endif; ?>

            <!-- 搜索表单 -->
            <div class="dashboard-card animate-fade-in mb-4">
                <div class="dashboard-card-body">
                    <form method="get" class="row g-3">
                        <div class="col-md-4">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" class="form-control" name="search" placeholder="搜索用户名或邮箱..." value="<?php echo escape($search); ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" name="role">
                                <option value="">所有角色</option>
                                <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>管理员</option>
                                <option value="editor" <?php echo $role_filter === 'editor' ? 'selected' : ''; ?>>编辑</option>
                                <option value="subscriber" <?php echo $role_filter === 'subscriber' ? 'selected' : ''; ?>>订阅者</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <div class="btn-group w-100">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search me-1"></i>搜索
                                </button>
                                <a href="users.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-1"></i>清除
                                </a>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <button type="button" class="btn btn-success w-100" data-bs-toggle="modal" data-bs-target="#userModal">
                                <i class="fas fa-plus me-1"></i>添加用户
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="dashboard-card animate-fade-in">
                <div class="dashboard-card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="dashboard-card-title mb-0">
                            <i class="fas fa-users me-2"></i>用户列表
                        </h5>
                        <small class="text-muted">
                            <?php if ($search || $role_filter): ?>
                                找到 <?php echo count($users); ?> 个用户
                                <?php if ($search): ?>
                                    <span class="badge bg-primary ms-1"><?php echo escape($search); ?></span>
                                <?php endif; ?>
                                <?php if ($role_filter): ?>
                                    <span class="badge bg-secondary ms-1">
                                        <?php 
                                        switch($role_filter) {
                                            case 'admin': echo '管理员'; break;
                                            case 'editor': echo '编辑'; break;
                                            case 'subscriber': echo '订阅者'; break;
                                            default: echo $role_filter;
                                        }
                                        ?>
                                    </span>
                                <?php endif; ?>
                            <?php else: ?>
                                共 <?php echo count($users); ?> 个用户
                            <?php endif; ?>
                        </small>
                    </div>
                </div>
                <div class="dashboard-card-body">
                    <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>用户名</th>
                                <th>邮箱</th>
                                <th>角色</th>
                                <th>最后登录</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-user-circle text-primary me-2"></i>
                                        <?php echo escape($user['username']); ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-envelope text-muted me-2"></i>
                                        <?php echo escape($user['email']); ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $user['role'] === 'admin' ? 'published' : ($user['role'] === 'editor' ? 'pending' : 'draft'); ?>">
                                        <?php 
                                        switch($user['role']) {
                                            case 'admin':
                                                echo '管理员';
                                                break;
                                            case 'editor':
                                                echo '编辑';
                                                break;
                                            case 'subscriber':
                                                echo '订阅者';
                                                break;
                                            default:
                                                echo $user['role'];
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center text-muted">
                                        <i class="fas fa-clock me-2"></i>
                                        <?php echo $user['last_login'] ? date('Y-m-d H:i', strtotime($user['last_login'])) : '从未登录'; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                                onclick='editUser(<?php echo json_encode($user); ?>)'>
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                                onclick="deleteUser(<?php echo $user['id']; ?>)" 
                                                title="删除用户 <?php echo escape($user['username']); ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- 用户管理模态框 -->
    <div class="modal fade" id="userModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-plus me-2"></i>
                        <span>添加/编辑用户</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="user_action" value="add">
                        <input type="hidden" name="user_id" value="">
                        
                        <!-- 用户信息显示（仅编辑时显示） -->
                        <div class="mb-3 edit-only-field" style="display: none;">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>当前用户：</strong><span id="current-username"></span><br>
                                <strong>邮箱：</strong><span id="current-email"></span><br>
                                <strong>角色：</strong><span id="current-role"></span>
                            </div>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>注意：</strong>编辑用户时只能修改密码，不能修改用户名、邮箱和角色。
                            </div>
                        </div>
                        
                        <!-- 添加用户时的字段 -->
                        <div class="mb-3 add-only-field">
                            <label class="form-label">用户名</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" class="form-control" name="username" required>
                            </div>
                        </div>
                        <div class="mb-3 add-only-field">
                            <label class="form-label">邮箱</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" name="email" required>
                            </div>
                        </div>
                        <div class="mb-3 add-only-field">
                            <label class="form-label">角色</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user-tag"></i></span>
                                <select class="form-select" name="role" required>
                                    <option value="subscriber">订阅者</option>
                                    <option value="editor">编辑</option>
                                    <option value="admin">管理员</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- 密码字段（添加和编辑都显示） -->
                        <div class="mb-3">
                            <label class="form-label">密码</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" name="password" id="password-field" required>
                            </div>
                            <div class="form-text add-only-field">添加新用户时必须设置密码</div>
                            <div class="form-text edit-only-field" style="display: none;">修改用户密码时必须输入新密码</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">确认密码</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" name="confirm_password" id="confirm-password-field" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>保存
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.bootcdn.net/ajax/libs/twitter-bootstrap/5.2.3/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/admin.js"></script>
</body>
</html> 