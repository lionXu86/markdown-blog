<?php

namespace app\xqy;

use think\App;
use think\Container;
use think\Db;
use think\db\Query;
use think\Validate;
use app\xqy\BaseController;

class Helper
{
    /**
     * 当前应用容器
     * @var App
     */
    public $app;

    /**
     * 数据库实例
     * @var Query
     */
    public $query;

    /**
     * 当前控制器实例
     * @var Controller
     */
    public $controller;

    public function __construct(App $app, BaseController $controller)
    {
        $this->app = $app;
        $this->controller = $controller;
    }

    protected function buildQuery($dbQuery)
    {
        return is_string($dbQuery) ? Db::name($dbQuery) : $dbQuery;
    }

    public static function instance()
    {
        return Container::getInstance()->invokeClass(static::class);
    }

    public function __call($name, $args)
    {
        if (is_callable($callable = [$this->query, $name])) {
            call_user_func_array($callable, $args);
        }
        return $this;
    }

    public function init($dbQuery)
    {
        $this->query = $this->buildQuery($dbQuery);
        return $this;
    }

    public function db()
    {
        return $this->query;
    }

    public function like($fields, $input = 'request', $alias = '#')
    {
        $data = $this->app->request->$input();
        foreach (is_array($fields) ? $fields : explode(',', $fields) as $field) {
            list($dk, $qk) = [$field, $field];
            if (stripos($field, $alias) !== false) {
                list($dk, $qk) = explode($alias, $field);
            }
            if (isset($data[$qk]) && $data[$qk] !== '') {
                $this->query->whereLike($dk, "%{$data[$qk]}%");
            }
        }
        return $this;
    }

    public function equal($fields, $input = 'request', $alias = '#')
    {
        $data = $this->app->request->$input();
        foreach (is_array($fields) ? $fields : explode(',', $fields) as $field) {
            list($dk, $qk) = [$field, $field];
            if (stripos($field, $alias) !== false) {
                list($dk, $qk) = explode($alias, $field);
            }
            if (isset($data[$qk]) && $data[$qk] !== '') {
                $this->query->where($dk, "{$data[$qk]}");
            }
        }
        return $this;
    }

    public function in($fields, $split = ',', $input = 'request', $alias = '#')
    {
        $data = $this->app->request->$input();
        foreach (is_array($fields) ? $fields : explode(',', $fields) as $field) {
            list($dk, $qk) = [$field, $field];
            if (stripos($field, $alias) !== false) {
                list($dk, $qk) = explode($alias, $field);
            }
            if (isset($data[$qk]) && $data[$qk] !== '') {
                $this->query->whereIn($dk, explode($split, $data[$qk]));
            }
        }
        return $this;
    }

    public function valueBetween($fields, $split = ' ', $input = 'request', $alias = '#')
    {
        return $this->setBetweenWhere($fields, $split, $input, $alias);
    }

    public function dateBetween($fields, $split = ' - ', $input = 'request', $alias = '#')
    {
        return $this->setBetweenWhere($fields, $split, $input, $alias, function ($value, $type) {
            if ($type === 'after') {
                return "{$value} 23:59:59";
            } else {
                return "{$value} 00:00:00";
            }
        });
    }

    public function timeBetween($fields, $split = ' - ', $input = 'request', $alias = '#')
    {
        return $this->setBetweenWhere($fields, $split, $input, $alias, function ($value, $type) {
            if ($type === 'after') {
                return strtotime("{$value} 23:59:59");
            } else {
                return strtotime("{$value} 00:00:00");
            }
        });
    }

    private function setBetweenWhere($fields, $split = ' ', $input = 'request', $alias = '#', $callback = null)
    {
        $data = $this->app->request->$input();
        foreach (is_array($fields) ? $fields : explode(',', $fields) as $field) {
            list($dk, $qk) = [$field, $field];
            if (stripos($field, $alias) !== false) {
                list($dk, $qk) = explode($alias, $field);
            }
            if (isset($data[$qk]) && $data[$qk] !== '') {
                list($begin, $after) = explode($split, $data[$qk]);
                if (is_callable($callback)) {
                    $after = call_user_func($callback, $after, 'after');
                    $begin = call_user_func($callback, $begin, 'begin');
                }
                $this->query->whereBetween($dk, [$begin, $after]);
            }
        }
        return $this;
    }

