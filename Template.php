<?php
namespace framework;
class Template
{
	protected $tplPath; //模板文件所在路径

	protected $cachePath; //缓存文件路径

	protected $vars = []; //保存变量

	protected $validTime;
	public function __construct($tplPath = './view/', $cachePath = './cache/template/' ,$validTime = 3600)
	{
		$this->tplPath = $this->checkPath($tplPath);
		$this->cachePath = $this->checkPath($cachePath);
		$this->validTime = $validTime;
	}
	//检查目录
	protected function checkPath($dir)
	{
		$dir = rtrim($dir, '/') . '/';
		if (!is_dir($dir)) {
			mkdir($dir, 0777, true);
		}
		if (!is_readable($dir) || !is_writeable($dir)) {
			chmod($dir, 0777);
		}
		return $dir;
	}
	//分配变量
	public function assign($name, $value)
	{
		$this->vars[$name] = $value;
	}
	/**
	 * [display 处理好的html文件返回显示出来]
	 * @param  [type]  $tplFile  [拼接好的路径]
	 * @param  boolean $isExcute [需要处理的html文件]
	 * @return [type]            [返回处理完成的文件]
	 */
	public function display($tplFile, $isExcute = true)
	{
		//返回拼接好的缓存路径
		$cacheFile = $this->getCacheFile($tplFile); //cache/template/index_html.php
		//html文件的绝对路径
		$tplFile = $this->tplPath . $tplFile; //拼接模板文件路径 ./view/index.html
		//如果目录不存在则提示错误
		if (!file_exists($tplFile)) {
			exit($tplFile . '模板文件不存在');
		}
		//缓存文件不存在 缓存文件修改时间<模板文修改  缓存文件的时间+3600 < time 
		if (!file_exists($cacheFile) 
			|| filemtime($cacheFile) < filemtime($tplFile)
			|| (filemtime($cacheFile) + $this->validTime) < time()
			) {
			//获取文件的内容信息
			$file = $this->complie($tplFile);
			//创建新生成的缓存文件路劲
			$this->checkPath(dirname($cacheFile));
			//将html的内容写入生成的文件中
			file_put_contents($cacheFile, $file);
		} else {
			//更新include的文件
			$this->updateInclude($tplFile);	//绝对路径下的html文件把\改成了/
		}
		if (!empty($this->vars)) {
			//变量修改但是值不会被覆盖
			extract($this->vars);	//传来不为空的html文件,把数组的键当作变量名,把数组的值当作变量的值
		}
		if ($isExcute) {
			include $cacheFile; 	//返回生成好的文件
		}
	}
	/**
	 * [updateInclude 对html的文件进行把\改成/]
	 * @param  [type] $tplFile [需要修改的文件]
	 * @return [type]          [没有返回,被调用的方法]
	 */
	protected function updateInclude($tplFile)
	{
		$file = file_get_contents($tplFile);	//读取文件的内容
		$reg = '/\{include (.+)\}/U';
		if (preg_match_all($reg, $file, $matches)) {
			//获取{include 引入的文件名} 和文件
			$this->display($matches[1][0], false);
		}
	}

	/**
	 * [complie 替换]
	 * @param  [type] $tplFile [需要正则替换的文件]
	 * @return [type]          [替换好的内容]
	 */
	protected function complie($tplFile)
	{
		$file = file_get_contents($tplFile);
		$keys = [
				'__%%__' 	 		  => '<?php echo \1;?>',
				'${%%}'     		  => '<?php echo \1;?>',
				'{elseif %%}'		  => '<?php elseif(\1):?>',
				'{$%%}'		 		  => '<?=$\1; ?>',
				'{if %%}'		 	  => '<?php if(\1):?>',
				'{else}' 		 	  => '<?php else:?>',
				'{/if}'				  => '<?php endif;?>',
				'{switch %% case %%}' => '<?php switch(\1): case \2: ?>',
				'{case %%}'  		  => '<?php case \1:?>',
				'{break}'    		  => '<?php break;?>',
				'{/switch}'  		  => '<?php endswitch;?>',
				'{include %%}' 		  => '<?php include "\1"?>',
				'{for %%}'  		  => '<?php for(\1):?>',
				'{/for}'  			  => '<?php endfor;?>',
				'{foreach %%}' 		  => '<?php foreach(\1): ?>',
				'{/foreach}' 		  => '<?php endforeach;?>',
				'{section}' 		  =>'<?php ',
				'{/section}' 		  => '?>',
			];
		foreach ($keys as $key => $value) {
			$key = preg_quote($key, '#');	//替换符号
			$reg = '#' . str_replace('%%', '(.+)', $key) . '#U';	//替换特殊字符
			if (strpos($reg, 'include')) {
				$file = preg_replace_callback($reg, [$this,'complieInclude'], $file);	//执行一个回调显示内容
			} else {
				$file = preg_replace($reg, $value, $file);	//替换内容
			}
		}

		return $file;	//替换好的内容
	}

	protected function complieInclude($matches)
	{
		$file = $matches[1];	//html文件
		// var_dump($file);
		$this->display($file, false);
		//include header.html ===>  php include 'header_html.php'
		$cacheFile = $this->getCacheFile($file);
		return "<?php include '$cacheFile';?>";

	}
	/**
	 * [getCacheFile html文件进行拼接]
	 * @param  [type] $tplFile []
	 * @return [type]          [返回拼接好的文件]
	 */
	protected function getCacheFile($tplFile)
	{
		//ing 'cache/index/head_html.php//这返回是被引入的文件是带缓存路径的文件
		return $this->cachePath . str_replace('.', '_', $tplFile) . '.php';
	}

	//文件
	public function clearCache()
	{
		$this->clearDir($this->cachePath);
	}
	//递归删除文件
	protected function clearDir($dir)
	{
		$dir = rtrim($dir, '/') . '/';
		$dp = opendir($dir);
		while ($file = readdir($dp)) {
			if ($file == '.' || $file == '..') {
				continue;
			}
			$fileName = $dir . $file;
			if (is_dir($fileName)) {
				$this->clearDir($fileName);
			} else {
				unlink($fileName);
			}
		}
		closedir($dp);
		rmdir($dir);
	}

}
