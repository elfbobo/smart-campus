<?phpnamespace app\index\controller;use controller\ApiBase;use think\Db;class Index extends ApiBase{    protected $checkAuth = true;    /**     * 首页     */    public function index()    {        return $this->fetch();    }    /**     * 今日菜谱     */    public function menu()    {        return $this->fetch();    }    /**     * 个人中心     */    public function me()    {        return $this->fetch();    }    /**     * 下单     */    public function cart($day)    {        if($day <= date("Y-m-d", strtotime('+0 day'))){ //暂时不增加提前订单            $param['day'] = $day;            $this->redirect('@index/index/orderInfo', $param);        }        return $this->fetch();    }    /**     * 用餐记录     */    public function orderList()    {        return $this->fetch();    }    /**     * 订单详情     */    public function orderInfo()    {        return $this->fetch();    }    /**     * 个人信息     */    public function myInfo()    {        return $this->fetch();    }    /**     * 密码修改     */    public function changePw()    {        return $this->fetch();    }    /**     * 我的余额     */    public function money()    {        return $this->fetch();    }    /**     * 部门管理     */    public function dept()    {        return $this->fetch();    }    /**     * 部门未订餐人员管理     */    public function deptMember()    {        return $this->fetch();    }}