<?php
/**
 * @Author jKey Lu <i@jkey.lu>
 * @licence GPL3
 */

require_once 'curl'.DIRECTORY_SEPARATOR.'curl.php';

class UpYun
{
	const HTTP_BASIC_AUTH = 0;
	const SIGNATURE_AUTH = 1;

	const API_HOST_1 = 'http://v1.api.upyun.com';
	const API_HOST_2 = 'http://v2.api.upyun.com';
	const API_HOST_3 = 'http://v3.api.upyun.com';
	const API_HOST_0 = 'http://v0.api.upyun.com';

	/**
	 * 空间名称
	 *
	 * @var string
	 */
	public $bucketname;

	/**
	 * 操作员用户名，非管理员账号
	 *
	 * @var string
	 */
	public $username;

	/**
	 * 操作员密码
	 *
	 * @var string
	 */
	public $password;

	/**
	 * Api 接口地址
	 *
	 * @var string
	 */
	public $apihost = UpYun::API_HOST_0;

	/**
	 * 认证方式
	 *
	 * @var int
	 */
	public $auth_type = UpYun::HTTP_BASIC_AUTH;

	/**
	 *
	 * @var boolen
	 */
	public $debug = false;


	private $_curl;
	private $_uri;
	private $_content_length = 0;
	private $_status_code = 0;
	private $_http_method;
	private $_auto_mkdir = true;
	private $_timeout = 30;
	private $_error;

	/**
	 * $config = array(
	 *		'username' => 'jkey',
	 *		'password' => 'pass1234',
	 *		'bucketname' => 'demobucket',
	 *		'apihost' => UpYun::API_HOST_1,
	 *		'auth_type' => UpYun::HTTP_BASIC_AUTH,
	 * );
	 *
	 * @param array $config
	 */
	public function __construct($config=array())
	{
		if ( !empty($config) ) {
			$default_config = array(
				'username' => '',
				'password' => '',
				'bucketname' => '',
				'apihost' => UpYun::API_HOST_0,
				'auth_type' => UpYun::HTTP_BASIC_AUTH,
			);

			$config = array_merge($default_config, $config);

			$this->username = $config['username'];
			$this->password = $config['password'];
			$this->bucketname = $config['bucketname'];
			$this->apihost = $config['apihost'];
			$this->auth_type = $config['auth_type'];
		}
	}

	/**
	 * 设置 curl 超时时间
	 *
	 * @param int $timeout
	 */
	public function setTimeout($timeout)
	{
		$this->_timeout = $timeout;
	}

	/**
	 *
	 * @return string
	 */
	public function error()
	{
		return $this->_error;
	}

	/**
	 *
	 * @return int
	 */
	public function statusCode()
	{
		return $this->_status_code;
	}

	/**
	 * 下载文件
	 *
	 * @param string $filename
	 * @param string $destFileName
	 * @param boolen $overwrite
	 * @return mixed
	 */
	public function download($filename, $savedFileName, $overwrite=true)
	{
		$content = $this->read($filename);
		if ( $content ) {
			if ( !file_exists($savedFileName) || $overwrite ) {
				file_put_contents($destFileName, $content);
			}

			return true;

		} else {
			return false;
		}
	}

	/**
	 * 上传本地文件
	 *
	 * @param string $filename
	 * @param string $uploadFileName
	 * @return boolen
	 */
	public function upload($filename, $uploadFileName)
	{
		$content = file_get_contents($uploadFileName);
		return $this->write($filename, $content);
	}

	/**
	 * 读取文件 upyun 上的文件
	 *
	 * @param string $filename
	 * @param resource $output
	 * @return string
	 */
	public function readFile($filename, $output=null)
	{
		$url = $this->buildUrl($filename);
		$response = $this->httpAction('GET', $url, null, $output);

		return $this->analyzeResponse($response, $output==null);
	}

	/**
	 * 把文件内容写入 upyun 中
	 *
	 * @param string $content
	 * @param string $destFileName
	 * @param string $auto_mkdir
	 * @return boolen
	 */
	public function writeFile($filename, $input, $auto_mkdir=true)
	{
		$url = $this->buildUrl($destFileName);
		$this->_auto_mkdir = $auto_mkdir;
		$response = $this->httpAction('PUT', $url, $input);

		return $this->analyzeResponse($response);
	}

	/**
	 * 删除文件
	 *
	 * @param string $filename
	 * @return boolen
	 */
	public function deleteFile($filename)
	{
		$url = $this->buildUrl($filename);
		$response = $this->httpAction('DELETE', $url, null);

		return $this->analyzeResponse($response);
	}

	/**
	 * 创建文件夹
	 *
	 * @param string $path
	 * @param boolen $auto_mkdir
	 * @return boolen
	 */
	public function mkDir($path, $auto_mkdir=true)
	{
		$url = $this->buildUrl($path, true);
		$this->_auto_mkdir = $auto_mkdir;
		$response = $this->httpAction('PUT', $url, 'folder:true');

		return $this->analyzeResponse($response);
	}

