/**
 * 文章管理页面 JavaScript 功能
 */

// 全局变量
let simplemde = null;

/**
 * 初始化Markdown编辑器
 */
function initializeMarkdownEditor(editPostId) {
    if (!document.getElementById("content")) {
        return;
    }

    simplemde = new SimpleMDE({
        element: document.getElementById("content"),
        spellChecker: false,
        autosave: {
            enabled: true,
            uniqueId: "post_" + (editPostId || 'new'),
            delay: 1000,
        },
        placeholder: "在这里写下你的文章内容...",
        toolbar: [
            "bold", "italic", "heading", "|",
            "quote", "unordered-list", "ordered-list", "|",
            "link", {
                name: "image",
                action: function() {
                    document.getElementById('image-upload').click();
                },
                className: "fa fa-image",
                title: "上传图片",
            }, "|",
            {
                name: "code",
                action: SimpleMDE.toggleCodeBlock,
                className: "fa fa-code",
                title: "代码块",
            }, {
                name: "code-inline",
                action: SimpleMDE.toggleInlineCode,
                className: "fa fa-code",
                title: "行内代码",
            }, {
                name: "code-template",
                action: function() {
                    showCodeTemplateDialog();
                },
                className: "fa fa-file-code",
                title: "代码模板",
            }, "|",
            "preview", "side-by-side", "fullscreen", "|",
            "guide"
        ],
        status: ["autosave", "lines", "words", "cursor"],
        renderingConfig: {
            singleLineBreaks: false,
            codeSyntaxHighlighting: true,
        },
        // 添加代码块配置
        codeMirror: {
            mode: 'markdown',
            lineNumbers: true,
            lineWrapping: true,
            theme: 'default',
            extraKeys: {
                "Ctrl-Space": "autocomplete",
                "Tab": function(cm) {
                    if (cm.somethingSelected()) {
                        cm.indentSelection("add");
                    } else {
                        cm.replaceSelection("    ", "end");
                    }
                }
            }
        },
        previewRender: function(plainText, preview) {
            // 自定义预览渲染，确保图片路径正确
            var renderedHTML = this.parent.markdown(plainText);
            
            // 修复图片路径，确保在预览中能正确显示
            renderedHTML = renderedHTML.replace(/src="\.\.\/uploads\/images\//g, 'src="../uploads/images/');
            
            // 延迟执行，确保DOM元素已渲染
            setTimeout(function() {
                var previewImages = document.querySelectorAll('.editor-preview img, .editor-preview-side img');
                previewImages.forEach(function(img) {
                    // 修复所有图片路径问题
                    var currentSrc = img.src;
                    
                    // 处理相对路径
                    if (currentSrc.includes('/admin/../uploads/images/')) {
                        img.src = currentSrc.replace('/admin/../uploads/images/', '/uploads/images/');
                    } else if (currentSrc.includes('/admin/uploads/images/')) {
                        img.src = currentSrc.replace('/admin/uploads/images/', '/uploads/images/');
                    } else if (currentSrc.includes('uploads/images/') && !currentSrc.startsWith('http')) {
                        // 确保是绝对路径
                        var baseUrl = window.location.origin + window.location.pathname.replace('/admin/posts.php', '');
                        img.src = baseUrl + '/' + currentSrc;
                    }
                    
                    // 添加样式
                    img.style.maxWidth = '100%';
                    img.style.height = 'auto';
                    img.style.borderRadius = '8px';
                    img.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
                    img.style.margin = '1rem 0';
                    
                    // 添加错误处理
                    img.onerror = function() {
                        this.style.display = 'block';
                        this.style.width = '100%';
                        this.style.maxWidth = '400px';
                        this.style.height = '200px';
                        this.style.backgroundColor = '#f8f9fa';
                        this.style.border = '2px dashed #dee2e6';
                        this.style.borderRadius = '8px';
                        this.style.color = '#6c757d';
                        this.style.textAlign = 'center';
                        this.style.lineHeight = '200px';
                        this.style.fontSize = '14px';
                        this.style.fontWeight = '500';
                        this.alt = '图片加载失败';
                        this.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHZpZXdCb3g9IjAgMCA0MCA0MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHBhdGggZD0iTTIwIDVMMzAgMTVIMjVWMzVIMTVWMjBIMTBMMjAgNVoiIGZpbGw9IiM2Yzc1N2QiLz4KPC9zdmc+';
                    };
                    
                    // 添加加载成功处理
                    img.onload = function() {
                        this.style.opacity = '1';
                        this.style.transition = 'opacity 0.3s ease';
                    };
                });
                
                // 应用代码语法高亮
                if (typeof Prism !== 'undefined') {
                    var codeBlocks = document.querySelectorAll('.editor-preview pre code, .editor-preview-side pre code');
                    codeBlocks.forEach(function(codeBlock) {
                        Prism.highlightElement(codeBlock);
                    });
                }
            }, 100);
            
            return renderedHTML;
        },
    });

    // 监听预览区域的变化，修复图片路径
    var observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.type === 'childList') {
                var previewImages = document.querySelectorAll('.editor-preview img, .editor-preview-side img');
                previewImages.forEach(function(img) {
                    if (img.src.includes('/uploads/images/') && !img.dataset.fixed) {
                        // 标记已处理，避免重复处理
                        img.dataset.fixed = 'true';
                        
                        // 修复图片路径
                        var currentSrc = img.src;
                        if (currentSrc.includes('/admin/../uploads/images/')) {
                            img.src = currentSrc.replace('/admin/../uploads/images/', '/uploads/images/');
                        } else if (currentSrc.includes('/admin/uploads/images/')) {
                            img.src = currentSrc.replace('/admin/uploads/images/', '/uploads/images/');
                        }
                        
                        // 添加样式
                        img.style.maxWidth = '100%';
                        img.style.height = 'auto';
                        img.style.borderRadius = '4px';
                        img.style.boxShadow = '0 2px 8px rgba(0,0,0,0.1)';
                    }
                });
            }
        });
    });

    // 开始监听预览区域
    var previewElements = document.querySelectorAll('.editor-preview, .editor-preview-side');
    previewElements.forEach(function(element) {
        observer.observe(element, {
            childList: true,
            subtree: true
        });
    });
}

