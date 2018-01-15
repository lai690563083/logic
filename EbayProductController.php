<?php
namespace App\Http\Controllers\Api;

use App\Business\PlatGoodsBiz;
use App\Business\PlatMessageBiz;
use App\Business\PlatOrderBiz;
use App\Business\SaasExchangeRateBiz;
use App\Business\SaasGoodsCategoryBiz;
use App\Http\Controllers\Api\Libraries\EbayProduct\Trading;
use App\Models\SaasGoodsCategory;
use Log;
use Cache;
use Illuminate\Http\Request;

class EbayProductController extends BaseController
{

    protected $saasGoodsCategoryBiz;
    protected $trading;
    protected $platGoodsBiz;
    protected $saasExchangeRateBiz;
    public function __construct()
    {
        $this->trading = new Trading(env('EBAY_CHECK_SANDBOX',false));
        $this->saasGoodsCategoryBiz = new SaasGoodsCategoryBiz;
        $this->platGoodsBiz = new PlatGoodsBiz;
        $this->saasExchangeRateBiz = new SaasExchangeRateBiz;
    }

    /**
     * 获取ebay用户的信息
     * @return \Illuminate\Http\Response
     */
    public function getUser($token)
    {
        $data = [
            'DetailLevel' => 'ReturnAll',
        ];
        $ret = $this->trading->getUser($token, $data);
        return $ret;
    }

    /**
     * 获取ebay授权的url
     * @return string
     */
    public function curlTake()
    {
        if(env('EBAY_CHECK_SANDBOX')){
            $token = $this->default_token['sandbox'];
        }else{
            $token = $this->default_token['logic'];
        }
        $ret = $this->trading->curlTake($token);
        return $ret;
    }

    /**
     * 获取ebay授权的token值
     * @return mixed
     */
    public function fetchToken()
    {
        if(env('EBAY_CHECK_SANDBOX')){
            $token = $this->default_token['sandbox'];
        }else{
            $token = $this->default_token['logic'];
        }
        $ret = $this->trading->fetchToken($token);
        return $ret;
    }

    /*
     * 获取ebay用户店铺配置信息，需该店铺有配置店铺才会返回成功
     * @return array
     */
    public function getStore($token)
    {
        $ret = $this->trading->getStore($token);
        return $ret;
    }

    /*
     *获取ebay用户的账户信息
     * @return array
     */
    public function getAccount()
    {
        $token = '';
        $data = [
            'AccountEntrySortType' => 'AccountEntryFeeTypeAscending',
            'AccountHistorySelection' => 'LastInvoice',
        ];
        $ret = $this->trading->getAccount($token, $data);
        dd($ret);
    }

    /**
     * 获取ebay用户店铺反馈信息
     */
    public function getFeedback()
    {
        $data = [
            'UserID' => 'testuser_logic',
        ];
        $token = '';
        $ret = $this->trading->getFeedback($token, $data);
        dd($ret);
    }
    /**
     * 获取一个产品的详细信息
     */
    public function getItem($token,$site,$platform_goods_id)
    {
        set_time_limit(0);
        $data = [
            'ItemID' => $platform_goods_id,
            'DetailLevel' => 'ReturnAll',
            'IncludeItemCompatibilityList' => 'true',
            'IncludeItemSpecifics' => 'true',
            'IncludeTaxTable' => 'true',
            'IncludeWatchCount' => 'true',
        ];
        $ret = $this->trading->getItem($token, $data, $site);
        return $ret;
    }

    /**
     * 获取一个产品的详细信息
     */
    public function getItemOne($token,$info = null)
    {
        set_time_limit(0);
        $data = [
            'ItemID' => $info['platform_goods_id'],
            'DetailLevel' => 'ReturnAll',
            'IncludeItemCompatibilityList' => 'true',
            'IncludeItemSpecifics' => 'true',
            'IncludeTaxTable' => 'true',
            'IncludeWatchCount' => 'true',
        ];
        // 最多重试10次
        $ret = [];
        for ($i = 0;$i < 10;$i++) {
            $ret = $this->trading->getItem($token, $data);
            if ($ret['Ack'] != 'Failure') {
                break;
            }
            Log::info('第' . $i . '次失败');
            if ($i == 9) {
                $result = $this->result(10503,'报错信息为：'.$ret['Errors']['LongMessage']);
                return $result;
            }
        }
        $datas['datas'][] = $ret['data'];
        if(empty($ret['data'])){
            Log::info($ret['Ack'].'但是没有数据');
            $result = $this->result(10503,'请求失败');
        }else{
            $result = $this->result(0,'',$datas);
        }
        return $result;
    }

    /**
     * 获取ebay在线商品的所有的id
     * @param $token
     * @param array $info
     * @return array
     */
    public function getAllSellerListCount($token ,$shop_id)
    {
        date_default_timezone_set('UTC');
        set_time_limit(0);
        //查询最近添加的时间
        global $ERP_COMPANY;
        $ERP_COMPANY['ALL_COMPANY'] = true;
        $companyShop = $this->platGoodsBiz->getCompanyShop($shop_id);
        $ERP_COMPANY['COMPANY_ID'] = $companyShop->company_id;
        $create_at = $this->platGoodsBiz->getLatestPlatGoods($shop_id);
        $ERP_COMPANY['ALL_COMPANY'] = false;
        unset($ERP_COMPANY['ALL_COMPANY']);
        if (!empty($create_at['data']['publish_start_time'])) {
            $count_time = time() - strtotime($create_at['data']['publish_start_time']);
            if($count_time > 60 * 60 * 24 * 90){
                $begintime = date('Y-m-d H:i:s', time() - 60 * 60 * 24 * 90);
            }else{
                $begintime = date('Y-m-d H:i:s', strtotime($create_at['data']['publish_start_time']) + 1);
            }
        } else {
            $begintime = date("Y-m-d H:i:s", time() - 60 * 60 * 24 * 90);
        }
        $endtime = date("Y-m-d H:i:s");
        $data = [
            '_OutputSelector_1' => 'ItemID',
            '_OutputSelector_2' => 'HasMoreItems',
            '_OutputSelector_3' => 'PageNumber',
            '_OutputSelector_4' => 'ItemsPerPage',
            '_OutputSelector_5' => 'PaginationResult',
            'StartTimeFrom' => $begintime,
            'StartTimeTo' => $endtime,
            'Pagination' => [
                'EntriesPerPage' => 200,
                'PageNumber' => 1,
            ],
        ];
        //获取ebay店铺商品ID
        $ret = $this->trading->getSellerListActive($token, $data);
        if ($ret['Ack'] == 'Failure') {
            Log::info('拉取商品，第一次'.$ret['Ack'] . ':' . $ret['Errors']['LongMessage']);
            $ret = $this->trading->getSellerListActive($token, $data);
            Log::info('拉取商品，第二次'.$ret['Ack'] . ':' . $ret['Errors']['LongMessage']);
            if($ret['Ack'] == 'Failure'){
                $result = $this->result(10503,'报错信息为：'.$ret['Errors']['LongMessage']);
                return $result;
            }
        }
        $has_more = $ret['HasMoreItems'];
        $allitem = [];
        if(!isset($ret['data'])){
            $result = $this->result(0,'',null);
            return $result;
        }
        foreach ($ret['data'] as $key => $val){
            $allitem[] = $val['platform_goods_id'];
        }
        while ($has_more == 'true') {
            Log::info('Call getAllSellerListCount times +1'); // todo
            $data['Pagination']['PageNumber'] += 1;
            $ret = $this->trading->getSellerListActive($token, $data);
            if ($ret['Ack'] == 'Failure') {
                Log::info('循环里面，拉取商品，第一次'.$ret['Ack'] . ':' . $ret['Errors']['LongMessage']);
                $ret = $this->trading->getSellerListActive($token, $data);
                Log::info('循环里面，拉取商品，第二次'.$ret['Ack'] . ':' . $ret['Errors']['LongMessage']);
                if($ret['Ack'] == 'Failure'){
                    $result = $this->result(10503,'报错信息为：'.$ret['Errors']['LongMessage']);
                    return $result;
                }
            }
            $resarr = [];
            foreach ($ret['data'] as $key => $val){
                $resarr[] = $val['platform_goods_id'];
            }
            $allitem = array_merge($allitem, $resarr);
            $has_more = $ret['HasMoreItems'];
        }
        $rets = [
            'data' => $allitem,
            'publish_time' => $begintime,
        ];
        $result = $this->result(0,'',$rets);
        return $result;
    }

    /**
     * 获取ebay店铺在线商品列表信息
     * @param string $token
     */
    public function getAllSellerList($token ,$info = [])
    {
        //延长脚本运行时间
        set_time_limit(0);
        //判断拉取时间是否存在
        $begintime = empty($info['publish_time']) ? date("Y-m-d H:i:s", time() - 60 * 60 * 24 * 90) : $info['publish_time'];
        $endtime = date("Y-m-d H:i:s");
        if(!empty($info['page'])){
            $data = [
                'DetailLevel' => 'ReturnAll',
                'IncludeVariations' => 'true',
                'IncludeWatchCount' => 'true',
                'StartTimeFrom' => $begintime,
                'StartTimeTo' => $endtime,
                'Pagination' => [
                    'EntriesPerPage' => 10,
                    'PageNumber' => $info['page'],
                ]
            ];
            //获取ebay店铺商品
            $ret = $this->trading->getSellerListActive($token, $data);
            if ($ret['Ack'] == 'Failure') {
                Log::info('拉取商品，第一次'.$ret['Ack'] . ':' . $ret['Errors']['LongMessage']);
                $ret = $this->trading->getSellerListActive($token, $data);
                Log::info('拉取商品，第二次'.$ret['Ack'] . ':' . $ret['Errors']['LongMessage']);
                if($ret['Ack'] == 'Failure'){
                    $result = $this->result(10503,'报错信息为：'.$ret['Errors']['LongMessage']);
                    return $result;
                }
            }
            $datas = [
                'datas' => $ret['data']
            ];
            if(empty($ret['data'])){
                Log::info($ret['Ack'].'但是没有数据');
                $result = $this->result(10503,'请求失败');
            }else{
                $result = $this->result(0,'',$datas);
            }
        } else {
            $result = $this->result(10010, '报错信息为：info中的page为空');
        }
        return $result;
    }


