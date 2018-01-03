<?phpnamespace app\index\controller;use controller\ApiBase;use think\Db;use think\Session;class MallApi extends ApiBase{    protected $checkAuth = true;    /**     * 获取首页数据     */    function getHomeData($time)    {        if (empty($time))            $this->error("数据查询繁忙，请稍后重试");        $firstDay = date('Y-m-d', strtotime($time));        $user_id = $this->getClientUserId();        $company_id = $this->getClientCompanyId();        $sqlStr = "exec [up_emp_dinner_calendar] ?,?,?";        $return = Db::query($sqlStr, [$company_id, $user_id, $firstDay]);        $data = [];        foreach ($return[0] as $key => $val) {            $data[$val['day_str']][] = $val;        }        return json($data);    }    /**     * 获取餐次信息     */    function getDinnerBase()    {        $company_id = $this->getClientCompanyId();        $data['dinnerBaseList'] = Db::name('dinner_base_info')->where(['company_id' => $company_id])->select();        $count = Db::name('dinner_base_info')->where(['company_id' => $company_id])->count();        $data['dinnerBaseCount'] = $this->canteen_W($count);        if (empty($data))            $this->error("餐次信息获取失败，请稍候再试！");        return json($data);    }    /**     * 获取菜谱CLASS数据     */    function cartProductList($day)    {        if (empty($day))            $this->error("数据查询繁忙，请稍后重试");        $weekArray = array("日", "一", "二", "三", "四", "五", "六");        $data['date'] = date('Y年m月d日 星期' . $weekArray[date("w")], strtotime($day));        $user_id = $this->getClientUserId();        $company_id = $this->getClientCompanyId();        $data['dinnerBaseList'] = Db::name('dinner_base_info')->where(['company_id' => $company_id])->select();        $count = Db::name('dinner_base_info')->where(['company_id' => $company_id])->count();        $data['dinnerBaseCount'] = $this->canteen_W($count);        if (empty($data['dinnerBaseList']))            $this->error("餐次信息获取失败，请稍候再试！");        $price_total = Db::name('emp_cookbook_dinner_info')            ->field('sum(cook_money) as price_total')            ->where(['emp_id' => $user_id, 'company_id' => $company_id, 'dinner_status' => '1', 'dinner_datetime' => $day])            ->select();        $data['price_total'] = number_format($price_total['0']['price_total'], 2);        $data['price_num'] = Db::name('emp_cookbook_dinner_info')            ->where(['emp_id' => $user_id, 'company_id' => $company_id, 'dinner_status' => '1', 'dinner_datetime' => $day])            ->count();        return json($data);    }    /**     * 获取菜谱CART数据     */    function cartList($dinner, $day)    {        $user_id = $this->getClientUserId();        $company_id = $this->getClientCompanyId();        $where['p.company_id'] = $company_id;        $where['p.dinner_flag'] = $dinner;        $where['p.sale_datetime'] = $day;        $where['p.status'] = '1';        $data['cartList'] = Db::name('canteen_cookbook_price')            ->alias('p')            ->join('canteen_base_info c', 'p.canteen_no = c.canteen_no and p.company_id = c.company_id', 'left')            ->join('cookbook_base_info b', 'p.cookbook_no = b.cookbook_no and p.company_id = b.company_id', 'left')            ->join('emp_cookbook_dinner_info d', 'p.canteen_no=d.canteen_no and p.cookbook_no=d.cookbook_no and d.emp_id= :emp_id and d.company_id = p.company_id and d.dinner_status = 1 and d.dinner_datetime = p.sale_datetime and d.dinner_flag = p.dinner_flag', 'left')            ->bind(['emp_id' => $user_id])            ->field('p.*,cookbook_name,cookbook_image,canteen_name,case when isnull(emp_id,\'\')=\'\' then 0 else 1 end as order_index ')            ->order('order_index desc')            ->where($where)->select();        foreach ($data['cartList'] as $key => $val) {            $data['cartList'][$key]['cookbook_price'] = number_format($val['cookbook_price'], 2);        }        $data['cartCount'] = Db::name('canteen_cookbook_price')            ->alias('p')            ->join('canteen_base_info c', 'p.canteen_no = c.canteen_no and p.company_id = c.company_id')            ->join('cookbook_base_info b', 'p.cookbook_no = b.cookbook_no and p.company_id = b.company_id')            ->where($where)->count();        return json($data);    }    /**     * 存储商品session     */    function addCart()    {        $param = $this->request->post();        if (empty($param))            $this->error('商品添加失败');        $user_id = $this->getClientUserId();        $company_id = $this->getClientCompanyId();        $emp_list = Db::name('emp_account_remain')->where(['emp_id' => $user_id, 'company_id' => $company_id, 'account_typeflag' => '1'])->find();        if (empty($emp_list))            $this->error("余额不足!");        if (floatval($param['cookbook_price']) > floatval($emp_list['account_remain_money']))            $this->error("余额不足!");        Db::name('emp_cookbook_dinner_info')            ->where(['emp_id' => $user_id, 'company_id' => $company_id, 'dinner_status' => '1', 'dinner_datetime' => $param['day'], 'dinner_flag' => $param['dinner_flag']])            ->delete();        $price_info = Db::name('canteen_cookbook_price')            ->where(['price_id' => $param['price_id'], 'sale_datetime' => $param['day'], 'dinner_flag' => $param['dinner_flag']])            ->find();        if (!empty($price_info)) {            $data['emp_id'] = $user_id;            $data['company_id'] = $company_id;            $data['canteen_no'] = $price_info['canteen_no'];            $data['cookbook_no'] = $price_info['cookbook_no'];            $data['dinner_flag'] = $price_info['dinner_flag'];            $data['dinner_quantity'] = 1;            $data['cook_money'] = $price_info['cookbook_price'];            $data['dinner_datetime'] = $param['day'];            $data['dinner_createtime'] = date("Y-m-d H:i:s");            $position = Db::name('canteen_cookbook_window_position')                ->where(['sale_datetime' => $param['day'], 'canteen_no' => $price_info['canteen_no'], 'cookbook_no' => $price_info['cookbook_no'], 'dinner_flag' => $price_info['dinner_flag'], 'company_id' => $company_id])->find();            $data['dinner_machine_no'] = $position['windows_no'];  //窗口号            $data['dinner_from_type'] = '微信';            $data['dinner_status'] = 1;            Db::name('emp_cookbook_dinner_info')->insert($data);        } else {            $this->error("菜品添加订单失败!");        }        $price_total = Db::name('emp_cookbook_dinner_info')            ->field('sum(cook_money) as price_total')            ->where(['emp_id' => $user_id, 'company_id' => $company_id, 'dinner_status' => '1', 'dinner_datetime' => $param['day']])            ->select();        $data['price_total'] = number_format($price_total['0']['price_total'], 2);        $data['price_num'] = Db::name('emp_cookbook_dinner_info')            ->where(['emp_id' => $user_id, 'company_id' => $company_id, 'dinner_status' => '1', 'dinner_datetime' => $param['day']])            ->count();        $this->success('商品添加成功', '', $data);    }    /**     * 删除商品session     */    function delCart()    {        $param = $this->request->post();        if (empty($param))            $this->error('商品删除失败');        $user_id = $this->getClientUserId();        $company_id = $this->getClientCompanyId();        Db::name('emp_cookbook_dinner_info')            ->where(['emp_id' => $user_id, 'company_id' => $company_id, 'dinner_status' => '1', 'dinner_datetime' => $param['day'], 'dinner_flag' => $param['dinner_flag']])            ->delete();        $price_total = Db::name('emp_cookbook_dinner_info')            ->field('sum(cook_money) as price_total')            ->where(['emp_id' => $user_id, 'company_id' => $company_id, 'dinner_status' => '1', 'dinner_datetime' => $param['day']])            ->select();        $data['price_total'] = number_format($price_total['0']['price_total'], 2);        $data['price_num'] = Db::name('emp_cookbook_dinner_info')            ->where(['emp_id' => $user_id, 'company_id' => $company_id, 'dinner_status' => '1', 'dinner_datetime' => $param['day']])            ->count();        $this->success('商品删除成功', '', $data);    }    /**     * 用餐纪录     */    function orderList()    {        $user_id = $this->getClientUserId();        $company_id = $this->getClientCompanyId();        $data['orderList'] = Db::name('emp_cookbook_dinner_info')            ->alias('e')            ->join('canteen_base_info b', 'e.canteen_no = b.canteen_no and e.company_id = b.company_id', 'left')            ->join('dinner_base_info d', 'e.dinner_flag = d.dinner_flag and e.company_id = d.company_id', 'left')            ->where(['e.emp_id' => $user_id, 'e.company_id' => $company_id, 'e.dinner_status' => '2'])            ->order('dinner_real_datetime', 'desc')            ->field('e.*,canteen_name,dinner_name')            ->select();        foreach ($data['orderList'] as $key => $val) {            $data['orderList'][$key]['dinner_real_datetime'] = date('Y年m月d日', strtotime($val['dinner_real_datetime']));            $data['orderList'][$key]['dinner_datetime'] = date('Y-m-d h:i:s', strtotime($val['dinner_datetime']));        }        $data['orderCount'] = Db::name('emp_cookbook_dinner_info')            ->where(['emp_id' => $user_id, 'company_id' => $company_id, 'dinner_status' => '2'])            ->count();        return json($data);    }    /**     * 获取菜谱订单CLASS数据     */    function orderProductList($day)    {        if (empty($day))            $this->error("数据查询繁忙，请稍后重试");        $weekArray = array("日", "一", "二", "三", "四", "五", "六");        $data['date'] = date('Y年m月d日 星期' . $weekArray[date("w")], strtotime($day));        $user_id = $this->getClientUserId();        $company_id = $this->getClientCompanyId();        $data['price_num'] = Db::name('emp_cookbook_dinner_info')            ->where(['emp_id' => $user_id, 'company_id' => $company_id, 'dinner_datetime' => $day])            ->count();        $data['price_total'] = Db::name('emp_cookbook_dinner_info')            ->where(['emp_id' => $user_id, 'company_id' => $company_id, 'dinner_datetime' => $day])            ->sum('cook_money');        $data['price_total'] = number_format($data['price_total'], 2);        if ($day == date("Y-m-d")) {            $data['dinner_status'] = 0; //可以取餐        } elseif ($day < date("Y-m-d")) {            $data['dinner_status'] = 1;//历史订餐        } else {            $data['dinner_status'] = 2;//等待取餐        }        return json($data);    }    /**     * 获取菜谱订单CART数据     */    function orderCartList($day)    {        $user_id = $this->getClientUserId();        $company_id = $this->getClientCompanyId();        $where['p.company_id'] = $company_id;        $where['p.emp_id'] = $user_id;        $where['p.dinner_datetime'] = $day;        $data['cartList'] = Db::name('emp_cookbook_dinner_info')            ->alias('p')            ->join('cookbook_base_info b', 'p.cookbook_no = b.cookbook_no and p.company_id = b.company_id', 'left')            ->join('canteen_base_info c', 'p.canteen_no = c.canteen_no and p.company_id = c.company_id', 'left')            ->join('dinner_base_info i', 'p.dinner_flag = i.dinner_flag and p.company_id = i.company_id', 'left')            ->field('p.*,cookbook_name,cookbook_image,canteen_name,dinner_name')            ->where($where)->select();        $data['cartCount'] = Db::name('emp_cookbook_dinner_info')            ->alias('p')            ->join('cookbook_base_info b', 'p.cookbook_no = b.cookbook_no and p.company_id = b.company_id', 'left')            ->join('canteen_base_info c', 'p.canteen_no = c.canteen_no and p.company_id = c.company_id', 'left')            ->join('dinner_base_info i', 'p.dinner_flag = i.dinner_flag and p.company_id = i.company_id', 'left')            ->field('p.*,cookbook_name,cookbook_image,canteen_name,dinner_name')            ->where($where)->count();        foreach ($data['cartList'] as $key => $val) {            $data['cartList'][$key]['cook_money'] = number_format($val['cook_money'], 2);        }        return json($data);    }    /**     * 个人中心     */    function getMyInfo()    {        $user_info = Db::table("Employee_List")            ->alias('a')            ->join('dept_info c', 'a.Dept_Id = c.dept_no', 'left')            ->join('t_user_manager_dept_id u', 'a.emp_id = u.u_id', 'left')            ->field('a.Emp_Name,a.Emp_image,a.Default_Tel,a.Ic_Card,dept_name,dept_type')            ->where('a.Emp_Id=:Emp_Id and a.Emp_MircoMsg_Id=:open_id and a.Emp_Status!=0',                ['Emp_Id' => $this->getClientUserId(),                    'open_id' => $this->getOpenId()])            ->find();        return json($user_info);    }    /**     * 我的余额     * @param $count     * @return string     */    function getMyMoney()    {        $data = Db::table("emp_account_remain")            ->field('sum(account_remain_money)as account_remain_money,sum(account_freeze_money) as account_freeze_money,sum(case when account_typeflag=1 then account_remain_money else 0 end )as money_sum')            ->where(['Emp_Id' => $this->getClientUserId(), 'company_id' => $this->getClientCompanyId()])            ->select();        return json($data);    }    /**     * 充值记录     */    function getRechargeList()    {        $data['list'] = Db::table("emp_account_get_money_detail")            ->join('cookbook_meal_type m', 'emp_account_get_money_detail.account_typeid = m.meal_id', 'left')            ->field('get_account_degree,get_account_money,case_date,meal_name')            ->where('emp_account_get_money_detail.Emp_Id=:Emp_Id and emp_account_get_money_detail.company_id=:company_id',                ['Emp_Id' => $this->getClientUserId(),                    'company_id' => $this->getClientCompanyId()])            ->order('case_date', 'desc')            ->select();        if (empty($data['list']))            $this->error("充值记录为空，请稍候再试！");        foreach ($data['list'] as $key => $val) {            $data['list'][$key]['case_date'] = date('Y年m月d日', strtotime($val['case_date']));            $data['list'][$key]['create_date'] = date('Y-m-d H:i', strtotime($val['case_date']));        }        $data['count'] = Db::table("emp_account_get_money_detail")            ->where('Emp_Id=:Emp_Id and company_id=:company_id',                ['Emp_Id' => $this->getClientUserId(),                    'company_id' => $this->getClientCompanyId()])            ->count();        return json($data);    }    /**     * 消费记录     */    function getRecordList()    {        $user_id = $this->getClientUserId();        $company_id = $this->getClientCompanyId();        $data['list'] = Db::table("emp_cookbook_dinner_info")            ->alias('e')            ->join('canteen_base_info b', 'e.canteen_no = b.canteen_no and e.company_id = b.company_id', 'left')            ->join('dinner_base_info d', 'e.dinner_flag = d.dinner_flag and e.company_id = d.company_id', 'left')            ->join('cookbook_base_info cb', 'e.cookbook_no = cb.cookbook_no and e.company_id = cb.company_id', 'left')            ->where(['e.emp_id' => $user_id, 'e.company_id' => $company_id])            ->order('dinner_datetime', 'desc')            ->field('e.*,canteen_name,dinner_name,cookbook_name')            ->select();        if (empty($data['list']))            $this->error("消费记录为空，请稍候再试！");        foreach ($data['list'] as $key => $val) {            $data['list'][$key]['dinner_datetime'] = date('Y年m月d日', strtotime($val['dinner_datetime']));            $data['list'][$key]['dinner_createtime'] = date('Y-m-d H:i', strtotime($val['dinner_createtime']));            $data['list'][$key]['day'] = date('Y-m-d', strtotime($val['dinner_datetime']));            $data['list'][$key]['cook_money'] = number_format($val['cook_money'], 2);            if ($val['dinner_status'] == 0) {                $data['list'][$key]['dinner_status'] = '未付款';            } elseif ($val['dinner_status'] == 1) {                $data['list'][$key]['dinner_status'] = '已付款';            } else {                $data['list'][$key]['dinner_status'] = '已取餐';            }        }        $data['count'] = Db::table("emp_cookbook_dinner_info")            ->where('emp_Id=:Emp_Id and company_id=:company_id',                ['Emp_Id' => $this->getClientUserId(),                    'company_id' => $this->getClientCompanyId()])            ->count();        return json($data);    }    /**     * 部门订餐管理     * @param $count     * @return string     */    function getmydept()    {        $user_id = $this->getClientUserId();        $company_id = $this->getClientCompanyId();        $sqlStr = "exec [up_emp_dept_dinner_count] ?,?";        $data = Db::query($sqlStr, [$company_id, $user_id]);        foreach ($data[0] as $key => $val) {            $data[0][$key]['datetime'] = substr($val['sale_datetime'], 2);        }        return json($data[0]);    }    /**     * 部门未订餐人员列表     * @param $count     * @return string     */    function getDeptEmpList()    {        $user_id = $this->getClientUserId();        $company_id = $this->getClientCompanyId();        $sqlStr = "exec [up_emp_dept_dinner_no_detail] ?,?,?,?,?";        $detail = Db::query($sqlStr, [$company_id,$_POST['sale_datetime'],$_POST['dept_id'],$_POST['dinner_flag'],$user_id]);        $data = [];        $dept = Db::table("dept_info")->where(['company_id' => $company_id, 'dept_no' => $_POST['dept_id']])->find();        $dinner = Db::table("dinner_base_info")->where(['company_id' => $company_id, 'dinner_flag' => $_POST['dinner_flag']])->find();        $data['dept_name'] = $dept['dept_name'];        $data['dinner_name'] = $dinner['dinner_name'];        $data['memberDeptList'] = $detail[0];        return json($data);    }    /**    * 获取菜谱CLASS数据    */    function deptCartProductList($day,$emp_id)    {        if(empty($emp_id)){            $this->error('人员信息不正确');        }        if (empty($day))            $this->error("数据查询繁忙，请稍后重试");        $weekArray = array("日", "一", "二", "三", "四", "五", "六");        $data['date'] = date('Y年m月d日 星期' . $weekArray[date("w")], strtotime($day));        $user_id = $emp_id; //部门人员        $company_id = $this->getClientCompanyId();        $data['dinnerBaseList'] = Db::name('dinner_base_info')->where(['company_id' => $company_id])->select();        $count = Db::name('dinner_base_info')->where(['company_id' => $company_id])->count();        $data['dinnerBaseCount'] = $this->canteen_W($count);        if (empty($data['dinnerBaseList']))            $this->error("餐次信息获取失败，请稍候再试！");        $price_total = Db::name('emp_cookbook_dinner_info')            ->field('sum(cook_money) as price_total')            ->where(['emp_id' => $user_id, 'company_id' => $company_id, 'dinner_status' => '1', 'dinner_datetime' => $day])            ->select();        $data['price_total'] = number_format($price_total['0']['price_total'], 2);        $data['price_num'] = Db::name('emp_cookbook_dinner_info')            ->where(['emp_id' => $user_id, 'company_id' => $company_id, 'dinner_status' => '1', 'dinner_datetime' => $day])            ->count();        return json($data);    }    /**     * 获取部门人员菜谱CART数据     */    function deptCartList($dinner, $day,$emp_id)    {        if(empty($emp_id)){            $this->error('人员信息不正确');        }        $user_id = $emp_id;        $company_id = $this->getClientCompanyId();        $where['p.company_id'] = $company_id;        $where['p.dinner_flag'] = $dinner;        $where['p.sale_datetime'] = $day;        $where['p.status'] = '1';        $data['cartList'] = Db::name('canteen_cookbook_price')            ->alias('p')            ->join('canteen_base_info c', 'p.canteen_no = c.canteen_no and p.company_id = c.company_id', 'left')            ->join('cookbook_base_info b', 'p.cookbook_no = b.cookbook_no and p.company_id = b.company_id', 'left')            ->join('emp_cookbook_dinner_info d', 'p.canteen_no=d.canteen_no and p.cookbook_no=d.cookbook_no and d.emp_id= :emp_id and d.company_id = p.company_id and d.dinner_status = 1 and d.dinner_datetime = p.sale_datetime and d.dinner_flag = p.dinner_flag', 'left')            ->bind(['emp_id' => $user_id])            ->field('p.*,cookbook_name,cookbook_image,canteen_name,case when isnull(emp_id,\'\')=\'\' then 0 else 1 end as order_index ')            ->order('order_index desc')            ->where($where)->select();        foreach ($data['cartList'] as $key => $val) {            $data['cartList'][$key]['cookbook_price'] = number_format($val['cookbook_price'], 2);        }        $data['cartCount'] = Db::name('canteen_cookbook_price')            ->alias('p')            ->join('canteen_base_info c', 'p.canteen_no = c.canteen_no and p.company_id = c.company_id')            ->join('cookbook_base_info b', 'p.cookbook_no = b.cookbook_no and p.company_id = b.company_id')            ->where($where)->count();        return json($data);    }    /**     * 存储部门人员商品session     */    function deptAddCart($emp_id)    {        if(empty($emp_id)){            $this->error('人员信息不正确');        }        $param = $this->request->post();        if (empty($param))            $this->error('商品添加失败');        $user_id = $emp_id;        $company_id = $this->getClientCompanyId();        $emp_list = Db::name('emp_account_remain')->where(['emp_id' => $user_id, 'company_id' => $company_id, 'account_typeflag' => '1'])->find();        if (empty($emp_list))            $this->error("余额不足!");        if (floatval($param['cookbook_price']) > floatval($emp_list['account_remain_money']))            $this->error("余额不足!");        Db::name('emp_cookbook_dinner_info')            ->where(['emp_id' => $user_id, 'company_id' => $company_id, 'dinner_status' => '1', 'dinner_datetime' => $param['day'], 'dinner_flag' => $param['dinner_flag']])            ->delete();        $price_info = Db::name('canteen_cookbook_price')            ->where(['price_id' => $param['price_id'], 'sale_datetime' => $param['day'], 'dinner_flag' => $param['dinner_flag']])            ->find();        if (!empty($price_info)) {            $data['emp_id'] = $user_id;            $data['company_id'] = $company_id;            $data['canteen_no'] = $price_info['canteen_no'];            $data['cookbook_no'] = $price_info['cookbook_no'];            $data['dinner_flag'] = $price_info['dinner_flag'];            $data['dinner_quantity'] = 1;            $data['cook_money'] = $price_info['cookbook_price'];            $data['dinner_datetime'] = $param['day'];            $data['dinner_createtime'] = date("Y-m-d H:i:s");            $position = Db::name('canteen_cookbook_window_position')                ->where(['sale_datetime' => $param['day'], 'canteen_no' => $price_info['canteen_no'], 'cookbook_no' => $price_info['cookbook_no'], 'dinner_flag' => $price_info['dinner_flag'], 'company_id' => $company_id])->find();            $data['dinner_machine_no'] = $position['windows_no'];  //窗口号            $data['dinner_from_type'] = '微信';            $data['dinner_status'] = 1;            $data['dinner_start_type'] = 2; //部门指定            Db::name('emp_cookbook_dinner_info')->insert($data);        } else {            $this->error("菜品添加订单失败!");        }        $price_total = Db::name('emp_cookbook_dinner_info')            ->field('sum(cook_money) as price_total')            ->where(['emp_id' => $user_id, 'company_id' => $company_id, 'dinner_status' => '1', 'dinner_datetime' => $param['day']])            ->select();        $data['price_total'] = number_format($price_total['0']['price_total'], 2);        $data['price_num'] = Db::name('emp_cookbook_dinner_info')            ->where(['emp_id' => $user_id, 'company_id' => $company_id, 'dinner_status' => '1', 'dinner_datetime' => $param['day']])            ->count();        $this->success('商品添加成功', '', $data);    }    /**     * 删除部门人员商品session     */    function deptDelCart($emp_id)    {        if(empty($emp_id)){            $this->error('人员信息不正确');        }        $param = $this->request->post();        if (empty($param))            $this->error('商品删除失败');        $user_id = $emp_id;        $company_id = $this->getClientCompanyId();        Db::name('emp_cookbook_dinner_info')            ->where(['emp_id' => $user_id, 'company_id' => $company_id, 'dinner_status' => '1', 'dinner_datetime' => $param['day'], 'dinner_flag' => $param['dinner_flag']])            ->delete();        $price_total = Db::name('emp_cookbook_dinner_info')            ->field('sum(cook_money) as price_total')            ->where(['emp_id' => $user_id, 'company_id' => $company_id, 'dinner_status' => '1', 'dinner_datetime' => $param['day']])            ->select();        $data['price_total'] = number_format($price_total['0']['price_total'], 2);        $data['price_num'] = Db::name('emp_cookbook_dinner_info')            ->where(['emp_id' => $user_id, 'company_id' => $company_id, 'dinner_status' => '1', 'dinner_datetime' => $param['day']])            ->count();        $this->success('商品删除成功', '', $data);    }    /**     * 餐厅宽度比     */    function canteen_W($count)    {        switch ($count) {            case 1:                $count = 'W';                return $count;            case 2:                $count = 'C6';                return $count;            case 3:                $count = 'C4';                return $count;            case 4:                $count = 'C3';                return $count;            default:                $count = 'W2';                return $count;        }    }    /**     * 修改密码     */    public function changePw()    {        $opassword = $this->request->post('opassword', '', 'trim');        $npassword = $this->request->post('npassword', '', 'trim');        $qpassword = $this->request->post('qpassword', '', 'trim');        (empty($npassword) || strlen($npassword) < 4) && $this->error('登录密码长度不能少于4位有效字符!');        (empty($qpassword) || strlen($qpassword) < 4) && $this->error('登录密码长度不能少于4位有效字符!');        if ($npassword != $qpassword)            $this->error('新密码和确认密码不相同!');        if ($opassword == $npassword)            $this->error('新密码和旧密码不能相同!');        $user_id = $this->getClientUserId();        $company_id = $this->getClientCompanyId();        $employee = Db::name('Employee_list')->where(['Emp_Id' => $user_id, 'company_id' => $company_id, 'Emp_MircoMsg_Upwd' => md5($opassword)])->find();  //用户登录表        if (empty($employee))            $this->error('旧密码不正确!');        Db::name('Employee_list')->where(['Emp_Id' => $user_id, 'company_id' => $company_id])->update(['Emp_MircoMsg_Upwd' => md5($qpassword)]);  //用户登录表        $this->success('密码修改成功!', 'index/index/me');    }    /**     * 注销登录     */    function logout()    {        //注销本站        //删除数据库中的openid        $userinfo = Db::table("Employee_list")->where('Emp_MircoMsg_Id=:custopenid', ['custopenid' => $this->getOpenId()])->find();        if ($userinfo) {            $openid['Emp_MircoMsg_Id'] = '';            Db::name('Employee_list')->where('Emp_MircoMsg_Id', $this->getOpenId())->update($openid);        }        $data['company_id'] = $this->getClientCompanyId();        $this->clearLoginSession();        $this->success('退出登录成功！', '@index/login', $data);    }}