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

    /*插件名称*/
    protected $addons;

    public function __construct(App $app)
    {
        $this->app  = $app;
        $this->request = $app->request;
        $this->addons  = $this->request->route('addons', '');
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

        // 3.加载插件配置
        $this->loadConfig();

        // 4.加载应用插件composer包
        $this->loadComposer();

        // 调度转发
        return $this->app->middleware
            ->pipeline('addons')
            ->send($request)
            ->then(function ($request) use ($next) {
                return $next($request);
            });
        return $next($request);
    }
    /**
     * 初始化插件
     * @return void
     */
    public function initAddons()
    {
        // 设置插件名称
        $this->request->addons = $this->addons;

        // 设置插件目录
        $this->request->addonsPath = $this->app->getRootPath() . "addons/{$this->addons}/";

        // 设置插件应用目录
        $this->request->addonsAppPath = $this->request->addonsPath . "app/";

        // 设置插件模板目录
        $this->request->addonsViewPath = $this->request->addonsPath . "view/";

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

        if (!$this->addons) {
            throw new HttpException(404, lang('addon %s not found', [$this->addons]));
        }

        // 解析路由
        $pathinfo  = str_replace("app/{$this->addons}", '', $pathinfo);
        $routeinfo = trim($pathinfo, '/');
        $pathArr   = explode('/', $routeinfo);
        $pathCount = count($pathArr);

        // 取控制器
        $control = config('route.default_controller', 'Index');

        // 取方法名
        $action = config('route.default_action', 'index');
        if ($pathCount > 1 && !is_dir($this->app->getRootPath() . "addons/{$this->addons}/app/" . $routeinfo)) {
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
        // 命名空间
        $levelRoute = '';
        if ($this->request->levelRoute) {
            $levelRoute = str_replace("/", "\\", $this->request->levelRoute);
            $levelRoute = "{$levelRoute}\\";
        }
        $controlLayout = config('route.controller_layer', 'controller');
        $this->app->setNamespace("addons\\{$this->addons}\\app\\{$levelRoute}{$controlLayout}");
    }

    /**
     * 加载插件配置项
     *
     * @return void
     */
    public function loadConfig()
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
                $configs = $this->app->config->get("addons.{$this->addons}", []);
                if (empty($configs)) {
                    // 首次添加
                    $configData = [
                        $this->addons => [
                            $configName => $configData,
                        ],
                    ];
                } else {
                    // 后续添加
                    $configs[$configName] = $configData;
                    $configData  = [
                        $this->addons => $configs,
                    ];
                }
                $this->app->config->set($configData, 'addons');
            }
        }

        // 加载事件文件
        if (is_file($configPath . '/event.php')) {
            $this->app->loadEvent(include $configPath . '/event.php');
        }

        // 加载容器文件
        if (is_file($configPath . '/provider.php')) {
            $this->app->bind(include $configPath . '/provider.php');
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
