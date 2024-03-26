<?php
/**
 * +----------------------------------------------------------------------
 * | think-addons [thinkphp6]
 * +----------------------------------------------------------------------
 *  .--,       .--,             | FILE: Addons.php
 * ( (  \.---./  ) )            | AUTHOR: byron
 *  '.__/o   o\__.'             | EMAIL: xiaobo.sun@qq.com
 *     {=  ^  =}                | QQ: 150093589
 *     /       \                | DATETIME: 2019/11/5 14:47
 *    //       \\               |
 *   //|   .   |\\              |
 *   "'\       /'"_.-~^`'-.     |
 *      \  _  /--'         `    |
 *    ___)( )(___               |-----------------------------------------
 *   (((__) (__)))              | 高山仰止,景行行止.虽不能至,心向往之。
 * +----------------------------------------------------------------------
 * | Copyright (c) 2019 http://www.zzstudio.net All rights reserved.
 * +----------------------------------------------------------------------
 */
declare(strict_types=1);

namespace think;

use think\App;
use think\helper\Str;
use think\facade\Config;
use think\facade\View;

abstract class Addons
{
    // app 容器
    protected $app;
    // 请求对象
    protected $request;
    // 当前插件标识
    protected $name;
    // 插件路径
    protected $addon_path;
    // 视图模型
    protected $view;
    // 插件配置
    protected $addon_config;
    // 插件信息
    protected $addon_info;

    /**
     * 插件构造函数
     * Addons constructor.
     * @param \think\App $app
     */
    public function __construct(App $app)
    {
        $this->app = $app;
        $this->request = $app->request;
        $this->name = $this->getName();
        $this->addon_path = $app->addons->getAddonsPath() . $this->name . DIRECTORY_SEPARATOR;
        $this->addon_config = "addon_{$this->name}_config";
        $this->addon_info = "addon_{$this->name}_info";
        $this->view = clone View::engine('Think');
        $this->view->config([
            'view_path' => $this->addon_path . 'view' . DIRECTORY_SEPARATOR
        ]);

        // 控制器初始化
        $this->initialize();
    }

    // 初始化
    protected function initialize()
    {}

    /**
     * 获取插件标识
     * @return mixed|null
     */
    final protected function getName()
    {
        $class = get_class($this);
        list(, $name, ) = explode('\\', $class);
        $this->request->addon = $name;

        return $name;
    }

    /**
     * 加载模板输出
     * @param string $template
     * @param array $vars           模板文件名
     * @return false|mixed|string   模板输出变量
     * @throws \think\Exception
     */
    protected function fetch($template = '', $vars = [])
    {
        return $this->view->fetch($template, $vars);
    }

    /**
     * 渲染内容输出
     * @access protected
     * @param  string $content 模板内容
     * @param  array  $vars    模板输出变量
     * @return mixed
     */
    protected function display($content = '', $vars = [])
    {
        return $this->view->display($content, $vars);
    }

    /**
     * 模板变量赋值
     * @access protected
     * @param  mixed $name  要显示的模板变量
     * @param  mixed $value 变量的值
     * @return $this
     */
    protected function assign($name, $value = '')
    {
        $this->view->assign([$name => $value]);

        return $this;
    }

    /**
     * 初始化模板引擎
     * @access protected
     * @param  array|string $engine 引擎参数
     * @return $this
     */
    protected function engine($engine)
    {
        $this->view->engine($engine);

        return $this;
    }

    /**
     * 插件基础信息
     * @return array
     */
    final public function getInfo()
    {
        $info = Config::get($this->addon_info, []);
        if ($info) {
            return $info;
        }

        // 文件属性
        $info = $this->info ?? [];
        // 文件配置
        $info_file = $this->addon_path . 'info.ini';
        if (is_file($info_file)) {
            $_info = parse_ini_file($info_file, true, INI_SCANNER_TYPED) ?: [];
            $_info['url'] = addons_url();
            $info = array_merge($info, $_info);
        }
        Config::set($info, $this->addon_info);

        return isset($info) ? $info : [];
    }

    /**
     * 设置插件信息数据
     * @param  array $data
     * @return array
     */
    final public function setInfo($data)
    {
        $info_file = $this->addon_path . 'info.ini';
        if (is_file($info_file)) {
            $info = parse_ini_file($info_file, true, INI_SCANNER_TYPED) ?: [];
        }else{
            $info = $this->info ?? [];
        }

        $info = array_merge($info, $data);
        $iniString = $this->arrayToIni($info);
        file_put_contents($info_file, $iniString);

        return isset($info) ? $info : [];
    }

    /**
     * 获取配置信息
     * @param bool $type 是否获取完整配置
     * @return array|mixed
     */
    final public function getConfig($type = false)
    {
        $config_file = $this->addon_path . 'config.json';
        if (is_file($config_file)) {
            $config = (array)json_decode(file_get_contents($config_file), true);
        }else{
            $config = [];
            $config_url = $this->addon_path . 'config.php';
            if (is_file($config_url)) {
                $config = (array)include $config_url;
                file_put_contents($config_file, json_encode($config, JSON_UNESCAPED_UNICODE));
            }
        }

        if($type) {
            return isset($config) ? $config : [];
        }

        $temp_arr = [];
        foreach ($config as $value) {
            $temp_arr[$value['name']] = $value['value'];
        }

        return $temp_arr;
    }

    /**
     * 数组转换ini字符串
     * @param $array 要转换的数组
     * @param $out
     * @return string
     */
    final public function arrayToIni($array, $out = "")
    {
        $t = "";
        $q = false;
        foreach ($array as $c => $d) {
            if (is_array($d)) $t .= $this->arrayToIni($d, $c);
            else {
                if ($c === intval($c)) {
                    if (!empty($out)) {
                        $t .= "\r\n" . $out . " = \"" . $d . "\"";
                        if ($q != 2) $q = true;
                    } else $t .= "\r\n" . $d;
                } else {
                    $t .= "\r\n" . $c . " = \"" . $d . "\"";
                    $q = 2;
                }
            }
        }
        if ($q != true && !empty($out)) return "[" . $out . "]\r\n" . $t;
        if (!empty($out)) return $t;
        return trim($t);
    }

    /**
     * 配置插件信息
     * @param array $data 是否获取完整配置
     */
    final public function setConfig($data)
    {
        $config_file = $this->addon_path . 'config.json';
        if (is_file($config_file)) {
            $config = (array) json_decode(file_get_contents($config_file), true);
        }else{
            $config = [];
            $config_url = $this->addon_path . 'config.php';
            if (is_file($config_url)) {
                $config = (array)include $config_url;
            }
        }
        $count = count($config);
        for($k=0;$k<$count;$k++) {
            if(isset($data[$config[$k]['name']])) {
                $config[$k]['value'] = $data[$config[$k]['name']];
            }
        }
        file_put_contents($config_file, json_encode($config, JSON_UNESCAPED_UNICODE));

        return isset($config) ? $config : [];
    }

    //必须实现安装
    abstract public function install();

    //必须卸载插件方法
    abstract public function uninstall();

    //必须升级插件方法
    abstract public function upgrade();

    //必须启用插件方法
    abstract public function enable();

    //必须禁用插件方法
    abstract public function disable();
}
