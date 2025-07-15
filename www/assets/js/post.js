/**
 * 文章详情页 JavaScript 功能
 */

// 页面加载完成后执行
document.addEventListener('DOMContentLoaded', function() {
    // 初始化所有功能
    initResourcePreload();
    initImageLazyLoad();
    initCodeHighlight();
    initScrollProgress();
    initReadingTime();
    initTableOfContents();
    initImageZoom();
    initCopyCode();
    initScrollToTop();
    initSmoothScroll();
});

/**
 * 预加载重要资源
 */
function initResourcePreload() {
    const preloadLinks = [
            '/assets/css/style.css',
    '/assets/css/post.css',
    '/assets/images/default-avatar.png'
    ];
    
    preloadLinks.forEach(href => {
        const link = document.createElement('link');
        link.rel = 'preload';
        link.as = href.endsWith('.css') ? 'style' : 'image';
        link.href = href;
        document.head.appendChild(link);
    });
}

/**
 * 图片懒加载
 */
function initImageLazyLoad() {
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    if (img.dataset.src) {
                        img.src = img.dataset.src;
                        img.classList.remove('lazy');
                        img.classList.add('loaded');
                        observer.unobserve(img);
                    }
                }
            });
        }, {
            rootMargin: '50px 0px',
            threshold: 0.01
        });
        
        document.querySelectorAll('img[data-src]').forEach(img => {
            imageObserver.observe(img);
        });
    }
}

/**
 * 代码高亮初始化
 */
function initCodeHighlight() {
    // 为代码块添加语言标签
    document.querySelectorAll('pre code').forEach(block => {
        // 检测语言
        const language = detectLanguage(block.textContent);
        if (language) {
            block.className = `language-${language}`;
        }
        
        // 添加复制按钮
        const pre = block.parentElement;
        if (pre && pre.tagName === 'PRE') {
            const copyBtn = document.createElement('button');
            copyBtn.className = 'copy-code-btn';
            copyBtn.textContent = '复制';
            copyBtn.onclick = () => copyCodeToClipboard(block);
            pre.appendChild(copyBtn);
        }
    });
}

/**
 * 检测代码语言
 */
function detectLanguage(code) {
    const patterns = {
        'javascript': /\b(function|var|let|const|if|else|for|while|return|console\.log)\b/,
        'php': /\<\?php|\$[a-zA-Z_]/,
        'python': /\b(def|import|from|if|else|for|while|class|print)\b/,
        'css': /\{[^}]*\}/,
        'html': /\<[^>]*\>/,
        'sql': /\b(SELECT|FROM|WHERE|INSERT|UPDATE|DELETE|CREATE|TABLE)\b/i,
        'json': /^\s*[\{\[]/
    };
    
    for (const [lang, pattern] of Object.entries(patterns)) {
        if (pattern.test(code)) {
            return lang;
        }
    }
    return null;
}

/**
 * 复制代码到剪贴板
 */
function copyCodeToClipboard(codeBlock) {
    const text = codeBlock.textContent;
    const button = codeBlock.parentElement.querySelector('.copy-code-btn');
    
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(() => {
            showCopySuccess(button);
        }).catch(() => {
            fallbackCopyText(text, button);
        });
    } else {
        fallbackCopyText(text, button);
    }
}

/**
 * 兼容性复制文本
 */
function fallbackCopyText(text, button) {
    const textArea = document.createElement('textarea');
    textArea.value = text;
    textArea.style.position = 'fixed';
    textArea.style.opacity = '0';
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    
    try {
        document.execCommand('copy');
        showCopySuccess(button);
    } catch (err) {
        console.error('复制失败:', err);
    }
    
    document.body.removeChild(textArea);
}

/**
 * 显示复制成功提示
 */
function showCopySuccess(button) {
    const originalText = button.textContent;
    button.textContent = '已复制!';
    button.style.background = '#28a745';
    
    setTimeout(() => {
        button.textContent = originalText;
        button.style.background = '';
    }, 2000);
}

/**
 * 阅读进度条
 */
