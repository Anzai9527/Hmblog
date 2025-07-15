// 管理后台通用JavaScript

// 移动端菜单功能
class MobileMenu {
    constructor() {
        this.mobileMenuToggle = document.getElementById('mobileMenuToggle');
        this.sidebar = document.getElementById('sidebar');
        this.init();
    }

    init() {
        if (this.mobileMenuToggle && this.sidebar) {
            this.bindEvents();
        }
    }

    bindEvents() {
        // 菜单切换
        this.mobileMenuToggle.addEventListener('click', () => {
            this.toggleMenu();
        });

        // 点击外部关闭菜单
        document.addEventListener('click', (event) => {
            if (!this.sidebar.contains(event.target) && !this.mobileMenuToggle.contains(event.target)) {
                this.closeMenu();
            }
        });

        // 窗口大小改变时重置菜单状态
        window.addEventListener('resize', () => {
            if (window.innerWidth > 992) {
                this.closeMenu();
            }
        });

        // ESC键关闭菜单
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                this.closeMenu();
            }
        });
    }

    toggleMenu() {
        this.sidebar.classList.toggle('active');
        this.mobileMenuToggle.classList.toggle('active');
        
        // 防止背景滚动
        if (this.sidebar.classList.contains('active')) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = '';
        }
    }

    closeMenu() {
        this.sidebar.classList.remove('active');
        this.mobileMenuToggle.classList.remove('active');
        document.body.style.overflow = '';
    }
}

// 表格响应式处理
class ResponsiveTable {
    constructor() {
        this.tables = document.querySelectorAll('.table-responsive');
        this.init();
    }

    init() {
        this.tables.forEach(table => {
            this.checkTableOverflow(table);
        });

        // 监听窗口大小变化
        window.addEventListener('resize', () => {
            this.tables.forEach(table => {
                this.checkTableOverflow(table);
            });
        });
    }

    checkTableOverflow(table) {
        const tableElement = table.querySelector('table');
        if (tableElement && tableElement.scrollWidth > table.clientWidth) {
            table.style.overflowX = 'auto';
                } else {
            table.style.overflowX = 'hidden';
        }
    }
}

// 表单验证
class FormValidator {
    constructor(formSelector) {
        this.form = document.querySelector(formSelector);
        if (this.form) {
            this.init();
        }
    }

    init() {
        this.form.addEventListener('submit', (event) => {
            if (!this.validateForm()) {
                event.preventDefault();
            }
        });
    }

    validateForm() {
        let isValid = true;
        const requiredFields = this.form.querySelectorAll('[required]');
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                this.showError(field, '此字段为必填项');
                isValid = false;
            } else {
                this.clearError(field);
            }
        });

        return isValid;
    }

    showError(field, message) {
        this.clearError(field);
        
        const errorDiv = document.createElement('div');
        errorDiv.className = 'invalid-feedback';
        errorDiv.textContent = message;
        
        field.classList.add('is-invalid');
        field.parentNode.appendChild(errorDiv);
    }

    clearError(field) {
        field.classList.remove('is-invalid');
        const errorDiv = field.parentNode.querySelector('.invalid-feedback');
        if (errorDiv) {
            errorDiv.remove();
        }
    }
}

// 确认对话框
class ConfirmDialog {
    static show(message, callback) {
        if (confirm(message)) {
            if (typeof callback === 'function') {
                callback();
            }
        }
    }
}

// 消息提示
class Toast {
    static show(message, type = 'info', duration = 3000) {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `
            <div class="toast-content">
                <i class="fas fa-${this.getIcon(type)}"></i>
                <span>${message}</span>
            </div>
        `;
        
        document.body.appendChild(toast);
        
        // 显示动画
        setTimeout(() => {
            toast.classList.add('show');
        }, 100);
        
        // 自动隐藏
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => {
                document.body.removeChild(toast);
            }, 300);
        }, duration);
    }

    static getIcon(type) {
        const icons = {
            success: 'check-circle',
            error: 'exclamation-circle',
            warning: 'exclamation-triangle',
            info: 'info-circle'
        };
        return icons[type] || 'info-circle';
    }
}

