<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_once 'includes/auth.php';

// 分类管理需要编辑以上权限
require_admin_permission('editor');

// 获取数据库连接
$db = Database::getInstance()->getConnection();

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($_POST['category_action']) {
        case 'add':
            $stmt = $db->prepare("INSERT INTO categories (name, slug, description, parent_id) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $_POST['name'],
                $_POST['slug'] ?: create_slug($_POST['name']),
                $_POST['description'],
                $_POST['parent_id'] ?: null
            ]);
            header('Location: categories.php?message=分类添加成功');
            exit;
            break;

        case 'edit':
            $stmt = $db->prepare("UPDATE categories SET name = ?, slug = ?, description = ?, parent_id = ? WHERE id = ?");
            $stmt->execute([
                $_POST['name'],
                $_POST['slug'] ?: create_slug($_POST['name']),
                $_POST['description'],
                $_POST['parent_id'] ?: null,
                $_POST['category_id']
            ]);
            header('Location: categories.php?message=分类更新成功');
            exit;
            break;

        case 'delete':
            $stmt = $db->prepare("DELETE FROM categories WHERE id = ?");
            $stmt->execute([$_POST['category_id']]);
            header('Location: categories.php?message=分类删除成功');
            exit;
            break;
    }
}

// 获取分类列表（含父分类）
$categories = $db->query("SELECT * FROM categories ORDER BY parent_id, name")->fetchAll();