    /**
     * 获取拉取商品的总页数
     * @param $token
     * @param int $shop_id
     * @return mixed
     */
    public function getAllSellerListPage($token, $shop_id = 1)
    {
        set_time_limit(0);
        //查询最近添加的时间
        global $ERP_COMPANY;
        $ERP_COMPANY['ALL_COMPANY'] = true;
        $companyShop = $this->platGoodsBiz->getCompanyShop($shop_id);
        $ERP_COMPANY['COMPANY_ID'] = $companyShop->company_id;
        $create_at = $this->platGoodsBiz->getLatestPlatGoods($shop_id);
        $ERP_COMPANY['ALL_COMPANY'] = false;
        unset($ERP_COMPANY['ALL_COMPANY']);
        if (!empty($create_at['data']['publish_start_time'])) {
            $count_time = time() - strtotime($create_at['data']['publish_start_time']);
            if($count_time > 60 * 60 * 24 * 90){
                $begintime = date('Y-m-d H:i:s', time() - 60 * 60 * 24 * 90);
            }else{
                $begintime = date('Y-m-d H:i:s', strtotime($create_at['data']['publish_start_time']) + 1);
            }
        } else {
            $begintime = date("Y-m-d H:i:s", time() - 60 * 60 * 24 * 90);
        }
        $endtime = date("Y-m-d H:i:s");
        $data = [
            'GranularityLevel' => 'Coarse',
            'StartTimeFrom' => $begintime,
            'StartTimeTo' => $endtime,
            'Pagination' => [
                'EntriesPerPage' => 10,
                'PageNumber' => 1,
            ],
            'OutputSelector' => 'PaginationResult'
        ];
        //获取ebay店铺商品ID
        $ret = $this->trading->getSellerListActive($token, $data);
        if($ret['Ack'] == 'Failure'){
            Log::info('拉取商品，第一次获取页数'.$ret['Ack'] . ':' . $ret['Errors']['LongMessage']);
            $ret = $this->trading->getSellerListActive($token, $data);
            Log::info('拉取商品，第二次获取页数'.$ret['Ack'] . ':' . $ret['Errors']['LongMessage']);
            if($ret['Ack'] == 'Failure'){
                $result = $this->result(10503,'报错信息为：'.$ret['Errors']['LongMessage']);
            }else{
                $page = $ret['PaginationResult']['TotalNumberOfPages'];
                $ret = [
                    'page' => $page,
                    'publish_time' => $begintime,
                ];
                $result = $this->result(0,'',$ret);
            }
        }else{
            $page = $ret['PaginationResult']['TotalNumberOfPages'];
            $ret = [
                'page' => $page,
                'publish_time' => $begintime,
            ];
            $result = $this->result(0,'',$ret);
        }
        return $result;
    }

