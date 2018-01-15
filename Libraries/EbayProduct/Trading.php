<?php
namespace App\Http\Controllers\Api\Libraries\EbayProduct;
use Common;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Api\Libraries\Connection as Base;
use App\Http\Controllers\Api\BaseController;
use Mockery\Exception;
use Log;
use Symfony\Component\Yaml\Tests\B;

class Trading extends Base
{
    private $appname = 'laicanfu-logic-PRD-e08fa563f-34011811';
    private $devname = '91993bea-0c14-4475-b1f7-5db8c984396f';
    private $certname = 'PRD-08fa563fd0a7-aca1-44a9-b1fe-b61a';
    private $runame = 'lai_canfu-laicanfu-logic--nbptrz';
    private $site = 0;
    private $baseController;
    private $serverUrl = 'https://api.ebay.com/ws/api.dll';

    public function __construct($sandbox = false)
    {
        if($sandbox){
            $this->appname = 'laicanfu-logic-SBX-9090fc79c-6455016d';
            $this->devname = '91993bea-0c14-4475-b1f7-5db8c984396f';
            $this->certname = 'SBX-090fc79c9ecb-5725-472d-b748-ea35';
            $this->runame = 'lai_canfu-laicanfu-logic--sggganla';
            $this->serverUrl = 'https://api.sandbox.ebay.com/ws/api.dll';
        }
        $this->baseController = new BaseController();
    }

    /**
     * 检查输入的token值是否存在
     * @param $token
     * @return string
     */
    public function checkToken($token){
        if($token == ''){
            throw new \Exception('token为空',106);
        }else{
            $final_token = $token;
        }
        return $final_token;
    }

    /**
     * 判断HTTP请求是否错误
     * @param $responseXml
     */
    public function judgeHttpError($responseXml){
        if(isset($responseXml['errcode']))
        {
            throw new \Exception('服务器访问地址返回错误，错误编号为:'.$responseXml['errcode'], $responseXml['errcode']);
        }
    }

    /**
     * 获取ebay接口返回数据
     * @param $requestName
     * @param $token
     * @param $data
     * @param int $site
     * @return mixed
     */
    public function getEbayResponseData($requestName, $token, $data, $site = 0)
    {
        $final_token = $this->checkToken($token);
        $serverUrl = $this->serverUrl;
        $operationName = $requestName;
        $header = $this->buildEbayHeaders($operationName,$site);
        $requestXmlBody = $this->build_request_data($operationName,$final_token,$data);
        $responseXml = Common::curlRequest($serverUrl,'POST',$header,$requestXmlBody);
        $this->judgeHttpError($responseXml);
        $arr = $this->obj($responseXml);
        return $arr;
    }

    /**
     * 根据token值获取ebay用户信息
     * @param $token
     * @param string $data
     * @return mixed
     */
    public function getUser($token)
    {
        $data = '';
        $operationName = 'GetUser';
        $arr = $this->getEbayResponseData($operationName,$token,$data);
        $ret = [];
        $ret['Ack'] = $arr['Ack'];
        if($ret['Ack'] != 'Failure'){
            $ret = $this->filterResponse($arr, [
                'User.UserID',
                'User.EIASToken',
                'User.Site',
                'User.Email',
                'User.RegistrationAddress'
            ]);
        }else{
            $ret = $this->filterResponse($arr, [
                'Ack',
                'Errors.ShortMessage',
                'Errors.LongMessage',
            ]);
        }
        return $ret;
    }

    /**
     * 根据token值获取ebay授权sessionid
     * @param string $token
     * @return mixed
     */
    public function getSessionID($token)
    {
        $data = [
            'RuName' => $this->runame,
        ];
        $operationName = 'GetSessionID';
        $arr = $this->getEbayResponseData($operationName,$token,$data);
        return $arr;
    }

    /**
     * 获取ebay授权时访问的url
     * @return string
     */
    public function curlTake($token)
    {
        $res = $this->getSessionID($token);
        $sessionid = $res['SessionID'];
        session_start();
        $_SESSION['SessionID'] = $sessionid;
        if(env('EBAY_CHECK_SANDBOX')){
            $url = "https://signin.sandbox.ebay.com/ws/eBayISAPI.dll?SignIn&RuName={$this->runame}&SessID=$sessionid";
        }else{
            $url = "https://signin.ebay.com/ws/eBayISAPI.dll?SignIn&RuName={$this->runame}&SessID=$sessionid";
        }
        return $url;
    }

    /**
     * 获取ebay授权后的token值
     * @param string $token
     * @return mixed
     */
    public function fetchToken($token)
    {
        //发送请求给eBay
        session_start();
        $data = [
            'SessionID' => isset($_SESSION['SessionID']) ? $_SESSION['SessionID'] : null
        ];
        $operationName = 'FetchToken';
        $arr = $this->getEbayResponseData($operationName,$token,$data);
        return $arr;

    }

    /**
     * 获取ebay用户店铺配置信息，需该店铺有配置店铺才会返回成功
     * @param $token
     * @param string $data
     * @return mixed
     */
    public function getStore($token,$data='')
    {
        $operationName = 'GetStore';
        $arr = $this->getEbayResponseData($operationName,$token,$data);
        return $arr;
    }

    /**
     * 获取ebay用户的账户信息
     * @param $token
     * @param string $data
     * @return mixed
     */
    public function getAccount($token,$data='')
    {
        $operationName = 'GetAccount';
        $arr = $this->getEbayResponseData($operationName,$token,$data);
        return $arr;
    }

