// 认证页面通用JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // 表单提交处理
    const authForm = document.getElementById('loginForm') || document.getElementById('registerForm');
    if (authForm) {
        authForm.addEventListener('submit', function(e) {
            const btn = this.querySelector('.btn-auth');
            const btnText = btn.querySelector('.btn-text');
            const spinner = btn.querySelector('.loading-spinner');
            
            // 显示加载状态
            btnText.style.opacity = '0';
            spinner.style.display = 'block';
            btn.disabled = true;
            
            // 添加加载动画
            btn.style.transform = 'translateY(-2px)';
        });
    }
    
    // 输入框交互效果
    document.querySelectorAll('.form-control').forEach(input => {
        input.addEventListener('focus', function() {
            this.parentElement.classList.add('focused');
        });
        
        input.addEventListener('blur', function() {
            this.parentElement.classList.remove('focused');
        });
        
        // 实时验证
        input.addEventListener('input', function() {
            if (this.value.trim() !== '') {
                this.parentElement.style.borderColor = 'rgba(255, 255, 255, 0.5)';
            } else {
                this.parentElement.style.borderColor = 'rgba(255, 255, 255, 0.2)';
            }
        });
    });
    
    // 注册页面特殊功能
    const passwordInput = document.querySelector('input[name="password"]');
    const confirmPasswordInput = document.querySelector('input[name="confirm_password"]');
    
    if (passwordInput && confirmPasswordInput) {
        function validatePasswords() {
            if (confirmPasswordInput.value && passwordInput.value !== confirmPasswordInput.value) {
                confirmPasswordInput.parentElement.style.borderColor = 'rgba(255, 107, 107, 0.8)';
            } else if (confirmPasswordInput.value) {
                confirmPasswordInput.parentElement.style.borderColor = 'rgba(76, 175, 80, 0.8)';
            }
        }
        
        passwordInput.addEventListener('input', validatePasswords);
        confirmPasswordInput.addEventListener('input', validatePasswords);
    }
    
    // 键盘快捷键
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            const authForm = document.getElementById('loginForm') || document.getElementById('registerForm');
            if (authForm) {
                authForm.submit();
            }
        }
    });
    
    // 页面加载动画
    window.addEventListener('load', function() {
        document.body.style.opacity = '0';
        document.body.style.transition = 'opacity 1s ease';
        
        setTimeout(() => {
            document.body.style.opacity = '1';
        }, 100);
    });
    
    // 鼠标移动视差效果
    document.addEventListener('mousemove', function(e) {
        const particles = document.querySelectorAll('.particle');
        const x = e.clientX / window.innerWidth;
        const y = e.clientY / window.innerHeight;
        
        particles.forEach((particle, index) => {
            const speed = (index + 1) * 0.8;
            const xOffset = (x - 0.5) * speed;
            const yOffset = (y - 0.5) * speed;
            
            particle.style.transform = `translate(${xOffset}px, ${yOffset}px)`;
        });
    });
    
    // 添加粒子生成效果
    function createParticle() {
        const particle = document.createElement('div');
        particle.className = 'particle';
        particle.style.left = Math.random() * 100 + '%';
        particle.style.animationDuration = (Math.random() * 20 + 20) + 's';
        particle.style.animationDelay = Math.random() * 10 + 's';
        
        const particlesContainer = document.querySelector('.cosmic-particles');
        if (particlesContainer) {
            particlesContainer.appendChild(particle);
            
            setTimeout(() => {
                particle.remove();
            }, 30000);
        }
    }
    
    // 定期生成新粒子
    setInterval(createParticle, 3000);
}); 