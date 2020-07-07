<?php 

namespace app\xqy;

use app\xqy\helper\DeleteHelper;
use app\xqy\helper\FormHelper;
use app\xqy\helper\InputHelper;
use app\xqy\helper\PageHelper;
use app\xqy\helper\QueryHelper;
use app\xqy\helper\SaveHelper;
use app\xqy\helper\TokenHelper;
use app\xqy\helper\ValidateHelper;
use think\App;
use think\Container;
use think\db\Query;
use think\exception\HttpResponseException;
use think\Response;
use Closure;
use think\admin\Controller;

class BaseController extends Controller
{

    /**
     * 动态方法集合
     */
    public $methods = [];

    /** 控制器返回数据 */
    public $response_data = [];

    /**
     * Controller constructor.
     * @param App $app
     */
    public function __construct(App $app)
    {
        $this->app = $app;
        $this->request = $app->request;
        if (in_array($this->request->action(), get_class_methods(__CLASS__))) {
            $this->error('Access without permission.');
        }
        $this->csrf_message = lang('think_library_csrf_error');
    }

    /**
     * 合并请求对象
     * @param Response $response 目标响应对象
     * @param Response $source 数据源响应对象
     * @return Response
     */
    private function __mergeResponse(Response $response, Response $source)
    {
        $response->code($source->getCode())->content($response->getContent() . $source->getContent());
        foreach ($source->getHeader() as $name => $value) if (!empty($name) && is_string($name)) $response->header($name, $value);
        return $response;
    }

    /**
     * 返回失败的操作
     * @param mixed $info 消息内容
     * @param array $data 返回数据
     * @param integer $code 返回代码
     */
    public function error($code = 0)
    {
        $result = ['code' => $code, 'info' => lang('think_library_save_error'), 'data' => $this->response_data];
        throw new HttpResponseException(json($result));
    }

    /**
     * 返回成功的操作
     * @param mixed $info 消息内容
     * @param array $data 返回数据
     * @param integer $code 返回代码
     */
    public function success($code = 1)
    {
        if ($this->csrf_state) {
            TokenHelper::instance()->clear();
        }
        throw new HttpResponseException(json([
            'code' => $code, 'info' => lang('think_library_save_success'), 'data' => $this->response_data,
        ]));
    }

    /**
     * 返回视图内容
     * @param string $tpl 模板名称
     * @param array $vars 模板变量
     * @param string $node CSRF授权节点
     */
    public function fetch($tpl = '', $vars = [], $node = null)
    {
        foreach ($this->response_data as $name => $value) $vars[$name] = $value;
        if ($this->csrf_state) {
            TokenHelper::instance()->fetchTemplate($tpl, $vars, $node);
        } else {
            throw new HttpResponseException(view($tpl, $vars));
        }
    }

    /**
     * 模板变量赋值
     * @param mixed $name 要显示的模板变量
     * @param mixed $value 变量的值
     * @return $this
     */
    public function assign($name, $value = '')
    {
        if (is_string($name)) {
            $this->response_data[$name] = $value;
        } elseif (is_array($name)) foreach ($name as $k => $v) {
            if (is_string($k)) $this->response_data[$k] = $v;
        }

        return $this;
    }

    /**
     * 数据回调处理机制
     * @param string $name 回调方法名称
     * @param mixed $one 回调引用参数1
     * @param mixed $two 回调引用参数2
     * @return boolean
     */
    public function callback($name, &$one = [], &$two = [])
    {
        if (is_callable($name)) {
            return call_user_func($name, $this, $one, $two);
        }

        foreach ([$name, "_{$this->request->action()}{$name}"] as $method) {
            if (isset($this->methods[$method]) && is_callable($this->methods[$method])) {
                if (false === call_user_func_array($this->methods[$method], array(&$one, &$two))) {
                    return false;
                }
            }
        }

        return true;
    }

    public function bindMethod(String $name, Closure $closure)
    {
        if (is_callable($closure) && get_class($closure) === Closure::class)
            $this->methods[$name] = Closure::bind($closure, $this, self::class);

        return $this;
    }

    protected function _form($dbQuery, $template = '', $field = '', $where = [], $data = [])
    {
        return Helper::instance()->form($dbQuery, $template, $field, $where, $data);
    }

    protected function _save($dbQuery, $data = [], $field = '', $where = [])
    {
        return Helper::instance()->save($dbQuery, $data, $field, $where);
    }

    protected function _delete($dbQuery, $field = '', $where = [])
    {
        return Helper::instance()->delete($dbQuery, $field, $where);
    }

    protected function _vali(array $rules, $type = '')
    {
        return Helper::instance()->validate($rules, $type);
    }

    /**
     * 属性重载，重载的属性都当做视图可用变量
     */
    public function __set($name, $value) 
    {
        $this->response_data[$name] = $value;
    }

    /**
     * 获取视图变量
     */
    public function __get($name) 
    {
        if (array_key_exists($name, $this->response_data)) {
            return $this->response_data[$name];
        }

        return null;
    }

    /**
     * 检测视图变量是否存在
     */
    public function __isset($name) 
    {
        return isset($this->response_data[$name]);
    }

    /**
     * 视图变量置空
     */
    public function __unset($name) 
    {
        unset($this->response_data[$name]);
    }
}