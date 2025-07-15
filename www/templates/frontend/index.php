<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($description); ?>">
    <link href="https://cdn.bootcdn.net/ajax/libs/twitter-bootstrap/5.2.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.bootcdn.net/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link href="/assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- 用户信息区 -->
    <div class="user-info">
        <div class="avatar">
            <img src="<?php echo get_user_avatar(); ?>" alt="用户头像">
        </div>
        <div class="user-stats">
            <div class="stat-item">
                <span>文章</span>
                <span>0</span>
            </div>
            <div class="stat-item">
                <span>评论</span>
                <span>0</span>
            </div>
            <div class="stat-item">
                <span>浏览</span>
                <span>0</span>
            </div>
        </div>
        <button class="follow-btn">关注我</button>
    </div>

    <!-- 导航区 -->
    <div class="nav-section">
        <a href="index.php" class="nav-link">
            <i class="fas fa-file-alt"></i>
            <span>最新博文</span>
        </a>
        <a href="#" class="nav-link">
            <i class="fas fa-clock"></i>
            <span>时间轴</span>
        </a>
        <a href="#" class="nav-link">
            <i class="fas fa-folder"></i>
            <span>分类</span>
        </a>
        <a href="#" class="nav-link">
            <i class="fas fa-tag"></i>
            <span>标签</span>
        </a>
        <a href="#" class="nav-link">
            <i class="fas fa-archive"></i>
            <span>归档</span>
        </a>
        <a href="#" class="nav-link">
            <i class="fas fa-link"></i>
            <span>友链</span>
        </a>
    </div>

    <!-- 工具栏 -->
    <div class="tool-bar">
        <a href="#" class="tool-link">
            <i class="fas fa-search"></i>
        </a>
        <a href="#" class="tool-link">
            <i class="fas fa-cog"></i>
        </a>
        <a href="#" class="tool-link">
            <i class="fas fa-star"></i>
        </a>
    </div>

    <!-- 标签云 -->
    <div class="tag-cloud">
        <?php foreach ($tags as $tag): ?>
        <a href="?tag=<?php echo urlencode($tag['name']); ?>" class="tag">
            <?php echo htmlspecialchars($tag['name']); ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- 时间归档 -->
    <div class="archive-list">
        <?php foreach ($archives as $archive): ?>
        <a href="?archive=<?php echo urlencode($archive['date']); ?>" class="archive-item">
            <span class="archive-dot"></span>
            <span class="archive-date"><?php echo $archive['date']; ?></span>
            <span class="archive-count"><?php echo $archive['count']; ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- 文章列表 -->
    <div class="article-list">
        <?php if (empty($posts)): ?>
        <div class="no-articles">暂无文章</div>
        <?php else: ?>
        <?php foreach ($posts as $post): ?>
        <article class="article-item">
            <div class="article-header">
                <h2 class="article-title"><?php echo htmlspecialchars($post['title']); ?></h2>
                <div class="article-meta">
                    <?php if (!empty($post['tags'])): ?>
                    <?php foreach (explode(',', $post['tags']) as $tag): ?>
                    <span class="article-tag"><?php echo htmlspecialchars(trim($tag)); ?></span>
                    <?php endforeach; ?>
                    <?php endif; ?>
                    <span class="article-date"><?php echo date('Y-m-d', strtotime($post['created_at'])); ?></span>
                </div>
            </div>
            <?php if (!empty($post['cover_image'])): ?>
            <div class="article-cover">
                <img src="<?php echo htmlspecialchars($post['cover_image']); ?>" 
                     alt="<?php echo htmlspecialchars($post['title']); ?>">
            </div>
            <?php endif; ?>
        </article>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- 分页 -->
    <?php if ($pagination['total_pages'] > 1): ?>
    <div class="pagination">
        <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
        <a href="?page=<?php echo $i; ?>" 
           class="page-number <?php echo $i == $pagination['current_page'] ? 'active' : ''; ?>">
            <?php echo $i; ?>
        </a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>

    <script src="https://cdn.bootcdn.net/ajax/libs/twitter-bootstrap/5.2.3/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('contextmenu', function(e) {
      e.preventDefault();
    });
    </script>
</body>
</html> 