function initScrollProgress() {
    const progressBar = document.createElement('div');
    progressBar.id = 'reading-progress';
    progressBar.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 0%;
        height: 3px;
        background: linear-gradient(90deg, #007bff, #28a745);
        z-index: 9999;
        transition: width 0.3s ease;
    `;
    document.body.appendChild(progressBar);
    
    window.addEventListener('scroll', () => {
        const scrollTop = window.pageYOffset;
        const docHeight = document.documentElement.scrollHeight - window.innerHeight;
        const scrollPercent = (scrollTop / docHeight) * 100;
        progressBar.style.width = scrollPercent + '%';
    });
}

/**
 * 估算阅读时间
 */
function initReadingTime() {
    const content = document.querySelector('.post-detail-content');
    if (!content) return;
    
    const text = content.textContent;
    const wordsPerMinute = 200; // 平均阅读速度
    const words = text.trim().split(/\s+/).length;
    const readingTime = Math.ceil(words / wordsPerMinute);
    
    // 添加阅读时间显示
    const readingTimeElement = document.createElement('span');
    readingTimeElement.className = 'reading-time';
    readingTimeElement.innerHTML = `📖 约${readingTime}分钟阅读`;
    readingTimeElement.style.cssText = `
        background: rgba(0, 123, 255, 0.1);
        color: #007bff;
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 12px;
        margin-left: 10px;
    `;
    
    const postMeta = document.querySelector('.post-detail-meta');
    if (postMeta) {
        postMeta.appendChild(readingTimeElement);
    }
}

/**
 * 生成文章目录
 */
function initTableOfContents() {
    const content = document.querySelector('.post-detail-content');
    if (!content) return;
    
    const headings = content.querySelectorAll('h1, h2, h3, h4, h5, h6');
    if (headings.length < 3) return; // 少于3个标题不生成目录
    
    const toc = document.createElement('div');
    toc.id = 'table-of-contents';
    toc.style.cssText = `
        position: fixed;
        right: 20px;
        top: 50%;
        transform: translateY(-50%);
        background: #fff;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        padding: 15px;
        max-width: 200px;
        max-height: 400px;
        overflow-y: auto;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        z-index: 1000;
        font-size: 14px;
        line-height: 1.4;
        display: none;
    `;
    
    const tocTitle = document.createElement('h4');
    tocTitle.textContent = '目录';
    tocTitle.style.cssText = `
        margin: 0 0 10px 0;
        font-size: 16px;
        color: #2c3e50;
        border-bottom: 1px solid #e9ecef;
        padding-bottom: 8px;
    `;
    toc.appendChild(tocTitle);
    
    const tocList = document.createElement('ul');
    tocList.style.cssText = `
        margin: 0;
        padding: 0;
        list-style: none;
    `;
    
    headings.forEach((heading, index) => {
        const id = `heading-${index}`;
        heading.id = id;
        
        const li = document.createElement('li');
        li.style.cssText = `
            margin-bottom: 5px;
            padding-left: ${(parseInt(heading.tagName.charAt(1)) - 1) * 10}px;
        `;
        
        const a = document.createElement('a');
        a.href = `#${id}`;
        a.textContent = heading.textContent;
        a.style.cssText = `
            color: #6c757d;
            text-decoration: none;
            font-size: 12px;
            display: block;
            padding: 2px 0;
            border-radius: 4px;
            transition: all 0.3s ease;
        `;
        
        a.addEventListener('click', (e) => {
            e.preventDefault();
            heading.scrollIntoView({ behavior: 'smooth' });
        });
        
        li.appendChild(a);
        tocList.appendChild(li);
    });
    
    toc.appendChild(tocList);
    document.body.appendChild(toc);
    
    // 显示/隐藏目录
    let tocVisible = false;
    window.addEventListener('scroll', () => {
        const scrollTop = window.pageYOffset;
        if (scrollTop > 300 && !tocVisible) {
            toc.style.display = 'block';
            tocVisible = true;
        } else if (scrollTop <= 300 && tocVisible) {
            toc.style.display = 'none';
            tocVisible = false;
        }
    });
}

/**
 * 图片点击放大
 */
