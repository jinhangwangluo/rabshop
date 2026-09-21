<?php
// +----------------------------------------------------------------------
// | Rabbit多商户商城系统
// +----------------------------------------------------------------------
// | Copyright (c) 2022~2026 https://www.rabshop.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed Rabbit多商户商城系统不是自由软件，未经许可不能去掉Rabbit相关版权
// +----------------------------------------------------------------------
// | Author: Rabbit Team <admin@rabshop.cn>
// +----------------------------------------------------------------------

declare (strict_types=1);

namespace app\common\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use Throwable;

/**
 * Class Key
 * @package app\common\command
 */
class Tool extends Command
{

    public $config;
    public $log = '创建记录:'.PHP_EOL;
    protected function configure()
    {
        $this->setName('tool:create')
            ->addArgument('table', Option::VALUE_REQUIRED, '表名称')
            ->addOption('path', 'p',Option::VALUE_REQUIRED, '创建路径,不填则创建在最外层')
            ->addOption('key', 'k',Option::VALUE_OPTIONAL, '主键名称')
            ->setDescription('根据表名生成代码,model、service、controller');
    }

    protected function execute(Input $input, Output $output)
    {
        $options['table'] = $input->getArgument('table');
        if (!preg_match('/^[a-z_]+$/', $options['table'])){
            $output->error('表名称格式不正确:仅包含小写字母和下划线');
            $output->error('例: php think createTool table_name -p system/merchant -c admin -k id');
            return;
        }
        $options['path'] = $input->getOption('path');
        $options['key'] = $input->getOption('key');
        $output->writeln('开始执行');
        $output->writeln('');
        $this->create($options);
        $output->writeln($this->log);
        $output->writeln('执行完成');
    }

    public function create($config)
    {
        $table = $config['table'];
        $prefix = env('database.prefix', '');
        $len = strlen($prefix);
        $_prefix = substr($table,0,$len);
        if ($prefix == $_prefix) {
            $table = substr($table,$len);
        }
        $config['table'] = $table;
        $class_name = str_replace(' ', '', ucwords(str_replace('_', ' ', $table)));
        $config['class_name'] = $class_name;
        $path = explode('/', $config['path']);
        $config['namespace_path']  = implode('\\',$path);
        $this->config = $config;

        //生成model
        $this->createModel();
    
        $this->getFiled();
        
        // //生成service
        $this->createService();

        //生成验证器
        if ($this->config['rule']) {
            $this->createValidate();
        }
        //生成controller
        $this->createController();
    }

    public function createValidate()
    {
        $file_path = app_path().'validate/'.$this->config['path'];
        $file_name = $this->config['class_name'].'Validate.php';
        
        $content = '<?php'.PHP_EOL.PHP_EOL;
        $content .= "namespace app\\validate\\{$this->config['namespace_path']};

use think\Validate;

class  {$this->config['class_name']}Validate extends Validate
{
    /**
     * @var string[]
     */
    protected \$rule = {$this->config['rule']}

    /**
     * @var string[]
     */
    protected \$message = [
    ];
}";
        $this->createFile($file_path, $file_name,$content);
    }

    public function getFiled()
    {
        try{
            $model = "\app\common\model\\{$this->config['namespace_path']}\\{$this->config['class_name']}";
            $model = new $model();
            $fields = $model->getTableFields();
    
            if ($fields) {
                $fields = array_splice($fields, 1,-3);
                if(count($fields)) {
                    $p = '['.PHP_EOL;
                    $r = '['.PHP_EOL;
                    foreach($fields as $v) {
                        $r .= "        '".$v."'=> 'require',".PHP_EOL; 
                        $p .= '            ["'.$v.'" , ""],'.PHP_EOL;
                    }
                    $r .= '    ];';
                    $p .= '        ]';
                    $this->config['rule'] = $r;
                    $this->config['params'] = $p;
                } else {
                    $this->config['rule'] = '';
                    $this->config['params'] = '[]';
                }
            }
        } catch(Throwable $exception) {
            $this->config['rule'] = '';
            $this->config['params'] = '[]';
        }
    }

