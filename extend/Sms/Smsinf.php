<?php
namespace Sms;
/**
 * Interface Smsinf
 * @package Sms
 */
interface Smsinf
{

    /**
     * 发送功能
     * @param $to
     * @param $content
     * @return mixed
     */
    public function send($to ,$content);

    /**
     * 查询余额
     * @return mixed
     */
    public function query();

    /**
     * 查询最后一条发送状态
     * @return mixed
     */
    public function getLastStatus();

    /**
     * 根据消息id 查询消息发送状态
     * @param $msgid
     * @return mixed
     */
    public function getMsgStatusByMSGID($msgid);

    /**
     * 是否欠费
     * @return mixed
     */
    public function isArrears();
}