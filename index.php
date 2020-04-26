<?php
ini_set('date.timezone', 'Asia/Shanghai');
error_reporting(E_ALL ^ E_NOTICE);

require "controller.php";
require "service.php";
require "model.php";
require "helper.php";

(new Application())->run();

class Application
{
    public function run()
    {
        try {
            $response = $this->handleRequest(new Request());
            $response->send();
        } catch (Exception $e) {
            return $e->getCode();
        }
    }

    /**
     * 处理请求并获取response
     * @param Request $request
     * @return Response
     * @throws ReflectionException
     */
    private function handleRequest(Request $request)
    {
        //简单路由
        $router = $_GET["r"] ?: "index";
        $c = new ReflectionClass("Controller");
        if (!$c->hasMethod($router)) {
            $router = "index";
        }
        $response = $c->getMethod($router)->invoke($c->newInstance($request));
        return $response;
    }
}

class Request
{
    private $_headers;

    private $_bodyParams;

    private $_queryParams;

    private $_rawBody;

    public function getHeader()
    {
        if (empty($this->_headers)) {
            if (function_exists('http_get_request_headers')) {
                $this->_headers = http_get_request_headers();
            } else {
                foreach ($_SERVER as $name => $value) {
                    if (strncmp($name, 'HTTP_', 5) === 0) {
                        $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                        $this->_headers[$name] = $value;
                    }
                }
            }
        }
        return $this->_headers;
    }

    public function getContentType()
    {
        if (isset($_SERVER['CONTENT_TYPE'])) {
            return $_SERVER['CONTENT_TYPE'];
        }
        return $this->getHeader()['Content-Type'];
    }

    public function getRawBody()
    {
        if ($this->_rawBody === null) {
            $this->_rawBody = file_get_contents('php://input');
        }

        return $this->_rawBody;
    }

    public function post($name = null, $defaultValue = null)
    {
        if (empty($this->_bodyParams)) {
            $rawContentType = $this->getContentType();
            $contentType = false !== ($pos = strpos($rawContentType, ';'))
                ? $contentType = substr($rawContentType, 0, $pos) : $rawContentType;
            $this->_bodyParams = 'application/json' == $contentType
                ? json_decode($this->getRawBody(), true) : $_POST;
        }

        return $name === null
            ? $this->_bodyParams
            : (isset($this->_bodyParams[$name]) ? $this->_bodyParams[$name] : $defaultValue);
    }

    public function get($name = null, $defaultValue = null)
    {
        if ($this->_queryParams === null) {
            $this->_queryParams = $_GET;
        }
        return $name === null
            ? $this->_queryParams
            : (isset($this->_queryParams[$name]) ? $this->_queryParams[$name] : $defaultValue);
    }
}

class Response
{

    private $data;

    public function send()
    {
        echo $this->data;
    }

    /**
     * ajax返回json数据
     * @param $res
     * @return Response
     */
    private static function ajaxReturn($res)
    {
        $response = new self();
        header("Content-type:text/json");
        $response->data = json_encode($res);
        return $response;
    }

    public static function ajaxJson($code, $msg = null, $data = null)
    {
        $res = ["code" => $code, "msg" => $msg, "data" => $data];
        return self::ajaxReturn($res);
    }

    public static function ajaxSuccess($msg = null, $data = null)
    {
        return self::ajaxJson(ResponseCode::CODE_SUCCESS, $msg, $data);
    }

    public static function ajaxError($msg)
    {
        return self::ajaxJson(ResponseCode::CODE_ERROR, $msg);
    }

    public static function notLogin()
    {
        return self::ajaxJson(ResponseCode::CODE_NOT_LOGIN, "请重新登录后再试");
    }

    /**
     * 渲染html页面
     * @param $html_file
     * @param array $params
     * @return Response
     */
    public static function htmlReturn($html_file, $params = [])
    {
        $response = new self();
        header("Content-Type:text/html;charset=utf-8");
        ob_start();
        ob_implicit_flush(false);
        extract($params, EXTR_OVERWRITE);
        require $html_file;
        $response->data = ob_get_clean();
        return $response;
    }
}

class ResponseCode
{
    const CODE_SUCCESS = 0;

    const CODE_ERROR = -1;

    const CODE_NOT_LOGIN = -2;

    const CODE_ALREADY_LOTTERY = 1;
}