	/**
	 * 删除文件夹
	 *
	 * @param string $path
	 * @return boolen
	 */
	public function rmDir($path)
	{
		$url = $this->buildUrl($path, true);
		$response = $this->httpAction('DELETE', $url, null);

		return $this->analyzeResponse($response);
	}

	/**
	 * 获取目录文件列表
	 *
	 * @param string $path
	 * @return array
	 */
	public function readDir($path)
	{
		$url = $this->buildUrl($path, true);
		$response = $this->httpAction('GET', $url, null);

		$body = $this->analyzeResponse($response, true);

		if (!$body) {
			return false;

		} else {
			$rs = explode("\n", $response->body);
			$list = array();
			foreach ( $rs as $r ) {
				$r = trim($r);
				$l = new stdclass;
				list($l->name, $l->type, $l->size, $l->time) = explode("\t", $r);

				if ( !empty($l->time) ) {
					$l->type = ($l->type=='N' ? 'file' : 'folder');
					$l->size = intval($l->size);
					$l->time = intval($l->time);
					$list[] = $l;
				}
			}

			return $list;
		}
	}

	/**
	 * 获取 bucket 使用情况
	 *
	 * @param string $foldername
	 * @return int
	 */
	public function getBucketUsage()
	{
		return $this->getFolderUsage('/');
	}

	/**
	 * 获取某个子目录的使用情况
	 *
	 * @param string $path
	 * @return int
	 */
	public function getFolderUsage($path)
	{
		$url = $this->buildUrl($path, true);
		$response = $this->httpAction('GET', $url.'?usage', null);

		$result = $this->analyzeResponse($response, true);
		return floatval($result);
	}

	/**
	 * http 请求
	 *
	 * @param string $method
	 * @param string $url
	 * @param mixed $input
	 * @param resource $output
	 * @return CurlResponse
	 */
	protected function httpAction($method, $url, $input, $output=null)
	{
		$this->_curl = new Curl();
		$this->_curl->options['CURLOPT_TIMEOUT'] = $this->_timeout;

		if ( $input == 'folder:true' ) {
			$this->_curl->headers['folder'] = 'true';
			$input = '';
		}

		$data = null;
		$this->_content_length = @strlen($input);
		if ( $method=='PUT' || $method=='POST' ) {
			$method = 'POST';

			if ( $this->_auto_mkdir ) {
				$this->_curl->headers['mkdir'] = 'true';
			}

			if ( $input && is_resource($input) ) {
				fseek($input, 0, SEEK_END);
				$this->_content_length = ftell($input);
				fseek($input, 0);
				$this->_curl->headers['Content-Length'] = $this->_content_length;
				$this->_curl->options['CURLOPT_INFILE'] = $input;
				$this->_curl->options['CURLOPT_INFILESIZE'] = $this->_content_length;
			} else {
				$data = $input;
			}
		}

		$this->_http_method = $method;

		if ( is_resource($output) ) {
			$this->_curl->options['CURLOPT_FILE'] = $output;
		}

		$this->_curl->headers['Expect'] = '';
		$this->auth();
		return $this->_curl->request($method, $url, $data);
	}

	/**
	 * 建立 CURLOPT_URL 需要的 url
	 *
	 * @param string $path
	 * @return string
	 * @access protected
	 */
	protected function buildUrl($path, $isFolder=false)
	{
		if ( substr($path, 0, 1) != '/' ) {
			$path = '/' . $path;
		}
		if ( $isFolder && substr($path, -1)!='/' ) {
			$path .= '/';
		}

		$this->_uri = '/' . $this->bucketname . $path;

		return $this->apihost . $this->_uri;
	}

	/**
	 * 根据验证方式，做相应的设置
	 *
	 * @return void
	 */
	private function auth()
	{
		if ($this->auth_type == UpYun::HTTP_BASIC_AUTH) {
			$this->_curl->options['CURLOPT_USERPWD'] = $this->username
				. ':' . $this->password;

		} else {
			$date = gmdate('D, d M Y H:i:s \G\M\T');

			$sign = $this->_http_method
				. '&' . $this->_uri
				. '&' . $date
				. '&' . $this->_content_length
				. '&' . md5($this->password);

			$sign = md5($sign);
			$auth = 'UpYun ' . $this->username . ':' . $sign;

			$this->_curl->headers['Date'] = $date;
			$this->_curl->headers['Authorization'] = $auth;
		}
	}

	/**
	 * 分析 curl 的返回
	 *
	 * @param CurlResponse $response
	 * @param boolen $return_body
	 * @return boolen
	 */
	protected function analyzeResponse($response, $return_body=false)
	{
		if ( !$response ) {
			$this->_status_code = 0;
			$this->_error = $this->_curl->error();

		} else {
			$this->_status_code = $response->headers['Status-Code'];
			if ( $response->headers['Status-Code'] == '200' ) {
				$this->_error = '';
				return $return_body ? $response->body : true;

			} else {
				$this->_error = $response->headers['Status'];
				return false;
			}

		}	

	}

}