    /**
     * 处理刊登商品最终数据
     * @param $data_info
     * @return mixed
     */
    public function getFinalEbayPublishData($data_info)
    {
        error_reporting(4);
        //------------------------------------店铺信息
        $site_info = $data_info['data_site'];
        $data['Item']['Country'] = $data_info['country']; //卖家注册的国家，在erp_company_shop表里面的info字段里的Country值// todo 必须项
        $data['Item']['Site'] = $site_info['value']; //物品刊登的站点// todo 必须项
        $data['Item']['Currency'] = $site_info['currency']; //物品刊登的货币类型// todo 必须项
        $data['Item']['PrimaryCategory']['CategoryID'] = $data_info['first_goods_category_id']; //产品分类1,string类型// todo 必须项
        if (!empty($data_info['second_goods_category_id'])) {
            //产品分类2,string类型
            $data['Item']['SecondaryCategory']['CategoryID'] = $data_info['second_goods_category_id'];
        }
        if (!empty($data_info['first_shop_goods_category_id'])) {
            //店铺分类1，long类型
            $data['Item']['Storefront']['StoreCategoryID'] = $data_info['first_shop_goods_category_id'];
        }
        if (!empty($data_info['second_shop_goods_category_id'])) {
            //店铺分类2，long类型
            $data['Item']['Storefront']['StoreCategory2ID'] = $data_info['second_shop_goods_category_id'];
        }
        if (!empty($data_info['first_shop_goods_category_name'])) {
            //店铺分类1的名称，string类型
            $data['Item']['Storefront']['StoreCategoryName'] = $data_info['first_shop_goods_category_name'];
        }
        if (!empty($data_info['second_shop_goods_category_name'])) {
            //店铺分类2的名称,string类型
            $data['Item']['Storefront']['StoreCategory2Name'] = $data_info['second_shop_goods_category_name'];
        }
        //----------------------------------------------产品信息
        $data['Item']['ListingType'] = $data_info['publish_type']; //售卖形式,Chinese为拍卖，FixedPriceItem为固定价格// todo 必须项
        $data['Item']['SKU'] = $data_info['platform_goods_sku']; //SKU，string类型// todo 必须项
        $data['Item']['Title'] = $data_info['title']; //产品标题，string类型// todo 必须项
        if (!empty($data_info['sub_title'])) {
            $data['Item']['SubTitle'] = $data_info['sub_title']; //产品子标题，string类型
        }
        if (!empty($data_info['condition_id'])) {
            //物品状况id,如果为1000则无需状况描述，在erp_saas_goods_category表的condition字段里面
            $data['Item']['ConditionID'] = $data_info['condition_id'];
        }
        if (!empty($data_info['condition_description'])) {
            //物品状况描述，如果上面的condition为新，就不要要描述，
            $data['Item']['ConditionDescription'] = $data_info['condition_description'];
        }
        if (!empty($data_info['images']['gallery_type'])) {
            //橱窗展示需要收费，默认为None(无)，Gallery(小图)，Plus(橱窗展示);
            $data['Item']['PictureDetails']['GalleryType'] = $data_info['images']['gallery_type'];
        }
        if (!empty($data_info['images']['photo_display'])) {
            //指定列表中使用的图像显示类型。值可以是None，PicturePack,SuperSize,SuperSizePictureShow(这个只适用Motors)
            //目前拉取的店铺商品的值都为PicturePack(增加显示的图像数量，这仅适用于使用ebay托管的图像)，
            $data['Item']['PictureDetails']['PhotoDisplay'] = $data_info['images']['photo_display'];
        }
        $data['Item']['PictureDetails']['GalleryURL'] = $data_info['images']['gallery_url']; // 商品主图片，todo 必须项
        for ($i = 0; $i < count($data_info['images']['pictureurls']); $i++) {
            if (!empty($data_info['images']['pictureurls'][$i])) {
                $data['Item']['PictureDetails']['_PictureURL_'.$i] = $data_info['images']['pictureurls'][$i];
            }
        }
        //-----------------------------------------------属性信息
        //自定义属性
        for ($i = 0; $i < count($data_info['specifics']['diy']['name_value_lists']); $i++) {
            if (!empty($data_info['specifics']['diy']['name_value_lists'][$i]['Name'])) {
                $data['Item']['ItemSpecifics']['_NameValueList_' . $i]['Name'] = $data_info['specifics']['diy']['name_value_lists'][$i]['Name'];
            }
            for ($j = 0; $j < count($data_info['specifics']['diy']['name_value_lists'][$i]['Value']); $j++) {
                if (!empty($data_info['specifics']['diy']['name_value_lists'][$i]['Value'][$j])) {
                    $data['Item']['ItemSpecifics']['_NameValueList_' . $i]['_Value_' . $j] = $data_info['specifics']['diy']['name_value_lists'][$i]['Value'][$j];
                }
            }
        }
        //商品属性
        for ($i = 0; $i < count($data_info['specifics']['product']['name_value_lists']); $i++) {
            if (!empty($data_info['specifics']['product']['name_value_lists'][$i]['Name'])) {
                $data['Item']['ProductListingDetails']['_NameValueList_' . $i]['Name'] = $data_info['specifics']['product']['name_value_lists'][$i]['Name'];
            }
            for ($j = 0; $j < count($data_info['specifics']['product']['name_value_lists'][$i]['Value']); $j++) {
                if (!empty($data_info['specifics']['product']['name_value_lists'][$i]['Value'][$j]['value'])) {
                    $data['Item']['ProductListingDetails']['_NameValueList_' . $i]['_Value_' . $j] = $data_info['specifics']['product']['name_value_lists'][$i]['Value'][$j]['value'];
                }
            }
        }
        if (!empty($data_info['specifics']['product']['brand_mpn']['brand'])) {
            $data['Item']['ProductListingDetails']['BrandMPN']['Brand'] = $data_info['specifics']['product']['brand_mpn']['brand'];
        }
        if (!empty($data_info['specifics']['product']['brand_mpn']['mpn'])) {
            $data['Item']['ProductListingDetails']['BrandMPN']['MPN'] = $data_info['specifics']['product']['brand_mpn']['mpn'];
        }
        if (!empty($data_info['specifics']['product']['ean'])) {
            $data['Item']['ProductListingDetails']['EAN'] = $data_info['specifics']['product']['ean'];
        }
        if (!empty($data_info['specifics']['product']['isbn'])) {
            $data['Item']['ProductListingDetails']['ISBN'] = $data_info['specifics']['product']['isbn'];
        }
        if (!empty($data_info['specifics']['product']['upc'])) {
            $data['Item']['ProductListingDetails']['UPC'] = $data_info['specifics']['product']['upc'];
        }
        if (!empty($data_info['specifics']['product']['product_reference_id'])) {
            $data['Item']['ProductListingDetails']['ProductReferenceID'] = $data_info['specifics']['product']['product_reference_id'];
        }
        //----------------------------------------变种属性
        //变种属性
        for ($i = 0; $i < count($data_info['variation_specifics_set']['NameValueList']); $i++) {
            if (!empty($data_info['variation_specifics_set']['NameValueList'][$i]['Name'])) {
                $data['Item']['Variations']['VariationSpecificsSet']['_NameValueList_' . $i]['Name'] = $data_info['variation_specifics_set']['NameValueList'][$i]['Name'];
            } //添加自定义属性名字
            for ($j = 0; $j < count($data_info['variation_specifics_set']['NameValueList'][$i]['Value']); $j++) {
                if (!empty($data_info['variation_specifics_set']['NameValueList'][$i]['Value'][$j])) {
                    $data['Item']['Variations']['VariationSpecificsSet']['_NameValueList_' . $i]['_Value_' . $j] = $data_info['variation_specifics_set']['NameValueList'][$i]['Value'][$j];
                } //属性值
            }
        }
        //变种图片
        if (!empty($data_info['variation_specific_name'])) {
            $data['Item']['Variations']['Pictures']['VariationSpecificName'] = $data_info['variation_specific_name'];
        }
        //变种类型
        for ($i = 0; $i < count($data_info['variation_specific_picture_sets']); $i++) {
            if (!empty($data_info['variation_specific_picture_sets'][$i]['VariationSpecificValue'])) {
                $data['Item']['Variations']['Pictures']['_VariationSpecificPictureSet_' . $i]['VariationSpecificValue'] = $data_info['variation_specific_picture_sets'][$i]['VariationSpecificValue'];
            } //属性值，如color:red，red就是这个值
            //属性对应名字下的图片数组
            for ($j = 0; $j < count($data_info['variation_specific_picture_sets'][$i]['PictureURL']); $j++) {
                if (!empty($data_info['variation_specific_picture_sets'][$i]['PictureURL'][$j])) {
                    $data['Item']['Variations']['Pictures']['_VariationSpecificPictureSet_' . $i]['_PictureURL_' . $j] = $data_info['variation_specific_picture_sets'][$i]['PictureURL'][$j];
                }
            }
        }
        //变种参数
        for ($f = 0; $f < count($data_info['variations']['variations']); $f++) {
            if (!empty($data_info['variations']['variations'][$f]['made_for_outlet_comparison_price'])) {
                $data['Item']['Variations']['_Variation_' . $f]['DiscountPriceInfo']['MadeForOutletComparisonPrice'] = $data_info['variations']['variations'][$f]['made_for_outlet_comparison_price'];
            }
            if (!empty($data_info['variations']['variations'][$f]['minimum_advertised_price'])) {
                $data['Item']['Variations']['_Variation_' . $f]['DiscountPriceInfo']['MinimumAdvertisedPrice'] = $data_info['variations']['variations'][$f]['minimum_advertised_price'];
            }
            if (!empty($data_info['variations']['variations'][$f]['minimum_advertised_price_exposure'])) {
                $data['Item']['Variations']['_Variation_' . $f]['DiscountPriceInfo']['minimum_advertised_price_exposure'] = $data_info['variations']['variations'][$f]['minimum_advertised_price_exposure'];
            }
            if (!empty($data_info['variations']['variations'][$f]['original_retail_price'])) {
                $data['Item']['Variations']['_Variation_' . $f]['DiscountPriceInfo']['OriginalRetailPrice'] = $data_info['variations']['variations'][$f]['original_retail_price'];
            }
            if (!empty($data_info['variations']['variations'][$f]['sold_off_ebay'])) {
                $data['Item']['Variations']['_Variation_' . $f]['DiscountPriceInfo']['SoldOffeBay'] = $data_info['variations']['variations'][$f]['sold_off_ebay'];
            }
            if (!empty($data_info['variations']['variations'][$f]['sold_on_ebay'])) {
                $data['Item']['Variations']['_Variation_' . $f]['DiscountPriceInfo']['SoldOneBay'] = $data_info['variations']['variations'][$f]['sold_on_ebay'];
            }
            if (!empty($data_info['variations']['variations'][$f]['Quantity'])) {
                $data['Item']['Variations']['_Variation_' . $f]['Quantity'] = $data_info['variations']['variations'][$f]['Quantity'];
            } //数量
            if (!empty($data_info['variations']['variations'][$f]['SKU'])) {
                $data['Item']['Variations']['_Variation_' . $f]['SKU'] = $data_info['variations']['variations'][$f]['SKU'];} //SKU
            if (!empty($data_info['variations']['variations'][$f]['StartPrice'])) {
                $data['Item']['Variations']['_Variation_' . $f]['StartPrice'] = $data_info['variations']['variations'][$f]['StartPrice'];
            } //价格
            if (!empty($data_info['variations']['variations'][$f]['VariationProductListingDetails']['UPC'])) {
                $data['Item']['Variations']['_Variation_' . $f]['VariationProductListingDetails']['UPC'] = $data_info['variations']['variations'][$f]['VariationProductListingDetails']['UPC'];
            } //UPC
            if (!empty($data_info['variations']['variations'][$f]['variation_ean'])) {
                $data['Item']['Variations']['_Variation_' . $f]['VariationProductListingDetails']['EAN'] = $data_info['variations']['variations'][$f]['variation_ean'];
            }
            if (!empty($data_info['variations']['variations'][$f]['variation_isbn'])) {
                $data['Item']['Variations']['_Variation_' . $f]['VariationProductListingDetails']['ISBN'] = $data_info['variations']['variations'][$f]['variation_isbn'];
            }
            //变种属性
            for ($i = 0; $i < count($data_info['variations']['variations'][$f]['variation_product_listing_details_name_value_lists']); $i++) {
                if (!empty($data_info['variations']['variations'][$f]['variation_product_listing_details_name_value_lists'][$i]['name'])) {
                    $data['Item']['Variations']['_Variation_' . $f]['VariationProductListingDetails']['_NameValueList_' . $i]['Name'] = $data_info['variations']['variations'][$f]['variation_product_listing_details_name_value_lists'][$i]['name'];
                }
                for ($j = 0; $j < count($data_info['variations']['variations'][$f]['variation_product_listing_details_name_value_lists'][$i]['values']); $j++) {
                    if (!empty($data_info['variations']['variations'][$f]['variation_product_listing_details_name_value_lists'][$i]['values'][$j]['value'])) {
                        $data['Item']['Variations']['_Variation_' . $f]['VariationProductListingDetails']['_NameValueList_' . $i]['_Value_' . $j] = $data_info['variations']['variations'][$f]['variation_product_listing_details_name_value_lists'][$i]['values'][$j]['value'];
                    }
                }
            }
            //wilson 变种参数
            for ($i = 0; $i < count($data_info['variations']['variations']); $i++) {
                if (!empty($data_info['variations']['variations'][$f]['VariationSpecifics']['NameValueList'][$i]['Name'])) {
                    $data['Item']['Variations']['_Variation_' . $f]['VariationSpecifics']['_NameValueList_'. $i]['Name'] = $data_info['variations']['variations'][$f]['VariationSpecifics']['NameValueList'][$i]['Name'];
                }
                if (!empty($data_info['variations']['variations'][$f]['VariationSpecifics']['NameValueList'][$i]['Value'])) {
                    $data['Item']['Variations']['_Variation_' . $f]['VariationSpecifics']['_NameValueList_'. $i]['Value'] = $data_info['variations']['variations'][$f]['VariationSpecifics']['NameValueList'][$i]['Value'];
                }

            }
            //logic 变种参数
            for ($i = 0; $i < count($data_info['variations']['variations'][$f]); $i++) {
                for ($j = 0; $j < count($data_info['variations']['variations'][$f]['VariationSpecifics'][$i]['NameValueList']); $j++) {
                    if (!empty($data_info['variations']['variations'][$f]['VariationSpecifics'][$i]['NameValueList'][$j]['Name'])) {
                        $data['Item']['Variations']['_Variation_' . $f]['_VariationSpecifics_' . $i]['_NameValueList_' . $j]['Name'] = $data_info['variations']['variations'][$f]['VariationSpecifics'][$i]['NameValueList'][$j]['Name'];
                    }

                    for ($k = 0; $k < count($data_info['variations']['variations'][$f]['VariationSpecifics'][$i]['NameValueList'][$j]['Value']); $k++) {
                        if (!empty($data_info['variations']['variations'][$f]['VariationSpecifics'][$i]['NameValueList'][$j]['Value'][$k])) {
                            $data['Item']['Variations']['_Variation_' . $f]['_VariationSpecifics_' . $i]['_NameValueList_' . $j]['_Value_' . $k] = $data_info['variations']['variations'][$f]['VariationSpecifics'][$i]['NameValueList'][$j]['Value'][$k];
                        }
                    }
                }
            }
        }
        //--------------------------------------商品描述信息
        $data['Item']['Description'] = $data_info['description']; // todo 必须项
        //--------------------------------------价格信息
        if (!empty($data_info['start_price'])) {
            $data['Item']['StartPrice'] = $data_info['start_price']; //起拍价// todo 必须项
        }
        if (!empty($data_info['reserve_price'])) {
            //保留价,卖方愿意出售物品的最低价格，StartPrice必须低于ReservePrice
            $data['Item']['ReservePrice'] = $data_info['reserve_price'];
        }
        if (!empty($data_info['buy_it_now_price'])) {
            //一口价
            $data['Item']['BuyItNowPrice'] = $data_info['buy_it_now_price'];
        }
        if (!empty($data_info['private_listing'])) {
            //设置为true则买家不希望将自己的ID透露给他人
            $data['Item']['PrivateListing'] = $data_info['private_listing'];
        }
        //刊登天数,在erp_saas_goods_category表里面的payment_method字段里面 todo 必须项
        $data['Item']['ListingDuration'] = $data_info['listing_duration'];
        if (!empty($data_info['best_offer_enabled'])) {
            //最佳优惠功能
            $data['Item']['BestOfferDetails']['BestOfferEnabled'] = $data_info['best_offer_enabled'];
        }
        //----------------------------------------------------付款信息
        for ($i = 1; $i < count($data_info['payment_methods']); $i++) {
            $data['Item']['_PaymentMethods_' . $i] = $data_info['payment_methods'][$i]; //收款方式 // todo 必须项
        }
        $data['Item']['PayPalEmailAddress'] = $data_info['payment_username']; //收款账号 // todo 必须项
        $data['Item']['_PaymentMethods_0'] = 'PayPal'; //收款方式 // todo 必须项
        if (!empty($data_info['payment_instructions'])) {
            $data['Item']['ShippingDetails']['PaymentInstructions'] = $data_info['payment_instructions'];
        } //付款说明
        //---------------------------------------------------买家限制
        if (!empty($data_info['disable_buyer_requirements'])) {
            //是否允许所有买家购买商品
            $data['Item']['DisableBuyerRequirements'] = $data_info['disable_buyer_requirements'];
        }
        if (!empty($data_info['linked_paypal_account'])) {
            //设置true为阻止没有将PayPal帐户与其eBay帐户相关联的投标人
            $data['Item']['BuyerRequirementDetails']['LinkedPayPalAccount'] = $data_info['linked_paypal_account'];
        }
        if (!empty($data_info['maximum_buyer_policy_violations_count'])) {
            //阻止在指定时间段内对其帐户有一个或多个买方违反政策的潜在买家
            $data['Item']['BuyerRequirementDetails']['MaximumBuyerPolicyViolations']['Count'] = $data_info['maximum_buyer_policy_violations_count'];
        }
        if (!empty($data_info['maximum_buyer_policy_violations_period'])) {
            $data['Item']['BuyerRequirementDetails']['MaximumBuyerPolicyViolations']['Period'] = $data_info['maximum_buyer_policy_violations_period'];
        }
        //限制10天期间买家可以从卖方购买的物品数量
        if (!empty($data_info['MaximumItemRequirements_MaximumItemCount'])) {
            $data['Item']['BuyerRequirementDetails']['MaximumItemRequirements']['MaximumItemCount'] = $data_info['MaximumItemRequirements_MaximumItemCount'];
        }
        if (!empty($data_info['MaximumItemRequirements_MinimumFeedbackScore'])) {
            $data['Item']['BuyerRequirementDetails']['MaximumItemRequirements']['MinimumFeedbackScore'] = $data_info['MaximumItemRequirements_MinimumFeedbackScore'];
        }
        ////阻止在指定的时间段内在他们的账户上有一个或多个未付项目罢工的潜在买家
        if (!empty($data_info['maximum_unpaid_item_strikes_info_count'])) {
            $data['Item']['BuyerRequirementDetails']['MaximumUnpaidItemStrikesInfo']['Count'] = $data_info['maximum_unpaid_item_strikes_info_count'];
        }
        if (!empty($data_info['maximum_unpaid_item_strikes_info_period'])) {
            $data['Item']['BuyerRequirementDetails']['MaximumUnpaidItemStrikesInfo']['Period'] = $data_info['maximum_unpaid_item_strikes_info_period'];
        }
        if (!empty($data_info['MinimumFeedbackScore'])) {
            //阻止反馈评分小于指定值的投标人
            $data['Item']['BuyerRequirementDetails']['MinimumFeedbackScore'] = $data_info['MinimumFeedbackScore'];
        }
        if (!empty($data_info['ship_to_registration_country'])) {
            //默认值为false,设置true为阻止在发货到排除列表的国家/地区,如果有限制地区，这个值必须为true
            $data['Item']['BuyerRequirementDetails']['ShipToRegistrationCountry'] = $data_info['ship_to_registration_country'];}
        if (!empty($data_info['zero_feedback_score'])) {
            //默认值false,只适用于中国网站的卖家 ，如果为true，以100美元或更高的价格购买商品来阻止潜在买家的反馈评分为0
            $data['Item']['BuyerRequirementDetails']['ZeroFeedbackScore'] = $data_info['zero_feedback_score'];
        }
        if (isset($data_info['dispatch_time_max'])) {
            //卖方承诺在收到清除付款后准备发货的物品的最长工作天数
            $data['Item']['DispatchTimeMax'] = $data_info['dispatch_time_max'];
        }
        if (!empty($data_info['quantity'])) {
            $data['Item']['Quantity'] = $data_info['quantity']; //刊登商品的数量// todo 必须项
        }
        //----------------------------------------------退货政策
        if (!empty($data_info['returns_accepted_option'])) {
            //是否要求有退款方式
            $data['Item']['ReturnPolicy']['ReturnsAcceptedOption'] = $data_info['returns_accepted_option'];
        }
        if (!empty($data_info['refund_option'])) {
            //退款方式
            $data['Item']['ReturnPolicy']['RefundOption'] = $data_info['refund_option'];
        }
        if (!empty($data_info['extended_holiday_returns'])) {
            //true表示卖方提供延长假期的物品的退货政策
            $data['Item']['ReturnPolicy']['ExtendedHolidayReturns'] = $data_info['extended_holiday_returns'];
        }
        if (!empty($data_info['returns_within_option'])) {
            //退货天数
            $data['Item']['ReturnPolicy']['ReturnsWithinOption'] = $data_info['returns_within_option'];
        }
        if (!empty($data_info['restocking_fee_value_option'])) {
            //表示卖方退回物品收取的补货费,折旧率
            $data['Item']['ReturnPolicy']['RestockingFeeValueOption'] = $data_info['restocking_fee_value_option'];
        }
        if (!empty($data_info['return_policy_description'])) {
            //退款说明
            $data['Item']['ReturnPolicy']['Description'] = $data_info['return_policy_description'];
        }
        if (!empty($data_info['shipping_cost_paid_by_option'])) {
            //退款运费由谁负担Seller
            $data['Item']['ReturnPolicy']['ShippingCostPaidByOption'] = $data_info['shipping_cost_paid_by_option'];
        }
        //--------------------------------------------------运输政策
        $data['Item']['Location'] = $data_info['location']; //物品所在地// todo 必须项
        //物品所在地邮编
        if (!empty($data_info['postal_code'])) {
            $data['Item']['PostalCode'] = $data_info['postal_code'];
        }
        //国际手续费
        if (!empty($data_info['international_packaging_handling_costs'])) {
            $data['Item']['ShippingDetails']['CalculatedShippingRate']['InternationalPackagingHandlingCosts'] = $data_info['international_packaging_handling_costs'];
        }
        //国内手续费，如果发货地为中国就不要勾选
        if (!empty($data_info['packaging_handling_costs'])) {
            $data['Item']['ShippingDetails']['CalculatedShippingRate']['PackagingHandlingCosts'] = $data_info['packaging_handling_costs'];
        }
        //包裹将从哪里出货的位置的邮政编码。
        if (!empty($data_info['originating_postal_code'])) {
            $data['Item']['ShippingDetails']['CalculatedShippingRate']['OriginatingPostalCode'] = $data_info['originating_postal_code'];
        }
        //------------------------运输类型
        if (!empty($data_info['shipping_type'])) {
            $data['Item']['ShippingDetails']['ShippingType'] = $data_info['shipping_type'];
        }
        if (!empty($data_info['shipping_terms_in_description'])) {
            //boole,指在物品描述中是否指定了关于运输成本和安排的细节。
            $data['Item']['ShippingTermsInDescription'] = $data_info['shipping_terms_in_description'];
        }
        //-----------------------------国内运输，可以指定4个国内运输服务
        for ($i = 0; $i < count($data_info['shipping_service_options']); $i++) {
            if (!empty($data_info['shipping_service_options'][$i]['FreeShipping'])) {
                //是否免运费，true为免运费
                if($data_info['shipping_service_options'][$i]['FreeShipping'] == 'on')
                {
                    $data['Item']['ShippingDetails']['_ShippingServiceOptions_'.$i]['FreeShipping'] = true;
                }else{
                    $data['Item']['ShippingDetails']['_ShippingServiceOptions_'.$i]['FreeShipping'] = false;
                }
            }
            if (!empty($data_info['shipping_service_options'][$i]['ShippingService'])) {
                //国内运输方式
                $data['Item']['ShippingDetails']['_ShippingServiceOptions_'.$i]['ShippingService'] = $data_info['shipping_service_options'][$i]['ShippingService']; //运输方式 todo 必须项
            }
            if (isset($data_info['shipping_service_options'][$i]['ShippingServiceCost'])) {
                //运费
                $data['Item']['ShippingDetails']['_ShippingServiceOptions_'.$i]['ShippingServiceCost'] = $data_info['shipping_service_options'][$i]['ShippingServiceCost'];
            }
            if (isset($data_info['shipping_service_options'][$i]['shipping_surcharge'])) {
                //附加费用
                $data['Item']['ShippingDetails']['_ShippingServiceOptions_'.$i]['ShippingSurcharge'] = $data_info['shipping_service_options'][$i]['shipping_surcharge'];
            }
            if (isset($data_info['shipping_service_options'][$i]['ShippingServiceAdditionalCost'])) {
                //额外费用
                $data['Item']['ShippingDetails']['_ShippingServiceOptions_'.$i]['ShippingServiceAdditionalCost'] = $data_info['shipping_service_options'][$i]['ShippingServiceAdditionalCost'];
            }
        }
        //-----------------------------国际运输，可以指定5个国际运输服务
        for ($i = 0; $i < count($data_info['international_shipping_service_options']); $i++) {
            if (!empty($data_info['international_shipping_service_options'][$i]['international_shipping_service_priority'])) {
                //表示该运输服务显示的顺序，最多可以列出五个运输服务选项，所以排序可以12345五种选择
                $data['Item']['ShippingDetails']['_InternationalShippingServiceOption_'.$i]['ShippingServicePriority'] = $data_info['international_shipping_service_options'][$i]['international_shipping_service_priority'];
            }
            $data['Item']['ShippingDetails']['_InternationalShippingServiceOption_'.$i]['ShippingServicePriority'] = $i;
            if (!empty($data_info['international_shipping_service_options'][$i]['ShippingService'])) {
                //国际运输方式
                $data['Item']['ShippingDetails']['_InternationalShippingServiceOption_'.$i]['ShippingService'] = $data_info['international_shipping_service_options'][$i]['ShippingService'];
            }
            for ($j = 0; $j < count($data_info['international_shipping_service_options'][$i]['ShipToLocation']); $j++) {
                if (!empty($data_info['international_shipping_service_options'][$i]['ShipToLocation'][$j])) {
                    //运送到以下国家或全球
                    $data['Item']['ShippingDetails']['_InternationalShippingServiceOption_'.$i]['_ShipToLocation_'.$j] = $data_info['international_shipping_service_options'][$i]['ShipToLocation'][$j];
                }
            }
            if (isset($data_info['international_shipping_service_options'][$i]['ShippingServiceCost'])) {
                //国际运送商品的费用
                $data['Item']['ShippingDetails']['_InternationalShippingServiceOption_'.$i]['ShippingServiceCost'] = $data_info['international_shipping_service_options'][$i]['ShippingServiceCost'];
            }
            if (isset($data_info['international_shipping_service_options'][$i]['ShippingServiceAdditionalCost'])) {
                //如果同一买方购买多个相同订单项的数量，则运送每个附加项目的费用。
                $data['Item']['ShippingDetails']['_InternationalShippingServiceOption_'.$i]['ShippingServiceAdditionalCost'] = $data_info['international_shipping_service_options'][$i]['ShippingServiceAdditionalCost'];
            }
        }
        //------------------------销售税
        if (!empty($data_info['sales_tax_percent'])) {
            //作为订单销售税的项目价格的百分比。传入的值以小数点后的3位数字存储
            $data['Item']['ShippingDetails']['SalesTax']['SalesTaxPercent'] = $data_info['sales_tax_percent'];
        }
        if (!empty($data_info['sales_tax_state'])) {
            //正在收取销售税的国家或管辖区。只有卖方指定了一个值才返回
            $data['Item']['ShippingDetails']['SalesTax']['SalesTaxState'] = $data_info['sales_tax_state'];
        }
        if (!empty($data_info['shipping_included_in_tax'])) {
            //（仅限美国）运输费用是否纳税的基础金额的一部分
            $data['Item']['ShippingDetails']['SalesTax']['ShippingIncludedInTax'] = $data_info['shipping_included_in_tax'];
        }
        //----------------------不运送的地区
        for ($i = 0; $i < count($data_info['ExcludeShipToLocation']); $i++) {
            if (!empty($data_info['ExcludeShipToLocation'][$i])) {
                //指定一个国际国家或地区或国内特殊位置，例如“PO Box”（美国）或“Packstation”（DE）），您不会在哪里发送关联的物品，在买家限制中ShipToRegistrationCountry，主要运送范围就为true了
                $data['Item']['ShippingDetails']['_ExcludeShipToLocation_' .$i] = $data_info['ExcludeShipToLocation'][$i];
            }
        }
        //-----------------------包装信息
        if (!empty($data_info['measurement_unit'])) {
            //测量单位
            $data['Item']['ShippingPackageDetails']['MeasurementUnit'] = $data_info['measurement_unit'];
        }
        if (!empty($data_info['package_depth'])) {
            //包装的深度，整数英寸
            $data['Item']['ShippingPackageDetails']['PackageDepth'] = $data_info['package_depth'];
        }
        if (!empty($data_info['package_length'])) {
            //包装的长度，整数英寸
            $data['Item']['ShippingPackageDetails']['PackageLength'] = $data_info['package_length'];
        }
        if (!empty($data_info['package_width'])) {
            $data['Item']['ShippingPackageDetails']['PackageWidth'] = $data_info['package_width'];
        } //包装的宽度，整数英寸
        if (!empty($data_info['shipping_irregular'])) {
            //包装是否不规则,true表示不规则
            $data['Item']['ShippingPackageDetails']['ShippingIrregular'] = $data_info['shipping_irregular'];
        }
        if (!empty($data_info['shipping_package'])) {
            //包裹类型
            $data['Item']['ShippingPackageDetails']['ShippingPackage'] = $data_info['shipping_package'];
        }
        if (!empty($data_info['weight_major'])) {
            //重量lbs
            $data['Item']['ShippingPackageDetails']['WeightMajor'] = $data_info['weight_major'];
        }
        if (!empty($data_info['weight_minor'])) {
            //重量oz
            $data['Item']['ShippingPackageDetails']['WeightMinor'] = $data_info['weight_minor'];
        }
        for ($i = 0; $i < count($data_info['listing_enhancements']); $i++) {
            if (!empty($data_info['listing_enhancements'][$i])) {
                //title加粗黑体
                $data['Item']['_ListingEnhancement_'.$i] = $data_info['listing_enhancements'][$i];
            }
        }
        return $data;
    }


