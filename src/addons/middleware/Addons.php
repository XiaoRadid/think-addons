<?php
/**
 * +----------------------------------------------------------------------
 * | think-addons [thinkphp6]
 * +----------------------------------------------------------------------
 *  .--,       .--,             | FILE: Addons.php
 * ( (  \.---./  ) )            | AUTHOR: byron
 *  '.__/o   o\__.'             | EMAIL: xiaobo.sun@qq.com
 *     {=  ^  =}                | QQ: 150093589
 *     /       \                | DATETIME: 2019/11/5 09:55
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

namespace think\addons\middleware;

use think\App;

class Addons
{
    /**
     * 应用实例
     * @var \think\App
     */
    protected $app;

    protected $request;

    public function __construct(App $app)
    {
        $this->app  = $app;
        $this->request = $app->request;
        $this->addon  = $this->request->route('addons', '');
    }

    /**
     * 插件中间件
     * @param $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle($request, \Closure $next)
    {
        // 检测是否资源文件
        if ($response = get_assets_check($request)) {
            return $response;
        }
        // 1.初始化应用插件基础参数
        $this->initAddons();

        // 2.解析路由
        $this->parseRoute();

        // 4.加载应用配置
        $appPath = $this->request->addonsAppPath;
        $this->loadPublic(); // 加载app目录配置

        $appName = $this->request->levelRoute;
        if($appName) {
            $appPath = $this->request->addonsAppPath . $appName . '/';
            $this->loadApp($appName, $appPath); // 加载app/应用目录配置
        }

        // 5.加载应用插件composer包
        $this->loadComposer();

        // 调度转发
        return $this->app->middleware
            ->pipeline('addons')
            ->send($request)
            ->then(function ($request) use ($next) {
                return $next($request);
            });
    }
    /**
     * 初始化插件
     * @return void
     */
    public function initAddons()
    {
        // 设置插件名称
        $this->request->addon = $this->addon;

        // 设置插件目录
        $this->request->addonsPath = $this->app->getRootPath() . "addons/{$this->addon}/";

        // 设置插件应用目录
        $this->request->addonsAppPath = $this->request->addonsPath . "app/";

        // 设置插件模板目录
        $this->request->addonsViewPath = $this->request->addonsPath . "template/";

        // 设置插件配置文件目录
        $this->request->addonsConfigPath = $this->request->addonsPath . "config/";

        // 设置插件静态资源目录
        $this->request->addonsPublicPath = $this->request->addonsPath . "public/";
    }

    /**
     * 解析路由
     *
     * @return void
     * @throws \Exception
     */
    private function parseRoute()
    {
        $pathinfo = $this->request->pathinfo();

        if (!$this->addon) {
            throw new HttpException(404, lang('addon %s not found', [$this->addon]));
        }

        // 解析路由
        $pathinfo  = str_replace("app/{$this->addon}", '', $pathinfo);
        $routeinfo = trim($pathinfo, '/');
        $pathArr   = explode('/', $routeinfo);
        $pathCount = count($pathArr);

        // 取控制器
        $control = config('route.default_controller', 'Index');

        // 取方法名
        $action = config('route.default_action', 'index');
        if ($pathCount > 1 && !is_dir($this->app->getRootPath() . "addons/{$this->addon}/app/" . $routeinfo)) {
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

        $isControlSuffix             = config('route.controller_suffix', true);
        $controllerSuffix            = $isControlSuffix ? 'Controller' : '';
        $this->request->control = "{$control}{$controllerSuffix}";
        $this->request->action  = $action;
        $this->request->setController($control);
        $this->request->setAction($action);

        // 层级
        $this->request->levelRoute = implode('/', $pathArr);
        if ($this->request->levelRoute) {
            $this->request->levelRoute = str_replace("/", "\\", $this->request->levelRoute);
        }

        // 设置应用名
        $this->app->http->name($this->request->levelRoute);

        // 设置应用目录路径
        $appPath = $this->app->http->getPath() ?: $this->request->addonsAppPath . $this->request->levelRoute . DIRECTORY_SEPARATOR;
        $this->app->setAppPath($appPath);
        $this->app->http->path($this->request->addonsAppPath);

        $controlLayout = config('route.controller_layer', 'controller');
        $this->app->setNamespace("addons\\{$this->addon}\\app\\{$this->request->levelRoute}");
    }

    /**
     * 加载应用文件
     * @param string $appName 应用名
     * @return void
     */
    protected function loadApp(string $appName, string $appPath): void
    {
        if (is_file($appPath . 'common.php')) {
            include_once $appPath . 'common.php';
        }

        $files = [];
        $files = array_merge($files, glob($appPath . 'config' . DIRECTORY_SEPARATOR . '*' . $this->app->getConfigExt()));

        foreach ($files as $file) {
//            $this->app->config->load($file, pathinfo($file, PATHINFO_FILENAME)); // 这里加载到config同级，可能会存在冲突
            if (!is_file($file)) {
                continue;
            }
            $configName = pathinfo($file, PATHINFO_FILENAME);
            $configData = include $file;
            if (is_array($configData)) {
                $configs = $this->app->config->get("addons.{$this->addon}", []);
                if (empty($configs)) {
                    // 首次添加
                    $configData = [
                        $this->addon => [
                            $configName => $configData,
                        ],
                    ];
                } else {
                    // 后续添加
                    $configs[$configName] = $configData;
                    $configData  = [
                        $this->addon => $configs,
                    ];
                }
                $this->app->config->set($configData, 'addons');
            }
        }

        if (is_file($appPath . 'event.php')) {
            $this->app->loadEvent(include $appPath . 'event.php');
        }

        if (is_file($appPath . 'middleware.php')) {
            $this->app->middleware->import(include $appPath . 'middleware.php', 'addons');
        }

        if (is_file($appPath . 'provider.php')) {
            $this->app->bind(include $appPath . 'provider.php');
        }
        // 加载应用默认语言包
        $this->app->loadLangPack($this->app->lang->defaultLangSet());
    }

    /**
     * 加载公共配置项
     *
     * @return void
     */
    public function loadPublic()
    {
        // 插件目录
        $addonsPath     = $this->request->addonsPath;

        // 插件应用目录
        $addonsAppPath  = $this->request->addonsAppPath;

        // 插件配置目录
        $configPath     = $this->request->addonsConfigPath;

        // 加载TP类型函数库文件
        if (is_file($addonsAppPath . 'common.php')) {
            include_once $addonsAppPath . 'common.php';
        }

        $files = [];
        // 合并配置文件
        $files = array_merge($files, glob($configPath . '*' . $this->app->getConfigExt()));

        // 加载配置文件
        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }
            $configName = pathinfo($file, PATHINFO_FILENAME);
            $configData = include $file;
            if (is_array($configData)) {
                $configs = $this->app->config->get("addons.{$this->addon}", []);
                if (empty($configs)) {
                    // 首次添加
                    $configData = [
                        $this->addon => [
                            $configName => $configData,
                        ],
                    ];
                } else {
                    // 后续添加
                    $configs[$configName] = $configData;
                    $configData  = [
                        $this->addon => $configs,
                    ];
                }
                $this->app->config->set($configData, 'addons');
            }
        }

        // 加载事件文件
        if (is_file($addonsAppPath . 'event.php')) {
            $this->app->loadEvent(include $addonsAppPath . '/event.php');
        }

        // 加载容器文件
        if (is_file($addonsAppPath . 'provider.php')) {
            $this->app->bind(include $addonsAppPath . '/provider.php');
        }

        // 加载中间件文件
        if (is_file($addonsAppPath . 'middleware.php')) {
            $this->app->middleware->import(include $addonsAppPath . 'middleware.php', 'addons');
        }
    }

    /**
     * 加载插件内composer包
     *
     * @return void
     */
    public function loadComposer()
    {
        $addonsPath     = $this->request->addonsPath;

        // 检测插件内composer包
        $vendorFile = $addonsPath . "vendor/autoload.php";

        if (!is_file($vendorFile)) {
            return;
        }
        // 加载插件内composer包
        include_once $vendorFile;
    }


}
