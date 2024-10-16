<?php
declare(strict_types=1);

use think\facade\Event;
use think\facade\Route;
use think\Response;
use think\helper\{
    Str, Arr
};

\think\Console::starting(function (\think\Console $console) {
    $console->addCommands([
        'addons:config' => '\\think\\addons\\command\\SendConfig'
    ]);
});

// 插件类库自动载入
spl_autoload_register(function ($class) {

    $class = ltrim($class, '\\');

    $dir = app()->getRootPath();
    $namespace = 'addons';

    if (strpos($class, $namespace) === 0) {
        $class = substr($class, strlen($namespace));
        $path = '';
        if (($pos = strripos($class, '\\')) !== false) {
            $path = str_replace('\\', '/', substr($class, 0, $pos)) . '/';
            $class = substr($class, $pos + 1);
        }
        $path .= str_replace('_', '/', $class) . '.php';
        $dir .= $namespace . $path;

        if (file_exists($dir)) {
            include $dir;
            return true;
        }

        return false;
    }

    return false;

});

if (!function_exists('get_addons_info')) {
    /**
     * 读取插件的基础信息
     * @param string $name 插件名
     * @return array
     */
    function get_addons_info($name)
    {
        $addon = get_addons_instance($name);
        if (!$addon) {
            return [];
        }

        return $addon->getInfo();
    }
}

if (!function_exists('set_addons_info')) {
    /**
     * 设置基础配置信息
     * @param string $name 插件名
     * @param array $info  配置信息
     * @return array
     */
    function set_addons_info($name, $info)
    {
        $addon = get_addons_instance($name);
        if (!$addon) {
            return [];
        }
        return $addon->setInfo($info);
    }
}

if (!function_exists('get_addons_instance')) {
    /**
     * 获取插件的单例
     * @param string $name 插件名
     * @return mixed|null
     */
    function get_addons_instance($name)
    {
        static $_addons = [];
        if (isset($_addons[$name])) {
            return $_addons[$name];
        }
        $class = get_addons_class($name);
        if (class_exists($class)) {
            $_addons[$name] = new $class(app());

            return $_addons[$name];
        } else {
            return null;
        }
    }
}

if (!function_exists('get_addons_class')) {
    /**
     * 获取插件类的类名
     * @param string $name 插件名
     * @param string $type 返回命名空间类型
     * @param string $class 当前类名
     * @return string
     */
    function get_addons_class($name, $type = 'hook', $class = null)
    {
        $name = trim($name);
        // 处理多级控制器情况
        if (!is_null($class) && strpos($class, '.')) {
            $class = explode('.', $class);

            $class[count($class) - 1] = Str::studly(end($class));
            $class = implode('\\', $class);
        } else {
            $class = Str::studly(is_null($class) ? $name : $class);
        }
        switch ($type) {
            case 'controller':
                $namespace = '\\addons\\' . $name . '\\controller\\' . $class;
                break;
            default:
                $namespace = '\\addons\\' . $name . '\\Plugin';
        }

        return class_exists($namespace) ? $namespace : '';
    }
}