    /**
     * 获取ebay用户店铺反馈信息
     * @param $token
     * @param string $data
     * @return mixed
     */
    public function getFeedback($token,$data='')
    {
        $operationName = 'GetFeedback';
        $arr = $this->getEbayResponseData($operationName,$token,$data);
        return $arr;
    }
    /**
     * 获取产品的详细信息
     * @param $token
     * @param string $data
     * @return mixed
     */
    public function getItem($token,$data,$site = 0)
    {
        date_default_timezone_set('UTC');
        $operationName = 'GetItem';
        $arr = $this->getEbayResponseData($operationName,$token,$data,$site);
        $ret['Ack'] = $arr['Ack'];
        error_reporting(4);
        if ($ret['Ack'] != 'Failure') {
            $ret['data']['platform_goods_id'] = $arr['Item']['ItemID'];
            $ret['data']['platform_id'] = '1';
            $ret['data']['site'] = $arr['Item']['Site'];
            $ret['data']['platform_goods_sku'] = $arr['Item']['SKU'];
            $ret['data']['title'] = $arr['Item']['Title'];
            $ret['data']['sub_title'] = $arr['Item']['SubTitle'];
            $ret['data']['images'] = json_encode($arr['Item']['PictureDetails']);
            $ret['data']['price'] = $arr['Item']['SellingStatus']['CurrentPrice'];
            $aa = strtotime(date("Y-m-d H:i:s"));
            $bb = strtotime($arr['Item']['ListingDetails']['EndTime']);
            if($bb-$aa>0){
                $ret['data']['status'] = '1';
            }else{
                $ret['data']['status'] = '2';
            }
            $ret['data']['stock_count'] = $arr['Item']['Quantity'];
            $ret['data']['sale_count'] = $arr['Item']['SellingStatus']['QuantitySold'];
            $ret['data']['collect_count'] = $arr['Item']['WatchCount'];
            $ret['data']['click_count'] = $arr['Item']['HitCount'];
            $ret['data']['matching_sku'] = null;
            $ret['data']['matching_sku_id'] = null;
            $ret['data']['matching_status'] = 0;
            $ret['data']['publish_start_time'] = date('Y-m-d H:i:s',strtotime($arr['Item']['ListingDetails']['StartTime']));
            $ret['data']['publish_end_time'] = date('Y-m-d H:i:s',strtotime($arr['Item']['ListingDetails']['EndTime']));
            $ret['data']['first_goods_category_id'] = $arr['Item']['PrimaryCategory']['CategoryID'];
            $ret['data']['first_shop_goods_category_id'] = $arr['Item']['Storefront']['StoreCategoryID'];
            $ret['data']['second_goods_category_id'] = $arr['Item']['SecondaryCategory']['CategoryID'];
            $ret['data']['second_shop_goods_category_id'] = $arr['Item']['Storefront']['StoreCategory2ID'];
            if($arr['Item']['ListingType'] == 'FixedPriceItem')
            {
                if(count($arr['Item']['Variations'])>0)
                {
                    $ret['data']['publish_type'] = 2;
                }else{
                    $ret['data']['publish_type'] = 1;
                }
            }else if($arr['Item']['ListingType'] == 'Chinese')
            {
                $ret['data']['publish_type'] = 0;
            }
            $ret['data']['specifics'] = json_encode(['ItemSpecifics' => $arr['Item']['ItemSpecifics'],'ProductListingDetails' => $arr['Item']['ProductListingDetails']]);
            $ret['data']['condition'] = json_encode(['ConditionID' =>$arr['Item']['ConditionID'],'ConditionDisplayName' => $arr['Item']['ConditionDisplayName'],'ConditionDescription' =>$arr['Item']['ConditionDefinition']]);
            $ret['data']['description'] = $arr['Item']['Description'];
            $ret['data']['price_info'] = json_encode(['ConvertedBuyItNowPrice' => $arr['Item']['ListingDetails']['ConvertedBuyItNowPrice'],'ListingDuration' => $arr['Item']['ListingDuration'],'ConvertedStartPrice' => $arr['Item']['ListingDetails']['ConvertedStartPrice'],'ConvertedReservePrice' => $arr['Item']['ListingDetails']['ConvertedReservePrice']]);
            $ret['data']['conditions_info'] = json_encode($arr['Item']['Variations']);
            $ret['data']['payment_method'] = json_encode($arr['Item']['PaymentMethods']);
            $ret['data']['payment_username'] = $arr['Item']['PayPalEmailAddress'];
            $ret['data']['payment_introduce'] = null;
            $ret['data']['return_policy_details'] = json_encode($arr['Item']['ReturnPolicy']);
            $ret['data']['shipping_details'] = json_encode($arr['Item']['ShippingDetails']);
            $ret['data']['shipping_package_details'] = json_encode($arr['Item']['ShippingPackageDetails']);
            $ret['data']['buyer_requirement_details'] = json_encode($arr['Item']['BuyerRequirementDetails']);
            $ret['data']['picture_ad_details'] = json_encode(['PostalCode' => $arr['Item']['PostalCode'],'GalleryType' => $arr['Item']['PictureDetails']['GalleryType'],'ListingEnhancement' => $arr['Item']['ListingEnhancement'],'DispatchTimeMax' => $arr['Item']['DispatchTimeMax'],'ViewItemUrl' =>$arr['Item']['ListingDetails']['ViewItemURL'],'Country' =>$arr['Item']['Country'],'Currency' =>$arr['Item']['Currency'],'Location' =>$arr['Item']['Location'],'PrivateListing' =>$arr['Item']['PrivateListing']]);
        } else {
            $ret = $this->filterResponse($arr, [
                'Ack',
                'Errors.ShortMessage',
                'Errors.LongMessage',
            ]);
        }
        return $ret;
    }

