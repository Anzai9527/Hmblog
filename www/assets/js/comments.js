// 评论系统JavaScript
document.addEventListener('DOMContentLoaded', function() {
    const commentForm = document.getElementById('commentForm');
    const commentContent = document.getElementById('commentContent');
    const charCount = document.getElementById('charCount');
    const parentIdInput = document.getElementById('parent_id');
    const replyToText = document.getElementById('replyToText');
    const cancelReplyBtn = document.getElementById('cancelReply');
    const loadMoreBtn = document.getElementById('loadMoreComments');
    const commentsList = document.getElementById('commentsList');
    
    // 字符计数
    if (commentContent && charCount) {
        commentContent.addEventListener('input', function() {
            const length = this.value.length;
            const maxLength = parseInt(this.getAttribute('maxlength'));
            charCount.textContent = length;
            
            // 更新字符计数颜色
            charCount.className = '';
            if (length > maxLength * 0.9) {
                charCount.classList.add('char-danger');
            } else if (length > maxLength * 0.7) {
                charCount.classList.add('char-warning');
            }
        });
    }
    
    // 评论表单提交
    if (commentForm) {
        commentForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('.comment-form-submit');
            const originalText = submitBtn.textContent;
            
            // 禁用提交按钮
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="loading"></span> 发送中...';
            
            // 发送AJAX请求
            fetch('/api/comments.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // 显示成功消息
                    showMessage(data.message, 'success');
                    
                    // 清空表单
                    commentContent.value = '';
                    charCount.textContent = '0';
                    
                    // 取消回复状态
                    cancelReply();
                    
                    // 如果评论直接通过审核，刷新评论列表
                    if (data.status === 'approved') {
                        setTimeout(() => {
                            location.reload();
                        }, 1000);
                    }
                } else {
                    showMessage(data.error, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('发送失败，请重试', 'error');
            })
            .finally(() => {
                // 恢复提交按钮
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            });
        });
    }
    
    // 点赞功能
    document.addEventListener('click', function(e) {
        if (e.target.matches('.comment-like-btn') || e.target.closest('.comment-like-btn')) {
            e.preventDefault();
            
            const btn = e.target.closest('.comment-like-btn');
            const commentId = btn.dataset.commentId;
            
            // 防止重复点击
            if (btn.disabled) return;
            btn.disabled = true;
            
            // 发送点赞请求
            fetch('/api/comments.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'like_comment',
                    comment_id: commentId,
                    csrf_token: document.querySelector('input[name="csrf_token"]').value
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // 更新点赞状态
                    if (data.is_liked) {
                        btn.classList.add('liked');
                    } else {
                        btn.classList.remove('liked');
                    }
                    
                    // 更新点赞数
                    const likeCount = btn.querySelector('.like-count');
                    if (likeCount) {
                        likeCount.textContent = data.likes_count;
                    }
                    
                    // 添加动画效果
                    const likeIcon = btn.querySelector('.like-icon');
                    if (likeIcon) {
                        likeIcon.style.transform = 'scale(1.2)';
                        setTimeout(() => {
                            likeIcon.style.transform = 'scale(1)';
                        }, 200);
                    }
                } else {
                    showMessage(data.error, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('操作失败，请重试', 'error');
            })
            .finally(() => {
                btn.disabled = false;
            });
        }
    });
    
    // 回复功能
    document.addEventListener('click', function(e) {
        if (e.target.matches('.comment-reply-btn') || e.target.closest('.comment-reply-btn')) {
            e.preventDefault();
            
            const btn = e.target.closest('.comment-reply-btn');
            const commentId = btn.dataset.commentId;
            const author = btn.dataset.author;
            
            // 设置回复状态
            setReplyTo(commentId, author);
            
            // 滚动到评论表单
            commentForm.scrollIntoView({ behavior: 'smooth' });
            commentContent.focus();
        }
    });
    
    // 删除功能
    document.addEventListener('click', function(e) {
        if (e.target.matches('.comment-delete-btn') || e.target.closest('.comment-delete-btn')) {
            e.preventDefault();
            
            const btn = e.target.closest('.comment-delete-btn');
            const commentId = btn.dataset.commentId;
            
            if (!confirm('确定要删除这条评论吗？')) {
                return;
            }
            
            // 发送删除请求
            fetch('/api/comments.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'delete_comment',
                    comment_id: commentId,
                    csrf_token: document.querySelector('input[name="csrf_token"]').value
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // 移除评论元素
                    const commentElement = btn.closest('.comment-item, .comment-reply');
                    if (commentElement) {
                        commentElement.style.opacity = '0';
                        commentElement.style.transform = 'translateY(-20px)';
                        setTimeout(() => {
                            commentElement.remove();
                        }, 300);
                    }
                    
                    showMessage(data.message, 'success');
                } else {
                    showMessage(data.error, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('删除失败，请重试', 'error');
            });
        }
    });
    
    // 取消回复
    if (cancelReplyBtn) {
        cancelReplyBtn.addEventListener('click', function() {
            cancelReply();
        });
    }
    
    // 加载更多评论
    if (loadMoreBtn) {
        loadMoreBtn.addEventListener('click', function() {
            const page = parseInt(this.dataset.page);
            const postId = document.querySelector('input[name="post_id"]').value;
            
            // 禁用按钮
            this.disabled = true;
            this.innerHTML = '<span class="loading"></span> 加载中...';
            
            // 发送请求
            fetch('/api/comments.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'load_comments',
                    post_id: postId,
                    page: page,
                    csrf_token: document.querySelector('input[name="csrf_token"]').value
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // 添加新评论到列表
                    const parser = new DOMParser();
                    data.comments.forEach(comment => {
                        const commentHtml = createCommentHtml(comment);
                        const commentElement = parser.parseFromString(commentHtml, 'text/html').body.firstChild;
                        commentsList.appendChild(commentElement);
                    });
                    
                    // 更新页码
                    if (data.has_more) {
                        this.dataset.page = page + 1;
                        this.disabled = false;
                        this.textContent = '加载更多评论';
                    } else {
                        this.remove();
                    }
                } else {
                    showMessage(data.error, 'error');
                    this.disabled = false;
                    this.textContent = '加载更多评论';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('加载失败，请重试', 'error');
                this.disabled = false;
                this.textContent = '加载更多评论';
            });
        });
    }
    
    // 设置回复状态
    function setReplyTo(commentId, author) {
        if (parentIdInput && replyToText && cancelReplyBtn) {
            parentIdInput.value = commentId;
            replyToText.textContent = '回复 @' + author;
            replyToText.style.display = 'inline';
            cancelReplyBtn.style.display = 'inline';
            
            // 高亮回复目标
            document.querySelectorAll('.comment-item').forEach(item => {
                item.classList.remove('replying');
            });
            
            const targetComment = document.querySelector(`[data-comment-id="${commentId}"]`);
            if (targetComment) {
                targetComment.classList.add('replying');
            }
        }
    }
    
    // 取消回复
    function cancelReply() {
        if (parentIdInput && replyToText && cancelReplyBtn) {
            parentIdInput.value = '';
            replyToText.style.display = 'none';
            cancelReplyBtn.style.display = 'none';
            
            // 移除高亮
            document.querySelectorAll('.comment-item').forEach(item => {
                item.classList.remove('replying');
            });
        }
    }
    
    // 显示消息
    function showMessage(message, type) {
        // 移除现有消息
        const existingMessage = document.querySelector('.comment-message');
        if (existingMessage) {
            existingMessage.remove();
        }
        
        // 创建新消息
        const messageDiv = document.createElement('div');
        messageDiv.className = `comment-message ${type}`;
        messageDiv.textContent = message;
        
        // 插入到评论表单前
        const commentsSection = document.querySelector('.comments-section');
        if (commentsSection) {
            commentsSection.insertBefore(messageDiv, commentsSection.firstChild);
        }
        
        // 自动隐藏消息
        setTimeout(() => {
            messageDiv.style.opacity = '0';
            setTimeout(() => {
                messageDiv.remove();
            }, 300);
        }, 3000);
    }
    
    // 创建评论HTML
    function createCommentHtml(comment) {
        const repliesHtml = comment.replies.map(reply => `
            <div class="comment-reply" data-comment-id="${reply.id}">
                <div class="comment-avatar">
                    <img src="${reply.avatar}" alt="${reply.username}">
                </div>
                <div class="comment-content">
                    <div class="comment-header">
                        <span class="comment-author">${reply.username}</span>
                        <span class="comment-time">${reply.created_at_formatted}</span>
                    </div>
                    <div class="comment-text">${reply.content}</div>
                    <div class="comment-actions">
                        <button class="comment-like-btn ${reply.is_liked ? 'liked' : ''}" data-comment-id="${reply.id}">
                            <span class="like-icon">❤️</span>
                            <span class="like-count">${reply.likes_count}</span>
                        </button>
                        ${reply.can_delete ? `<button class="comment-delete-btn" data-comment-id="${reply.id}">删除</button>` : ''}
                    </div>
                </div>
            </div>
        `).join('');
        
        return `
            <div class="comment-item" data-comment-id="${comment.id}">
                <div class="comment-avatar">
                    <img src="${comment.avatar}" alt="${comment.username}">
                </div>
                <div class="comment-content">
                    <div class="comment-header">
                        <span class="comment-author">${comment.username}</span>
                        <span class="comment-time">${comment.created_at_formatted}</span>
                    </div>
                    <div class="comment-text">${comment.content}</div>
                    <div class="comment-actions">
                        <button class="comment-like-btn ${comment.is_liked ? 'liked' : ''}" data-comment-id="${comment.id}">
                            <span class="like-icon">❤️</span>
                            <span class="like-count">${comment.likes_count}</span>
                        </button>
                        <button class="comment-reply-btn" data-comment-id="${comment.id}" data-author="${comment.username}">回复</button>
                        ${comment.can_delete ? `<button class="comment-delete-btn" data-comment-id="${comment.id}">删除</button>` : ''}
                    </div>
                    ${comment.replies.length > 0 ? `<div class="comment-replies">${repliesHtml}</div>` : ''}
                </div>
            </div>
        `;
    }
    
    // 键盘快捷键
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + Enter 提交评论
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            if (commentContent && commentContent === document.activeElement) {
                e.preventDefault();
                commentForm.dispatchEvent(new Event('submit'));
            }
        }
        
        // Escape 取消回复
        if (e.key === 'Escape') {
            cancelReply();
        }
    });
    
    // 图片懒加载
    const commentImages = document.querySelectorAll('.comment-avatar img');
    const imageObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                if (img.dataset.src) {
                    img.src = img.dataset.src;
                    img.removeAttribute('data-src');
                    imageObserver.unobserve(img);
                }
            }
        });
    });
    
    commentImages.forEach(img => {
        imageObserver.observe(img);
    });
});

// 工具函数
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    
    return text.replace(/[&<>"']/g, function(m) {
        return map[m];
    });
}

// 时间格式化
function formatTime(timestamp) {
    const now = Date.now();
    const diff = now - timestamp;
    
    if (diff < 60000) {
        return '刚刚';
    } else if (diff < 3600000) {
        return Math.floor(diff / 60000) + '分钟前';
    } else if (diff < 86400000) {
        return Math.floor(diff / 3600000) + '小时前';
    } else if (diff < 2592000000) {
        return Math.floor(diff / 86400000) + '天前';
    } else {
        return new Date(timestamp).toLocaleDateString();
    }
} 