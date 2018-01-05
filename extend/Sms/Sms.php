<?php
namespace Sms;
use Exception;

class Sms
{
    private $sms_obj  ;
    public $api_list  ;

    /**
     * Sms constructor.
     * @param $api
     * @param $account
     * @param bool $type
     */
    public function __construct( $api , $account , $type =false )
    {
        //可用的api项
        $path = __DIR__.'/src' ;
        foreach(scandir($path) as $v)
        {
            if($v != '.' && $v!='..')
            {
                $api_ = explode('.', $v)[0];
                $this->api_list[strtolower($api_)] = $api_;
            }
        }
        $class = __NAMESPACE__."\\src\\".$this->api_list[strtolower($api)];
        $this->sms_obj = new $class($type ,$account );
    }

    /**
     *  信息发送
     * @param $to
     * @param $content
     * @return mixed
     */
    public function send($to , $content )
    {
        try{
            $json_res =  $this->sms_obj->send($to,$content);

        }
        catch(Exception $e)
        {

        }
    }

    public function batchSend()
    {

    }

    /**
     * 获取发送的状态
     * @return mixed
     */
    public function getLastStatus()
    {
        return $this->sms_obj->getLastStatus();
    }

    /**
     * 余额查询功能
     * @return mixed
     */
    public function query()
    {
        return  $this->sms_obj->query();
    }
}