    public function page($dbQuery, $page = true, $display = true, $total = false, $limit = 0)
    {
        $this->page = $page;
        $this->total = $total;
        $this->limit = $limit;
        $this->display = $display;
        $this->query = $this->buildQuery($dbQuery);

        // 列表排序操作
        if ($this->controller->request->isPost()) $this->_sort();
        // 未配置 order 规则时自动按 sort 字段排序
        if (!$this->query->getOptions('order') && method_exists($this->query, 'getTableFields')) {
            if (in_array('sort', $this->query->getTableFields())) $this->query->order('sort desc');
        }
        // 列表分页及结果集处理
        if ($this->page) {
            // 分页每页显示记录数
            $limit = intval($this->controller->request->get('limit', cookie('page-limit')));
            cookie('page-limit', $limit = $limit >= 10 ? $limit : 20);
            if ($this->limit > 0) $limit = $this->limit;
            $rows = [];
            $page = $this->query->paginate($limit, $this->total, ['query' => ($query = $this->controller->request->get())]);
            foreach ([10, 20, 30, 40, 50, 60, 70, 80, 90, 100, 110, 120, 130, 140, 150, 160, 170, 180, 190, 200] as $num) {
                list($query['limit'], $query['page'], $selected) = [$num, '1', $limit === $num ? 'selected' : ''];
                $url = url('@admin') . '#' . $this->controller->request->baseUrl() . '?' . urldecode(http_build_query($query));
                array_push($rows, "<option data-num='{$num}' value='{$url}' {$selected}>{$num}</option>");
            }
            $selects = "<select onchange='location.href=this.options[this.selectedIndex].value' data-auto-none>" . join('', $rows) . "</select>";
            $pagetext = lang('think_library_page_html', [$page->total(), $selects, $page->lastPage(), $page->currentPage()]);
            $pagehtml = "<div class='pagination-container nowrap'><span>{$pagetext}</span>{$page->render()}</div>";
            $this->controller->assign('pagehtml', preg_replace('|href="(.*?)"|', 'data-open="$1" onclick="return false" href="$1"', $pagehtml));
            $result = ['page' => ['limit' => intval($limit), 'total' => intval($page->total()), 'pages' => intval($page->lastPage()), 'current' => intval($page->currentPage())], 'list' => $page->items()];
        } else {
            $result = ['list' => $this->query->select()];
        }

        $this->controller->response_data = $result;
        $this->controller->callback('_page_after');

        return $this->controller;
    }

    public function form($dbQuery, $template = '', $field = '', $where = [], $data = [])
    {
        $this->query = $this->buildQuery($dbQuery);
        list($this->template, $this->where, $this->data) = [$template, $where, $data];
        $this->field = empty($field) ? ($this->query->getPk() ? $this->query->getPk() : 'id') : $field;;
        $this->value = input($this->field, isset($data[$this->field]) ? $data[$this->field] : null);
        // GET请求, 获取数据并显示表单页面
        if ($this->app->request->isGet()) {
            if ($this->value !== null) {
                $where = [$this->field => $this->value];
                $data = (array)$this->query->where($where)->where($this->where)->find();
            }
            $data = array_merge($data, $this->data);
            $this->controller->response_data = $data;
            $this->controller->callback('_form_filter', $data);

            return $this->controller;
        }
        // POST请求, 数据自动存库处理
        if ($this->app->request->isPost()) {
            $data = array_merge($this->app->request->post(), $this->data);
            $this->controller->callback('_form_filter', $data, $this->where);
            $result = $this->data_save($this->query, $data, $this->field, $this->where);
            $this->controller->response_data = $result;
            $this->controller->callback('_form_result', $result, $data);
 
            return $this->controller;
        }
    }