    /**
     * 检测拍卖或固价商品刊登费用
     * @param $data_info
     * @return mixed
     */
    public function verifyAddItem($data_info)
    {
        set_time_limit(0);
        $token = $data_info['token']; // todo 必须项
        $site_info = $data_info['data_site'];
        $data = $this->getFinalEbayPublishData($data_info);
//        var_dump($data);
//        dd('s');
        $ret = $this->trading->verifyAddItem($token,$data,$data_info['data_site_id']);
        if($ret['Ack'] != 'Failure')
        {
            //说明可以刊登，计算费用
            $count = 0;
            $count_name = [];
            for ($i=0;$i<count($ret['Fees']['Fee']);$i++)
            {
                if($ret['Fees']['Fee'][$i]['Fee']>0){
                    $count += $ret['Fees']['Fee'][$i]['Fee'];
                    $count_name[$i]['name'] = $ret['Fees']['Fee'][$i]['Name'];
                    $count_name[$i]['fee'] = $ret['Fees']['Fee'][$i]['Fee'].' ('.$site_info['currency'].')';
                }
            }
            $ret['countname'] = array_merge($count_name);
            $ret['countfee'] = $count.' ('.$site_info['currency'].')';
            return $ret;
        }else{
            return $ret;
        }
    }

    /**
     * 刊登拍卖或固价商品
     * @param $data_info
     * @return mixed
     */
    public function addItem($data_info)
    {
        set_time_limit(0);
        error_reporting(4);
        //------------------------------------店铺信息
        $token = $data_info['token']; // todo 必须项
        $data = $this->getFinalEbayPublishData($data_info);
        $ret = $this->trading->addItem($token,$data,$data_info['data_site_id']);
        if($ret['Ack'] != 'Failure')
        {
            //说明可以刊登，计算费用
            $count = 0;
            for ($i=0;$i<count($ret['Fees']['Fee']);$i++)
            {
                $count += $ret['Fees']['Fee'][$i]['Fee'];
            }
            $ret['countfee'] = $count;
            return $ret;
        }else{
            return $ret;
        }
    }

