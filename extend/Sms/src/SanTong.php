<?php
namespace Sms\src;
use Exception;

/**
 * 三通批量短信发送
 * Class SanTong
 * @package Sms\src
 */
class SanTong implements \Sms\Smsinf
{
    protected $url_base = 'http://www.dh3t.com/json/sms' ;
    protected $base_data ;
    protected $is_Arrears;
    protected $sign = '【玖融网】';
    protected $data ;

    public function __construct($type,$account)
    {
        $this->base_data['account'] = $account['account'];
        $this->base_data['password'] = md5($account['password']);
    }

    public function send($to, $content)
    {
        $this->data = $this->base_data;
        $this->data['msgid'] = $this->createMsgId();
        $this->data['content'] = $content;
        $this->data['sign'] = $this->sign;
        $this->data['sendtime'] = date('YmdHi');
        $this->data['phones'] = $to;
        try{
            $res = $this->request(json_encode($this->data,JSON_UNESCAPED_UNICODE),$this->url_base.'/submit');
            if($res)
            {

            }
        }
        catch (Exception $e)
        {
            $res = array('error'=>'','info'=>'');
        }
    }

    /**
     * 批量短信接口
     * @param array $data
     */
    /**
     * @param array $data | data = array( $to => $content,$to1 = $content )
     * @throws Exception
     */
    public function batchSend( array $data )
    {
        $this->data = $this->base_data;
        $num = count($data ) ;
        if($num>500)
        {
            throw new Exception('can not more 500! ');
        }
        foreach($data as $k=>$v)
        {
            $this->data['data'][] = array(
                'msgid'=>''
                );
        }

    }

    public function getLastStatus()
    {
        // TODO: Implement getLastStatus() method.
    }

    public function query()
    {
        // TODO: Implement query() method.
    }

    public function getMsgStatusByMSGID($msgid)
    {
        // TODO: Implement getMsgStatusByMSGID() method.
    }

    public function isArrears()
    {
        // TODO: Implement isArrears() method.
    }

    protected function createMsgId()
    {

    }

    /**
     * 字符串化
     * @param $arr
     * @return array
     */
    protected function toString($arr)
    {

        if(is_array($arr))
        {
            foreach($arr as $k=>$v)
            {
                $arr[(string)$k] = $this->toString($v);
            }
        }
        else{
            return $arr ;
        }
    }

    protected function request($data,$url)
    {
        $ch = curl_init ( $url );
        curl_setopt ( $ch, CURLOPT_POST, 1 );
        curl_setopt ( $ch, CURLOPT_HEADER, 0 );
        curl_setopt ( $ch, CURLOPT_FRESH_CONNECT, 1 );
        curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt ( $ch, CURLOPT_FORBID_REUSE, 1 );
        curl_setopt ( $ch, CURLOPT_TIMEOUT, 30 );
        curl_setopt ( $ch, CURLOPT_HTTPHEADER, array ('Content-Type: application/json; charset=utf-8', 'Content-Length: ' . strlen ( $data ) ) );
        curl_setopt ( $ch, CURLOPT_POSTFIELDS, $data );
        $ret = curl_exec ( $ch );
        if(curl_errno($ch))
        {
            throw new \Exception(curl_error($ch));
        }
        curl_close ( $ch );
        return json_encode($ret,true);
    }

}