/**
 * 处理文件上传
 */
function handleFileUpload(file, type) {
    const allowedImageTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    const allowedFileTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 
                            'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                            'text/plain', 'application/zip', 'application/x-rar-compressed', 'application/x-7z-compressed',
                            'audio/mpeg', 'video/mp4', 'video/x-msvideo'];

    if (type === 'image' && !allowedImageTypes.includes(file.type)) {
        alert('只允许上传 JPG, PNG, GIF 或 WebP 格式的图片');
        return false;
    }

    if (type === 'file') {
        const fileExtension = file.name.split('.').pop().toLowerCase();
        const allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'zip', 'rar', '7z', 'mp3', 'mp4', 'avi'];
        
        if (!allowedExtensions.includes(fileExtension)) {
            alert('不支持的文件类型。支持的格式：PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT, ZIP, RAR, 7Z, MP3, MP4, AVI');
            return false;
        }
    }

    const maxSize = type === 'image' ? 5 * 1024 * 1024 : 100 * 1024 * 1024;
    if (file.size > maxSize) {
        const sizeLimit = type === 'image' ? '5MB' : '100MB';
        alert(`文件大小不能超过${sizeLimit}`);
        return false;
    }

    return true;
}

/**
 * 上传文件
 */
function uploadFile(file, type) {
    return new Promise((resolve, reject) => {
        const progress = document.querySelector('.upload-progress');
        const uploadText = type === 'image' ? '正在上传图片...' : 
                          type === 'cover' ? '正在上传封面图片...' : 
                          '正在上传附件...';
        
        progress.innerHTML = `<i class="fas fa-spinner fa-spin me-2"></i>${uploadText}`;
        progress.style.display = 'block';

        const formData = new FormData();
        formData.append(type === 'file' ? 'file' : 'image', file);

        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            progress.style.display = 'none';
            
            if (data.error) {
                throw new Error(data.error);
            }
            
            resolve(data);
        })
        .catch(error => {
            progress.style.display = 'none';
            reject(error);
        });
    });
}

/**
 * 显示成功消息
 */