if (!function_exists('addons_url')) {
    /**
     * 插件显示内容里生成访问插件的url
     * @param $url
     * @param array $param
     * @param bool|string $suffix 生成的URL后缀
     * @param bool|string $domain 域名
     * @return bool|string
     */
    function addons_url($url = '', $param = [], $suffix = true, $domain = false)
    {
        $request = app('request');
        $addon = $request->addon;

        // 命名空间
        $levelRoute = '';

        if (empty($url)) {
            // 生成 url 模板变量
            $controller = $request->controller();
            $controller = str_replace('/', '.', $controller);
            $action = $request->action();
        } else {
//            $url = Str::studly($url);
//            $url = parse_url($url);
//            if (isset($url['scheme'])) {
//                $addon = strtolower($url['scheme']);
//                $controller = $url['host'];
//                $action = trim($url['path'], '/');
//            } else {
//                $route = explode('/', $url['path']);
//                $addon = $request->addon;
//                $action = array_pop($route);
//                $controller = array_pop($route) ?: $request->controller();
//            }
//            $controller = Str::snake((string)$controller);

            // 解析路由
            $pathinfo  = str_replace("app/{$request->addon}", '', $url);
            $routeinfo = trim($pathinfo, '/');
            $pathArr   = explode('/', $routeinfo);
            $pathCount = count($pathArr);

            // 取控制器
            $control = config('route.default_controller', 'Index');

            // 取方法名
            $action = config('route.default_action', 'index');
            if ($pathCount > 1 && !is_dir(root_path() . "addons/{$addon}/app/" . $routeinfo)) {
                // 控制器
                $controlIndex = $pathCount - 2;
                $control      = ucfirst($pathArr[$controlIndex]);
                unset($pathArr[$pathCount - 2]);
            }
            if ($pathCount > 1) {
                // 方法
                $acionIndex = $pathCount - 1;
                $action     = $pathArr[$acionIndex];
                unset($pathArr[$pathCount - 1]);
            }
            $action = pathinfo($action, PATHINFO_FILENAME);

            $controller = $control;
            $action  = $action;

            // 层级
            $levelRoute = implode('/', $pathArr);

            /* 解析URL带的参数 */
            if (isset($url['query'])) {
                parse_str($url['query'], $query);
                $param = array_merge($query, $param);
            }
        }

        $url = "@app/{$addon}";
        if($levelRoute) $url .= "/{$levelRoute}";
        $url .= "/{$controller}/{$action}";
        return Route::buildUrl($url, $param)->suffix($suffix)->domain($domain);
    }
}

if (!function_exists('hook')) {
    /**
     * 处理插件钩子
     * @param string $event 钩子名称
     * @param array|null $params 传入参数
     * @param bool $once 是否只返回一个结果
     * @return mixed
     */
    function hook($event, $params = null, bool $once = false)
    {
        $result = Event::trigger($event, $params, $once);

        return join('', $result);
    }
}

if (!function_exists('get_addons_list')) {
    /**
     * 获取插件列表
     * @return array
     */
    function get_addons_list()
    {
        $addonsPath = app()->addons->getAddonsPath();
        $results = scandir($addonsPath);

        $list = [];
        foreach ($results as $name) {
            if ($name === '.' or $name === '..') {
                continue;
            }
            //检查指定的文件是否是常规的文件
            if (is_file($addonsPath . $name)) {
                continue;
            }
            $addonDir = $addonsPath . $name . DIRECTORY_SEPARATOR;
            //检查指定的文件是否是一个目录
            if (!is_dir($addonDir)) {
                continue;
            }
            $info = get_addons_info($name);
            if (empty($info)) continue;

            $list[$name] = $info;
        }
        return $list;
    }
}

if (!function_exists('get_addons_config')) {
    /**
     * 获取插件配置信息
     * @param string $name 插件名
     * @param bool $type 是否获取完整配置
     * @return array
     */
    function get_addons_config($name, $type = false)
    {
        $addon = get_addons_instance($name);
        if (!$addon) {
            return [];
        }

        return $addon->getConfig($type);
    }
}

if (!function_exists('set_addons_config')) {
    /**
     * 获取插件类的配置值值
     * @param string $name 插件名
     * @return array
     */
    function set_addons_config($name, $value = [])
    {
        $addon = get_addons_instance($name);
        if (!$addon) {
            return [];
        }

        return $addon->setConfig($value);
    }
}