    /**
     * 检测刊登多属性费用
     * @param $data_info
     * @return mixed
     */
    public function verifyAddFixedPriceItem($data_info)
    {
        set_time_limit(0);
        error_reporting(4);
        //------------------------------------店铺信息
        $token = $data_info['token']; // todo 必须项
        $site_info = $data_info['data_site'];
        $data = $this->getFinalEbayPublishData($data_info);
//        var_dump($data);
//        dd($data);
//        dd(json_encode($data));
        $ret = $this->trading->verifyAddFixedPriceItem($token,$data,$data_info['data_site_id']);
        if($ret['Ack'] != 'Failure') {
            //说明可以刊登，计算费用
            //说明可以刊登，计算费用
            $count = 0;
            $count_name = [];
            for ($i=0;$i<count($ret['Fees']['Fee']);$i++)
            {
                if($ret['Fees']['Fee'][$i]['Fee']>0){
                    $count += $ret['Fees']['Fee'][$i]['Fee'];
                    $count_name[$i]['name'] = $ret['Fees']['Fee'][$i]['Name'];
                    $count_name[$i]['fee'] = $ret['Fees']['Fee'][$i]['Fee'].' ('.$site_info['currency'].')';
                }
            }
            $ret['countname'] = array_merge($count_name);
            $ret['countfee'] = $count.' ('.$site_info['currency'].')';
            return $ret;
        }else{
            return $ret;
        }
    }

