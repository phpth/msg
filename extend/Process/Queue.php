<?php
namespace Process;
use Redis;
use \Exception;
class Queue
{
    protected $client;

    /**
     * Queue constructor.
     * @param $config
     * @param string $type
     * @throws Exception
     */
    public function __construct($config , $type = 'redis')
    {
        $this->client = new $type();
        if(!$this->client->connect($config['host'],$config['port']))
        {
            throw new Exception("$type 无法连接");
        }
        if(!$this->client->auth($config['auth']))
        {
            throw new Exception("$type 无法连接");
        }
    }

    /**
     * 入列
     * @param $key
     * @param $value
     * @return mixed
     */
    public function push($key,$value)
    {
       return $this->client->lPush($key,$value);
    }

    /**
     * 安全出列
     * @param $key
     * @param string $key_copy
     * @return string
     */
    public function pop($key,$key_copy = MOBILE_QUEUE_COPY)
    {
        #$this->client = new Redis();
        return $this->client->rpoplpush($key,$key_copy);
    }

    /**
     * 已经确定完成消费
     * @param $value
     * @param string $key_copy
     * @return bool
     */
    public function dealer($value,$key_copy = MOBILE_QUEUE_COPY)
    {
        #$this->client = new Redis();
        #todo: 完成确定消费逻辑
        return $this->client->lRem($key_copy, $value, 1);
    }

    /**
     * 删除队列
     * @param $key
     * @param string $key_copy
     * @return bool
     */
    public function delete($key,$key_copy = MOBILE_QUEUE_COPY)
    {
        return $this->client->delete($key) && $this->client->delete($key_copy);
    }

    /**
     *
     */
    public function rePush()
    {

    }
}