    /**
     * 获取ebay店铺在线商品列表信息
     * @param $token
     * @param string $data
     * @return mixed
     */
    public function getSellerListActive($token,$data='')
    {
        date_default_timezone_set('UTC');
        $operationName = 'GetSellerList';
        $arr = $this->getEbayResponseData($operationName,$token,$data);
        $ret['Ack'] = $arr['Ack'];
        error_reporting(4);
        if ($ret['Ack'] != 'Failure') {
            $ret = $this->filterResponse($arr, [
                'Ack',
                'HasMoreItems',
                'Version',
                'PageNumber',
                'ItemsPerPage',
                'PaginationResult.TotalNumberOfPages',
                'PaginationResult.TotalNumberOfEntries',
            ]);
            if (isset($arr['ItemArray']['Item']['ItemID'])) {
                $arr['ItemArray']['Item'] = [$arr['ItemArray']['Item']];
            }
            foreach ($arr['ItemArray']['Item'] as $key => $value) {
                $ret['data'][$key] = [
                    'platform_id' => 1,
                    'site' => $value['Site'],
                    'platform_goods_id' => $value['ItemID'],
                    'platform_goods_sku' => $value['SKU'],
                    'title' => $value['Title'],
                    'sub_title' => $value['SubTitle'],
                    'images' => json_encode($value['PictureDetails']),
                    'price' => $value['SellingStatus']['CurrentPrice'],
                    'stock_count' => $value['Quantity'],
                    'sale_count' => $value['SellingStatus']['QuantitySold'],
                    'collect_count' => $value['WatchCount'],
                    'click_count' => $value['HitCount'],
                    'matching_sku' => null,
                    'matching_sku_id' => null,
                    'matching_status' => 0,
                    'publish_start_time' => date('Y-m-d H:i:s',strtotime($value['ListingDetails']['StartTime'])),
                    'publish_end_time' => date('Y-m-d H:i:s',strtotime($value['ListingDetails']['EndTime'])),
                    'first_goods_category_id' => $value['PrimaryCategory']['CategoryID'],
                    'first_shop_goods_category_id' => $value['Storefront']['StoreCategoryID'],
                    'second_goods_category_id' => $value['SecondaryCategory']['CategoryID'],
                    'second_shop_goods_category_id' => $value['Storefront']['StoreCategory2ID'],
                    'specifics' => json_encode(['ItemSpecifics' => $value['ItemSpecifics'],'ProductListingDetails' => $value['ProductListingDetails']]),
                    'condition' => json_encode(['ConditionID' =>$value['ConditionID'],'ConditionDisplayName' => $value['ConditionDisplayName'],'ConditionDescription' => $value['ConditionDescription']]),
                    'description' => $value['Description'],
                    'price_info' => json_encode(['ConvertedBuyItNowPrice' => $value['ListingDetails']['ConvertedBuyItNowPrice'],'ListingDuration' => $value['ListingDuration'],'ConvertedStartPrice' => $value['ListingDetails']['ConvertedStartPrice'],'ConvertedReservePrice' => $value['ListingDetails']['ConvertedReservePrice']]),
                    'conditions_info' => json_encode($value['Variations']),
                    'payment_method' => json_encode($value['PaymentMethods']),
                    'payment_username' => $value['PayPalEmailAddress'],
                    'payment_introduce' => null,
                    'return_policy_details' => json_encode($value['ReturnPolicy']),
                    'shipping_details' => json_encode($value['ShippingDetails']),
                    'shipping_package_details' => json_encode($value['ShippingPackageDetails']),
                    'buyer_requirement_details' => json_encode($value['BuyerRequirementDetails']),
                    'picture_ad_details' => json_encode(['PostalCode' => $value['PostalCode'],'GalleryType' => $value['PictureDetails']['GalleryType'],'ListingEnhancement' => $value['ListingEnhancement'],'ViewItemUrl' =>$value['ListingDetails']['ViewItemURL'],'Country' =>$value['Country'],'Currency' =>$value['Currency'],'DispatchTimeMax' => $value['DispatchTimeMax'],'Location' =>$value['Location'],'PrivateListing' =>$value['PrivateListing']])
                ];
                $aa = strtotime(date("Y-m-d H:i:s"));
                $bb = strtotime($value['ListingDetails']['EndTime']);
                if($bb-$aa>0){
                    $ret['data'][$key]['status'] = '1';
                }else{
                    $ret['data'][$key]['status'] = '2';
                }
                if($value['ListingType'] == 'FixedPriceItem'){
                    if(count($value['Variations'])>0){
                        $ret['data'][$key]['publish_type'] = 2;
                    }else{
                        $ret['data'][$key]['publish_type'] = 1;
                    }
                }else if($value['ListingType'] == 'Chinese'){
                    $ret['data'][$key]['publish_type'] = 0;
                }
            }
        } else {
            $ret = $this->filterResponse($arr, [
                'Ack',
                'Errors.ShortMessage',
                'Errors.LongMessage',
            ]);
        }
        return $ret;
    }
    /**
     * 获获取ebay店铺下架120天内的商品列表信息
     * @param $token
     * @param string $data
     * @return mixed
     */
    public function getSellerListUnsold($token,$data='')
    {
        $operationName = 'GetSellerList';
        $arr = $this->getEbayResponseData($operationName,$token,$data);
        $ret['Ack'] = $arr['Ack'];
        error_reporting(4);
        if ($ret['Ack'] != 'Failure') {
            $ret = $this->filterResponse($arr, [
                'HasMoreItems',
                'Version',
                'PageNumber',
                'ItemsPerPage',
                'PaginationResult.TotalNumberOfPages',
                'PaginationResult.TotalNumberOfEntries'
            ]);
            foreach ($arr['ItemArray']['Item'] as $key => $value) {
                $ret['data'][$key]['company_id'] = null;
                $ret['data'][$key]['platform_id'] = '1';
                $ret['data'][$key]['shop_id'] = null;
                $ret['data'][$key]['site'] = $value['Site'];
                $ret['data'][$key]['platform_sku'] = $value['SKU'] ? $value['SKU'] : null;
                $ret['data'][$key]['title'] = $value['Title'];
                $ret['data'][$key]['images'] = json_encode($value['PictureDetails']['PictureURL']) ? json_encode($value['PictureDetails']['PictureURL']) : json_encode($value['PictureDetails']['GalleryURL']);
                $ret['data'][$key]['price'] = $value['SellingStatus']['ConvertedCurrentPrice'];
                $ret['data'][$key]['sku_status'] = '0';
                $ret['data'][$key]['stock_count'] = $value['Quantity'];
                $ret['data'][$key]['sale_count'] = $value['SellingStatus']['QuantitySold'];
                $ret['data'][$key]['collect_count'] = $value['WatchCount'];
                $ret['data'][$key]['matching_sku'] = null;
                $ret['data'][$key]['matching_sku_id'] = null;
                $ret['data'][$key]['matching_status'] = 0;
                $ret['data'][$key]['publish_start_time'] = $value['ListingDetails']['StartTime'];
                $ret['data'][$key]['publish_end_time'] = $value['ListingDetails']['EndTime'];
                $ret['data'][$key]['updated_at'] = null;
            }
        } else {
            $ret = $this->filterResponse($arr, [
                'Ack',
                'Errors.ShortMessage',
                'Errors.LongMessage',
            ]);
        }
        return $arr;
    }

    /**
     * 获取在线商品列表
     * @param $token
     * @param string $data
     * @return mixed
     */
    public function getMyeBaySelling($token,$data='')
    {
        $operationName = 'GetMyeBaySelling';
        $arr = $this->getEbayResponseData($operationName,$token,$data);
        $ret['Ack'] = $arr['Ack'];
        error_reporting(4);
        if ($ret['Ack'] != 'Failure') {
            $ret = $this->filterResponse($arr, [
                'ActiveList.PaginationResult.TotalNumberOfPages',
                'ActiveList.PaginationResult.TotalNumberOfEntries'
            ]);
            foreach ($arr['ActiveList']['ItemArray']['Item'] as $key => $value) {
                $ret['data'][$key]['platform_goods_id'] = $value['ItemID'];
            }
        } else {
            $ret = $this->filterResponse($arr, [
                'Ack',
                'Errors.ShortMessage',
                'Errors.LongMessage',
            ]);
        }
        return $ret;
    }

    /**
     * 在ebay店铺刊登商品时先进行试刊登，防止刊登出错
     * @param $token
     * @param string $data
     * @return mixed
     */
    public function verifyAddItem($token,$data,$site)
    {
        $operationName = 'VerifyAddItem';
        $arr = $this->getEbayResponseData($operationName,$token,$data,$site);
        return $arr;
    }

    /**
     * 根据用户token在ebay平台刊登该token店铺的商品
     * @param $token
     * @param string $data
     * @return mixed
     */
    public function addItem($token,$data,$site)
    {
        $operationName = 'AddItem';
        $arr = $this->getEbayResponseData($operationName,$token,$data,$site);
        return $arr;
    }

    /**
     * 验证刊登固定价格的商品
     * @param $token
     * @param string $data
     * @return mixed
     */
    public function verifyAddFixedPriceItem($token,$data='',$site)
    {
        $operationName = 'VerifyAddFixedPriceItem';
        $arr = $this->getEbayResponseData($operationName,$token,$data,$site);
        return $arr;
    }

    /**
     * 刊登固定价格的商品
     * @param $token
     * @param string $data
     * @return mixed
     */
    public function addFixedPriceItem($token,$data='')
    {
        $operationName = 'AddFixedPriceItem';
        $arr = $this->getEbayResponseData($operationName,$token,$data,$site);
        return $arr;
    }

