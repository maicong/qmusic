<?php
/**
 * 客户端适配器
 */
class Http_Client
{
    /**
     * 方法名
     *
     * @access protected
     * @var string
     */
    protected $method = 'GET';

    /**
     * 传递参数
     *
     * @access protected
     * @var string
     */
    protected $query;

    /**
     * 设置超时
     *
     * @access protected
     * @var string
     */
    protected $timeout = 3;

    /**
     * 需要在body中传递的值
     *
     * @access protected
     * @var array
     */
    protected $data = array();

    /**
     * 文件列表
     *
     * @access protected
     * @var array
     */
    protected $files = array();

    /**
     * 头信息参数
     *
     * @access protected
     * @var array
     */
    protected $headers = array();

    /**
     * cookies
     *
     * @access protected
     * @var array
     */
    protected $cookies = array();

    /**
     * 协议名称及版本
     *
     * @access protected
     * @var string
     */
    protected $rfc = 'HTTP/1.1';

    /**
     * 请求地址
     *
     * @access protected
     * @var string
     */
    protected $url;

    /**
     * 主机名
     *
     * @access protected
     * @var string
     */
    protected $host;

    /**
     * 前缀
     *
     * @access protected
     * @var string
     */
    protected $scheme = 'http';

    /**
     * 路径
     *
     * @access protected
     * @var string
     */
    protected $path = '/';

    /**
     * 设置ip
     *
     * @access protected
     * @var string
     */
    protected $ip;

    /**
     * 端口
     *
     * @access protected
     * @var integer
     */
    protected $port = 80;

    /**
     * 回执头部信息
     *
     * @access protected
     * @var array
     */
    protected $responseHeader = array();

    /**
     * 回执代码
     *
     * @access protected
     * @var integer
     */
    protected $responseStatus;

    /**
     * 回执身体
     *
     * @access protected
     * @var string
     */
    protected $responseBody;

    /**
     * 服务端参数
     *
     * @access private
     * @var array
     */
    private $_server = array();

    /**
     * 来源页
     *
     * @access private
     * @var string
     */
    private $_referer = NULL;

    /**
     * 判断适配器是否可用
     *
     * @access public
     * @return boolean
     */
    public static function isAvailable()
    {
        return true;
    }

    /**
     * 设置方法名
     *
     * @access public
     * @param string $method
     * @return Http_Client
     */
    public function setMethod($method)
    {
        $this->method = $method;
        return $this;
    }

    /**
     * 设置指定的COOKIE值
     *
     * @access public
     * @param string $key 指定的参数
     * @param mixed $value 设置的值
     * @return Http_Client
     */
    public function setCookie($key, $value)
    {
        $this->cookies[$key] = $value;
        return $this;
    }

    /**
     * 设置传递参数
     *
     * @access public
     * @param mixed $query 传递参数
     * @return Http_Client
     */
    public function setQuery($query)
    {
        $query = is_array($query) ? http_build_query($query) : $query;
        $this->query = empty($this->query) ? $query : $this->query . '&' . $query;
        return $this;
    }

    /**
     * 设置需要POST的数据
     *
     * @access public
     * @param array $data 需要POST的数据
     * @return Http_Client
     */
    public function setData($data)
    {
        $this->data = $data;
        $this->setMethod('POST');
        return $this;
    }

    /**
     * 设置需要POST的文件
     *
     * @access public
     * @param array $files 需要POST的文件
     * @return Http_Client
     */
    public function setFiles(array $files)
    {
        $this->files = empty($this->files) ? $files : array_merge($this->files, $files);
        $this->setMethod('POST');
        return $this;
    }

    /**
     * 设置超时时间
     *
     * @access public
     * @param integer $timeout 超时时间
     * @return Http_Client
     */
    public function setTimeout($timeout)
    {
        $this->timeout = $timeout;
        return $this;
    }

    /**
     * 设置http协议
     *
     * @access public
     * @param string $rfc http协议
     * @return Http_Client
     */
    public function setRfc($rfc)
    {
        $this->rfc = $rfc;
        return $this;
    }

