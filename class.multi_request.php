<?php

class Multi_Request {
    private $curl_list = array();
    private $curl_multi;
    //как передать массив Request объектов
    public function __construct(array $req_arr)  {
        foreach ($req_arr as $req) {
            $this->curl_list[] = $req->get_curl();
        }

    }

    private function execute_requests() {
        $this->curl_multi = curl_multi_init();
        foreach ($this->curl_list as $curl){
            curl_multi_add_handle($this->curl_multi, $curl);
        }

        $active = null;

        do {
            $mrc = curl_multi_exec( $this->curl_multi, $active);
            print_r("MRC1 ". $mrc . "\n");
            // print_r(CURLM_CALL_MULTI_PERFORM);
        } while ($mrc == CURLM_CALL_MULTI_PERFORM);

        while ($active && $mrc == CURLM_OK) {
            echo "123\n";
            if (curl_multi_select($this->curl_multi) == -1) usleep(100);
            do { $mrc = curl_multi_exec($this->curl_multi, $active);
            }
            while ($mrc == CURLM_CALL_MULTI_PERFORM);
        }
    }

    public function get_response_objects() {
        $this->execute_requests();
        $response = array();
        foreach ($this->curl_list as $curl) {
           // print_r(curl_multi_getcontent($curl));
            $response[] = json_decode(curl_multi_getcontent($curl), true);
            curl_close($curl);
        }
        curl_multi_close($this->curl_multi);
        return $response;
    }

}