if (!function_exists('get_assets_check')) {
    /**
     * 检测是否存在静态资源并处理
     * @param \think\Request $request
     * @return false|\think\Response
     * @throws Exception
     */
    function get_assets_check(\think\Request $request)
    {
        $staticSuffix = config('addons.static_suffix');
        if (!is_array($staticSuffix)) {
            throw new \Exception("配置项addons.static_suffix必须为数组");
        }
        if (empty($staticSuffix)) {
            throw new \Exception("配置项addons.static_suffix不能为空");
        }
        # 检测是否资源文件
        $extension = pathinfo($request->pathinfo(), PATHINFO_EXTENSION);
        if (in_array($extension, $staticSuffix)) {
            $mimeContentTypes = [
                'xml'   => 'application/xml,text/xml,application/x-xml',
                'json'  => 'application/json,text/x-json,application/jsonrequest,text/json',
                'js'    => 'text/javascript,application/javascript,application/x-javascript',
                'css'   => 'text/css',
                'rss'   => 'application/rss+xml',
                'yaml'  => 'application/x-yaml,text/yaml',
                'atom'  => 'application/atom+xml',
                'pdf'   => 'application/pdf',
                'text'  => 'text/plain',
                'image' => 'image/png,image/jpg,image/jpeg,image/pjpeg,image/gif,image/webp,image/*',
                'csv'   => 'text/csv',
                'html'  => 'text/html,application/xhtml+xml,*/*',
                'vue'   => 'application/octet-stream',
                'svg'   => 'image/svg+xml',
            ];
            # 检测文件媒体类型
            $mimeContentType = 'text/plain';
            if (isset($mimeContentTypes[$extension])) {
                $mimeContentType = $mimeContentTypes[$extension];
            }
            # 检测是否框架GZ资源
            $file = public_path().$request->pathinfo();
            if (file_exists($file)) {
                $content  = file_get_contents($file);
                return response()->code(200)->contentType($mimeContentType)->content($content);
            }
            # 检测是否插件资源
            $pluginRoute = explode('/',$request->pathinfo());
            if (isset($pluginRoute[1])) {
                $plugin = $pluginRoute[1];
                unset($pluginRoute[0]);
                unset($pluginRoute[1]);
                $pluginRoute = implode('/', $pluginRoute);
                $file = root_path()."addons/{$plugin}/public/{$pluginRoute}";
                if (file_exists($file)) {
                    $content  = file_get_contents($file);
                    return response()->code(200)->contentType($mimeContentType)->content($content);
                }
            }
            # 文件资源不存在则找官方库
            $file = root_path()."view/{$request->pathinfo()}.gz";
            if (file_exists($file)) {
                $content  = file_get_contents($file);
                return response()->code(200)->header([
                    'Content-Type'      => $mimeContentType,
                    'Content-Encoding'  => 'gzip'
                ])->content($content);
            }
            # 普通文件
            $file = root_path()."view/{$request->pathinfo()}";
            if (file_exists($file)) {
                $content  = file_get_contents($file);
                return response()->code(200)->contentType($mimeContentType)->content($content);
            }
        }
        return false;
    }
}

if (!function_exists('get_addons_template_path')) {
    /**
     * 获取模板目录位置
     * @return string
     */
    function get_addons_template_path()
    {
        $view = config('view.view_dir_name');
        if (is_dir(app_path() . $view)) {
            $path = app_path() . $view . DIRECTORY_SEPARATOR;
        } else {
            $appName = config('xbao.view_style');
            $path = root_path() . $view . DIRECTORY_SEPARATOR . ($appName ? $appName . DIRECTORY_SEPARATOR : '');
        }
        return $path;
    }
}

if (!function_exists('addons_view')) {
    /**
     * 加载模板输出
     * @param string $template
     * @param array $vars           模板文件名
     * @return false|mixed|string   模板输出变量
     * @throws \think\Exception
     */
    function addons_view($template = '', $vars = [])
    {
        $addon = get_addons_instance(request()->addon);
        return $addon->view($template, $vars);
    }
}
//if (!function_exists('get_addons_view')) {
//    /**
//     * 获取插件视图
//     * @param mixed $plugin
//     */
//    function get_addons_view($plugin = '')
//    {
//        $viewPath = public_path() . 'xbaocms/index.html';
//        if (!file_exists($viewPath)) {
//            throw new Exception('官方后台视图模板文件不存在');
//        }
//        $content = file_get_contents($viewPath);
//        $response = Response::create()->content($content);
//        return $response;
//    }
//}