    public function data_save($dbQuery, $data, $key = 'id', $where = [])
    {
        $db = is_string($dbQuery) ? Db::name($dbQuery) : $dbQuery;
        list($table, $value) = [$db->getTable(), isset($data[$key]) ? $data[$key] : null];
        $map = isset($where[$key]) ? [] : (is_string($value) ? [[$key, 'in', explode(',', $value)]] : [$key => $value]);
        if (is_array($info = Db::table($table)->master()->where($where)->where($map)->find()) && !empty($info)) {
            if (Db::table($table)->strict(false)->where($where)->where($map)->update($data) !== false) {
                return isset($info[$key]) ? $info[$key] : true;
            } else {
                return false;
            }
        } else {
            return Db::table($table)->strict(false)->insertGetId($data);
        }
    }

    public function save($dbQuery, $data = [], $field = '', $where = [])
    {
        $this->where = $where;
        $this->query = $this->buildQuery($dbQuery);
        $this->data = empty($data) ? $this->app->request->post() : $data;
        $this->field = empty($field) ? $this->query->getPk() : $field;
        $this->value = $this->app->request->post($this->field, null);
        // 主键限制处理
        if (!isset($this->where[$this->field]) && is_string($this->value)) {
            $this->query->whereIn($this->field, explode(',', $this->value));
            if (isset($this->data)) unset($this->data[$this->field]);
        }
        // 前置回调处理
        $this->controller->callback('_save_filter', $this->query, $this->data);

        // 执行更新操作
        $result = $this->query->where($this->where)->update($this->data) !== false;

        $this->controller->callback('_save_result', $result);
        
        return $this->controller;
    }

    public function delete($dbQuery, $field = '', $where = [])
    {
        $this->where = $where;
        $this->query = $this->buildQuery($dbQuery);
        $this->field = empty($field) ? $this->query->getPk() : $field;
        $this->value = $this->app->request->post($this->field, null);
        // 主键限制处理
        if (!isset($this->where[$this->field]) && is_string($this->value)) {
            $this->query->whereIn($this->field, explode(',', $this->value));
        }
        // 前置回调处理
        $this->controller->callback('_delete_filter', $this->query, $where);

        // 执行删除操作
        if (method_exists($this->query, 'getTableFields') && in_array('is_deleted', $this->query->getTableFields())) {
            $result = $this->query->where($this->where)->update(['is_deleted' => '1']);
        } else {
            $result = $this->query->where($this->where)->delete();
        }

        // 结果回调处理
        $this->controller->callback('_delete_result', $result);

        return $this->controller;
    }

    public function validate(array $rules, $type = '')
    {
        list($data, $rule, $info) = [[], [], []];
        foreach ($rules as $name => $message) {
            if (stripos($name, '#') !== false) {
                list($name, $alias) = explode('#', $name);
            }
            if (stripos($name, '.') === false) {
                if (is_numeric($name)) {
                    $keys = $message;
                    if (is_string($message) && stripos($message, '#') !== false) {
                        list($name, $alias) = explode('#', $message);
                        $keys = empty($alias) ? $name : $alias;
                    }
                    $data[$name] = input("{$type}{$keys}");
                } else {
                    $data[$name] = $message;
                }
            } else {
                list($_rgx) = explode(':', $name);
                list($_key, $_rule) = explode('.', $name);
                $keys = empty($alias) ? $_key : $alias;
                $info[$_rgx] = $message;
                $data[$_key] = input("{$type}{$keys}");
                $rule[$_key] = empty($rule[$_key]) ? $_rule : "{$rule[$_key]}|{$_rule}";
            }
        }
        $validate = new Validate();
        if ($validate->rule($rule)->message($info)->check($data)) {
            return $data;
        } else {
            $this->controller->error($validate->getError());
        }
    }

    public function input($data, $rule, $info)
    {
        list($this->rule, $this->info) = [$rule, $info];
        $this->data = $this->parse($data);
        $validate = Validate::make($this->rule, $this->info);
        if ($validate->check($this->data)) {
            return $this->data;
        } else {
            $this->controller->error($validate->getError());
        }
    }

    private function parse($data, $result = [])
    {
        if (is_array($data)) return $data;
        if (is_string($data)) foreach (explode(',', $data) as $field) {
            if (strpos($field, '#') === false) {
                $array = explode('.', $field);
                $result[end($array)] = input($field);
            } else {
                list($name, $value) = explode('#', $field);
                $array = explode('.', $name);
                $result[end($array)] = input($name, $value);
            }
        }
        return $result;
    }

}