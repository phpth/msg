<?php
namespace Sms\src;
use Exception;

class ChuangLan implements \Sms\Smsinf
{
    /**
     * @var array 两种类型的短信接口url
     */
    protected $ip_fix = array(156, 169,158);
    //发送数据
    protected $send_data ;
    //请求地址
    protected $url ;
    //实例化基地址
    protected $base_url ;
    //new url
    protected $https_url = 'https://sapi.253.com';

    public $status ;

    public $is_arrears ;
    /**
     * @param $type 0普通短信，1营销短信
     * SmsApi constructor.
     * @param int $type
     * @param array $account
     * @param boolean $use_domain
     * @throws Exception
     */
    public function __construct($type = 1,array $account ,$use_domain=false )
    {
        $this->send_data['pswd'] = $account['password'];
        $this->send_data['account'] = $account['account'];
        if(isset($this->ip_fix[$type]))
        {
            if ($use_domain && in_array($type,array(0,2)))
            {
                $this->base_url = $this->https_url.'/msg/';
            }
            else
            {
                $this->base_url = 'http://222.73.117.'.($this->ip_fix[$type]).'/msg/';
            }
        }
        else
        {
            throw new Exception('不支持的短信账号类型！');
        }
    }

    /**
     * @param $mobile
     * @param $msg
     * @param string $extno
     * @param string $status
     * @return array
     * @throws Exception
     */
    public function send($mobile, $msg,$extno = '',$status='true')
    {
        $this->send_data['mobile'] = $mobile;
        $this->send_data['msg'] = $msg;
        $this->send_data['extno'] = $extno;
        $this->send_data['needstatus'] = $status == 'false'?:'true';
        $this->url = $this->base_url.'HttpBatchSendSM';
        return $this->curlPost($this->send_data);
    }

    /**
     * 查询余额
     * @return array
     * @throws Exception
     */
    public function query()
    {
        $this->url = $this->base_url.'QueryBalance';
        return $this->curlPost($this->send_data);
    }

    public function getLastStatus()
    {
        // TODO: Implement getStatus() method.
    }

    public function getMsgStatusByMSGID($msgid)
    {
        // TODO: Implement getMsgStatusByMSGID() method.
    }

    /**
     * 是否欠费 true 代表欠费
     * @return mixed
     */
    public function isArrears()
    {
        return $this->is_arrears;
    }

    /**
     * 结果处理
     * @param $result
     * @return array
     */
    protected function execResult($result)
    {
        $result = preg_replace('/<\/{0,1}\w+>/','',$result);
        $result = preg_split("/[,\r\n]/", $result);
        return $result;
    }

    /**
     * 接口调用
     * @param $postFields
     * @return array
     * @throws Exception
     */
    private function curlPost( $postFields)
    {
        $postFields = http_build_query($postFields);
        $ch = curl_init();
        curl_setopt ($ch, CURLOPT_POST, 1);
        curl_setopt ($ch, CURLOPT_HEADER, 0);
        curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt ($ch, CURLOPT_URL, $this->url);
        curl_setopt ($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt ( $ch , CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt ( $ch , CURLOPT_SSL_VERIFYHOST, 2);
        $result = curl_exec($ch);
        if(curl_errno($ch))
        {
            throw new \Exception(curl_error($ch));
        }
        else
        {
            $res = $this->execResult($result);
            $res['res'] = $this->getMessage($res);
            $res['msg'] = $this->send_data['msg'];
            $this->status = $res[1] ;
            if($res[1] == 109)
            {
                $this->is_arrears = 1 ;
            }
            return $res;
        }
    }

    /**
     * 状态说明
     * @param $status
     * @return mixed
     */
    protected function getMessage($status)
    {
        $array = array(
            '0' => '成功提交到服务器',
            '101' => '无此用户',
            '102' => '密码错误',
            '103' => '提交过快',
            '104' => '系统忙',
            '105' => '敏感短信',
            '106' => '消息长度错',
            '107' => '包含错误手机号码',
            '108' => '手机号码个数错',
            '109' => '无发送额度',
            '110' => '不在发送时间内',
            '111' => '超出该账户当月发送额度限制',
            '112' => '无此产品',
            '113' => 'extno格式错（非数字或者长度不对）',
            '115' => '自动审核驳回',
            '116' => '签名不合法，未带签名（用户必须带签名的前提下）',
            '117' => 'IP地址认证错,请求调用的IP地址不是系统登记的IP地址',
            '118' => '用户没有相应的发送权限',
            '119' => '用户已过期',
            '120' => '测试内容不是白名单'
        );
        if(isset($status[1])&& isset($array[$status[1]]))
        {
            return $array[$status[1]];
        }
        else
        {
            file_put_contents('./chaunglan_error_request.log',join(',',$status)."\r\n",FILE_APPEND|LOCK_EX );
            return '未知服务器错误:'.join(',',$status);
        }
    }


    //魔术获取
    public function __get($name)
    {
        return $this->$name;
    }

    //魔术设置
    public function __set($name, $value)
    {
        $this->$name = $value;
    }
}