    /**
     * 获取ebay商品分类信息
     * @param $token
     * @param string $data
     * @return mixed
     */
    public function getCategories($token,$data='',$site='0')
    {
        $operationName = 'GetCategories';
        $arr = $this->getEbayResponseData($operationName,$token,$data,$site);
        $ret['Ack'] = $arr['Ack'];
        if ($ret['Ack'] != 'Failure') {
            foreach ($arr['CategoryArray']['Category'] as $key => $value) {
                $ret['data'][$key]['platform_id'] = 1;
                $ret['data'][$key]['platform_category_id'] = $value['CategoryID'];
                $ret['data'][$key]['platform_parent_id'] = $value['CategoryParentID'];
                $ret['data'][$key]['site'] = $site;
                $ret['data'][$key]['english_name'] = $value['CategoryName'];
                $ret['data'][$key]['level'] = intval($value['CategoryLevel']);
                $ret['data'][$key]['version'] = intval($arr['CategoryVersion']);
            }
        } else {
            $ret = $this->filterResponse($arr, [
                'Ack',
                'Errors.ShortMessage',
                'Errors.LongMessage',
            ]);
        }
        return $ret;
    }
    /**
     * 获取ebay商品分类信息的特征
     * @param $token
     * @param string $data
     * @return mixed
     */
    public function getCategoryFeatures($token,$data=[],$site='0')
    {
        $operationName = 'GetCategoryFeatures';
        $arr = $this->getEbayResponseData($operationName,$token,$data,$site);
        return $arr;
    }
    /**
     * 获取ebay商品分类信息的属性
     * @param $token
     * @param string $data
     * @return mixed
     */
    public function getCategorySpecifics($token,$data=[],$site='0')
    {
        $operationName = 'GetCategorySpecifics';
        $arr = $this->getEbayResponseData($operationName,$token,$data,$site);
        return $arr;
    }

    /**
     * 获取ebay站点信息，物流信息等
     * @param $token
     * @param string $data
     * @return mixed
     */
    public function geteBayDetails($token,$data='',$site='201')
    {
        $operationName = 'GeteBayDetails';
        $arr = $this->getEbayResponseData($operationName,$token,$data,$site);
        return $arr;
    }

