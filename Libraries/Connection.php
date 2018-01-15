<?php

namespace App\Http\Controllers\Api\Libraries;

use Common;

class Connection
{


    public $setting;
    public $url;
    public $request;
    public $response;
    public $default_settings = [
        'debug' => False,
        'method' => 'GET',
        'proxy_host' => null,
        'timeout' => 20,
        'proxy_port' => 80,
    ];

    public function __construct($arg = [])
    {
        $this->setting = array_merge($this->default_settings, $arg);
        $this->__init();
    }


    /*
     * 前置初始化方法
     */
    public function __init()
    {
        return null;
    }


    /**
     * 构建请求
     * @param string $verb
     * @param array $data
     * @param string $method
     * @param null $files
     */
    public function buildRequest($verb, $request_data, $method = null, $files = null)
    {
        $url = $this->buildRequestUrl($verb);
        $headers = $this->buildRequestHeaders($verb);
        $data = $this->buildRequestData($verb, $request_data);
        $method = ($method !== null) ? strtoupper($method) : null;

        $this->request = [
            'url' => $url,
            'data' => $data,
            'headers' => $headers,
            'files' => $files,
            'method' => $method,
        ];
    }


    /**
     * 构建请求 data
     * @param string $verb
     * @param array $data
     * @return string
     */
    public function buildRequestData($verb, $data)
    {
        return "";
    }


    /**
     * 构建请求 header
     * @param string $verb
     * @return array
     */
    public function buildRequestHeaders($verb)
    {
        return [];
    }


    /**
     * 构建请求 url
     * @param string $verb
     * @return string
     */
    public function buildRequestUrl($verb)
    {
        return $this->url . $verb;
    }


    /**
     * 执行请求
     * @param string $verb
     * @param array $data
     * @param string $method
     * @param null $files
     * @return mixed
     */
    public function execute($verb, $data = null, $method = null, $files = null)
    {
        $this->buildRequest($verb, $data, $method, $files);
        $this->executeRequest();
        return $this->obj($this->response);
    }


    /**
     * 执行请求细节
     */
    public function executeRequest()
    {
        try {
            $this->response = Common::curlRequest($this->request['url'], $this->request['method'], $this->request['headers'], $this->request['data']);
        } catch (\Exception $e) {
            $this->response = $this->executeRequestBackup(
                $this->request['url'],
                $this->request['method'],
                $this->request['headers'],
                $this->request['data']
            );
        }
    }


