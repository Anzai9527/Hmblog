<?php
class Template {
    private $variables = [];
    private $template_dir;
    
    public function __construct($template_dir = 'templates/frontend/') {
        $this->template_dir = rtrim($template_dir, '/') . '/';
    }
    
    /**
     * 设置模板变量
     * @param string|array $key 变量名或关联数组
     * @param mixed $value 变量值
     */
    public function assign($key, $value = null) {
        if (is_array($key)) {
            $this->variables = array_merge($this->variables, $key);
        } else {
            $this->variables[$key] = $value;
        }
    }
    
    /**
     * 渲染模板
     * @param string $template_file 模板文件名
     * @return string
     */
    public function render($template_file) {
        $template_path = $this->template_dir . $template_file;
        
        if (!file_exists($template_path)) {
            throw new Exception("模板文件不存在: {$template_path}");
        }
        
        // 将变量导入到当前作用域
        extract($this->variables);
        
        // 启动输出缓冲
        ob_start();
        
        try {
            // 包含模板文件
            include $template_path;
            
            // 获取输出缓冲内容
            $content = ob_get_clean();
            
            return $content;
            
        } catch (Exception $e) {
            // 清理输出缓冲
            ob_end_clean();
            throw $e;
        }
    }
    
    /**
     * 设置模板目录
     * @param string $dir 目录路径
     */
    public function setTemplateDir($dir) {
        $this->template_dir = rtrim($dir, '/') . '/';
    }
    
    /**
     * 获取已分配的变量
     * @param string $key 变量名
     * @return mixed|null
     */
    public function getVariable($key) {
        return isset($this->variables[$key]) ? $this->variables[$key] : null;
    }
    
    /**
     * 清除所有已分配的变量
     */
    public function clearVariables() {
        $this->variables = [];
    }
} 