    /**
     * 获取ebay店铺交易信息
     * @param $token
     * @param string $data
     * @return mixed
     */
    public function getSellerTransactions($data='', $token='', $platform_id=1)
    {
        $operationName = 'GetSellerTransactions';
        $arr = $this->getEbayResponseData($operationName,$token,$data);
        $ret['Ack'] = $arr['Ack'];
        error_reporting(4);
        if ($ret['Ack'] != 'Failure') {
            $ret = $this->filterResponse($arr, [
                'Ack',
                'HasMoreTransactions',
                'Version',
                'PageNumber',
                'PaginationResult.TotalNumberOfPages',
            ]);

            // 统一多订单与单一订单格式
            if (isset($arr['TransactionArray']['Transaction']['Status'])) {
                $transaction = $arr['TransactionArray']['Transaction'];
                unset($arr['TransactionArray']['Transaction']);
                $arr['TransactionArray']['Transaction'][] = $transaction;
            }

            foreach ($arr['TransactionArray']['Transaction'] as $key => $value) {
                $ret['data']['orders'][$key]['clients_id'] = $value['Buyer']['UserID'];
                $ret['data']['orders'][$key]['platform_id'] = 1; // ebay
//                $ret['data']['orders'][$key]['identifier'] = $value['TransactionID'];
                $ret['data']['orders'][$key]['identifier'] = $value['ExtendedOrderID'];
                $ret['data']['orders'][$key]['shop_id'] = $arr['Seller']['UserID'];
                if (! $value['PaidTime']) {
                    $ret['data']['orders'][$key]['status'] = 0;
                } elseif (! $value['ShippedTime']) {
                    $ret['data']['orders'][$key]['status'] = 1;
                } elseif ($value['ShippedTime']) {
                    $ret['data']['orders'][$key]['status'] = 5;
                }
                $ret['data']['orders'][$key]['country_code'] = $value['Buyer']['BuyerInfo']['ShippingAddress']['Country']; // 买家地址?站点?
                $ret['data']['orders'][$key]['logistics_channel_id'] = null;
                $ret['data']['orders'][$key]['money'] = floatval($value['TransactionPrice']);
                $ret['data']['orders'][$key]['order_created_time'] = date("Y-m-d H:i:s", strtotime($value['CreatedDate']));
                $ret['data']['orders'][$key]['order_paid_time'] = date("Y-m-d H:i:s", strtotime($value['PaidTime']));
                $ret['data']['orders'][$key]['order_deliver_time'] = date("Y-m-d H:i:s", strtotime($value['ShippedTime']));
                $ret['data']['orders'][$key]['order_end_time'] = null;

                // 商品
                $ret['data']['orders'][$key]['goods_info']['item_id'] = $value['Item']['ItemID'];
                $ret['data']['orders'][$key]['goods_info']['current_price'] = floatval($value['Item']['SellingStatus']['CurrentPrice']);
                $ret['data']['orders'][$key]['goods_info']['quantity_sold'] = intval($value['Item']['SellingStatus']['QuantitySold']);
                $ret['data']['orders'][$key]['goods_info']['listing_status'] = $value['Item']['SellingStatus']['ListingStatus'];
                $ret['data']['orders'][$key]['goods_info']['sku'] = $value['Item']['SKU'];
                $ret['data']['orders'][$key]['goods_info']['name'] = $value['Item']['Title'];
                $ret['data']['orders'][$key]['goods_info']['item_img'] = "http://i.ebayimg.com/images/i/{$value['Item']['ItemID']}-0-1/s-l1000.jpg";
                $ret['data']['orders'][$key]['goods_info']['page_url'] = "http://www.ebay.com/itm/{$value['Item']['ItemID']}";
                $ret['data']['orders'][$key]['goods_info'] = json_encode([$ret['data']['orders'][$key]['goods_info']]);

                // 地址
                $ret['data']['orders'][$key]['address_info']['name'] = $value['Buyer']['BuyerInfo']['ShippingAddress']['Name'];
                $ret['data']['orders'][$key]['address_info']['street_1'] = $value['Buyer']['BuyerInfo']['ShippingAddress']['Street1'];
                $ret['data']['orders'][$key]['address_info']['street_2'] = $value['Buyer']['BuyerInfo']['ShippingAddress']['Street2'];
                $ret['data']['orders'][$key]['address_info']['city_name'] = $value['Buyer']['BuyerInfo']['ShippingAddress']['CityName'];
                $ret['data']['orders'][$key]['address_info']['state_or_province'] = $value['Buyer']['BuyerInfo']['ShippingAddress']['StateOrProvince'];
                $ret['data']['orders'][$key]['address_info']['country'] = $value['Buyer']['BuyerInfo']['ShippingAddress']['Country'];
                $ret['data']['orders'][$key]['address_info']['country_name'] = $value['Buyer']['BuyerInfo']['ShippingAddress']['CountryName'];
                $ret['data']['orders'][$key]['address_info']['phone'] = $value['Buyer']['BuyerInfo']['ShippingAddress']['Phone'];
                $ret['data']['orders'][$key]['address_info']['postal_code'] = $value['Buyer']['BuyerInfo']['ShippingAddress']['PostalCode'];
                $ret['data']['orders'][$key]['address_info']['address_id'] = $value['Buyer']['BuyerInfo']['ShippingAddress']['AddressID'];
                $ret['data']['orders'][$key]['address_info']['address_owner'] = $value['Buyer']['BuyerInfo']['ShippingAddress']['AddressOwner'];
                $ret['data']['orders'][$key]['address_info']['address_usage'] = $value['Buyer']['BuyerInfo']['ShippingAddress']['AddressUsage'];
                $ret['data']['orders'][$key]['address_info'] = json_encode($ret['data']['orders'][$key]['address_info']);

                $ret['data']['orders'][$key]['clients_designated_logistics'] = $value['ShippingDetails']['ShippingServiceUsed'];
                // $ret['data']['orders'][$key]['clients_designated_logistics'] = $value['ShippingServiceSelected']['ShippingService'];
                $ret['data']['orders'][$key]['is_over_weight'] = null;
                $ret['data']['orders'][$key]['is_has_message'] = $value['BuyerCheckoutMessage'] ? 1 : 0 ;
                $ret['data']['orders'][$key]['is_virtual_delivery'] = null;
                $ret['data']['orders'][$key]['is_remarks'] = null;
                $ret['data']['orders'][$key]['is_other_abnormal'] = null;
                $ret['data']['orders'][$key]['is_repeat'] = null;

                // 运单跟踪号
                $ret['data']['orders'][$key]['shipment_tracking_number'] = $value['ShippingDetails']['ShipmentTrackingDetails']['ShipmentTrackingNumber'];

                // 买家支付邮费
//                $ret['data']['orders'][$key]['ShippingServiceCost'] = floatval($value['ShippingServiceSelected']['ShippingServiceCost']);

                // 买家实际支付邮费
//                $ret['data']['orders'][$key]['actual_shipping_cost'] = floatval($value['ActualShippingCost']);

                // 交易价格货币类型
                $ret['data']['orders'][$key]['currency_type'] = $value['Item']['Currency'];

                // 最快最慢送达日期
                $ret['data']['orders'][$key]['estimated_delivery_time_min'] = date("Y-m-d H:i:s", strtotime($value['ShippingServiceSelected']['ShippingPackageInfo']['EstimatedDeliveryTimeMin']));
                $ret['data']['orders'][$key]['estimated_delivery_time_max'] = date("Y-m-d H:i:s", strtotime($value['ShippingServiceSelected']['ShippingPackageInfo']['EstimatedDeliveryTimeMax']));

                // 买家结账留言
                $ret['data']['orders'][$key]['buyer_checkout_message'] = $value['BuyerCheckoutMessage'];

                // 买家信息
                $ret['data']['orders'][$key]['buyer']['eias_token'] = $value['Buyer']['EIASToken'];
                $ret['data']['orders'][$key]['buyer']['email'] = ($value['Buyer']['Email'] == "Invalid Request") ? null : $value['Buyer']['Email'] ;
                $ret['data']['orders'][$key]['buyer']['registration_date'] = date("Y-m-d H:i:s", strtotime($value['Buyer']['RegistrationDate']));
                $ret['data']['orders'][$key]['buyer']['site'] = $value['Buyer']['Site'];
                $ret['data']['orders'][$key]['buyer']['user_id'] = $value['Buyer']['UserID'];
                $ret['data']['orders'][$key]['buyer']['static_alias'] = $value['Buyer']['StaticAlias'];
                $ret['data']['orders'][$key]['buyer']['name'] = $value['Buyer']['BuyerInfo']['ShippingAddress']['Name'];
                $ret['data']['orders'][$key]['buyer']['street_1'] = $value['Buyer']['BuyerInfo']['ShippingAddress']['Street1'];
                $ret['data']['orders'][$key]['buyer']['street_2'] = $value['Buyer']['BuyerInfo']['ShippingAddress']['Street2'];
                $ret['data']['orders'][$key]['buyer']['city_name'] = $value['Buyer']['BuyerInfo']['ShippingAddress']['CityName'];
                $ret['data']['orders'][$key]['buyer']['state_or_province'] = $value['Buyer']['BuyerInfo']['ShippingAddress']['StateOrProvince'];
                $ret['data']['orders'][$key]['buyer']['country'] = $value['Buyer']['BuyerInfo']['ShippingAddress']['Country'];
                $ret['data']['orders'][$key]['buyer']['country_name'] = $value['Buyer']['BuyerInfo']['ShippingAddress']['CountryName'];
                $ret['data']['orders'][$key]['buyer']['phone'] = $value['Buyer']['BuyerInfo']['ShippingAddress']['Phone'];
                $ret['data']['orders'][$key]['buyer']['postal_code'] = $value['Buyer']['BuyerInfo']['ShippingAddress']['PostalCode'];
                $ret['data']['orders'][$key]['buyer']['address_id'] = $value['Buyer']['BuyerInfo']['ShippingAddress']['AddressID'];
                $ret['data']['orders'][$key]['buyer']['address_owner'] = $value['Buyer']['BuyerInfo']['ShippingAddress']['AddressOwner'];
                $ret['data']['orders'][$key]['buyer']['address_usage'] = $value['Buyer']['BuyerInfo']['ShippingAddress']['AddressUsage'];
                $ret['data']['orders'][$key]['buyer']['platform_id'] = $platform_id;
                $ret['data']['orders'][$key]['buyer'] = json_encode($ret['data']['orders'][$key]['buyer']);

                // 支付类型
                $ret['data']['orders'][$key]['payment_methods'] = $value['Item']['PaymentMethods'];
                $ret['data']['orders'][$key]['payment_methods'] = $value['Status']['PaymentMethodUsed'];

                // 支付 paypal 邮箱账号
                $ret['data']['orders'][$key]['paypal_email_address'] = $value['PayPalEmailAddress'];
                $ret['data']['orders'][$key]['platform_payment_id'] = $value['TransactionID'];

                // 站点
                $ret['data']['orders'][$key]['site'] = $value['TransactionSiteID'];
            }
        } else {
            $ret = $this->filterResponse($arr, [
                'Ack',
                'Errors.ShortMessage',
                'Errors.LongMessage',
            ]);
        }
        return $ret;
    }