// 图片预览
class ImagePreview {
    constructor() {
        this.init();
    }

    init() {
        document.addEventListener('click', (event) => {
            if (event.target.matches('.image-preview')) {
                this.showPreview(event.target.src, event.target.alt);
            }
        });
    }
    
    showPreview(src, alt) {
        const overlay = document.createElement('div');
        overlay.className = 'image-preview-overlay';
        overlay.innerHTML = `
            <div class="image-preview-container">
                <img src="${src}" alt="${alt}" class="image-preview-img">
                <button class="image-preview-close">&times;</button>
            </div>
        `;
        
        document.body.appendChild(overlay);
        
        // 关闭预览
        overlay.addEventListener('click', (event) => {
            if (event.target === overlay || event.target.classList.contains('image-preview-close')) {
                document.body.removeChild(overlay);
            }
        });
        
        // ESC键关闭
        const handleKeydown = (event) => {
            if (event.key === 'Escape') {
                document.body.removeChild(overlay);
                document.removeEventListener('keydown', handleKeydown);
            }
        };
        document.addEventListener('keydown', handleKeydown);
    }
}

// 数据表格增强
class DataTable {
    constructor(tableSelector, options = {}) {
        this.table = document.querySelector(tableSelector);
        this.options = {
            searchable: true,
            sortable: true,
            pagination: true,
            pageSize: 10,
            ...options
        };
        
        if (this.table) {
            this.init();
        }
    }

    init() {
        if (this.options.searchable) {
            this.addSearch();
        }
        
        if (this.options.sortable) {
            this.addSorting();
        }
        
        if (this.options.pagination) {
            this.addPagination();
        }
    }

    addSearch() {
        const searchContainer = document.createElement('div');
        searchContainer.className = 'table-search mb-3';
        searchContainer.innerHTML = `
            <input type="text" class="form-control" placeholder="搜索..." id="tableSearch">
        `;
        
        this.table.parentNode.insertBefore(searchContainer, this.table);
        
        const searchInput = document.getElementById('tableSearch');
        searchInput.addEventListener('input', (event) => {
            this.filterTable(event.target.value);
        });
    }

    filterTable(searchTerm) {
        const rows = this.table.querySelectorAll('tbody tr');
        const term = searchTerm.toLowerCase();
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(term) ? '' : 'none';
        });
    }

    addSorting() {
        const headers = this.table.querySelectorAll('th[data-sortable]');
        headers.forEach(header => {
            header.style.cursor = 'pointer';
            header.addEventListener('click', () => {
                this.sortTable(header);
            });
        });
    }

    sortTable(header) {
        const column = Array.from(header.parentNode.children).indexOf(header);
        const rows = Array.from(this.table.querySelectorAll('tbody tr'));
        const isAscending = header.classList.contains('sort-asc');
        
        rows.sort((a, b) => {
            const aValue = a.children[column].textContent;
            const bValue = b.children[column].textContent;
            
            if (isAscending) {
                return bValue.localeCompare(aValue);
            } else {
                return aValue.localeCompare(bValue);
            }
        });
        
        // 更新排序状态
        this.table.querySelectorAll('th').forEach(th => {
            th.classList.remove('sort-asc', 'sort-desc');
        });
        
        header.classList.add(isAscending ? 'sort-desc' : 'sort-asc');
        
        // 重新插入排序后的行
        const tbody = this.table.querySelector('tbody');
        rows.forEach(row => tbody.appendChild(row));
    }

    addPagination() {
        // 分页功能实现
        // 这里可以根据需要添加分页逻辑
    }
}

// 文件上传预览
class FileUpload {
    constructor(inputSelector, previewSelector) {
        this.input = document.querySelector(inputSelector);
        this.preview = document.querySelector(previewSelector);
        
        if (this.input && this.preview) {
            this.init();
        }
    }