    public function createModel()
    {
        $file_path = app_path().'common/model/'.$this->config['path'];
        $file_name = $this->config['class_name'].'.php';
        $id = $this->config['key'] ?: 'id';
        $content = '<?php'.PHP_EOL.PHP_EOL;
        $content .= "namespace app\common\model\\{$this->config['namespace_path']};
        
use app\common\model\Base;
use think\model\concern\SoftDelete;

class {$this->config['class_name']} extends Base
{
    use SoftDelete;

    /**
     * @var string
     */
    protected \$name = '{$this->config['table']}';

    /**
     * @var string
     */
    protected \$pk = '{$id}';
    
    /**
     * 搜索
     * @param array \$where
     * @return {$this->config['class_name']}|\\think\db\BaseQuery
     */
    public function search(array \$where)
    {
        return \$this
        ->when(isset(\$where['{$id}']) && '' !== \$where['{$id}'],
            fn(\$q) => \$q->where('{$id}', \$where['{$id}'])
        )
        ->when(!empty(\$where['date']),
            fn(\$q) => set_model_time(\$q, \$where['create_time'])
        )
        ;
    }
}
";
        $this->createFile($file_path, $file_name,$content);
    }

    public function createService()
    {
        $file_path = app_path().'common/service/'.$this->config['path'];
        $file_name = $this->config['class_name'].'Service.php';
        $id = $this->config['key'] ?: 'id';
        $content = '<?php'.PHP_EOL.PHP_EOL;
        $content .= "namespace app\common\service\\{$this->config['namespace_path']};
   
use think\annotation\Inject;     
use app\common\service\BaseService;
use app\common\model\\{$this->config['namespace_path']}\\{$this->config['class_name']};

class {$this->config['class_name']}Service extends BaseService
{
    protected function setModel(): string
    {
        return {$this->config['class_name']}::class;
    }

    /**
     *  获取后台列表
     * @param array \$where
     * @return array
     */
    public function getAdminList(array \$where)
    {
        [\$page, \$limit] = \$this->getPageValue();

        \$model = \$this->getModel()->search(\$where);
        \$count = \$model->count();
        \$list = \$model->order('{$id}', 'desc')->page(\$page, \$limit)->select()->toArray();
        return compact('count', 'list');
    }
}
";
        $this->createFile($file_path, $file_name,$content);
    }

    public function createController()
    {
        $file_path = app_path().'controller/admin/'.$this->config['path'];
        $file_name = $this->config['class_name'].'.php';
        $id = $this->config['key'] ?: 'id';
        $content = '<?php'.PHP_EOL.PHP_EOL;
        $content .= "namespace app\controller\admin\\{$this->config['namespace_path']};
   
use think\annotation\Inject;     
use app\controller\admin\Auth;
use app\common\service\\{$this->config['namespace_path']}\\{$this->config['class_name']}Service;
";

        if ($this->config['params']) {
            $content .= "use app\\validate\\{$this->config['namespace_path']}\\{$this->config['class_name']}Validate;
            
";
        } 

        $content .= "
class {$this->config['class_name']} extends Auth
{
    /**
     * @var {$this->config['class_name']}Service
     */
    #[Inject]
    protected {$this->config['class_name']}Service \$service;

    /**
     *  获取后台列表
     * @param array \$where
     * @return array
     */
   public function list()
    {
        \$where = \$this->request->getMore([
            ['{$id}', ''],
            ['date','']
        ]);
        return \$this->success(\$this->service->getAdminList(\$where));
    }
    
    public function save()
    {
        \$data = \$this->checkParams();
        \$this->service->save(\$data);
        return \$this->success();
    }
    
    public function edit(\$id)
    {
        \$data = \$this->checkParams();
        \$this->service->update(\$id, \$data);
        return \$this->success();
    }

    public function status(\$id, \$status)
    {
        \$status = \$status == 1 ? : 0;
        \$this->service->update(\$id, ['status' => \$status]);
        return \$this->success();
    }

    public function delete(\$id)
    {
        if (!\$id) {
            return \$this->fail('缺少参数');
        }
        \$this->service->destroy(\$id);
        return \$this->success();
    }
";
    if ($this->config['params']) {
        $content .= "
    public function checkParams()
    {
        \$data = \$this->request->postMore({$this->config['params']});
        \$this->validate(\$data, {$this->config['class_name']}Validate::class);
        return \$data;
    }
";
    }
        $content .="}";
        $this->createFile($file_path, $file_name,$content);
    }

    public function createFile($path,$name, $content)
    {
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
        $file_path = $path.'/'.$name;
        if (file_exists($file_path)) {
            throw new \Exception('文件已存在:'.$file_path);
        }
        try{
            file_put_contents($file_path, $content);
            $this->log .= $file_path. PHP_EOL;
            return [true, '创建成功'];
        }catch (\Exception $exception) {
            throw new \Exception($exception->getMessage());
        }
    }

}
