<?php

namespace app\orderchat\controller;

use controller\BasicAdmin;
use service\LogService;
use service\DataService;
use think\Db;

/**
 * 系统用户管理控制器
 * Class User
 * @package app\admin\controller
 * @author Anyon <zoujingli@qq.com>
 * @date 2017/02/15 18:12
 */
class Cbdinnerinfo extends BasicAdmin {

    /**
     * 指定当前数据表
     * @var string
     */
    public $table = 'emp_cookbook_dinner_info';

    /**
     * 订单列表
     */
    public function index() {
        // 设置页面标题
        $this->title = '订单列表管理';
        // 获取到所有GET参数
        $get = $this->request->get();
        // 实例Query对象
        $db = Db::name($this->table)
            ->alias('a')
            ->join('Employee_list e','a.emp_id = e.emp_id and a.company_id = e.company_id','left')
            ->join('canteen_base_info c','a.canteen_no = c.canteen_no and a.company_id = c.company_id','left')
            ->join('dinner_base_info b','a.dinner_flag = b.dinner_flag and a.company_id=b.company_id','left')
            ->join('canteen_sale_window_base_info s','a.dinner_machine_no = s.sale_window_no','left')
            ->join('cookbook_base_info i','a.cookbook_no = i.cookbook_no','left')
            ->field('a.*,canteen_name,cookbook_name,dinner_name,sale_window_name,emp_name')
            ->where(['a.company_id'=>session('user.company_id')])
            ->order('');
        // 应用搜索条件
        foreach (['canteen_name'] as $key) {
            if (isset($get[$key]) && $get[$key] !== '') {
                $db->where($key, 'like', "%{$get[$key]}%");
            }
        }
        // 实例化并显示
        return parent::_list($db);
    }



//    /**
//     * 订单添加
//     */
//    public function add() {
//        $extendData = [];
//        if ($this->request->isPost()) {
//            $sqlstr = "exec [up_get_max_id] ?,?,?,?";
//            $data = Db::query($sqlstr, ['', 'canteen', 'CANT', 0]);
//            $extendData['canteen_no'] = $data[0][0]['id'];
//            $extendData['company_id'] = session('user.company_id');
//        }
//        LogService::write('订餐管理', '执行订单添加操作');
//        return $this->_form($this->table, 'form','canteen_no','',$extendData);
//    }
//
//    /**
//     * 订单编辑
//     */
//    public function edit() {
//        LogService::write('订餐管理', '执行订单编辑操作');
//        return $this->_form($this->table, 'form','canteen_no');
//    }
//
//
//
//    /**
//     * 表单数据默认处理
//     * @param array $data
//     */
//    public function _form_filter(&$data) {
//        if ($this->request->isPost()) {
//            if (Db::name($this->table)->where('company_id', session('user.company_id'))->where('canteen_no', $data['canteen_no'])->find()) {
//                unset($data['canteen_name']);
//            } elseif (Db::name($this->table)->where('company_id', session('user.company_id'))->where('canteen_name', $data['canteen_name'])->find()) {
//                $this->error('订单名称已经存在，请使用其它名称！');
//            }
//        }
//    }
//
//    /**
//     * 删除订单
//     */
//    public function del() {
//        LogService::write('订餐管理', '执行订单删除操作');
//        $where = [];
//        $where['company_id'] = session('user.company_id');
//        if (DataService::update($this->table,$where,'canteen_no')) {
//            $this->success("订单删除成功！", '');
//        }
//        $this->error("订单删除失败，请稍候再试！");
//    }


}