    /**
     * 获取ebay店铺订单信息
     * @param $token
     * @param string $data
     * @return mixed
     */
    public function getOrders($token, $data = '', $platform_id = 1, $shop_id = 1)
    {
        $operationName = 'GetOrders';
        $arr = $this->getEbayResponseData($operationName,$token,$data);
        $ret['Ack'] = $arr['Ack'];
        error_reporting(4);
        if ($ret['Ack'] != 'Failure') {
            $ret = $this->filterResponse($arr, [
                'Ack',
                'HasMoreTransactions',
                'Version',
                'PageNumber',
                'PaginationResult.TotalNumberOfPages',
            ]);

            // 统一多条订单与单条订单的格式
            if (isset($arr['OrderArray']['Order']['OrderID'])) {
                $arr['OrderArray']['Order'] = [$arr['OrderArray']['Order']];
            }

            // 获取货币类型
            preg_match('/<Total.+currencyID=\"(.+)\">/i',$responseXml,$match);
            $currency = $match[1];

            foreach ($arr['OrderArray']['Order'] as $key => $value) {
                $order['clients_id'] = $value['BuyerUserID'];
                $order['platform_id'] = $platform_id; // ebay
                $order['identifier'] = explode('-',$value['OrderID'])[0];
                $order['shop_id'] = $shop_id;
                if (! $value['PaidTime']) {
                    $order['status'] = 0;
                } elseif (! $value['ShippedTime']) {
                    $order['status'] = 1;
                } elseif ($value['ShippedTime']) {
                    $order['status'] = 5;
                }

                // 地址
                $address = [];
                $address['name'] = $value['ShippingAddress']['Name'] ? $value['ShippingAddress']['Name'] : "";
                $address['street_1'] = $value['ShippingAddress']['Street1'] ? $value['ShippingAddress']['Street1'] : "";
                $address['street_2'] = $value['ShippingAddress']['Street2'] ? $value['ShippingAddress']['Street2'] : "";
                $address['city_name'] = $value['ShippingAddress']['CityName'] ? $value['ShippingAddress']['CityName'] : "";
                $address['state_or_province'] = $value['ShippingAddress']['StateOrProvince'] ? $value['ShippingAddress']['StateOrProvince'] : "";
                $address['country'] = $value['ShippingAddress']['Country'] ? $value['ShippingAddress']['Country'] : "";
                $address['country_name'] = $value['ShippingAddress']['CountryName'] ? $value['ShippingAddress']['CountryName'] : "";
                $address['phone'] = $value['ShippingAddress']['Phone'] ? $value['ShippingAddress']['Phone'] : "";
                $address['postal_code'] = $value['ShippingAddress']['PostalCode'] ? $value['ShippingAddress']['PostalCode'] : "";
                $address['address_id'] = $value['ShippingAddress']['AddressID'] ? $value['ShippingAddress']['AddressID'] : "";
                $address['address_owner'] = $value['ShippingAddress']['AddressOwner'] ? $value['ShippingAddress']['AddressOwner'] : "";
                $address['address_usage'] = $value['ShippingAddress']['AddressUsage'] ? $value['ShippingAddress']['AddressUsage'] : "";
                $order['address_info'] = json_encode($address);

                // 支付类型
                $order['payment_methods'] = $value['PaymentMethods'];

                // 总价
                $order['money'] = floatval($value['Total']);

                $order['logistics_channel_id'] = "";

                $order['order_created_time'] = date("Y-m-d H:i:s", strtotime($value['CreatedTime']));
                $order['order_paid_time'] = $value['PaidTime'] ? date("Y-m-d H:i:s", strtotime($value['PaidTime'])) : null;
                $order['order_deliver_time'] = $value['ShippedTime'] ? date("Y-m-d H:i:s", strtotime($value['ShippedTime'])): null;
                $order['order_end_time'] = "";

                // 统一订单内多商品与单件商品格式
                if (isset($value['TransactionArray']['Transaction']['CreatedDate'])) { // 单笔交易
                    $value['TransactionArray']['Transaction'] = [$value['TransactionArray']['Transaction']];
                }

                $order['country_code'] = $value['TransactionArray']['Transaction'][0]['Item']['Site']; // 买家地址?站点?

                // 获取商品数据
                $item_data = function ($item) {
                    $arr['item_id'] = $item['Item']['ItemID'];
                    $arr['current_price'] = floatval($item['TransactionPrice']);
                    $arr['quantity_sold'] = intval($item['QuantityPurchased']);
                    $arr['sku'] = $item['Item']['SKU'];
                    $arr['name'] = $item['Item']['Title'];
                    $arr['item_img'] = "http://i.ebayimg.com/images/i/{$item['Item']['ItemID']}-0-1/s-l1000.jpg";
                    $arr['page_url'] = "http://www.ebay.com/itm/{$item['Item']['ItemID']}";
                    return $arr;
                };
                $goods = array_map(function($n) use (&$goods, $item_data) {
                    return $item_data($n);
                }, $value['TransactionArray']['Transaction']);
                $order['goods_info'] = json_encode($goods);
                // dump($order['goods_info']);

                $order['clients_designated_logistics'] = $value['ShippingServiceSelected']['ShippingService'];
                $order['is_has_message'] = $value['BuyerCheckoutMessage'] ? 1 : 0 ;

                // 运单跟踪号
                $order['shipment_tracking_number'] = $value['TransactionArray']['Transaction'][0]['ShippingDetails']['ShipmentTrackingDetails']['ShipmentTrackingNumber'];
                $order['shipping_carrier_used'] = $value['TransactionArray']['Transaction'][0]['ShippingDetails']['ShipmentTrackingDetails']['ShippingCarrierUsed'];

                // 最快最慢送达日期
                $order['estimated_delivery_time_min'] = date("Y-m-d H:i:s", strtotime($value['TransactionArray']['Transaction'][0]['ShippingServiceSelected']['ShippingPackageInfo']['EstimatedDeliveryTimeMin']));
                $order['estimated_delivery_time_max'] = date("Y-m-d H:i:s", strtotime($value['TransactionArray']['Transaction'][0]['ShippingServiceSelected']['ShippingPackageInfo']['EstimatedDeliveryTimeMax']));

                // 买家结账留言
                $order['buyer_checkout_message'] = $value['BuyerCheckoutMessage'] ? "" : $value['BuyerCheckoutMessage'];

                // 买家信息
                $buyer = [];
                $buyer['eias_token'] = $value['EIASToken'];
                $buyer['user_id'] = $value['BuyerUserID'];
                $buyer['name'] = $value['ShippingAddress']['Name'];
                $buyer['street_1'] = $value['ShippingAddress']['Street1'];
                $buyer['street_2'] = $value['ShippingAddress']['Street2'] ? "" : $value['ShippingAddress']['Street2'];
                $buyer['city_name'] = $value['ShippingAddress']['CityName'];
                $buyer['state_or_province'] = $value['ShippingAddress']['StateOrProvince'];
                $buyer['country'] = $value['ShippingAddress']['Country'];
                $buyer['country_name'] = $value['ShippingAddress']['CountryName'];
                $buyer['phone'] = $value['ShippingAddress']['Phone'];
                $buyer['postal_code'] = $value['ShippingAddress']['PostalCode'];
                $buyer['address_id'] = $value['ShippingAddress']['AddressID'];
                $buyer['address_owner'] = $value['ShippingAddress']['AddressOwner'];
                $buyer['address_usage'] = $value['ShippingAddress']['AddressUsage'];
                $buyer['platform_id'] = $platform_id;
                $buyer['email'] = ($value['TransactionArray']['Transaction'][0]['Buyer']['Email'] == "Invalid Request") ? "" : $value['TransactionArray']['Transaction'][0]['Buyer']['Email'];
                $buyer['static_alias'] = $value['TransactionArray']['Transaction'][0]['Buyer']['StaticAlias'];
                $order['buyer'] = json_encode($buyer);

                // 站点
                $order['site'] = $value['TransactionArray']['Transaction'][0]['TransactionSiteID'];

                // 收款账号
                $order['platform_payment_id'] = $value['TransactionArray']['Transaction'][0]['TransactionID'];

                // 运费
                $order['actual_shipping_cost'] = floatval($value['ShippingServiceSelected']['ShippingServiceCost']);

                // 退款数据
                if ($value['MonetaryDetails']['Refunds']['Refund']) {
                    $refunds = array_map(function ($refund){
                        $arr['fee_or_credit_amount'] = $refund['FeeOrCreditAmount'];
                        $arr['reference_id'] = $refund['ReferenceID'];
                        $arr['refund_amount'] = $refund['RefundAmount'];
                        $arr['refund_status'] = $refund['RefundStatus'];
                        $arr['refund_time'] = $refund['RefundTime'];
                        $arr['refund_to'] = $refund['RefundTo'];
                        return $arr;
                    }, $value['MonetaryDetails']['Refunds']['Refund']);
                    $order['refunds'] = json_encode($refunds);
                } else {
                    $order['refunds'] = "";
                }

                // 交易价格货币类型
                $order['currency_type'] = $currency;

                $ret['data']['orders'][$key] = $order;
            }
        } else {
            $ret = $this->filterResponse($arr, [
                'Ack',
                'Errors.ShortMessage',
                'Errors.LongMessage',
            ]);
        }
        //dump($ret);
        return $ret;
    }