    /**
     * 刊登多屬性商品
     *
     * @param $data_info
     * @return mixed
     */
    public function addFixedPriceItem($data_info)
    {
        set_time_limit(0);
        error_reporting(4);
        //------------------------------------店铺信息
        $token = $data_info['token']; // todo 必须项
        $site_info = $data_info['data_site'];
        $data = $this->getFinalEbayPublishData($data_info);
//        var_dump($data);
//        dd($data);
//        dd(json_encode($data));
        $ret = $this->trading->addFixedPriceItem($token,$data,$data_info['data_site_id']);
        if($ret['Ack'] != 'Failure') {
            //说明可以刊登，计算费用
            //说明可以刊登，计算费用
            $count = 0;
            $count_name = [];
            for ($i=0;$i<count($ret['Fees']['Fee']);$i++)
            {
                if($ret['Fees']['Fee'][$i]['Fee']>0){
                    $count += $ret['Fees']['Fee'][$i]['Fee'];
                    $count_name[$i]['name'] = $ret['Fees']['Fee'][$i]['Name'];
                    $count_name[$i]['fee'] = $ret['Fees']['Fee'][$i]['Fee'].' ('.$site_info['currency'].')';
                }
            }
            $ret['countname'] = array_merge($count_name);
            $ret['countfee'] = $count.' ('.$site_info['currency'].')';
            return $ret;
        }else{
            return $ret;
        }
    }
    /*
     * 获取ebay店铺在线商品列表信息
     */
    public function getSellerListActive()
    {
        set_time_limit(0);
        $token = '';
        date_default_timezone_set('UTC');
        $begintime = date("Y-m-d H:i:s");
        $endtime = date("Y-m-d H:i:s", time() + 60 * 60 * 24 * 31);
        dd($begintime);
        $data = [
            'GranularityLevel' => 'Coarse',
            'EndTimeFrom' => $begintime,
            'EndTimeTo' => $endtime,
            'IncludeWatchCount' => 'true',
            'Pagination' => [
                'EntriesPerPage' => 10,
                'PageNumber' => 1,
            ],
        ];
        $ret = $this->trading->getSellerListActive($token, $data);
        dd($ret);
        $has_more = $ret['HasMoreItems'];
        $activeitem = $ret['data'];
        while ($has_more == 'true') {
            $data['Pagination']['PageNumber'] += 1;
            $ret = $this->trading->getSellerListActive($token, $data);
            $activeitem = array_merge($activeitem, $ret['data']);
            $has_more = $ret['HasMoreItems'];
            Log::error('sadsfs');
        }
        $ret['data'] = $activeitem;
        dd($ret);
    }
    /*
     * 获取ebay店铺下架90天内的商品列表信息
     */
    public function getSellerListUnsold()
    {
        set_time_limit(0);
        $token = '';
        date_default_timezone_set('GMT');
        $begintime = date("Y-m-d H:i:s", time() - 60 * 60 * 24 * 90);
        $endtime = date("Y-m-d H:i:s");
        $data = [
            'GranularityLevel' => 'Coarse',
            'EndTimeFrom' => $begintime,
            'EndTimeTo' => $endtime,
            'IncludeWatchCount' => 'true',
            'Pagination' => [
                'EntriesPerPage' => 40,
                'PageNumber' => 1,
            ],
        ];
        $ret = $this->trading->getSellerListUnsold($token, $data);
        dd($ret);
        $has_more = $ret['HasMoreItems'];
        $unsolditem = $ret['data'];
        while ($has_more == 'true') {
            $data['Pagination']['PageNumber'] += 1;
            $ret = $this->trading->getSellerList($token, $data);
            $unsolditem = array_merge($unsolditem, $ret['data']);
            $has_more = $ret['HasMoreItems'];
            Log::error('sadsfs');
        }
        $ret['data'] = $unsolditem;
        dd($ret);
    }

    /**
     * 查找在线商品列表
     */
    public function getMyeBayActiveList()
    {
        $token = '';
        $data = [
            'ActiveList' => [
                'Pagination' => [
                    'EntriesPerPage' => '10',
                    'PageNumber' => '1',
                ],
            ],
        ];
        $ret = $this->trading->getMyeBaySelling($token, $data);
        dd($ret);
    }

    /**
     * 查找未卖出的商品
     */
    public function getMyeBayUnsoldList()
    {
        $token = '';
        $data = [
            'UnsoldList' => [
                'Pagination' => [
                    'EntriesPerPage' => '5',
                    'PageNumber' => '1',
                ],
            ],
        ];
        $ret = $this->trading->getMyeBaySelling($token, $data);
        dd($ret);
    }
    /**
     * 获取ebay店铺在线商品列表信息
     * @param string $token
     */
    public function getAllSellerList1($token, $shop_id = 1,$info = [])
    {
        set_time_limit(0);
        //查询最近添加的时间
        global $ERP_COMPANY;
        $ERP_COMPANY['ALL_COMPANY'] = true;
        $companyShop = $this->platGoodsBiz->getCompanyShop($shop_id);
        $ERP_COMPANY['COMPANY_ID'] = $companyShop->company_id;
        $create_at = $this->platGoodsBiz->getLatestPlatGoods($shop_id);
        if(!empty($info['publish_time'])){
            $begintime = $info['publish_time'];
        }else{
            if (!empty($create_at['data']['publish_start_time'])) {
                $begintime = date('Y-m-d H:i:s', strtotime($create_at['data']['publish_start_time']) + 1);
            } else {
                $begintime = date("Y-m-d H:i:s", time() - 60 * 60 * 24 * 90);
            }
        }
        $endtime = date("Y-m-d H:i:s");
        if(!empty($info['page'])){
            $data = [
                'DetailLevel' => 'ReturnAll',
                'IncludeVariations' => 'true',
                'IncludeWatchCount' => 'true',
                'StartTimeFrom' => $begintime,
                'StartTimeTo' => $endtime,
                'Pagination' => [
                    'EntriesPerPage' => 10,
                    'PageNumber' => $info['page'],
                ]
            ];
            //获取ebay店铺商品ID
            $ret = $this->trading->getSellerListActive($token, $data);
            $result = [
                'datas' => $ret['data']
            ];
        }else{
            $data = [
                'DetailLevel' => 'ReturnAll',
                'IncludeVariations' => 'true',
                'IncludeWatchCount' => 'true',
                'StartTimeFrom' => $begintime,
                'StartTimeTo' => $endtime,
                'Pagination' => [
                    'EntriesPerPage' => 10,
                    'PageNumber' => 1,
                ]
            ];
            //获取ebay店铺商品ID
            $ret = $this->trading->getSellerListActive($token, $data);
            Cache::put('sellerListVal0',$ret['data'],3600);
            $this->resultCheck($ret, __METHOD__);
            $has_more = $ret['HasMoreItems'];
            $n = 1;
            while ($has_more == 'true') {
                $data['Pagination']['PageNumber'] += 1;
                Log::info('getSellerListActive time+1');
                $ret = $this->trading->getSellerListActive($token, $data);
                Cache::put('sellerListVal'.$n,$ret['data'],3600);
                $n++;
                $this->resultCheck($ret, __METHOD__);
                $has_more = $ret['HasMoreItems'];
            }
            $allitem = [];
            $n--;
            for($i=0;$i<=$n;$i++){
                $allitem = array_merge($allitem,Cache::get('sellerListVal'.$i));
            }
            $result = [
                'datas' => array_chunk($allitem, 10)
            ];
        }
        return $result;
    }

    /**
     * 获取ebay平台的商品分类信息
     * @param array $data
     * @param string $token
     * @param string $site
     */
    public function getCategories(Request $request)
    {
        if(env('EBAY_CHECK_SANDBOX')){
            $token = $this->default_token['sandbox'];
        }else{
            $token = $this->default_token['logic'];
        }
        $site = $request->site_id;
        set_time_limit(0);
        $data = [
            'DetailLevel' => 'ReturnAll',
            'LevelLimit' => '1',
            'ViewAllNodes' => 'true',
        ];
        $ret = $this->trading->getCategories($token, $data, $site);
        $model = SaasGoodsCategory::where('platform_id', $ret['data'][0]['platform_id'])->where('site', $site)->where('version', $ret['data'][0]['version'])->where('platform_category_id', $ret['data'][0]['platform_category_id'])->first();
        if (!empty($model)) {
            dd('版本已存在');
        } else {
            $data1 = [
                'DetailLevel' => 'ReturnAll',
                'ViewAllNodes' => 'true',
            ];
            $ret1 = $this->trading->getCategories($token, $data1, $site);
            $allnum = count($ret1['data']); //总条数
            $onenum = 2000; //一次添加的条数
            for ($a = 0; $a < $allnum; $a += $onenum) {
                $retall = array_slice($ret1['data'], $a, $onenum);
                $res = $this->saasGoodsCategoryBiz->insert($retall);
                if ($res['errcode'] == 0) {
                    continue;
                } else {
                    dd('添加失败');
                }
            }
        }

    }

    /**
     * 获取ebay平台的商品分类信息的特征
     * @param array $data
     * @param string $token
     * @param string $site
     */
    public function getCategoryFeatures($token)
    {
        $site = '0';
        $data = [
            'AllFeaturesForCategory' => 'true',
            'DetailLevel' => 'ReturnAll',
            'CategoryID' => '65',
        ];
        $ret = $this->trading->getCategoryFeatures($token, $data, $site);
        dd($ret);
    }
    /**
     * 获取ebay平台的商品分类信息的属性
     * @param array $data
     * @param string $token
     * @param string $site
     */
    public function getCategorySpecifics($token)
    {
        $site = '201';
        $data = [
            'CategorySpecific' => [
                'CategoryID' => '63869',
            ],
            'CategorySpecificsFileInfo' => true,
            'ExcludeRelationships' => true
        ];
        $ret = $this->trading->getCategorySpecifics($token, $data, $site);
        dd($ret);
    }

    /**
     * 获取ebay站点支持的运输服务
     * @param string $token
     */
    public function getShippingServiceDetails($token = '')
    {
        $data = [
            'DetailName' => 'ShippingServiceDetails',
        ];
        $ret = $this->trading->geteBayDetails($token, $data);
        dd($ret);
    }

    /**
     * 获取ebay站点广告特色的属性值是否可用
     * @param string $token
     */
    public function getListingFeatureDetails($token = '')
    {
        $data = [
            'DetailName' => 'ListingFeatureDetails',
        ];
        $ret = $this->trading->geteBayDetails($token, $data);
        dd($ret);
    }
    /**
     * 获取ebay站点产品的特征属性
     * @param string $token
     */
    public function getItemSpecificDetails($token = '')
    {
        $data = [
            'DetailName' => 'ItemSpecificDetails',
        ];
        $ret = $this->trading->geteBayDetails($token, $data);
        dd($ret);
    }
    /**
     * 获取ebay站点产品刊登开始价格特点
     * @param string $token
     */
    public function getListingStartPriceDetails($token = '')
    {
        $data = [
            'DetailName' => 'ListingStartPriceDetails',
        ];
        $ret = $this->trading->geteBayDetails($token, $data);
        dd($ret);
    }