    /**
     * 设置ip地址
     *
     * @access public
     * @param string $ip ip地址
     * @return Http_Client
     */
    public function setIp($ip)
    {
        $this->ip = $ip;
        return $this;
    }

    /**
     * 设置头信息参数
     *
     * @access public
     * @param string $key 参数名称
     * @param string $value 参数值
     * @return Http_Client
     */
    public function setHeader($key, $value)
    {
        $key = str_replace(' ', '-', ucwords(str_replace('-', ' ', $key)));
        $this->headers[$key] = $value;
        return $this;
    }

    /**
     * 发送请求
     *
     * @access public
     * @param string $url 请求地址
     * @return string
     * @throws Exception
     */
    public function send($url)
    {
        $params = parse_url($url);

        if (!empty($params['host'])) {
            $this->host = $params['host'];
        } else {
            throw new Exception('Unknown Host', 500);
        }

        if (!in_array($params['scheme'], array('http', 'https'))) {
            throw new Exception('Unknown Scheme', 500);
        }

        if (!empty($params['path'])) {
            $this->path = $params['path'];
        }

        $query = empty($params['query']) ? '' : $params['query'];

        if (!empty($this->query)) {
            $query = empty($query) ? $this->query : '&' . $this->query;
        }

        if (!empty($query)) {
            $this->path .= '?' . $query;
            $params['query'] = $query;
        }

        $this->scheme = $params['scheme'];
        $this->port = ('https' == $params['scheme']) ? 443 : 80;
        $url = $this->buildUrl($params);

        if (!empty($params['port'])) {
            $this->port = $params['port'];
        }

        /** 整理cookie */
        if (!empty($this->cookies)) {
            $this->setHeader('Cookie', str_replace('&', '; ', http_build_query($this->cookies)));
        }

        $response = $this->httpSend($url);

        if (!$response) {
            return;
        }

        str_replace("\r", '', $response);
        $rows = explode("\n", $response);

        $foundStatus = false;
        $foundInfo = false;
        $lines = array();

        foreach ($rows as $key => $line) {
            if (!$foundStatus) {
                if (0 === strpos($line, "HTTP/")) {
                    if ('' == trim($rows[$key + 1])) {
                        continue;
                    } else {
                        $status = explode(' ', str_replace('  ', ' ', $line));
                        $this->responseStatus = intval($status[1]);
                        $foundStatus = true;
                    }
                }
            } else {
                if (!$foundInfo) {
                    if ('' != trim($line)) {
                        $status = explode(':', $line);
                        $name = strtolower(array_shift($status));
                        $data = implode(':', $status);
                        $this->responseHeader[trim($name)] = trim($data);
                    } else {
                        $foundInfo = true;
                    }
                } else {
                    $lines[] = $line;
                }
            }
        }

        $this->responseBody = implode("\n", $lines);
        return $this->responseBody;
    }

    public function get($key)
    {
        return isset($_GET[$key]) ? $_GET[$key] : null;
    }

    public function post($key)
    {
        return isset($_POST[$key]) ? $_POST[$key] : null;
    }

    /**
     * 获取回执的头部信息
     *
     * @access public
     * @param string $key 头信息名称
     * @return string
     */
    public function getResponseHeader($key)
    {
        $key = strtolower($key);
        return isset($this->responseHeader[$key]) ? $this->responseHeader[$key] : NULL;
    }

    /**
     * 获取回执代码
     *
     * @access public
     * @return integer
     */
    public function getResponseStatus()
    {
        return $this->responseStatus;
    }

    /**
     * 获取回执身体
     *
     * @access public
     * @return string
     */
    public function getResponseBody()
    {
        return $this->responseBody;
    }

    /**
     * 获取环境变量
     *
     * @access public
     * @param string $name 获取环境变量名
     * @return string
     */
    public function getServer($name)
    {
        if (!isset($this->_server[$name])) {
            $this->setServer($name);
        }
        return $this->_server[$name];
    }

