<?php
namespace Process;
use Exception;
class Share
{
    protected $share_resource;

    protected $share_id ;

    protected $size ;

    public $tmp_file ;

    /**
     * Share constructor.
     * @param $path
     * @param single-string $project
     * @param float|int $size
     * @throws Exception
     */
    public function __construct($path,$project='a',$size = 1024*1024*20)
    {
        if(!is_string($project) && strlen($project)!= 1)
        {
            throw new Exception('project 参数必须为单个字符');
        }

        if(!is_dir($path))
        {
            if( !mkdir($path,0777,true))
            {
                throw new Exception('创建目录失败或者目录不可用！');
            }
        }
        $this->tmp_file = realpath($path)."/{$project}.tmp";
        if(file_put_contents($this->tmp_file, $this->share_id)===false)
        {
            throw new Exception('文件目录不可写!');
        }
        $this->share_id = ftok($this->tmp_file, $project[0]);
        if($this->share_id == -1)
        {
            throw new Exception('共享存储创建失败!');
        }
        $this->size = $size ;
        $this->share_resource = shm_attach($this->share_id,$this->size,0777);
        if(!is_resource($this->share_resource)){
            throw new Exception('无法创建共享存储!');
        }
    }

    /**
     * 设置变量
     * @param $var
     * @param $value
     * @return bool
     */
    public function set($var , $value)
    {
        return shm_put_var($this->share_resource, $this->stringHashInt($var), $value);
    }

    /**
     * 获取变量
     * @param $var
     * @return mixed
     */
    public function get($var)
    {
        if(!shm_has_var($this->share_resource, $this->stringHashInt($var)))
        {
            return false ;
        }
        return shm_get_var($this->share_resource, $this->stringHashInt($var));
    }

    /**
     * 删除一个变量
     * @param $var
     * @return bool
     */
    public function delete($var)
    {
        return shm_remove_var($this->share_resource,$this->stringHashInt($var)) ;
    }

    /**
     * 删除共享占用的存储
     * @return bool
     */
    public function remove()
    {
        return shm_remove($this->share_resource);
    }



    /**
     * @return float|int
     */
    public function getSize()
    {
        return $this->size;
    }

    /**
     * 映射关系
     * @param $key
     * @return int
     */
    protected function stringHashInt($key)
    {
        return  abs(crc32($key)) ;
    }

    /**
     * 适当的销毁
     */
    public function __destruct()
    {
        if($this->share_resource)
        {
            shm_detach($this->share_resource);
        }
        if(file_exists($this->tmp_file))
        {
            unlink($this->tmp_file);
        }
    }
}