function initImageZoom() {
    const images = document.querySelectorAll('.post-detail-content img');
    
    images.forEach(img => {
        img.style.cursor = 'zoom-in';
        img.addEventListener('click', () => {
            showImageModal(img);
        });
    });
}

/**
 * 显示图片模态框
 */
function showImageModal(img) {
    const modal = document.createElement('div');
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.8);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 10000;
        cursor: zoom-out;
    `;
    
    const modalImg = document.createElement('img');
    modalImg.src = img.src;
    modalImg.style.cssText = `
        max-width: 90%;
        max-height: 90%;
        object-fit: contain;
        border-radius: 8px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
    `;
    
    modal.appendChild(modalImg);
    document.body.appendChild(modal);
    
    // 关闭模态框
    modal.addEventListener('click', () => {
        document.body.removeChild(modal);
    });
    
    // ESC键关闭
    document.addEventListener('keydown', function escHandler(e) {
        if (e.key === 'Escape') {
            if (document.body.contains(modal)) {
                document.body.removeChild(modal);
            }
            document.removeEventListener('keydown', escHandler);
        }
    });
}

/**
 * 代码复制功能
 */
function initCopyCode() {
    const style = document.createElement('style');
    style.textContent = `
        .copy-code-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #007bff;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
            z-index: 10;
            transition: background 0.3s ease;
        }
        
        .copy-code-btn:hover {
            background: #0056b3;
        }
        
        pre {
            position: relative;
        }
        
        .copy-code-btn:active {
            transform: scale(0.95);
        }
    `;
    document.head.appendChild(style);
}

/**
 * 返回顶部按钮
 */
function initScrollToTop() {
    const scrollToTopBtn = document.createElement('button');
    scrollToTopBtn.id = 'scroll-to-top';
    scrollToTopBtn.innerHTML = '↑';
    scrollToTopBtn.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        width: 50px;
        height: 50px;
        background: #007bff;
        color: white;
        border: none;
        border-radius: 50%;
        font-size: 20px;
        cursor: pointer;
        z-index: 1000;
        display: none;
        transition: all 0.3s ease;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    `;
    
    document.body.appendChild(scrollToTopBtn);
    
    // 显示/隐藏按钮
    window.addEventListener('scroll', () => {
        if (window.pageYOffset > 300) {
            scrollToTopBtn.style.display = 'block';
        } else {
            scrollToTopBtn.style.display = 'none';
        }
    });
    
    // 点击返回顶部
    scrollToTopBtn.addEventListener('click', () => {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });
    
    // 悬停效果
    scrollToTopBtn.addEventListener('mouseenter', () => {
        scrollToTopBtn.style.background = '#0056b3';
        scrollToTopBtn.style.transform = 'scale(1.1)';
    });
    
    scrollToTopBtn.addEventListener('mouseleave', () => {
        scrollToTopBtn.style.background = '#007bff';
        scrollToTopBtn.style.transform = 'scale(1)';
    });
}

/**
 * 平滑滚动
 */
function initSmoothScroll() {
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
}

/**
 * 图片加载失败处理
 */
function initImageErrorHandling() {
    document.querySelectorAll('img').forEach(img => {
        img.addEventListener('error', function() {
            this.style.display = 'none';
            
            // 创建替代元素
            const placeholder = document.createElement('div');
            placeholder.style.cssText = `
                width: 100%;
                height: 200px;
                background: #f8f9fa;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #6c757d;
                font-size: 14px;
                border-radius: 8px;
                border: 1px dashed #dee2e6;
            `;
            placeholder.textContent = '图片加载失败';
            
            this.parentNode.insertBefore(placeholder, this);
        });
    });
}

// 页面卸载时的清理
window.addEventListener('beforeunload', function() {
    // 清理定时器和事件监听器
    document.querySelectorAll('#reading-progress, #table-of-contents, #scroll-to-top').forEach(el => {
        if (el && el.parentNode) {
            el.parentNode.removeChild(el);
        }
    });
});

// 错误处理
window.addEventListener('error', function(e) {
    console.error('页面错误:', e.error);
});

// 初始化图片错误处理
document.addEventListener('DOMContentLoaded', function() {
    initImageErrorHandling();
}); 