function showSuccessMessage(message) {
    const successAlert = document.createElement('div');
    successAlert.className = 'alert alert-success alert-dismissible fade show';
    successAlert.innerHTML = `
        <i class="fas fa-check-circle me-2"></i>${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    const progress = document.querySelector('.upload-progress');
    progress.parentNode.insertBefore(successAlert, progress);
    
    // 3秒后自动隐藏成功提示
    setTimeout(() => {
        successAlert.remove();
    }, 3000);
}

/**
 * 初始化文件上传事件
 */
function initializeFileUploads() {
    // 处理图片上传
    const imageUpload = document.getElementById('image-upload');
    if (imageUpload) {
        imageUpload.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file || !handleFileUpload(file, 'image')) {
                e.target.value = '';
                return;
            }

            // 显示图片预览
            showImagePreview(file);
        });
    }

    // 处理封面图片上传
    const coverUpload = document.getElementById('cover-upload');
    if (coverUpload) {
        coverUpload.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file || !handleFileUpload(file, 'image')) {
                e.target.value = '';
                return;
            }

            uploadFile(file, 'cover')
                .then(data => {
                    // 设置封面图片URL到输入框
                    const coverImageInput = document.getElementById('cover_image');
                    coverImageInput.value = data.url;
                    
                    e.target.value = '';
                    showSuccessMessage('封面图片上传成功！');
                })
                .catch(error => {
                    alert('上传失败：' + error.message);
                    e.target.value = '';
                });
        });
    }

    // 处理附件上传
    const fileUpload = document.getElementById('file-upload');
    if (fileUpload) {
        fileUpload.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file || !handleFileUpload(file, 'file')) {
                e.target.value = '';
                return;
            }

            uploadFile(file, 'file')
                .then(data => {
                    // 在编辑器中插入附件链接
                    const fileUrl = data.url;
                    const fileName = data.filename || file.name;
                    const fileText = `[📎 ${fileName}](${fileUrl})`;
                    const pos = simplemde.codemirror.getCursor();
                    simplemde.codemirror.replaceRange(fileText + '\n', pos);
                    
                    e.target.value = '';
                    showSuccessMessage('附件上传成功！');
                })
                .catch(error => {
                    alert('上传失败：' + error.message);
                    e.target.value = '';
                });
        });
    }
}

/**
 * 初始化标签输入功能
 */
function initializeTagsInput() {
    const tagsInput = document.getElementById('tags');
    const tagSuggestions = document.getElementById('tag-suggestions');
    
    if (!tagsInput) return;

    // 获取现有标签用于自动完成
    let existingTags = [];
    
    // 从页面中的标签徽章获取现有标签
    document.querySelectorAll('.badge').forEach(badge => {
        const tagText = badge.textContent.trim();
        if (tagText && !existingTags.includes(tagText)) {
            existingTags.push(tagText);
        }
    });
    
    // 标签输入处理
    tagsInput.addEventListener('input', function() {
        const value = this.value;
        const lastCommaIndex = Math.max(value.lastIndexOf(','), value.lastIndexOf(' '));
        const currentTag = value.substring(lastCommaIndex + 1).trim().toLowerCase();
        
        if (currentTag.length > 0) {
            const suggestions = existingTags.filter(tag => 
                tag.toLowerCase().includes(currentTag) && 
                !value.toLowerCase().includes(tag.toLowerCase())
            );
            
            if (suggestions.length > 0) {
                tagSuggestions.innerHTML = suggestions.slice(0, 5).map(tag => 
                    `<span class="badge bg-light text-dark border me-1 mb-1" style="cursor: pointer;" onclick="addTag('${tag}')">${tag}</span>`
                ).join('');
            } else {
                tagSuggestions.innerHTML = '';
            }
        } else {
            tagSuggestions.innerHTML = '';
        }
    });
    
    // 标签输入框样式改进
    tagsInput.addEventListener('blur', function() {
        setTimeout(() => {
            tagSuggestions.innerHTML = '';
        }, 200);
    });
}

/**
 * 添加标签到输入框
 */
function addTag(tag) {
    const tagsInput = document.getElementById('tags');
    const tagSuggestions = document.getElementById('tag-suggestions');
    
    if (!tagsInput) return;

    const currentValue = tagsInput.value;
    const lastCommaIndex = Math.max(currentValue.lastIndexOf(','), currentValue.lastIndexOf(' '));
    
    if (lastCommaIndex >= 0) {
        tagsInput.value = currentValue.substring(0, lastCommaIndex + 1) + ' ' + tag + ', ';
    } else {
        tagsInput.value = tag + ', ';
    }
    
    tagSuggestions.innerHTML = '';
    tagsInput.focus();
}

/**
 * 删除文章确认
 */
function deletePost(id, commentsCount = 0) {
    let message = '确定要删除这篇文章吗？\n\n';
    
    if (commentsCount > 0) {
        message += `⚠️ 此文章有 ${commentsCount} 条评论，删除文章时会同时删除所有相关评论！\n\n`;
    }
    
    message += '注意：删除文章时会同时删除相关的评论和标签关联，此操作不可撤销！';
    
    if (confirm(message)) {
        window.location.href = `posts.php?action=delete&id=${id}`;
    }
}

/**
 * 批量操作功能
 */
function toggleSelectAll() {
    const selectAllCheckbox = document.getElementById('select-all');
    const postCheckboxes = document.querySelectorAll('.post-checkbox');
    
    postCheckboxes.forEach(checkbox => {
        checkbox.checked = selectAllCheckbox.checked;
    });
    
    updateSelection();
}

function updateSelection() {
    const selectedCheckboxes = document.querySelectorAll('.post-checkbox:checked');
    const selectAllCheckbox = document.getElementById('select-all');
    const toolbar = document.querySelector('.batch-actions-toolbar');
    const selectedCount = document.getElementById('selected-count');
    
    if (!toolbar || !selectedCount) return;

    // 更新选中数量
    selectedCount.textContent = selectedCheckboxes.length;
    
    // 显示或隐藏批量操作工具栏
    if (selectedCheckboxes.length > 0) {
        toolbar.style.display = 'block';
    } else {
        toolbar.style.display = 'none';
    }
    
    // 更新全选复选框状态
    const allCheckboxes = document.querySelectorAll('.post-checkbox');
    if (selectAllCheckbox) {
        if (selectedCheckboxes.length === allCheckboxes.length) {
            selectAllCheckbox.checked = true;
            selectAllCheckbox.indeterminate = false;
        } else if (selectedCheckboxes.length > 0) {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = true;
        } else {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = false;
        }
    }
}

function clearSelection() {
    const checkboxes = document.querySelectorAll('.post-checkbox, #select-all');
    checkboxes.forEach(checkbox => {
        checkbox.checked = false;
        checkbox.indeterminate = false;
    });
    updateSelection();
}

function batchAction(action) {
    const selectedCheckboxes = document.querySelectorAll('.post-checkbox:checked');
    const selectedIds = Array.from(selectedCheckboxes).map(checkbox => checkbox.value);
    
    if (selectedIds.length === 0) {
        alert('请先选择要操作的文章');
        return;
    }
    
    let confirmMessage = '';
    let actionName = '';
    
    switch (action) {
        case 'delete':
            // 计算选中文章的评论总数
            let totalComments = 0;
            selectedCheckboxes.forEach(checkbox => {
                const row = checkbox.closest('tr');
                const commentsCell = row.querySelector('td:nth-child(7)'); // 评论列
                if (commentsCell && window.getComputedStyle(commentsCell).display !== 'none') {
                    const commentsText = commentsCell.textContent.trim();
                    const commentsNum = parseInt(commentsText) || 0;
                    totalComments += commentsNum;
                }
            });
            
            confirmMessage = `确定要删除选中的 ${selectedIds.length} 篇文章吗？\n\n`;
            
            // 检查评论列是否可见
            const commentsHeader = document.querySelector('th:nth-child(7)');
            const commentsVisible = commentsHeader && window.getComputedStyle(commentsHeader).display !== 'none';
            
            if (commentsVisible && totalComments > 0) {
                confirmMessage += `⚠️ 这些文章共有 ${totalComments} 条评论，删除文章时会同时删除所有相关评论！\n\n`;
            } else if (!commentsVisible) {
                confirmMessage += `⚠️ 删除文章时会同时删除所有相关评论！\n\n`;
            }
            
            confirmMessage += '注意：删除文章时会同时删除相关的评论和标签关联，此操作不可撤销！';
            actionName = '删除';
            break;
        case 'offline':
            confirmMessage = `确定要下架选中的 ${selectedIds.length} 篇文章吗？下架后文章将不在前台显示。`;
            actionName = '下架';
            break;
        case 'publish':
            confirmMessage = `确定要发布选中的 ${selectedIds.length} 篇文章吗？`;
            actionName = '发布';
            break;
    }
    
    if (!confirm(confirmMessage)) {
        return;
    }
    
    // 创建并提交表单
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'posts.php';
    
    // 添加批量操作类型
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'batch_action';
    actionInput.value = action;
    form.appendChild(actionInput);
    
    // 添加选中的文章ID
    selectedIds.forEach(id => {
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'selected_posts[]';
        idInput.value = id;
        form.appendChild(idInput);
    });
    
    document.body.appendChild(form);
    form.submit();
}

/**
 * 初始化移动端侧边栏
 */
function initializeMobileSidebar() {
    const sidebar = document.querySelector('.sidebar');
    if (!sidebar) return;

    const toggleBtn = document.createElement('button');
    toggleBtn.className = 'btn btn-primary d-md-none position-fixed';
    toggleBtn.style.cssText = 'top: 1rem; right: 1rem; z-index: 1000;';
    toggleBtn.innerHTML = '<i class="fas fa-bars"></i>';
    document.body.appendChild(toggleBtn);

    toggleBtn.addEventListener('click', function() {
        sidebar.classList.toggle('active');
    });
}

/**
 * 初始化键盘快捷键
 */
function initializeKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        // Ctrl+A 全选
        if (e.ctrlKey && e.key === 'a' && e.target.tagName !== 'INPUT' && e.target.tagName !== 'TEXTAREA') {
            e.preventDefault();
            const selectAllCheckbox = document.getElementById('select-all');
            if (selectAllCheckbox) {
                selectAllCheckbox.checked = true;
                toggleSelectAll();
            }
        }
        
        // Escape 取消选择
        if (e.key === 'Escape') {
            clearSelection();
        }
    });
}

/**
 * 初始化页面离开确认
 */
function initializeBeforeUnload() {
    window.addEventListener('beforeunload', function(e) {
        const selectedCheckboxes = document.querySelectorAll('.post-checkbox:checked');
        if (selectedCheckboxes.length > 0) {
            e.preventDefault();
            e.returnValue = '您有已选择的文章，确定要离开页面吗？';
            return '您有已选择的文章，确定要离开页面吗？';
        }
    });
}

/**
 * 初始化表格行点击选择
 */
function initializeRowClickSelection() {
    document.querySelectorAll('tr').forEach(row => {
        const checkbox = row.querySelector('.post-checkbox');
        if (checkbox) {
            row.addEventListener('click', function(e) {
                // 如果点击的是链接、按钮或复选框本身，不触发行选择
                if (e.target.tagName === 'A' || e.target.tagName === 'BUTTON' || 
                    e.target.type === 'checkbox' || e.target.closest('a') || e.target.closest('button')) {
                    return;
                }
                
                // 切换复选框状态
                checkbox.checked = !checkbox.checked;
                updateSelection();
            });
            
            // 添加鼠标悬停效果
            row.addEventListener('mouseenter', function() {
                if (!checkbox.checked) {
                    this.style.backgroundColor = 'rgba(13, 110, 253, 0.02)';
                }
            });
            
            row.addEventListener('mouseleave', function() {
                if (!checkbox.checked) {
                    this.style.backgroundColor = '';
                }
            });
        }
    });
}

/**
 * 初始化Bootstrap工具提示
 */
function initializeTooltips() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

/**
 * 页面DOM加载完成后的初始化
 */
document.addEventListener('DOMContentLoaded', function() {
    // 获取编辑文章ID（如果有）
    const editPostId = document.querySelector('input[name="id"]')?.value || null;
    
    // 初始化各个功能模块
    initializeMarkdownEditor(editPostId);
    initializeFileUploads();
    initializeTagsInput();
    
    // 初始化批量操作状态（仅在有文章的情况下）
    if (document.querySelector('.post-checkbox')) {
        updateSelection();
    }
    
    // 初始化其他功能
    initializeMobileSidebar();
    initializeKeyboardShortcuts();
    initializeBeforeUnload();
    initializeRowClickSelection();
    initializeTooltips();
});

// 导出全局函数供HTML调用
window.deletePost = deletePost;
window.toggleSelectAll = toggleSelectAll;
window.updateSelection = updateSelection;
window.clearSelection = clearSelection;
window.batchAction = batchAction;
window.addTag = addTag;

/**
 * 显示代码模板对话框
 */
function showCodeTemplateDialog() {
    const templates = {
        'php': {
            name: 'PHP代码',
            code: `<?php
// PHP代码示例
function helloWorld() {
    echo "Hello, World!";
    return true;
}

// 使用示例
helloWorld();
?>`
        },
        'javascript': {
            name: 'JavaScript代码',
            code: `// JavaScript代码示例
function helloWorld() {
    console.log("Hello, World!");
    return true;
}

// 使用示例
helloWorld();`
        },
        'html': {
            name: 'HTML代码',
            code: `<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>示例页面</title>
</head>
<body>
    <h1>Hello, World!</h1>
    <p>这是一个HTML示例。</p>
</body>
</html>`
        },
        'css': {
            name: 'CSS代码',
            code: `/* CSS样式示例 */
.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.button {
    background-color: #007bff;
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}

.button:hover {
    background-color: #0056b3;
}`
        },
        'python': {
            name: 'Python代码',
            code: `# Python代码示例
def hello_world():
    """打印Hello World"""
    print("Hello, World!")
    return True

# 使用示例
if __name__ == "__main__":
    hello_world()`
        },
        'sql': {
            name: 'SQL代码',
            code: `-- SQL查询示例
SELECT 
    u.username,
    p.title,
    p.created_at
FROM users u
JOIN posts p ON u.id = p.author_id
WHERE p.status = 'publish'
ORDER BY p.created_at DESC
LIMIT 10;`
        },
        'bash': {
            name: 'Bash脚本',
            code: `#!/bin/bash
# Bash脚本示例

echo "Hello, World!"

# 检查文件是否存在
if [ -f "example.txt" ]; then
    echo "文件存在"
else
    echo "文件不存在"
fi`
        }
    };

    // 创建模态对话框
    const modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.id = 'codeTemplateModal';
    modal.innerHTML = `
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-file-code me-2"></i>代码模板
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="list-group">
                                ${Object.keys(templates).map(key => `
                                    <button class="list-group-item list-group-item-action" 
                                            onclick="selectCodeTemplate('${key}')">
                                        <i class="fas fa-code me-2"></i>${templates[key].name}
                                    </button>
                                `).join('')}
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label">代码预览</label>
                                <pre><code id="codePreview" class="language-markup"></code></pre>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">语言标识</label>
                                <input type="text" class="form-control" id="languageInput" 
                                       placeholder="例如：php, javascript, html, css, python, sql, bash">
                                <div class="form-text">指定代码块的语言，用于语法高亮</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="button" class="btn btn-primary" onclick="insertCodeTemplate()">
                        <i class="fas fa-plus me-2"></i>插入代码
                    </button>
                </div>
            </div>
        </div>
    `;

    document.body.appendChild(modal);
    
    // 显示模态对话框
    const modalInstance = new bootstrap.Modal(modal);
    modalInstance.show();
    
    // 默认选择第一个模板
    selectCodeTemplate('php');
    
    // 模态对话框关闭时移除DOM元素
    modal.addEventListener('hidden.bs.modal', function() {
        document.body.removeChild(modal);
    });
}

/**
 * 选择代码模板
 */
function selectCodeTemplate(templateKey) {
    const templates = {
        'php': { name: 'PHP代码', code: `<?php\n// PHP代码示例\nfunction helloWorld() {\n    echo "Hello, World!";\n    return true;\n}\n\n// 使用示例\nhelloWorld();\n?>` },
        'javascript': { name: 'JavaScript代码', code: `// JavaScript代码示例\nfunction helloWorld() {\n    console.log("Hello, World!");\n    return true;\n}\n\n// 使用示例\nhelloWorld();` },
        'html': { name: 'HTML代码', code: `<!DOCTYPE html>\n<html lang="zh-CN">\n<head>\n    <meta charset="UTF-8">\n    <meta name="viewport" content="width=device-width, initial-scale=1.0">\n    <title>示例页面</title>\n</head>\n<body>\n    <h1>Hello, World!</h1>\n    <p>这是一个HTML示例。</p>\n</body>\n</html>` },
        'css': { name: 'CSS代码', code: `/* CSS样式示例 */\n.container {\n    max-width: 1200px;\n    margin: 0 auto;\n    padding: 20px;\n}\n\n.button {\n    background-color: #007bff;\n    color: white;\n    padding: 10px 20px;\n    border: none;\n    border-radius: 4px;\n    cursor: pointer;\n}\n\n.button:hover {\n    background-color: #0056b3;\n}` },
        'python': { name: 'Python代码', code: `# Python代码示例\ndef hello_world():\n    """打印Hello World"""\n    print("Hello, World!")\n    return True\n\n# 使用示例\nif __name__ == "__main__":\n    hello_world()` },
        'sql': { name: 'SQL代码', code: `-- SQL查询示例\nSELECT \n    u.username,\n    p.title,\n    p.created_at\nFROM users u\nJOIN posts p ON u.id = p.author_id\nWHERE p.status = 'publish'\nORDER BY p.created_at DESC\nLIMIT 10;` },
        'bash': { name: 'Bash脚本', code: `#!/bin/bash\n# Bash脚本示例\n\necho "Hello, World!"\n\n# 检查文件是否存在\nif [ -f "example.txt" ]; then\n    echo "文件存在"\nelse\n    echo "文件不存在"\nfi` }
    };
    
    const template = templates[templateKey];
    if (template) {
        document.getElementById('codePreview').textContent = template.code;
        document.getElementById('languageInput').value = templateKey;
        
        // 更新选中状态
        document.querySelectorAll('.list-group-item').forEach(item => {
            item.classList.remove('active');
        });
        event.target.classList.add('active');
        
        // 应用语法高亮
        if (typeof Prism !== 'undefined') {
            Prism.highlightElement(document.getElementById('codePreview'));
        }
    }
}