    /**
     * 标注商品已发货并上传包裹单号
     * @param $token
     * @param string $data
     * @return mixed
     */
    public function completeSale($token,$data='')
    {
        $operationName = 'CompleteSale';
        $arr = $this->getEbayResponseData($operationName,$token,$data);
        return $arr;
    }
    /**
     * 获取用户的邮箱信息
     * @param $token
     * @param string $data
     * @return mixed
     */
    public function getMyMessagesPage($token, $data='')
    {
        $this->token = $this->checkToken($token);
        $arr = $this->execute('GetMyMessages', $data, 'POST');
        return $arr;
    }

    /**
     * 获取用户的邮箱信息
     * @param $token
     * @param string $data
     * @return mixed
     */
    public function getMyMessages($token, $data='')
    {
        $this->token = $this->checkToken($token);
        $arr = $this->execute('GetMyMessages', $data, 'POST');
        $ret['Ack'] = $arr['Ack'];
        // dump($arr);
        error_reporting(4);
        if ($ret['Ack'] != 'Failure') {
            $ret = $this->filterResponse($arr, [
                'Ack',
                'Version',
            ]);

            $is_assoc_array = function ($array) {
                return array_keys($array) !== range(0, count($array) - 1);
            };

            if ($is_assoc_array($arr['Messages']['Message'])) {
                $arr['Messages']['Message'] = [$arr['Messages']['Message']];
            }

            foreach ($arr['Messages']['Message'] as $key => $value) {
                $ret['data']['messages'][$key]['platform_id'] = 1;
                $ret['data']['messages'][$key]['message_topic'] = $value['Subject'];
                //$ret['data']['messages'][$key]['is_read'] = ($value['Read'] == 'true') ? 1 : 0;
                $ret['data']['messages'][$key]['is_read'] = 0;
//                 if ($value['Folder']['FolderID'] == 0) {
//                     $ret['data']['messages'][$key]['platform_clients_id'] = $value['SendingUserID'] ? $value['SendingUserID'] : 0;
//                 } elseif ($value['Folder']['FolderID'] == 1) {
//                     $ret['data']['messages'][$key]['platform_clients_id'] = $value['RecipientUserID'];
//                 }
//                $ret['data']['messages'][$key]['platform_clients_id'] = $value['Sender'];
                if ($value['Sender'] == 'eBay') {
                    $ret['data']['messages'][$key]['is_system'] = 1;
                } else {
                    $ret['data']['messages'][$key]['is_system'] = 0;
                }
                if ($value['Folder']['FolderID'] == 0) {
                    $ret['data']['messages'][$key]['send_status'] = 2;
                }
                $ret['data']['messages'][$key]['type'] = intval($value['Folder']['FolderID']);
                $ret['data']['messages'][$key]['platform_message_id'] = $value['MessageID'];
                $ret['data']['messages'][$key]['platform_goods_id'] = $value['ItemID'];
                $ret['data']['messages'][$key]['content'] = $value['Text'];
                if($value['ResponseDetails']['ResponseEnabled'] == 'true'){
                    $ret['data']['messages'][$key]['response_enabled'] = 1;
                }else{
                    $ret['data']['messages'][$key]['response_enabled'] = 0;
                }
                // 附加
                $ret['data']['messages'][$key]['token'] = $this->token;
                $ret['data']['messages'][$key]['username'] = $value['Sender'];
                $ret['data']['messages'][$key]['company_id'] = isset(Auth::user()->company_id) ? Auth::user()->company_id : null;

//                $ret['data']['messages'][$key]['flagged'] = $value['Flagged'];
//                $ret['data']['messages'][$key]['receive_date'] = $value['ReceiveDate'];
//                $ret['data']['messages'][$key]['expiration_date'] = $value['ExpirationDate'];
//                $ret['data']['messages'][$key]['response_enabled'] = $value['ResponseDetails']['ResponseEnabled'];
//                $ret['data']['messages'][$key]['replied'] = $value['Replied'];
            }
        } else {
            $ret = $this->filterResponse($arr, [
                'Ack',
                'Errors.ShortMessage',
                'Errors.LongMessage',
            ]);
        }
        //dump($ret);
        return $ret;
    }
    /**
     * 获取买家的信息 todo
     * @param $token
     * @param string $data
     * @return mixed
     */
    public function getMemberMessages($token, $data='')
    {
        $final_token = $this->checkToken($token);
        //发送请求给eBay
        $serverUrl = $this->serverUrl;
        $operationName = 'GetMemberMessages';
        $header = $this->buildEbayHeaders($operationName);
        $requestXmlBody = $this->build_request_data($operationName,$final_token,$data);
        $responseXml = Common::curlRequest($serverUrl,'POST',$header,$requestXmlBody);
        $this->judgeHttpError($responseXml);
        $arr = $this->obj($responseXml);
        $ret['Ack'] = $arr['Ack'];
        error_reporting(4);
        if ($ret['Ack'] != 'Failure') {
            $ret = $this->filterResponse($arr, [
                'Ack',
                'Version',
                'HasMoreItems',
            ]);
            if ($arr['MemberMessage']['MemberMessageExchange']) {
                foreach ($arr['MemberMessage']['MemberMessageExchange'] as $key => $value) {
                    $ret['data']['messages'][$key]['response'] = $value['Response'];
                    $ret['data']['messages'][$key]['message_status'] = $value['MessageStatus'];
                    $ret['data']['messages'][$key]['creation_date'] = $value['CreationDate'];
                    $ret['data']['messages'][$key]['item_id'] = $value['Item']['ItemID'];
                    $ret['data']['messages'][$key]['item_title'] = $value['Item']['Title'];
                    $ret['data']['messages'][$key]['item_current_price'] = $value['Item']['SellingStatus']['CurrentPrice'];
                    $ret['data']['messages'][$key]['item_url'] = $value['Item']['ListingDetails']['ViewItemURL'];
                    $ret['data']['messages'][$key]['SenderID'] = $value['Question']['SenderID'];
                    $ret['data']['messages'][$key]['SenderEmail'] = $value['Question']['SenderEmail'];
                    $ret['data']['messages'][$key]['RecipientID'] = $value['Question']['RecipientID'];
                    $ret['data']['messages'][$key]['Subject'] = $value['Question']['Subject'];
                    $ret['data']['messages'][$key]['Body'] = $value['Question']['Body'];
                    $ret['data']['messages'][$key]['MessageID'] = $value['Question']['MessageID'];
                }
            } else {
                $ret['data']['messages'] = [];
            }
        } else {
            $ret = $this->filterResponse($arr, [
                'Ack',
                'Errors.ShortMessage',
                'Errors.LongMessage',
            ]);
        }
        return $ret;
    }