    /**
     * 获取ebay站点ID及名称
     */
    public function getSiteDetails()
    {
        $site = '0';
        $data = [
            'DetailName' => 'SiteDetails',
        ];
        $token = '';
        $ret = $this->trading->geteBayDetails($token, $data, $site);
        dd($ret);
    }
    /*
     *获取eBay系统支持的每个国家的国家代码和关联名称
     */
    public function getCountryDetails($token)
    {
        $site = '0';
        $data = [
            'DetailName' => 'CountryDetails',
        ];
        $ret = $this->trading->geteBayDetails($token, $data, $site);
        dd($ret);
    }
    /*
     * 获取买方需求值
     */
    public function getBuyerRequirementDetails()
    {
        $site = '0';
        $data = [
            'DetailName' => 'BuyerRequirementDetails',
        ];
        $token = '';
        $ret = $this->trading->geteBayDetails($token, $data, $site);
        dd($ret);
    }

    /**
     * 获取ebay站点支持的不同销售税地区
     */
    public function getTaxJurisdiction()
    {
        $site = '0';
        $data = [
            'DetailName' => 'TaxJurisdiction',
        ];
        $token = '';
        $ret = $this->trading->geteBayDetails($token, $data, $site);
        dd($ret);
    }
    /**
     * 获取退货政策值
     */
    public function getReturnPolicyDetails()
    {
        $site = '0';
        $data = [
            'DetailName' => 'ReturnPolicyDetails',
        ];
        $token = '';
        $ret = $this->trading->geteBayDetails($token, $data, $site);
        dd($ret);
    }

    /**
     * 获取eBay运输服务支持的区域和位置
     */
    public function getShippingLocationDetails($token)
    {
        $site = '0';
        $data = [
            'DetailName' => 'ShippingLocationDetails',
        ];
        $ret = $this->trading->geteBayDetails($token, $data, $site);
        dd($ret);
    }

    /**
     * 获取指定站点支持的运输运营商
     */
    public function getShippingCarrierDetails()
    {
        $site = '0';
        $data = [
            'DetailName' => 'ShippingCarrierDetails',
        ];
        $token = '';
        $ret = $this->trading->geteBayDetails($token, $data, $site);
        dd($ret);
    }

    /**
     * 获取指定站点支持的各种运送包
     */
    public function getShippingPackageDetails()
    {
        $site = '0';
        $data = [
            'DetailName' => 'ShippingPackageDetails',
        ];
        $token = '';
        $ret = $this->trading->geteBayDetails($token, $data, $site);
        dd($ret);
    }

    /**
     * 获取卖方可以在列表上设置的处理时间值，处理发货的时间
     */
    public function getDispatchTimeMaxDetails()
    {
        $site = '0';
        $data = [
            'DetailName' => 'DispatchTimeMaxDetails',
        ];
        $token = '';
        $ret = $this->trading->geteBayDetails($token, $data, $site);
        dd($ret);
    }

    /**
     * 获取不运送地区的值
     */
    public function getExcludeShippingLocationDetails()
    {
        $site = '0';
        $data = [
            'DetailName' => 'ExcludeShippingLocationDetails',
        ];
        $token = '';
        $ret = $this->trading->geteBayDetails($token, $data, $site);
        dd($ret);
    }

    /**
     * 获取卖家交易订单
     *
     * @param string $token
     */
    public function getSellerTransactions($token = '', $duration = 30, $platform_id = 1, $shop_id = 1)
    {
//        dd(config('site.0'));
        set_time_limit(0);
        date_default_timezone_set('UTC');
        global $ERP_COMPANY;
        $ERP_COMPANY['ALL_COMPANY'] = true;

        $data = [
            'Pagination' => [
                'EntriesPerPage' => 100,
                'PageNumber' => 1,
            ],
            'DetailLevel' => 'ReturnAll',
        ];

        // 获取订单最近一次更新时间
        $p = new PlatOrderBiz();
        $last_time = $p->queryLastTime($shop_id, $platform_id)['data'];
        $now = explode(' ', microtime());
        if ($last_time) {
            $data['ModTimeTo'] = Date('Y-m-d H:i:s', $now[1]);
            $data['ModTimeFrom'] = Date('Y-m-d H:i:s', strtotime($last_time));
        } else {
            $data['NumberOfDays'] = $duration;
        }

        // 数据库操作
        $insert_data = function ($result) use ($p) {
            foreach ($result['data']['orders'] as $r) {
                $ret = $p->add($r);
                dump($ret);
            }
        };

        // 预请求订单数据
        $ret = $this->trading->getSellerTransactions($data, $token, $platform_id);
        $page_total = $ret['PaginationResult']['TotalNumberOfPages'];
        if ($page_total == 1) {
            // 订单数据存入数据库
            $insert_data($ret);
            return;
        }

        // 获取多页订单数据
        $transactions = function () use (&$page_total, &$data, &$token, $platform_id) {
            foreach (range(1, $page_total) as $value) {
                $data['Pagination']['PageNumber'] = $value;
                yield $this->trading->getSellerTransactions($data, $token, $platform_id);
            }
        };

        // 合并多页订单
        $orders = [];
        foreach ($transactions() as $ret) {
            $orders = array_merge($orders, $ret['data']['orders']);
        }
        $ret['data']['orders'] = $orders;

        // 订单数据存入数据库
        dd($ret);
    }

    /**
     *
     *
     * @return mixed
     */
    public function getOrders($token = '', $duration = 30, $platform_id = 1, $shop_id = 1)
    {
//        $data = [
        ////            'OrderIDArray' => [
        ////                'OrderID' => '142429798869-1419245090004'
        ////            ],
        //            'CreateTimeFrom' => '2017-07-01 09:00:00',
        //            'CreateTimeTo' => '2017-09-01 09:00:00',
        //            'OrderRole' => 'Seller',
        //            'DetailLevel' => 'ReturnAll'
        //        ];
        //        $token = '';
        //        $ret = $this->trading->getOrders($token,$data);
        //        dump($ret);
        //        return $ret;

        //        dd(config('site.0'));
        set_time_limit(0);
        date_default_timezone_set('UTC');
        global $ERP_COMPANY;
        $ERP_COMPANY['ALL_COMPANY'] = true;

        $data = [
            'Pagination' => [
                'EntriesPerPage' => 100,
                'PageNumber' => 1,
            ],
            'OrderRole' => 'Seller',
            'DetailLevel' => 'ReturnAll',
        ];

        // 获取订单最近一次更新时间
        $p = new PlatOrderBiz();
        $last_time = $p->queryLastTime($shop_id, $platform_id)['data'];
        $now = explode(' ', microtime());
        if ($last_time) {
            $data['CreateTimeFrom'] = Date('Y-m-d H:i:s', strtotime($last_time) + 1);
            $data['CreateTimeTo'] = Date('Y-m-d H:i:s', $now[1]);
        } else {
            $data['NumberOfDays'] = $duration;
        }

        // 数据库操作
        $insert_data = function ($result) use ($p) {
            foreach ($result['data']['orders'] as $r) {
                $ret = $p->add($r);
                dump($ret);
            }
        };

        // 预请求订单数据
        $ret = $this->trading->getOrders($token, $data);
        // $ret = $this->trading->getSellerTransactions($data, $token, $platform_id);
        $page_total = $ret['PaginationResult']['TotalNumberOfPages'];
        if ($page_total == 1) {
//            dd($ret);
            $insert_data($ret);
            // 订单数据存入数据库
            return;
        }
        dd();

        // 获取多页订单数据
        $transactions = function () use (&$page_total, &$data, &$token, $platform_id) {
            foreach (range(1, $page_total) as $value) {
                $data['Pagination']['PageNumber'] = $value;
                yield $this->trading->getSellerTransactions($data, $token, $platform_id);
            }
        };

        // 合并多页订单
        $orders = [];
        foreach ($transactions() as $ret) {
            $orders = array_merge($orders, $ret['data']['orders']);
        }
        $ret['data']['orders'] = $orders;

        // 订单数据存入数据库
        dd($ret);
    }

    /**
     * 完成交易 (发货)
     *
     * @param string $token
     * @param array $data
     */
    public function completeSale($token = '', $data = [])
    {
        $data = [
            'target_user' => 'testuser_guoqing',
            'item_id' => '110207552322',
            'shipment_tracking_number' => '',
            'shipping_carrier_used' => '',
            'transaction_id' => '28681259001',
            'comment_text' => '123',
            'comment_type' => 'Positive',
        ];
        $data_new = [
            'Paid' => 'true',
            'Shipped' => 'true',
        ];
        $data_new['FeedbackInfo']['TargetUser'] = $data['target_user'];
        $data_new['FeedbackInfo']['CommentText'] = $data['comment_text'];
        $data_new['FeedbackInfo']['CommentType'] = $data['comment_type'];
        $data_new['ItemID'] = $data['item_id'];
        $data_new['Paid'] = $data['paid'] ? $data['paid'] : 'true';
        $data_new['Shipped'] = $data['shipped'] ? $data['shipped'] : 'true';
        $data_new['TransactionID'] = $data['transaction_id'];
        if ($data['shipment_tracking_number'] and $data['shipping_carrier_used']) {
            $data_new['Shipment']['ShipmentTrackingDetails']['ShipmentTrackingNumber'] = $data['shipment_tracking_number'];
            $data_new['Shipment']['ShipmentTrackingDetails']['ShippingCarrierUsed'] = $data['shipping_carrier_used'];
        }
        $ret = $this->trading->completeSale($token, $data_new);
        dd($ret);
    }

