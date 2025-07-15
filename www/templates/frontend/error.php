<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>出错了 - <?php echo isset($title) ? htmlspecialchars($title) : '我的博客'; ?></title>
    <link href="https://cdn.bootcdn.net/ajax/libs/twitter-bootstrap/5.2.3/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body text-center">
                        <h1 class="card-title mb-4">抱歉，出错了！</h1>
                        <p class="card-text">系统遇到了一些问题，请稍后再试。</p>
                        <?php if (isset($e) && $e instanceof Exception): ?>
                        <div class="alert alert-danger mt-3">
                            <p class="mb-0">错误信息：</p>
                            <pre class="mb-0 mt-2"><?php echo htmlspecialchars($e->getMessage()); ?></pre>
                        </div>
                        <?php endif; ?>
                        <a href="<?php echo $site_url; ?>" class="btn btn-primary mt-3">返回首页</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 