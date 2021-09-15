<?php

namespace App;

class Connect
{
    public $url;
    private $date;
    private $path;
    private $method;
    private $token;
    public $result_url;
    public $sleep = 5;


    public function __construct($url,$date,$path,$method)
    {
        $this->url = $url;
        $this->date = $date;
        $this->path = $path;
        $this->method = $method;
        $this->token = file_get_contents('token.txt');
        if (!$this->date && !$this->path){
            $this->result_url = "https://mpstats.io/api/wb/".$this->url;

        }
        $this->result_url = "https://mpstats.io/api/wb/".$this->url."?d1=".trim($this->date)."&path=".$this->path;

    }

    public function getInfoForApi()
    {

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->result_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $this->method,
            CURLOPT_POSTFIELDS => "{
        \"startRow\":0,\"filterModel\":{},\"sortModel\":[{\"colId\":\"revenue\",\"sort\":\"desc\"}]}",
            CURLOPT_HTTPHEADER => array(
                "X-Mpstats-TOKEN: ".$this->token,
                "Content-Type: application/json"
            ),
        ));

        $response = curl_exec($curl);
        $getInfo = curl_getinfo($curl);
        if($getInfo['http_code'] !== 200 ){
            if($getInfo['http_code'] === 202){
                print_r("\n[".date('H:i:s')."] Апи долго не отвечает, перезапуск через $this->sleep секунд...\n");
                sleep($this->sleep);
                $this->getInfoForApi();
            }
            if($getInfo['http_code'] === 401){
                exit("\n[".date('H:i:s')."] Токен в файле token.txt не является действительным\n");
            }
            if($getInfo['http_code'] === 429){
                exit("\n[".date('H:i:s')."] Допустимое колличество запросов закончилось\n");
            } else{
                print_r("\n[".date('H:i:s')."] Апи долго не отвечает, перезапуск через $this->sleep секунд...\n");
                sleep($this->sleep);
                $this->getInfoForApi();
            }
        }else{
            return $response;
        }
        curl_close($curl);
    }
}
