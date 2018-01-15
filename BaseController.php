<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Business\PlatGoodsBiz;

class BaseController extends Controller
{
    protected $default_token = [
        //laoda的token
        'laoda' => 'AgAAAA**AQAAAA**aAAAAA**soM/WQ**nY+sHZ2PrBmdj6wVnY+sEZ2PrA2dj6ABlYekDJGLqAydj6x9nY+seQ**kMADAA**AAMAAA**1FmFddBkseUAcssBfYJNgTC6c9XEAB3tMJq7x72DzZZtPHWDlgwkYpBxgAOh01emX4D/VwSXq0egPBu25d9witDrakP0sf9wzgS6pPdHE7YTGJXogmLsK6Q4QsaDpS+VyKjNAsSSIMbQ7fwQQvTNc1YhLe8eDiqR4MSpMA3XKuAAAqjtueGnWJMQBfY/u5kvBFNnACuZ0E0E5pVznIONODfnz5eJ69zo11bDqXvZIoAtN7+y9zTAD2kjrubeqGdNeKFOR2ZyKTCrTaQZB+ab5ygiMDusax+J3OWykUnbyxcvN+jSc50lhk7cJUE3c1j+h7yMiWjxEPv2DpN0Pg1lNg/5tRNTEYL8YzZ4gD/A06O5BpoBdli4p5Py0jADCpyuGuyo4uTJWrMf/GEDIY9UCMOUoKNYLFsZZmPlAZdF6n1fVICAqqor5PxqskkxKUCyeeUB95pYVAU4dj3RPqTND19XsXQ3Wvh+ZfAJCm3gmQPa8rhfz/BlL2a325INFGg95tOFXelh8hNihtu1iY/f68E2mHSDg47Pv0nUVpybT6IiGEw5Kc75kiINDWNaw/8cgylBsnNhZWBy9oCId+xsxyd2VANLeun5g7IgEpS/Nu9SXF2NXSHA9mnlIp8wGbyRt54VNsV1NoYddGH19xnFC/L5kIyqCdabKYxmVpUBHJ0OJetYBvDkplMoCVG9FMV7jTGOzczFvHki7aVwc2P2lJspofI+CtBxlCDxTlQLTX9GdIW6eDURwUIoK9D2qcJh',
        //logic的token
        'logic' => 'AgAAAA**AQAAAA**aAAAAA**TFk/WQ**nY+sHZ2PrBmdj6wVnY+sEZ2PrA2dj6ACkoWoD5CHoQydj6x9nY+seQ**kMADAA**AAMAAA**7VtL67+uDGoPGwpzzegnSCLgL9ZwsNRQHBaZ8T6zTQuqDg8NXWGU37zosAe7c/KcRGEzb60QX3nV1ujxFDsMwI9Kw4CAPTqVOBXz72l6mhT9m2oiVX0dCAsYd0GKMTgIrO1+uhizmiIXej3Uh7NIF8L/76k/tiS/E304JsrTmKrk2r2Rr+dST1ONfj8fb5TFzeDen0hmBzgRctchyjPesIFTRd7WupOtbi6ciScQ/yYCqfH7GRGQADCIaiMnIpdQUfnwigoNoi4OyP/mH7tr03WfCDlHTAi1Ret2LsfXh0UAYi8rwuMtVSAvP52fRtwe4lom3DzBt2jB7U7rj8KZ89ea30SAIXVsag/vo3B0jkl64pSB5/zKbBPRrG5qZ+28aDKuUSuAfn9lPNCF//esp4QIF7HIPUeioLgQK5WoPT9/BCPmn0Y+tNMAPSEcUWTY42WwahoN1eYpBgqX/hZolTvupd5907NkDTxYHfij6WtcGQdHfHBWCPGHrgWcdLefochtz7pDpVzdHYCUQbv4bVzHQNbVfhNHCMp4LZ63qrkJVpWsmSeSgZi5dVECI7gp0t/Rq1y5uBsRJK6OViZS02jYw0MR7kjAyrIsK43bP4Pz8wwvpfyuaoxkgvziCaM35taQuB3qlUPeawULUSFX6olCC0kMZqdUT5HPqYD2+YTj2n0wBXvP7Lbkj3gejSbelCwS6XHdKqAXP2gY93eBtbogLic7//FGdnQvbbISceo/9hgdXKbNBcMh0zoQ0KRm',
        //沙盒环境下的token
        'sandbox' => 'AgAAAA**AQAAAA**aAAAAA**3a4iWQ**nY+sHZ2PrBmdj6wVnY+sEZ2PrA2dj6wFk4GkCJmFpg2dj6x9nY+seQ**ujMEAA**AAMAAA**ZZWwr45JDMzmsnsWl4i47kl7XrcEC+0Bufjd62DQwY9+fCLFW7O1oLLknBIXlThv0gpLSkdLZLrCYYLmL6G6Ct0nUu1CnNx0KsC9uyAnZOuTgKyrROKdTXi3Amq6YONEFcD/6PZkRmNeWi60rfGFozlhzXkd1+VYOvX1NzcK1A54xiBtVsiX6WUWMejIUIp2prX1X1bbnW2NCHIQ9VUr/x15mYt3QloY8KwtB5NyUfQg5Wv7Zz+6R5t2FsnIyylbE3pHwBRMqKvShQlcGKwBYfIdOWNf6/toy1fd5FGo4pJ971x8R+SLHdzxsk3fI2GVWb4D8DRwCSFFS5DCPnbgJsIF3wQpFddXcGpcUR+zuajwN8NJ6qggP3MWCLygbA7KFXdwuqklzrBg4CFhtn3ZG0c61UDujrbJgsHcj3D6SxiLy+KFaAd3+lTbR+v0xrNHYDBGhwPmdyKyb9K6XH9IFaOUCFx587o6WaytqPT7fAsfKukcNZtG5lXwTIftU8ShRpoMbE+4sj8DwXK7a3yXpGgQJGhlyuq5mFhgBUKDQ4tuKV6a51aQLu+7UMuSn5Z6Ve3TmrU85Gz+a6T8I+MTk+Tzys9yoMrhv2ebZj9dcQ4V81fwJ2/G8xKYzhPiKffMtR0ncoMPX4VyI+W+nIGx9LTX6mejN+RJhJocumudyOD9XMWu+QgrTrhqo+93IFpVzoPMHE10bEguHpRxbI6P8J3JeXcZ+sdF88HfWg66OZYkdzwLlz4Kdt6UG+ofzOn3'
    ];