    init() {
        this.input.addEventListener('change', (event) => {
            const file = event.target.files[0];
            if (file) {
                this.showPreview(file);
            }
        });
    }

    showPreview(file) {
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = (event) => {
                this.preview.innerHTML = `
                    <img src="${event.target.result}" alt="预览" class="img-fluid">
                    <div class="file-info">
                        <small>${file.name} (${this.formatFileSize(file.size)})</small>
                    </div>
                `;
            };
            reader.readAsDataURL(file);
        } else {
            this.preview.innerHTML = `
                <div class="file-info">
                    <i class="fas fa-file"></i>
                    <span>${file.name} (${this.formatFileSize(file.size)})</span>
                </div>
            `;
        }
    }

    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
}

// 工具函数
const Utils = {
    // 防抖函数
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },

    // 节流函数
    throttle(func, limit) {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    },

    // 格式化日期
    formatDate(date, format = 'YYYY-MM-DD') {
        const d = new Date(date);
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        const hours = String(d.getHours()).padStart(2, '0');
        const minutes = String(d.getMinutes()).padStart(2, '0');
        const seconds = String(d.getSeconds()).padStart(2, '0');

        return format
            .replace('YYYY', year)
            .replace('MM', month)
            .replace('DD', day)
            .replace('HH', hours)
            .replace('mm', minutes)
            .replace('ss', seconds);
    },

    // 复制到剪贴板
    copyToClipboard(text) {
        if (navigator.clipboard) {
            return navigator.clipboard.writeText(text);
        } else {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            return Promise.resolve();
        }
    }
};

// 分类管理函数
function editCategory(category) {
    // 填充表单数据
    document.querySelector('input[name="category_action"]').value = 'edit';
    document.querySelector('input[name="category_id"]').value = category.id;
    document.querySelector('input[name="name"]').value = category.name;
    document.querySelector('input[name="slug"]').value = category.slug;
    document.querySelector('textarea[name="description"]').value = category.description;
    
    // 更新模态框标题
    document.querySelector('#categoryModal .modal-title span').textContent = '编辑分类';
    
    // 显示模态框
    const modal = new bootstrap.Modal(document.getElementById('categoryModal'));
    modal.show();
}

function deleteCategory(categoryId) {
    ConfirmDialog.show('确定要删除这个分类吗？删除后无法恢复。', function() {
        // 创建表单并提交
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="category_action" value="delete">
            <input type="hidden" name="category_id" value="${categoryId}">
        `;
        document.body.appendChild(form);
        form.submit();
    });
}

// 重置分类表单
function resetCategoryForm() {
    document.querySelector('input[name="category_action"]').value = 'add';
    document.querySelector('input[name="category_id"]').value = '';
    document.querySelector('input[name="name"]').value = '';
    document.querySelector('input[name="slug"]').value = '';
    document.querySelector('textarea[name="description"]').value = '';
    document.querySelector('#categoryModal .modal-title span').textContent = '添加分类';
}

// 初始化
document.addEventListener('DOMContentLoaded', function() {
    // 初始化移动端菜单
    new MobileMenu();
    
    // 初始化响应式表格
    new ResponsiveTable();
    
    // 初始化图片预览
    new ImagePreview();
    
    // 触摸设备优化
    if ('ontouchstart' in window) {
        document.body.classList.add('touch-device');
    }
    
    // 减少动画偏好
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        document.body.classList.add('reduced-motion');
    }
    
    // 深色模式支持
    if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
        document.body.classList.add('dark-mode');
    }
    
    // 分类模态框事件监听
    const categoryModal = document.getElementById('categoryModal');
    if (categoryModal) {
        categoryModal.addEventListener('hidden.bs.modal', function() {
            resetCategoryForm();
        });
    }
});

// 导出到全局
window.AdminJS = {
    MobileMenu,
    ResponsiveTable,
    FormValidator,
    ConfirmDialog,
    Toast,
    ImagePreview,
    DataTable,
    FileUpload,
    Utils
}; 