    /**
     * 设置来源页
     *
     * @access public
     * @param string $referer 客户端字符串
     * @return void
     */
    public function setReferer($referer = NULL)
    {
        $this->_referer = (NULL === $referer) ? $this->getServer('HTTP_REFERER') : $referer;
    }

    /**
     * 获取客户端
     *
     * @access public
     * @return string
     */
    public function getReferer()
    {
        if (NULL === $this->_referer) {
            $this->setReferer();
        }
        return $this->_referer;
    }

    /**
     * 设置服务端参数
     *
     * @access public
     * @param string $name 参数名称
     * @param mixed $value 参数值
     * @return void
     */
    public function setServer($name, $value = NULL)
    {
        if (NULL == $value) {
            if (isset($_SERVER[$name])) {
                $value = $_SERVER[$name];
            } else if (isset($_ENV[$name])) {
                $value = $_ENV[$name];
            }
        }
        $this->_server[$name] = $value;
    }

    /**
     * 在http头部请求中声明类型和字符集
     *
     * @access public
     * @param string $contentType 文档类型
     * @return void
     */
    public function setContentType($contentType = 'text/html')
    {
        header('Content-Type: ' . $contentType . '; charset=UTF-8', true);
    }

    /**
     * 抛出json回执信息
     *
     * @access public
     * @param mixed $message 消息体
     * @return void
     */
    public function throwJson($message)
    {
        /** 设置http头信息 */
        $this->setContentType('application/json');
        echo json_encode($message);
        exit;
    }

    /**
     * 根据parse_url的结果重新组合url
     *
     * @access public
     * @param array $params 解析后的参数
     * @return string
     */
    public function buildUrl($params)
    {
        return (isset($params['scheme']) ? $params['scheme'] . '://' : NULL)
        . (isset($params['user']) ? $params['user'] . (isset($params['pass']) ? ':' . $params['pass'] : NULL) . '@' : NULL)
        . (isset($params['host']) ? $params['host'] : NULL)
        . (isset($params['port']) ? ':' . $params['port'] : NULL)
        . (isset($params['path']) ? $params['path'] : NULL)
        . (isset($params['query']) ? '?' . $params['query'] : NULL)
        . (isset($params['fragment']) ? '#' . $params['fragment'] : NULL);
    }

    /**
     * 发送请求
     *
     * @access public
     * @param string $url 请求地址
     * @return string
     */
    public function httpSend($url)
    {
        $ch = curl_init();

        if ($this->ip) {
            $url = $this->scheme . '://' . $this->ip . $this->path;
            $this->headers['Rfc'] = $this->method . ' ' . $this->path . ' ' . $this->rfc;
            $this->headers['Host'] = $this->host;
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_PORT, $this->port);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        /** 设置HTTP版本 */
        switch ($this->rfc) {
            case 'HTTP/1.0':
                curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
                break;
            case 'HTTP/1.1':
                curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
                break;
            default:
                curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_NONE);
                break;
        }

        /** 设置header信息 */
        if (!empty($this->headers)) {
            if (isset($this->headers['User-Agent'])) {
                curl_setopt($ch, CURLOPT_USERAGENT, $this->headers['User-Agent']);
                unset($this->headers['User-Agent']);
            }

            $headers = array();

            if (isset($this->headers['Rfc'])) {
                $headers[] = $this->headers['Rfc'];
                unset($this->headers['Rfc']);
            }

            foreach ($this->headers as $key => $val) {
                $headers[] = $key . ': ' . $val;
            }

            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        /** POST模式 */
        if ('POST' == $this->method) {
            if (!isset($this->headers['content-type'])) {
                curl_setopt($ch, CURLOPT_POST, true);
            }

            if (!empty($this->data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($this->data) ? http_build_query($this->data) : $this->data);
            }

            if (!empty($this->files)) {
                foreach ($this->files as $key => &$file) {
                    $file = '@' . $file;
                }
                curl_setopt($ch, CURLOPT_POSTFIELDS, $this->files);
            }
        }

        $response = curl_exec($ch);
        if (false === $response) {
            throw new Exception(curl_error($ch), 500);
        }

        curl_close($ch);
        return $response;
    }
}