    /**
     * Create error message
     *
     * @param \Exception $exception
     * @param $data
     * @return array
     */
    public function error(\Exception $exception, $data = "")
    {
        $code = $exception->getCode();
        return [
            'errcode' => $code > 0 ? $code : 1,
            'errmsg' => $exception->getMessage(),
            'data' => $data,
        ];
    }

    /**
     * 返回结果消息
     *
     * @param $errcode
     * @param $errmsg
     * @param null $data
     * @return array
     */
    public function result($errcode,$errmsg,$data = null)
    {
        return [
            'errcode' => $errcode,
            'errmsg' => $errmsg,
            'data' => $data,
        ];
    }

    /**
     * Create success message
     *
     * @param $data
     * @return array
     */
    public function success($data = "")
    {
        return [
            'errcode' => 0,
            'data' => $data,
        ];
    }


    /**
     * Deal with api data exception
     *
     * @param $result
     * @param $function_name
     * @throws \Exception
     */
    public function resultCheck($result, $function_name = __METHOD__)
    {
        $msg = "异常来源: {$function_name} \n";

        // 无响应或数据格式不正确
        if (!isset($result['Ack'])) {
            dump($result);
            throw new \Exception($msg . '异常信息: <接口响应数据异常>', '100');
        }

        // 请求失败
        if ($result['Ack'] == 'Failure') {

            // 统一格式
            if (isset($result['Errors']['ShortMessage'])) {
                $result['Errors'] = [$result['Errors']];
            }

            // 错误代码
            $code = 200;
            if (isset($result['Errors'][0]['ErrorCode'])) {
                $code = $result['Errors'][0]['ErrorCode'];
            }

            // 拼接异常信息
            array_map(function ($n) use (&$msg) {
                $msg .= "异常信息: <" . $n['LongMessage'] . "> \n";
            }, $result['Errors']);

            dump($result);
            $e = new \Exception($msg, $code);
            $e->data = $result['Errors'];
            throw $e;
        }
    }


    /**
     * China time convert to UTC time
     *
     * @param $cn_time
     * @return false|string
     */
    public function utcTime($cn_time)
    {
        return $this->convertTime($cn_time, -8);
    }


    /**
     * UTC time convert to China time
     *
     * @param $utc_time
     * @return false|string
     */
    public function cnTime($utc_time)
    {
        return $this->convertTime($utc_time, +8);
    }


    /**
     * Convert time to other timezone
     *
     * @param $time
     * @param $difference int
     * @return false|string
     */
    public function convertTime($time, $difference)
    {
        if (!is_int($time)) {
            $time = strtotime($time);
        }
        return date("Y-m-d H:i:s", $time + $difference * 60 * 60);
    }


    /**
     * 测试 (指定方法)
     *
     * @param $action
     * @return string
     */
    public function testAction($action)
    {
        if(env('EBAY_CHECK_SANDBOX')){
            $token = $this->default_token['sandbox'];
        }else{
            $token = $this->default_token['logic'];
        }
        $action = str_replace(['_'], '', $action);
        foreach (get_class_methods($this) as $method) {
            if (strtoupper($method) == strtoupper($action)) {
                try {
                    return $this->$method($token);
                } catch (\Exception $e) {
                    return $this->$method();
                }
            }
        }
        return "<h1>404</h1>";
    }


    /**
     * Set company id
     *
     * @param $shop_id int
     */
    public function setCompanyId($shop_id)
    {
        $biz = new PlatGoodsBiz();
        $companyShop = $biz->getCompanyShop($shop_id);
        global $ERP_COMPANY;
        $ERP_COMPANY['COMPANY_ID'] = $companyShop->company_id;
    }
}