    /**
     * 执行请求细节备用方法
     * @param $url
     * @param $method
     * @param $header
     * @param $data
     * @return mixed
     */
    public function executeRequestBackup($url, $method = 'POST', $header = [], $data = '')
    {
        $connection = curl_init();
        curl_setopt($connection, CURLOPT_URL, $url); //请求的URL地址
        curl_setopt($connection, CURLOPT_SSL_VERIFYPEER, false); //使用HTTPS协议，服务器端不需要身份验证
        curl_setopt($connection, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($connection, CURLOPT_HTTPHEADER, $header); //http header
        curl_setopt($connection, CURLOPT_RETURNTRANSFER, true);
        switch ($method) {
            case 'GET':
                break;
            case 'POST':
                curl_setopt($connection, CURLOPT_POST, true);
                curl_setopt($connection, CURLOPT_POSTFIELDS, $data); //设置请求体，提交数据包
                break;
            case 'PUT':
                curl_setopt($connection, CURLOPT_CUSTOMREQUEST, 'PUT');
                curl_setopt($connection, CURLOPT_POSTFIELDS, $data); //设置请求体，提交数据包
                break;
            case 'DELETE':
                curl_setopt($connection, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
        }
        $response = curl_exec($connection);
        curl_close($connection);
        return $response;
    }


    /**
     * array 转 xml_string
     * @param array $array
     * @return string
     */
    public function xml($array)
    {
        // 解决 xml 标签可重复但数组 key 不能重复的问题

        if (!$array) {
            return "";
        }
        $xml = "";
        foreach ($array as $key => $val) {
            $xml .= "<" . $this->xmlFilterKey($key) . ">";
            if (is_array($val)) {
                $xml .= $this->xml($val);
            } else {
                if (is_int($val)) {
                    $xml .= $val;
                } else {
                    $xml .= "<![CDATA[" . $val . "]]>";
                }
            }
            $xml .= "</" . $this->xmlFilterKey($key) . ">";
        }
        return $xml;
    }


    /**
     * 预处理 xml_string 中的 key
     * @param string $key
     * @return mixed|string
     */
    public function xmlFilterKey($key)
    {
        if (substr($key, 0, 1) === "_") {
            $key = explode('_', $key)[1];
        }
        return $key;
    }


    /**
     * xml_string 转 array
     * @param string $xml
     * @return mixed
     */
    public function obj($xml)
    {
        try {
            return json_decode(json_encode(simplexml_load_string($xml)), TRUE);
        } catch (\Exception $e) {
            return $xml;
        }
    }


    /**
     * 筛选接口返回数据
     *
     * @param array $data 接口原始数据
     * @param array $list 筛选 key 列表
     * @return array
     */
    public function filterResponse($data, $list)
    {
        $array_new = [];
        foreach ($list as $l) {
            $keys = explode('.', $l);
            try {
                $array_new = $this->filterResponseValue($data, $array_new, $keys);
            } catch (\Exception $e) {
                $array_new = $this->filterResponseNull($array_new, $keys);
            }
        }
        return $array_new;
    }


    /**
     * 接口数据填充新值
     *
     * @param array $array 数据来源
     * @param array $array_new 新数组
     * @param array $keys 键值
     * @return mixed
     */
    public function filterResponseValue($array, $array_new, $keys)
    {
        $level = count($keys);
        switch ($level) {
            case 1:
                $array_new[$keys[0]] = $array[$keys[0]];
                break;
            case 2:
                $array_new[$keys[0]][$keys[1]] = $array[$keys[0]][$keys[1]];
                break;
            case 3:
                $array_new[$keys[0]][$keys[1]][$keys[2]] = $array[$keys[0]][$keys[1]][$keys[2]];
                break;
            case 4:
                $array_new[$keys[0]][$keys[1]][$keys[2]][$keys[3]] = $array[$keys[0]][$keys[1]][$keys[2]][$keys[3]];
                break;
            case 5:
                $array_new[$keys[0]][$keys[1]][$keys[2]][$keys[3]][$keys[4]] = $array[$keys[0]][$keys[1]][$keys[2]][$keys[3]][$keys[4]];
                break;
            case 6:
                $array_new[$keys[0]][$keys[1]][$keys[2]][$keys[3]][$keys[4]][$keys[5]] = $array[$keys[0]][$keys[1]][$keys[2]][$keys[3]][$keys[4]][$keys[5]];
                break;
        }
        return $array_new;
    }


    /**
     * 接口数据填充 null
     *
     * @param array $array_new 新数组
     * @param array $keys
     * @return mixed
     */
    public function filterResponseNull($array_new, $keys)
    {
        $level = count($keys);
        switch ($level) {
            case 1:
                $array_new[$keys[0]] = null;
                break;
            case 2:
                $array_new[$keys[0]][$keys[1]] = null;
                break;
            case 3:
                $array_new[$keys[0]][$keys[1]][$keys[2]] = null;
                break;
            case 4:
                $array_new[$keys[0]][$keys[1]][$keys[2]][$keys[3]] = null;
                break;
            case 5:
                $array_new[$keys[0]][$keys[1]][$keys[2]][$keys[3]][$keys[4]] = null;
                break;
            case 6:
                $array_new[$keys[0]][$keys[1]][$keys[2]][$keys[3]][$keys[4]][$keys[5]] = null;
                break;
        }
        return $array_new;
    }


    /**
     * 是否为关联数组
     *
     * @param $array
     * @return bool
     */
    public function isAssocArray($array)
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }


}