/**
 * 插入代码模板
 */
function insertCodeTemplate() {
    const language = document.getElementById('languageInput').value.trim();
    const codePreview = document.getElementById('codePreview');
    const code = codePreview.textContent;
    
    if (simplemde) {
        const languagePrefix = language ? language : '';
        const codeBlock = `\`\`\`${languagePrefix}\n${code}\n\`\`\`\n\n`;
        
        // 在光标位置插入代码块
        const cursor = simplemde.codemirror.getCursor();
        simplemde.codemirror.replaceRange(codeBlock, cursor);
        
        // 关闭模态对话框
        const modal = bootstrap.Modal.getInstance(document.getElementById('codeTemplateModal'));
        modal.hide();
        
        // 显示成功消息
        showSuccessMessage('代码模板已插入到编辑器中');
    }
}

// 导出代码模板相关函数
window.showCodeTemplateDialog = showCodeTemplateDialog;
window.selectCodeTemplate = selectCodeTemplate;
window.insertCodeTemplate = insertCodeTemplate;

// 全局变量存储预览图片信息
let previewImageData = null;

/**
 * 显示图片预览
 */
function showImagePreview(file) {
    const reader = new FileReader();
    reader.onload = function(e) {
        const previewDiv = document.getElementById('image-preview');
        const previewImg = document.getElementById('preview-img');
        
        previewImg.src = e.target.result;
        previewDiv.style.display = 'block';
        
        // 存储文件信息
        previewImageData = {
            file: file,
            dataUrl: e.target.result
        };
        
        // 滚动到预览区域
        previewDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
    };
    reader.readAsDataURL(file);
}

