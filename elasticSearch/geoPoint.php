<?php


use App\Constants\StatusCode;
use App\Services\Dao\GoodsDao;
use App\Services\Dao\PartnerDao;
use App\Services\ElasticsearchService;
use App\Utils\Functions;
use App\Utils\GeoHash;
use PhpBoot\Utils\Logger;

/**
 * 相关文章 铭毅天下
 * http://www.voidcn.com/article/p-queccqxx-bop.html
 * http://www.voidcn.com/article/p-fqrmvhkh-ov.html
 * http://www.voidcn.com/article/p-pfvnkceu-bny.html
 * Class geoPoint
 */
class geoPoint
{

    /**
     * 使用elasticsearch方式通过位置获取数据
     * @param $store_dist_key
     * @param $conditions
     * @param int $page
     * @param int $page_size
     * @return array
     */
    private function searchPartnerByGeoEla($lat,$lng,$city_key,$conditions,$page=1,$page_size=20){
        $params = $GLOBALS['app']->get('elastic_search_index');
        $page = max($page - 1,0);

        $lonelyIds = $this->getLonelyIds();


        $start = $page * $page_size;
        $PARTNER_STATUS_OK = PartnerDao::PARTNER_STATUS_OK;
        $BUSINESS_TYPE_NORMAL = PartnerDao::BUSINESS_TYPE_NORMAL;

        if(isset($conditions['keyword'])){
            $query = "{
                \"query\":{
                    \"function_score\":{
                        \"boost_mode\":\"replace\",
                        \"functions\":[
                            {
                                \"filter\":{
                                    \"term\":{
                                        \"partner_type\":3
                                    }
                                },
                                \"weight\":1.1
                            },
                            {
                                \"filter\":{
                                    \"terms\":{
                                        \"partner_type\":[
                                            1,
                                            2
                                        ]
                                    }
                                },
                                \"weight\":1
                            }
                        ],
                        \"query\":{
                            \"bool\":{
                                \"must\":[
                                    {
                                        \"bool\":{
                                            \"should\":[
                                                {
                                                    \"match\":{
                                                        \"city\":\"{$city_key}\"
                                                    }
                                                }
                                            ]
                                        }
                                    },
                                    {
                                        \"bool\":{
                                            \"should\":[
                                                {
                                                    \"match_phrase\":{
                                                        \"partner_name\":{
                                                            \"query\":\"{$conditions['keyword']}\",
                                                            \"slop\":1
                                                        }
                                                    }
                                                },
                                                {
                                                    \"has_child\":{
                                                        \"type\":\"goods\",
                                                        \"query\":{
                                                            \"bool\":{
                                                                \"filter\":[
                                                                    {
                                                                        \"match_phrase\":{
                                                                            \"g_name\":{
                                                                                \"query\":\"{$conditions['keyword']}\",
                                                                                \"slop\":1
                                                                            }
                                                                        }
                                                                    },
                                                                    {
                                                                        \"term\":{
                                                                            \"g_status\":\"5\"
                                                                        }
                                                                    }
                                                                ]
                                                            }
                                                        }
                                                    }
                                                }
                                            ]
                                        }
                                    },
                                    {
                                        \"match\":{
                                            \"partner_status\":\"{$PARTNER_STATUS_OK}\"
                                        }
                                    },
                                    {
                                        \"match\":{
                                            \"is_normal\":\"{$BUSINESS_TYPE_NORMAL}\"
                                        }
                                    }
                                ],
                                \"must_not\":{
                                    \"terms\":{
                                        \"_id\":{$lonelyIds}
                                    }
                                }
                            }
                        }
                    }
                },
                \"_source\":[\"partner_name\",\"type_label\",\"send_out_money\",\"partner_cates\",\"partner_type\",\"partner_header_img\",\"distribut_info\",\"trade_name\"],
                \"sort\":[
                    {
                        \"_score\":\"desc\"
                    },
                    {
                        \"_geo_distance\":{
                            \"location\":{
                                \"lat\":{$lat},
                                \"lon\":{$lng}
                            },
                            \"order\":\"asc\",
                            \"unit\":\"m\"
                        }
                    }
                ],
                \"from\": {$start},
                \"size\": {$page_size}
            }";
        }else{
            $cat_params_str = '';
            if($conditions['cat_id'] != 'all'){
                $cat_params_str = ",{\"term\":{\"partner_cates\":{$conditions['cat_id']}}}";
            }

            $query = "{
                \"query\":{
                    \"function_score\":{
                        \"boost_mode\":\"replace\",
                        \"functions\":[
                            {
                                \"filter\":{
                                    \"term\":{
                                        \"partner_type\":3
                                    }
                                },
                                \"weight\":1
                            },
                            {
                                \"filter\":{
                                    \"terms\":{
                                        \"partner_type\":[
                                            1,
                                            2
                                        ]
                                    }
                                },
                                \"weight\":0
                            }
                        ],
                        \"query\":{
                            \"bool\":{
                                \"must\":[
                                    {
                                        \"bool\":{
                                            \"should\":[
                                                {
                                                    \"match\":{
                                                        \"city\":\"{$city_key}\"
                                                    }
                                                }
                                            ]
                                        }
                                    },
                                    {
                                        \"match\":{
                                            \"partner_status\":\"{$PARTNER_STATUS_OK}}\"
                                        }
                                    },
                                    {
                                        \"match\":{
                                            \"is_normal\":\"{$BUSINESS_TYPE_NORMAL}\"
                                        }
                                    }{$cat_params_str}
                                ],
                                \"must_not\":{
                                    \"terms\":{
                                        \"_id\":{$lonelyIds}
                                    }
                                }
                            }
                        }
                    }
                },
                \"_source\":[\"partner_name\",\"type_label\",\"send_out_money\",\"partner_cates\",\"partner_type\",\"partner_header_img\",\"distribut_info\",\"trade_name\"],
                \"sort\":[
                    {
                        \"_score\":\"desc\"
                    },
                    {
                        \"_geo_distance\":{
                            \"location\":{
                                \"lat\":{$lat},
                                \"lon\":{$lng}
                            },
                            \"order\":\"asc\",
                            \"unit\":\"m\"
                        }
                    }
                ],
                \"from\": {$start},
                \"size\": {$page_size}
            }";
        }

        $params['body'] = json_decode($query,true);

        $result = ElasticsearchService::search($params);

        if(isset($result['hits'])){
            $partner_geo_map = count($result['hits']['hits'])?$result['hits']['hits']:[];
        }

        if($partner_geo_map){
            foreach ($partner_geo_map as $key=>$value){
                $item = $value['_source'];
                $item['dist'] = Functions::dist_format($value['sort'][1]);
                $item['partner_id'] = $value['_id'];
                $item['partner_cates'] = self::getGoodsCateService()->getCateNameByIds($item['partner_cates']);
                unset($item['partner_goods']);
                unset($item['location']);
                $geo_data[] = $item;
            }
        }else{
            $geo_data = [];
        }
        return $geo_data;
    }

    /**
     * 使用elasticsearch方式通过位置获取数据
     * @param $lat
     * @param $lng
     * @param $city_key
     * @param int $page
     * @param int $page_size
     * @return array
     */
    private function getPartnerByGeoEla($lat,$lng,$city_key,$page=1,$page_size=20){
        $params = $GLOBALS['app']->get('elastic_search_index');
        $page = max($page - 1,0);

        $lonelyIds = $this->getLonelyIds();


        $start = $page * $page_size;
        $PARTNER_STATUS_OK = PartnerDao::PARTNER_STATUS_OK;
        $BUSINESS_TYPE_NORMAL = PartnerDao::BUSINESS_TYPE_NORMAL;

        $query = "{
                \"query\":{
                    \"function_score\":{
                        \"boost_mode\": \"replace\",
                        \"functions\":[
                            {
                               \"filter\":{ 
                                   \"term\":{\"partner_type\":3}
                               },
                               \"weight\":1
                            },
                            {
                               \"filter\":{ 
                                   \"terms\":{\"partner_type\":[1,2]}
                               },
                               \"weight\":0
                            }
                        ],
                        \"query\":{
                            \"bool\":{
                                \"must\":[
                                    {
                                        \"bool\":{
                                            \"should\":[
                                                {
                                                    \"match\":{
                                                        \"city\":\"{$city_key}\"
                                                    }
                                                }
                                            ]
                                        }
                                    },
                                    {
                                        \"match\":{
                                            \"partner_status\":\"{$PARTNER_STATUS_OK}\"
                                        }
                                    },
                                    {
                                        \"match\":{
                                            \"is_normal\":\"{$BUSINESS_TYPE_NORMAL}\"
                                        }
                                    }
                                ],
                                \"must_not\":{
                                    \"terms\":{
                                        \"_id\":{$lonelyIds}
                                    }
                                }
                            }
                        }
                    }
                },
                \"_source\":[\"partner_name\",\"type_label\",\"send_out_money\",\"partner_cates\",\"partner_type\",\"partner_header_img\",\"distribut_info\",\"trade_name\",\"is_activity\",\"activity_start_time\",\"activity_end_time\",\"activity_img_url\"],
                \"sort\":[
                    {
                        \"_score\":\"desc\"
                    },
                    {
                        \"_geo_distance\":{
                            \"location\":{
                                \"lat\":{$lat},
                                \"lon\":{$lng}
                            },
                            \"order\":\"asc\",
                            \"unit\":\"m\"
                        }
                    }
                ],
                \"from\": {$start},
                \"size\": {$page_size}
            }";


        $params['body'] = json_decode($query,true);

        $result = ElasticsearchService::search($params);

        if(isset($result['hits'])){
            $partner_geo_map = count($result['hits']['hits'])?$result['hits']['hits']:[];
        }

        if($partner_geo_map){
            foreach ($partner_geo_map as $key=>$value){
                $item = $value['_source'];
                $item['dist'] = Functions::dist_format($value['sort'][1]);
                $item['partner_id'] = $value['_id'];
                $item['partner_cates'] = self::getGoodsCateService()->getCateNameByIds($item['partner_cates']);
                unset($item['partner_goods']);
                unset($item['location']);
                $geo_data[] = $item;
            }
        }else{
            $geo_data = [];
        }

        return $geo_data;
    }

    /**
     * 使用elasticsearch方式通过位置获取首页商户数据
     * @param $lat
     * @param $lng
     * @param $city_key
     * @param int $page
     * @param int $page_size
     * @return array
     */
    private function getHomePartnerByGeoEla($lat,$lng,$city_key,$page=1,$page_size=20){
        $params = $GLOBALS['app']->get('elastic_search_index');
        $page = max($page - 1,0);

        $lonelyIds = $this->getLonelyIds();


        $start = $page * $page_size;
        $PARTNER_STATUS_OK = PartnerDao::PARTNER_STATUS_OK;
        $BUSINESS_TYPE_NORMAL = PartnerDao::BUSINESS_TYPE_NORMAL;
        $current_time = time();

        $query = "{
                \"query\":{
                    \"function_score\":{
                        \"boost_mode\": \"replace\",
                        \"score_mode\":\"sum\",
                        \"functions\":[
                            {
                              \"script_score\" : {
                                \"script\" : {
                                  \"params\": {
                                    \"goods_rate\": 1.1,
                                    \"order_rate\": 1.5,
                                    \"invite_rate\": 1.4
                                  },
                                  \"source\":\"doc[\u0027goods_num\u0027].value > 0 ? Math.log10(1 + params.order_rate * doc[\u0027order_num\u0027].value) + Math.log10(1 + params.goods_rate * doc[\u0027goods_num\u0027].value)  + Math.log10(1 + params.invite_rate * doc[\u0027invite_num\u0027].value): 0.001\"
                                }
                              }
                            },
                            {
                              \"script_score\" : {
                                \"script\" : {
                                  \"source\":\"doc[\u0027partner_type\u0027].value == 3 ? 0.15: 0.1\"
                                }
                              }
                            },
                            {
                              \"script_score\" : {
                                \"script\" : {
                                  \"lang\"   : \"painless\",
                                  \"params\" : {
                                    \"current_time\" : {$current_time}
                                  },
                                  \"source\":\"(doc[\u0027is_activity\u0027].value == 1 && (doc[\u0027activity_start_time\u0027].value < params.current_time && doc[\u0027activity_end_time\u0027].value > params.current_time)) ? 1: 0\"
                                }
                              }
                            }
                        ],
                        \"query\":{
                            \"bool\":{
                                \"must\":[
                                    {
                                        \"bool\":{
                                            \"should\":[
                                                {
                                                    \"match\":{
                                                        \"city\":\"{$city_key}\"
                                                    }
                                                }
                                            ]
                                        }
                                    },
                                    {
                                        \"match\":{
                                            \"partner_status\":\"{$PARTNER_STATUS_OK}\"
                                        }
                                    },
                                    {
                                        \"match\":{
                                            \"is_normal\":\"{$BUSINESS_TYPE_NORMAL}\"
                                        }
                                    }
                                ],
                                \"must_not\":{
                                    \"terms\":{
                                        \"_id\":{$lonelyIds}
                                    }
                                }
                            }
                        }
                    }
                },
                \"_source\":[\"partner_name\",\"type_label\",\"send_out_money\",\"partner_cates\",\"partner_type\",\"partner_header_img\",\"distribut_info\",\"trade_name\",\"is_activity\",\"activity_start_time\",\"activity_end_time\",\"activity_img_url\"],
                \"sort\":[
                    {
                        \"_score\":\"desc\"
                    },
                    {
                        \"_geo_distance\":{
                            \"location\":{
                                \"lat\":{$lat},
                                \"lon\":{$lng}
                            },
                            \"order\":\"asc\",
                            \"unit\":\"m\"
                        }
                    }
                ],
                \"from\": {$start},
                \"size\": {$page_size}
            }";

        $params['body'] = json_decode($query,true);

        $result = ElasticsearchService::search($params);

        if(isset($result['hits'])){
            $partner_geo_map = count($result['hits']['hits'])?$result['hits']['hits']:[];
        }

        if($partner_geo_map){
            foreach ($partner_geo_map as $key=>$value){
                $item = $value['_source'];
                $item['dist'] = Functions::dist_format($value['sort'][1]);
                $item['partner_id'] = $value['_id'];
                $item['partner_cates'] = self::getGoodsCateService()->getCateNameByIds($item['partner_cates']);
                unset($item['partner_goods']);
                unset($item['location']);
                $geo_data[] = $item;
            }
        }else{
            $geo_data = [];
        }

        return $geo_data;
    }

    /**
     * 使用elasticsearch方式通过位置获取商品数据
     * @param $lat
     * @param $lng
     * @param $city_key
     * @param int $page
     * @param int $page_size
     * @return array
     */
    private function getGoodsByGeoEla($lat,$lng,$city_key,$cat_id=0,$page=1,$page_size=20){
        $params = $GLOBALS['app']->get('elastic_search_index');
        $page = max($page - 1,0);

        $lonelyIds = $this->getLonelyIds(true);
        $current_time = time();
        $start = $page * $page_size;
        $query = [];

        $query_matchs = [];
        $query_matchs[] = [
            "match"=>[
                "g_status"=>GoodsDao::GOODS_STATUS_PUBLICED
            ]
        ];

        if($cat_id){
            $query_matchs[] = [
                "match"=>[
                    "gc_top_id" => $cat_id
                ]
            ];
        }

        $query['query'] = [
            'bool' => [
//                'must'=>[
//                    [
//                        'bool'=>[
                'must_not'=>[
                    'terms' => [
                        'partner_id'=>$lonelyIds
                    ]
                ],
                'must'=>[
                    [
                        'has_parent'=>[
                            "parent_type" =>"partner",
                            "score"=>true,
                            "query"=>[
                                [
                                    'function_score'=>[
                                        "boost_mode"=> "replace",
                                        "score_mode"=>"sum",
                                        'query' =>[
                                            'bool' => [
                                                'must' => [
                                                    //["match"=>["partner_type"=>PartnerDao::PARTNER_TYPE_SELF]], 可获取所有类型店铺商品
                                                    ["match"=>["partner_status"=>PartnerDao::PARTNER_STATUS_OK]],
                                                    ["match"=>["city"=>$city_key]],
                                                    ["match"=>["is_normal"=>PartnerDao::BUSINESS_TYPE_NORMAL]],
                                                ]
                                            ],
                                        ],
                                        'functions'=>[
                                            [

                                                "script_score"=>[
                                                    'script' => [
                                                        'params'=>[
                                                            'goods_rate' => 1.1,
                                                            'order_rate' => 1.5,
                                                            'invite_rate' => 1.4
                                                        ],
                                                        'source' => "doc['goods_num'].value > 0 ? Math.log10(1 + params.order_rate * doc['order_num'].value) + Math.log10(1 + params.goods_rate * doc['goods_num'].value) + Math.log10(1 + params.invite_rate * doc['invite_num'].value): 0.001",
                                                    ]
                                                ]
                                            ],
                                            [

                                                "script_score"=>[
                                                    'script' => [
                                                        'source' => "doc['partner_type'].value == 3 ? 0.15: 0.1"
                                                    ]
                                                ]
                                            ],
                                            [

                                                "script_score"=>[
                                                    'script' => [
                                                        "params" => [
                                                            "current_time" => $current_time
                                                        ],
                                                        'source' => "(doc['is_activity'].value == 1 && (doc['activity_start_time'].value < params.current_time && doc['activity_end_time'].value > params.current_time)) ? 1: 0"
                                                    ]
                                                ]
                                            ],
                                            [

                                                "gauss"=>[
                                                    'location' => [
                                                        'origin'=>[
                                                            'lat' => $lat,
                                                            'lon' => $lng
                                                        ],
                                                        'offset' => '15m',
                                                        'scale' => '300km'
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ]]
                    ],
                    $query_matchs
                ]
//                        ]
//                    ]
//                ]
            ]
        ];

        $query['sort'] = [
            [
                '_score'=>'desc'
            ]
        ];

        $query['from'] = $start;
        $query['size'] = $page_size;


        $params['body'] = $query;

        $result = ElasticsearchService::search($params);

        if(isset($result['hits'])){
            $partner_geo_map = count($result['hits']['hits'])?$result['hits']['hits']:[];
        }

        if($partner_geo_map){
            foreach ($partner_geo_map as $key=>$value){
                $item = $value['_source'];
//                foreach ($item['specs'] as &$row){
//                    $row['gn_stock'] = self::getGoodsService()->getStock($row['gn_id']);
//                }

                $geo_data[] = $item;
            }
        }else{
            $geo_data = [];
        }

        return $geo_data;
    }

    private function getPartnerByGeo($lat,$lng,$city_key,$page=1,$page_size=20){
        $store_dist_key = $this->getStoreKey($lat,$lng,$city_key);

        $page = max($page - 1,0);

        $start = $page * $page_size;
        $end = $start + $page_size - 1;

        $partner_geo_map = self::$redis->zrange($store_dist_key,$start,$end,true);

        if($partner_geo_map){
            $lonelyPartners = self::getPartnerService()->getLonelyPartners();
            $lonelyPartnersIds = is_array($lonelyPartners) ?  array_values($lonelyPartners):  [];
            $ids = array_keys($partner_geo_map);

            $ids = array_diff($ids,$lonelyPartnersIds);

            $list = self::getPartnerService()->getListByIds($ids);
            foreach ($partner_geo_map as $key=>$value){
                if(isset($list[$key])){
                    $item = $list[$key];
                    $item['dist'] = Functions::dist_format($value);
                    $item['partner_cates'] = self::getGoodsCateService()->getPartnerCate($key);
                    $geo_data[] = $item;
                }
            }
        }else{
            $geo_data = [];
        }


        return $geo_data;
    }

    /**
     * 根据位置获取城市id
     * @param $lat
     * @param $lng
     * @return bool
     */
    private function getCityKey($lat,$lng){
        $params['lat'] = $lat;
        $params['lng'] = $lng;
        $ret = self::getWS('channel','geo_to_address',$params);
        Logger::debug('api:channel_geoToAddress',array('send' => $params, 'result' => $ret));
        if($ret['status'] == StatusCode::SUCCESS){
            $city_key = $ret['result']['result_info']['addressComponent']['adcode'];
            $city_info = self::getCityService()->getCityByKey($city_key);
            if($city_info){
                $city_path = explode('/',$city_info['city_path']);
                $city_key = $city_path[2];
            }else{
                $city_key = null;
            }

            return $city_key;
        }else{
            return null;
        }
    }

    /**
     * 获取用户geohash,只取8位误差19米
     * @param $lat
     * @param $lng
     * @return bool|string
     */
    private function getGeoHash($lat,$lng){
        $geo_hash = GeoHash::getInstance()->encode($lat,$lng);
        return substr($geo_hash,0,8);
    }
    /**
     * 根据两点间的经纬度计算距离
     * @param $lng1
     * @param $lat1
     * @param $lng2
     * @param $lat2
     * @return int 米
     */
    public static function getDistance($lng1, $lat1, $lng2, $lat2)
    {
        //将角度转为狐度
        $radLat1 = deg2rad($lat1);//deg2rad()函数将角度转换为弧度
        $radLat2 = deg2rad($lat2);
        $radLng1 = deg2rad($lng1);
        $radLng2 = deg2rad($lng2);
        $a = $radLat1 - $radLat2;
        $b = $radLng1 - $radLng2;
        $s = 2 * asin(sqrt(pow(sin($a / 2), 2) + cos($radLat1) * cos($radLat2) * pow(sin($b / 2), 2))) * 6378.137 * 1000;
        return $s;
    }
}