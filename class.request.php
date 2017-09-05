<?php

class Request {
    private $url;
    private $request_fields;
    private $is_post_request;
    private $response_status_code;
    private $response_object;

    const USER_AGENT = "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US) AppleWebKit/525.19 (KHTML, like Gecko) Chrome/1.0.154.53 Safari/525.19";

    public function __construct($url, $req_fields, $is_post_req = false) {
        $this->url = $url;
        $this->request_fields = $req_fields;
        $this->is_post_request = $is_post_req;
    }
    private function execute_request(){
        $curl = curl_init();
        //if request is POST
        if($this->is_post_request) {
            curl_setopt($curl,CURLOPT_URL, $this->url);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $this->request_fields);
            //curl_setopt($curl,CURLOPT_PROXY,'http://192.168.0.105:4321');
        }
        //if request is GET
        else {
            curl_setopt($curl,CURLOPT_URL, $this->url . $this->request_fields);
            //curl_setopt($curl,CURLOPT_PROXY,'http://192.168.0.105:4321');
        }

        curl_setopt($curl,CURLOPT_USERAGENT, self::USER_AGENT );
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $this->response_object = json_decode(curl_exec($curl), true);
        $this->response_status_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
    }


    public function get_response_object() {
        $this->execute_request();
        return $this->response_object;
    }

    public function get_response_status_code(){
        if(isset($this->response_status_code)) {
            return $this->response_status_code;
        }
        else return 0;
    }
}



/*public static function get_data ($url, $request_fields, $is_post_request = false) {
    $curl = curl_init();
    //if request is POST
    if($is_post_request) {
        curl_setopt($curl,CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $request_fields);
        //curl_setopt($curl,CURLOPT_PROXY,'http://192.168.0.105:4321');
    }
    //if request is GET
    else {
        curl_setopt($curl,CURLOPT_URL, $url . $request_fields);
        //curl_setopt($curl,CURLOPT_PROXY,'http://192.168.0.105:4321');
    }

    curl_setopt($curl,CURLOPT_USERAGENT, self::USER_AGENT );
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

    $response_data["response_object"] = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $response_data["response_code"] = $http_code;
    curl_close ($curl);
    print_r($response_data);
    return $response_data;
}*/