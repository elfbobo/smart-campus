<?php

namespace app\orderchat\controller;

use controller\BasicAdmin;
use service\LogService;
use service\DataService;
use think\Db;

/**
 * 人员信息管理控制器
 * Class Company
 * @package app\admin\controller
 * @author Anyon <zoujingli@qq.com>
 * @date 2017/02/15 18:12
 */
class Member extends BasicAdmin
{

    /**
     * 指定当前数据表
     * @var string
     */
    public $table = 'Employee_list';

    /**
     * 人员列表
     */
    public function index()
    {
        // 设置页面标题
        $this->title = '人员信息管理';
        // 获取到所有GET参数
        $get = $this->request->get();
        // 实例Query对象
        $db = Db::name($this->table)
            ->alias('a')
            ->field('a.*,dept_name')
            ->join('dept_info c', 'a.Dept_Id = c.dept_no and a.company_id = c.company_id','left')
            ->where('a.company_id', session('user.company_id'));
        // 应用搜索条件ss
        foreach (['Emp_Name'] as $key) {
            if (isset($get[$key]) && $get[$key] !== '') {
                $db->where($key, 'like', "%{$get[$key]}%");
            }
        }
        foreach (['Emp_MircoMsg_Uid'] as $key) {
            if (isset($get[$key]) && $get[$key] !== '') {
                $db->where($key, 'like', "%{$get[$key]}%");
            }
        }
        if (isset($get['tag']) && $get['tag'] !== '') {
            //$db->where("concat(',',tagid_list,',') like :tag", ['tag' => "%,{$get['tag']},%"]);   //mysql存在contcat内置函数
            $db->where('dept_no',$get['tag']);
        }
        // 实例化并显示
        return parent::_list($db);
    }

    /**
     * 列表数据处理
     * @param type $list
     */
    protected function _data_filter(&$list)
    {
        $tags = Db::name('dept_info')->where('company_id', session('company_id'))->column('dept_no,dept_name');
        $this->assign('tags', $tags);
    }

    /**
     * 授权管理
     * @return array|string
     */
    public function auth()
    {
        LogService::write('订餐管理', '执行人员授权操作');
        return $this->_form($this->table, 'auth', 'Emp_Id');
    }

    /**
     * 人员添加
     */
    public function add()
    {
        LogService::write('订餐管理', '执行人员添加操作');
        $extendData = [];
        if ($this->request->isPost()) {
            $sqlstr = "exec [up_get_max_id] ?,?,?,?";
            $data = Db::query($sqlstr, ['', 'member', 'EPID', 0]);
            $extendData['Emp_Id'] = $data[0][0]['id'];
            $extendData['company_id'] = session('user.company_id');
            $extendData['Emp_Status'] = 1;
            $data = $this->request->post();
            if ($data['Emp_MircoMsg_Upwd']) {
                $extendData['Emp_MircoMsg_Upwd'] = md5($data['Emp_MircoMsg_Upwd']);
            }
        }
        return $this->_form($this->table, 'form', 'Emp_Id', '', $extendData);
    }

    /**
     * 人员编辑
     */
    public function edit()
    {
        LogService::write('订餐管理', '执行人员编辑操作');
        $extendData = [];
        if ($this->request->isPost()) {
            $data = $this->request->post();
            if ($data['Emp_MircoMsg_Upwd']) {
                $extendData['Emp_MircoMsg_Upwd'] = md5($data['Emp_MircoMsg_Upwd']);
            }
        }
        return $this->_form($this->table, 'form', 'Emp_Id', '', $extendData);
    }

    /**
     * 表单数据默认处理
     * @param array $data
     */
    public function _form_filter(&$data)
    {
        if ($this->request->isPost()) {

            if (isset($data['dept']) && is_array($data['dept'])) {
                Db::name('t_user_manager_dept_id')->where(['company_id' => session('user.company_id'), 'u_id' => $data['Emp_Id'], 'dept_type' => 'userdept'])->delete();
                foreach ($data['dept'] as $key => $val) {
                    Db::name('t_user_manager_dept_id')->insert(['u_id' => $data['Emp_Id'], 'dept_id' => $data['dept'][$key], 'company_id' => session('user.company_id'), 'dept_type' => 'userdept']);
                }
                unset($data['dept']);
            } else {
                Db::name('t_user_manager_dept_id')->where(['company_id' => session('user.company_id'), 'u_id' => $data['Emp_Id'], 'dept_type' => 'userdept'])->delete();
            }

            if (Db::name($this->table)->where('company_id', session('user.company_id'))->where(['Emp_Id' => $data['Emp_Id'], 'Emp_MircoMsg_Uid' => array('neq', '')])->find()) {
                unset($data['Emp_MircoMsg_Uid']);
            } elseif (isset($data['Emp_MircoMsg_Uid']) and !empty($data['Emp_MircoMsg_Uid']) and Db::name($this->table)->where('Emp_MircoMsg_Uid', $data['Emp_MircoMsg_Uid'])->find()) {
                $this->error('账号名称已经存在，请使用其它账号名称！');
            } elseif (isset($data['Ic_Card'])  and !empty($data['Ic_Card']) and !empty($data['Emp_MircoMsg_Uid']) and Db::name($this->table)->where('Ic_Card', $data['Ic_Card'])->where('company_id', session('user.company_id'))->find()) {
                $this->error('ID卡编号已经存在，请使用其它ID卡编号！');
            }
        } else {
            if(isset( $_GET['Emp_Id'])){
                $list = Db::name('t_user_manager_dept_id')->where(['company_id' => session('user.company_id'), 'u_id' => $_GET['Emp_Id']])->select();
                $dept_ids = array_column($list, 'dept_id');
                $this->assign('manager', $dept_ids);
            }
            $this->assign('dept_infos', Db::name("dept_info")->where('company_id', session('user.company_id'))->select());
            $db = Db::name('dept_info')->where('company_id', session('user.company_id'))->select();
            $this->assign('depts', $db);
        }
    }

    /**
     * 删除人员
     */
//    public function del() {
//        if (DataService::update($this->table)) {
//            $this->success("人员删除成功！", '');
//        }
//        $this->error("人员删除失败，请稍候再试！");
//    }

    /**
     * 人员禁用
     */
    public function forbid()
    {
        LogService::write('订餐管理', '执行人员禁用操作');
        if (DataService::update($this->table, '', 'Emp_Id')) {
            $this->success("人员禁用成功！", '');
        }
        $this->error("人员禁用失败，请稍候再试！");
    }

    /**
     * 人员启用
     */
    public function resume()
    {
        LogService::write('订餐管理', '执行人员启用操作');
        if (DataService::update($this->table, '', 'Emp_Id')) {
            $this->success("人员启用成功！", '');
        }
        $this->error("人员启用失败，请稍候再试！");
    }

}