    /**
     * 获取用户的token值的信息
     * @param $token
     * @param string $data
     * @return mixed
     */
    public function getTokenStatus($token, $data='')
    {
        $this->token = $this->checkToken($token);
        $arr = $this->execute('GetTokenStatus', $data,'POST');
        if ($arr['Ack'] != 'Failure') {
            $ret['Ack'] = 'Success';
            $ret['data'] = $arr['TokenStatus']['Status'];
        } else {
            $ret = $this->filterResponse([
                'Ack',
                'Errors.ShortMessage',
                'Errors.LongMessage',
            ], $arr);
        }
        return $ret;
    }

    /**
     * 卖家用来回答潜在或主动投标人关于有效上市的问题。
     * @param $token
     * @param string $data
     * @return mixed
     */
    public function addMemberMessageRTQ($token,$data='')
    {
        $operationName = 'AddMemberMessageRTQ';
        $arr = $this->getEbayResponseData($operationName,$token,$data);
        return $arr;
    }

    /**
     * 用于存在交易关系的卖家和买家之间发送message
     * @param $token
     * @param string $data
     * @return mixed
     */
    public function addMemberMessageAAQToPartner($token,$data='')
    {
        $operationName = 'AddMemberMessageAAQToPartner';
        $arr = $this->getEbayResponseData($operationName,$token,$data);
        return $arr;
    }

    /**
     * 用于卖家对于已经对在线商品进行bid或者best offer的买家发送message
     * @param $token
     * @param string $data
     * @return mixed
     */
    public function addMemberMessagesAAQToBidder($token,$data='')
    {
        $operationName = 'AddMemberMessagesAAQToBidder';
        $arr = $this->getEbayResponseData($operationName,$token,$data);
        return $arr;
    }

    /**
     * 修改ebay商店在线商品属性
     * @param $token
     * @param string $data
     * @return mixed
     */
    public function reviseItem($token,$data=''){
        $operationName = 'ReviseItem';
        $arr = $this->getEbayResponseData($operationName,$token,$data);
        return $arr;
    }
    /**
     * 修改ebay商店在线固定价格商品属性
     * @param $token
     * @param string $data
     * @return mixed
     */
    public function reviseFixedPriceItem($token,$data=''){
        $operationName = 'ReviseFixedPriceItem';
        $arr = $this->getEbayResponseData($operationName,$token,$data);
        return $arr;
    }

    /**
     * 下架单个ebay商店在线的商品
     * @param $token
     * @param string $data
     * @return mixed
     */
    public function endItem($token,$site,$data=''){
        $operationName = 'EndItem';
        $arr = $this->getEbayResponseData($operationName,$token,$data,$site);
        return $arr;
    }
    /**
     * 批量下架ebay商店在线的商品
     * @param $token
     * @param string $data
     * @return mixed
     */
    public function endItems($token,$site_id,$data=''){
        $operationName = 'EndItems';
        $arr = $this->getEbayResponseData($operationName,$token,$data,$site_id);
        return $arr;
    }
    /**
     * 下架ebay商店在线的固定价格的商品
     * @param $token
     * @param string $data
     * @return mixed
     */
    public function endFixedPriceItem($token,$site_id,$data=''){
        $operationName = 'EndFixedPriceItem';
        $arr = $this->getEbayResponseData($operationName,$token,$data,$site_id);
        return $arr;
    }
    /**
     * 重新上架ebay商店下架的商品，要求下架90天之内的才能重新上架
     * @param $token
     * @param string $data
     * @return mixed
     */
    public function relistItem($token,$site_id,$data=''){
        $operationName = 'RelistItem';
        $arr = $this->getEbayResponseData($operationName,$token,$data,$site_id);
        return $arr;
    }
    /**
     * 重新上架ebay商店下架的固定价格的商品，要求下架90天之内的才能重新上架
     * @param $token
     * @param string $data
     * @return mixed
     */
    public function relistFixedPriceItem($token,$site_id,$data=''){
        $operationName = 'RelistFixedPriceItem';
        $arr = $this->getEbayResponseData($operationName,$token,$data,$site_id);
        return $arr;
    }

    /**
     * 获取汇率
     * @return mixed
     */
    public function getExchangeRate()
    {
        $serverUrl = 'http://op.juhe.cn/onebox/exchange/query?key=723ef9ace8dae0684d260be6b262a49e';
        $response = Common::curlRequest($serverUrl,'POST');
        $this->judgeHttpError($response);
        return $response;
    }

    /**
     * 获取其他汇率
     * @param $other
     * @return mixed
     */
    public function getOtherExchangeRate($other)
    {
        $serverUrl = 'http://op.juhe.cn/onebox/exchange/currency?key=723ef9ace8dae0684d260be6b262a49e&from=CNY&to='.$other;
        $response = Common::curlRequest($serverUrl,'POST');
        $this->judgeHttpError($response);
        return $response;

    }


    /**
     * 获取用户的token值的信息
     * @param $token
     * @param string $data
     * @return mixed
     */
    public function findItemsByCategory($token, $data='')
    {
        $this->token = $this->checkToken($token);
        $arr = $this->execute('findItemsByCategory', $data,'POST');
        dump($arr);
        return $arr;
    }


    /**
     * 构建请求header
     * @param $operationName
     * @param string $site
     * @return array
     */
    private function buildEbayHeaders($operationName,$site='0')
    {
        $headers = [
            'X-EBAY-API-SITEID:'.$site,
            'X-EBAY-API-COMPATIBILITY-LEVEL: 967',
            'X-EBAY-API-CALL-NAME:'.$operationName,
            'X-EBAY-API-APP-NAME:'.$this->appname,
            'X-EBAY-API-DEV-NAME:'.$this->devname,
            'X-EBAY-API-CERT-NAME:'.$this->certname,
        ];
        return $headers;
    }

    /**
     * 构建请求 data
     * @param $verb
     * @param $token
     * @param string $data
     * @return string
     */
    public function build_request_data($verb,$token,$data=[])
    {
        $xml = "<?xml version='1.0' encoding='utf-8'?>";
        $xml .= "<{$verb}Request xmlns=\"urn:ebay:apis:eBLBaseComponents\">";
        $xml .= "<RequesterCredentials>";
        $xml .= "<eBayAuthToken>{$token}</eBayAuthToken>";
        $xml .= "</RequesterCredentials>";
        $xml.= "<ErrorLanguage>en_US</ErrorLanguage>";
        $xml.= "<WarningLevel>High</WarningLevel>";
        $xml .= $this->xml($data);
        $xml .= "</{$verb}Request>";
        return $xml;
    }



    public function buildRequestData($verb, $data)
    {
        return $this->build_request_data($verb, $this->token, $data);
    }


    public function buildRequestHeaders($verb)
    {
        return $this->buildEbayHeaders($verb, $this->site);
    }


    public function buildRequestUrl($verb)
    {
        return $this->serverUrl;
    }

}