// 构建分类树
function build_category_tree($categories, $parent_id = null, $level = 0) {
    $tree = [];
    foreach ($categories as $cat) {
        if ($cat['parent_id'] == $parent_id) {
            $cat['level'] = $level;
            $cat['children'] = build_category_tree($categories, $cat['id'], $level + 1);
            $tree[] = $cat;
        }
    }
    return $tree;
}
$category_tree = build_category_tree($categories);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>分类管理 - <?php echo escape(get_setting('site_title', '我的博客')); ?></title>
    <link href="https://cdn.bootcdn.net/ajax/libs/twitter-bootstrap/5.2.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.bootcdn.net/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/sidebar.css" rel="stylesheet">
    <link href="assets/dashboard.css" rel="stylesheet">
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
                <a class="nav-link active" href="categories.php">
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
                <a class="nav-link" href="users.php">
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
            <div class="page-header d-flex justify-content-between align-items-center mb-4">
                <h1 class="page-title">分类管理</h1>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#categoryModal">
                    <i class="fas fa-plus me-2"></i>添加分类
                </button>
            </div>

            <?php if (isset($_GET['message'])): ?>
            <div class="alert alert-success animate-fade-in">
                <?php echo escape($_GET['message']); ?>
            </div>
            <?php endif; ?>

            <div class="data-card animate-fade-in">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>名称</th>
                                <th>别名</th>
                                <th>描述</th>
                                <th>文章数</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php function render_category_rows($tree) {
    foreach ($tree as $cat) {
        echo '<tr>';
        echo '<td><div class="d-flex align-items-center">';
        echo '<i class="fas fa-folder text-primary me-2"></i>';
        echo str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $cat['level']) . escape($cat['name']);
        echo '</div></td>';
        echo '<td><div class="d-flex align-items-center text-muted"><i class="fas fa-link me-2"></i>' . escape($cat['slug']) . '</div></td>';
        echo '<td><div class="d-flex align-items-center"><i class="fas fa-info-circle text-muted me-2"></i>' . escape($cat['description']) . '</div></td>';
        echo '<td><div class="d-flex align-items-center"><i class="fas fa-file-alt text-muted me-2"></i>';
        global $db;
        $stmt = $db->prepare("SELECT COUNT(*) FROM post_categories WHERE category_id = ?");
        $stmt->execute([$cat['id']]);
        echo $stmt->fetchColumn();
        echo '</div></td>';
        echo '<td><div class="btn-group">';
        echo '<button type="button" class="btn btn-sm btn-outline-primary" onclick=\'editCategory(' . htmlspecialchars(json_encode($cat), ENT_QUOTES) . ')\'><i class="fas fa-edit"></i></button>';
        echo '<button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteCategory(' . $cat['id'] . ')"><i class="fas fa-trash"></i></button>';
        echo '</div></td>';
        echo '</tr>';
        if (!empty($cat['children'])) render_category_rows($cat['children']);
    }
}
render_category_rows($category_tree);
?>
</tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- 分类管理模态框 -->
    <div class="modal fade" id="categoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-folder-plus me-2"></i>
                        <span>添加/编辑分类</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="category_action" value="add">
                        <input type="hidden" name="category_id" value="">
                        <div class="mb-3">
                            <label class="form-label">分类名称</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-folder"></i></span>
                                <input type="text" class="form-control" name="name" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">别名</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-link"></i></span>
                                <input type="text" class="form-control" name="slug" placeholder="留空将自动生成">
                            </div>
                            <div class="form-text">用于URL中的标识，建议使用英文</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">描述</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-info-circle"></i></span>
                                <textarea class="form-control" name="description" rows="3"></textarea>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">父分类</label>
                            <select class="form-select" name="parent_id">
                                <option value="">无（顶级分类）</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo str_repeat('—', $cat['parent_id'] ? 1 : 0) . escape($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
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
    <script>
        // 调试代码
        console.log('页面加载完成');
        
        // 检查函数是否存在
        if (typeof editCategory === 'function') {
            console.log('editCategory 函数已加载');
        } else {
            console.log('editCategory 函数未找到');
        }
        
        if (typeof deleteCategory === 'function') {
            console.log('deleteCategory 函数已加载');
        } else {
            console.log('deleteCategory 函数未找到');
        }
        
        // 如果函数不存在，重新定义
        if (typeof editCategory !== 'function') {
            window.editCategory = function(category) {
                console.log('编辑分类:', category);
                // 填充表单数据
                document.querySelector('input[name="category_action"]').value = 'edit';
                document.querySelector('input[name="category_id"]').value = category.id;
                document.querySelector('input[name="name"]').value = category.name;
                document.querySelector('input[name="slug"]').value = category.slug;
                document.querySelector('textarea[name="description"]').value = category.description;
                document.querySelector('select[name="parent_id"]').value = category.parent_id || ''; // 设置父分类
                
                // 更新模态框标题
                document.querySelector('#categoryModal .modal-title span').textContent = '编辑分类';
                
                // 显示模态框
                const modal = new bootstrap.Modal(document.getElementById('categoryModal'));
                modal.show();
            };
        }
        
        if (typeof deleteCategory !== 'function') {
            window.deleteCategory = function(categoryId) {
                console.log('删除分类:', categoryId);
                if (confirm('确定要删除这个分类吗？删除后无法恢复。')) {
                    // 创建表单并提交
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                        <input type="hidden" name="category_action" value="delete">
                        <input type="hidden" name="category_id" value="${categoryId}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            };
        }
        
        // 添加点击事件监听器作为备用方案
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM加载完成，添加事件监听器');
            
            // 为编辑按钮添加事件监听器
            document.querySelectorAll('.btn-outline-primary').forEach(function(btn) {
                if (btn.querySelector('.fa-edit')) {
                    btn.addEventListener('click', function(e) {
                        console.log('编辑按钮被点击');
                        // 这里可以添加编辑逻辑
                    });
                }
            });
            
            // 为删除按钮添加事件监听器
            document.querySelectorAll('.btn-outline-danger').forEach(function(btn) {
                if (btn.querySelector('.fa-trash')) {
                    btn.addEventListener('click', function(e) {
                        console.log('删除按钮被点击');
                        // 这里可以添加删除逻辑
                    });
                }
            });
        });
    </script>
</body>
</html> 