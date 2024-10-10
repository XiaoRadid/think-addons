<?php
/**
 * +----------------------------------------------------------------------
 * | think-addons [thinkphp6]
 * +----------------------------------------------------------------------
 *  .--,       .--,             | FILE: Route.php
 * ( (  \.---./  ) )            | AUTHOR: byron
 *  '.__/o   o\__.'             | EMAIL: xiaobo.sun@qq.com
 *     {=  ^  =}                | QQ: 150093589
 *     /       \                | DATETIME: 2019/11/5 09:57
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

namespace think\addons;

use think\Request;
use think\App;
use think\helper\Str;
use think\facade\Event;
use think\facade\Config;
use think\exception\HttpException;

class Route
{
    /** @var App */
    protected $app;

    /**
     * 请求对象
     * @var Request
     * @author 贵州猿创科技有限公司
     * @email 416716328@qq.com
     */
    protected $request;

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->app     = app();
        $this->request = $this->app->request;
    }
    /**
     * 插件路由请求
     * @param null $addons
     * @param null $controller
     * @param null $action
     * @return mixed
     */
    public function execute($addons = null, $controller = null, $action = null)
    {
        $app = app();
        $request = $app->request;

        // 获取三层数据
        $controller = $this->request->control;
        $action     = $this->request->action;
        $addons     = $this->request->addons;

        if (empty($addons) || empty($controller) || empty($action)) {
            throw new HttpException(500, lang('addon can not be empty'));
        }

        // 获取插件基础信息
        $info = get_addons_info($addons);
        if (!$info) {
            throw new HttpException(404, lang('addon %s not found', [$addons]));
        }
        if (!$info['status']) {
            throw new HttpException(500, lang('addon %s is disabled', [$addons]));
        }

        // 重写视图基础路径
        $config = Config::get('view');
        $config['view_path'] = $app->addons->getAddonsPath() . $addons . DIRECTORY_SEPARATOR . 'view' . DIRECTORY_SEPARATOR;

        Config::set($config, 'view');

        // 组装命名空间
        $addonsNameSpace = "addons\\{$addons}";
        $this->app->setNamespace($addonsNameSpace);

        // 层级路由
        $levelRoute = '';
        if ($this->request->levelRoute) {
            $levelRoute = str_replace("/", "\\", $this->request->levelRoute);
            $levelRoute = "{$levelRoute}\\";
        }

        // 组装控制器命名空间
        $controlLayout = config('route.controller_layer', 'controller');
        $class         = "{$addonsNameSpace}\\app\\{$levelRoute}{$controlLayout}\\{$controller}";

        if (!class_exists($class)) {
            throw new \Exception("controller not exists：{$class}");
        }

        if (!method_exists($class, $action)) {
            throw new \Exception("action not exists：{$class}@{$action}");
        }

        // 执行调度转发
        return app($class)->$action($this->request);
    }
}