/**
 * 插入预览图片
 */
function insertPreviewImage() {
    if (!previewImageData) return;
    
    const file = previewImageData.file;
    
    uploadFile(file, 'image')
        .then(data => {
            // 在编辑器中插入图片
            const imageUrl = data.url;
            const imageText = `![${file.name}](${imageUrl})`;
            const pos = simplemde.codemirror.getCursor();
            simplemde.codemirror.replaceRange(imageText + '\n', pos);
            
            // 立即刷新编辑器
            simplemde.codemirror.refresh();
            
            // 如果预览模式开启，强制刷新预览
            if (simplemde.isPreviewActive()) {
                simplemde.togglePreview();
                setTimeout(() => {
                    simplemde.togglePreview();
                }, 50);
            }
            
            // 隐藏预览区域
            cancelImagePreview();
            
            showSuccessMessage('图片上传成功！');
        })
        .catch(error => {
            alert('上传失败：' + error.message);
            cancelImagePreview();
        });
}

/**
 * 取消图片预览
 */
function cancelImagePreview() {
    const previewDiv = document.getElementById('image-preview');
    const imageUpload = document.getElementById('image-upload');
    
    previewDiv.style.display = 'none';
    imageUpload.value = '';
    previewImageData = null;
}

// 导出图片预览相关函数
window.showImagePreview = showImagePreview;
window.insertPreviewImage = insertPreviewImage;
window.cancelImagePreview = cancelImagePreview; 