    /**
     * 获取卖家邮箱信息
     *
     * @param string $token
     * @param int $folder_id
     */
    public function getMyMessages($token = '', $folder_id = 0, $shop_id = 141)
    {
        set_time_limit(0);

//        global $ERP_COMPANY;
        //        $ERP_COMPANY['ALL_COMPANY'] = true;

        $data = [
            'DetailLevel' => 'ReturnHeaders',
            'FolderID' => "{$folder_id}",
        ];
        $ret = $this->trading->getMyMessages($token, $data, $shop_id);

        // 解析 MessageID
        $messagesIds = [];
        foreach ($ret['data']['messages'] as $k => $v) {
            $messagesIds[] = $v['platform_message_id'];
        }

        // 剔除已获取的 MessageID
        $p = new PlatMessageBiz();
        $messagesIds = $p->getPlatMessageIds($messagesIds)['data'];

        // 消息数据生成器
        $messagesData = function ($ret) use ($folder_id, $shop_id) {

            // 合并 MessageID
            $data = [
                'DetailLevel' => 'ReturnMessages',
                'FolderID' => "{$folder_id}",
                'MessageIDs' => [],
            ];
            foreach ($ret as $key => $value) {
                $k = '_MessageID_' . ($key + 1);
                $data['MessageIDs'][$k] = $value;
            }

            // 获取 Message 所有数据
            return $this->trading->getMyMessages('', $data, $shop_id);
        };

        // 循环请求与拼接数据
        $data = [];
        foreach (array_chunk($messagesIds, 10) as $k => $ids) {
            $messages = $messagesData($ids);
            $messagesDatas = $messages['data']['messages'];
            //dump('$messages', $messages);
            //dump('$messagesDatas', $messagesDatas);
            foreach ($messagesDatas as $message) {
                $data[] = $message;
            }
        }
        //dump('m', $data);

        return $data; // todo-1 test
        // // 信息数据存入数据库
        // $error = 0; // 异常计数
        // foreach ($data as $d) {
        //     $ret = $p->createMessageForApi($d);
        //     //dump($ret);
        //     $error += $ret['errcode'];
        // }

        // if ($error > 0) {
        //     return ['errcode' => 123, 'errmsg' => "数据库写入失败 失败次数: {$error}", 'data' => $error];
        // } else {
        //     return ['errcode' => 0, 'data' => ''];
        // }
    }

    /**
     * 获取买家信息
     */
    public function getMemberMessages($token = '', $data = [])
    {
        date_default_timezone_get('GMT');
        $start_time = date('Y-m-d H:i:s');
        $data = [
            'MailMessageType' => 'AskSellerQuestion',
            'MessageStatus' => 'Answered',
            'StartCreationTime' => '2017-07-01',
            'EndCreationTime' => $start_time,
            'Pagination' => [
                'EntriesPerPage' => '5',
                'PageNumber' => '1',
            ],
        ];
        $ret = $this->trading->getMemberMessages($token, $data);
        dd($ret);
    }

    /**
     * 获取 token 状态
     *
     * @param string $token
     */
    public function getTokenStatus($token = '')
    {
        $ret = $this->trading->getTokenStatus($token);
        dd($ret);
    }

    public function addMemberMessageRTQ($token = '')
    {
        $data = [
//            'ItemID' => '110207552322',
            'MemberMessage' => [
                'Body' => 'thank you buy my book.',
//                'DisplayToPublic' => 'true',
                //                'EmailCopyToSender' => 'true',
                'ParentMessageID' => '90177955140',
                'RecipientID' => 'logic_0_logic',
            ],
        ];
        $ret = $this->trading->addMemberMessageRTQ($token, $data);
        dd($ret);
    }

    /**
     * @param string $token
     * @param array $data
     */
    public function addMemberMessageAAQToPartner($token = '', $data = [])
    {
//        $data = [
        //            'ItemID' => '142455161226',
        //            'MemberMessage' => [
        //                'Subject' => 'Thank You for your purchase123',
        //                'Body' => '123 test 123',
        //                'QuestionType' => 'General',
        //                'RecipientID' => 'logic_0_logic'
        //            ]
        //        ];
        //        $data = array_merge($default, $data);
        $data['MemberMessage']['QuestionType'] = 'General';
//        dump($data);
        // dump($default, $data);
        $ret = $this->trading->addMemberMessageAAQToPartner($token, $data);
        return $ret;
    }

    public function addMemberMessagesAAQToBidder()
    {
        $data = [
            'AddMemberMessagesAAQToBidderRequestContainer' => [
                'ItemID' => '142455161226',
                'MemberMessage' => [
                    'Body' => 'Greetings! Thank you for your lcf',
                    'RecipientID' => 'logic_0_logic',
                ],
            ],
        ];
        $token = '';
        $ret = $this->trading->addMemberMessagesAAQToBidder($token, $data);
        dd($ret);
    }

    /**
     * 修改ebay店铺在线商品属性
     */
    public function reviseItem($goodsid,$data_info)
    {
        set_time_limit(0);
        error_reporting(4);
        //------------------------------------店铺信息
        $token = $data_info['token']; // todo 必须项
        $data = $this->getFinalEbayPublishData($data_info);
        $data['Item']['ItemID'] = $goodsid;
        $ret = $this->trading->reviseItem($token,$data,$data_info['data_site_id']);
        return $ret;
    }
    /**
     * 修改ebay店铺在线固定价格商品属性
     */
    public function reviseFixedPriceItem($goodsid,$data_info)
    {
        set_time_limit(0);
        error_reporting(4);
        //------------------------------------店铺信息
        $token = $data_info['token']; // todo 必须项
        $data = $this->getFinalEbayPublishData($data_info);
        $data['Item']['ItemID'] = $goodsid;
        //dd(json_encode($data));
        $ret = $this->trading->reviseFixedPriceItem($token,$data,$data_info['data_site_id']);
        return $ret;
    }
    /**
     * 下架单个ebay商店的在线商品
     */
    public function endItem($token,$site, $item_id)
    {
        $data = [
            'ItemID' => $item_id,
            'EndingReason' => 'NotAvailable',
        ];
        $ret = $this->trading->endItem($token,$site, $data);
        return $ret;
    }
    /**
     * 批量下架ebay商店的在线商品
     */
    public function endItems($token,$site, $item_id)
    {
        $data = [];
        foreach ($item_id as $key => $val){
            $data['_EndItemRequestContainer_'.$key] = [
                'MessageID' => '1',
                'EndingReason' => 'LostOrBroken',
                'ItemID' => $val,
            ];
        }
        $ret = $this->trading->endItems($token,$site, $data);
        dd($ret);
    }
    /**
     * 下架单个ebay商店的固定价格的在线商品
     */
    public function endFixedPriceItem($token,$site, $item_id)
    {
        $data = [
            'ItemID' => $item_id,
            'EndingReason' => 'NotAvailable',
        ];
        $ret = $this->trading->endFixedPriceItem($token,$site, $data);
        return $ret;
    }
    /**
     * 重新上架ebay商店下架的商品，要求下架90天之内的才能重新上架
     */
    public function relistItem($token,$site_id, $item_id)
    {
        $data = [
            'Item' => [
                'ItemID' => $item_id
            ]
        ];
        $ret = $this->trading->relistItem($token, $site_id,$data);
        return $ret;
    }
    /**
     * 重新上架ebay商店下架的固定价格商品，要求下架90天之内的才能重新上架
     */
    public function relistFixedPriceItem($token,$site_id, $item_id)
    {
        $data = [
            'Item' => [
                'ItemID' => $item_id
            ]
        ];
        $ret = $this->trading->relistFixedPriceItem($token,$site_id, $data);
        return $ret;
    }

    /**
     * 获取汇率值
     */
    public function getExchangeRate()
    {
        $ret = $this->trading->getExchangeRate();
        $response = json_decode($ret, true);
        if ($response['error_code'] == 0) {
            $list_data = $response['result']['list'];
            $resdata = [];
            foreach ($list_data as $key => $val) {
                $resdata[$key]['currency_name'] = $val[0];
                switch ($val[0]) {
                    case '美元':
                        $resdata[$key]['currency_symbol'] = 'USD';
                        break;
                    case '日元':
                        $resdata[$key]['currency_symbol'] = 'JPY';
                        break;
                    case '欧元':
                        $resdata[$key]['currency_symbol'] = 'EUR';
                        break;
                    case '英镑':
                        $resdata[$key]['currency_symbol'] = 'GBP';
                        break;
                    case '澳大利亚元':
                        $resdata[$key]['currency_symbol'] = 'AUD';
                        break;
                    case '加拿大元':
                        $resdata[$key]['currency_symbol'] = 'CAD';
                        break;
                    case '瑞士法郎':
                        $resdata[$key]['currency_symbol'] = 'CHF';
                        break;
                    case '港币':
                        $resdata[$key]['currency_symbol'] = 'HKD';
                        break;
                    case '新西兰元':
                        $resdata[$key]['currency_symbol'] = 'NZD';
                        break;
                    case '新加坡元':
                        $resdata[$key]['currency_symbol'] = 'SGD';
                        break;
                    default:
                        $resdata[$key]['currency_symbol'] = null;

                }
                $resdata[$key]['rate'] = $val[5] / $val[1];
            }
            for ($i = 0; $i < count($resdata); $i++) {
                $res = $this->saasExchangeRateBiz->updateRateForApi($resdata[$i]);
                if ($res['errcode'] == 0) {
                    continue;
                } else {
                    dd('更新失败');
                }
            }
            $arr_other = ['INR','MYR','PHP','PLN'];
            //获取其他汇率的数据
            for($j=0;$j<count($arr_other);$j++)
            {
                $ret_other = $this->trading->getOtherExchangeRate($arr_other[$j]);
                $ret_other_info = json_decode($ret_other,true);
                if($ret_other_info['error_code'] == 0)
                {
                    $dbdata['currency_symbol'] = $ret_other_info['result'][0]['currencyT'];
                    $dbdata['currency_name'] = $ret_other_info['result'][0]['currencyT_Name'];
                    $dbdata['rate'] = $ret_other_info['result'][0]['exchange'];
                    $res_db = $this->saasExchangeRateBiz->updateRateForApi($dbdata);
                    if ($res_db['errcode'] == 0) {
                        continue;
                    } else {
                        dd('更新失败');
                    }
                }else{
                    dd('获取其他汇率失败');
                }
            }
        } else {
            $list_data = '获取汇率失败!';
        }
        dd($list_data);
    }

}
