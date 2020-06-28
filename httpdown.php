<?php
/**
 * 基于http的断点续传下载器
 * author：xqy
 */
class HttpDown
{
    protected $file_url;
    protected $file_down_url;
    protected $dest;
    protected $block = 102400;

    protected $total_file_size;

    function __construct($file_url, $dest_file)
    {
        $this->file_url = $file_url;
        $this->dest     = $dest_file;
    }

    /*
     * 探测文件的真实下载地址
     *
     */
    function detect($url)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'HEAD');
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_NOBODY, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_exec($curl);
        $header = curl_getinfo($curl);
        curl_close($curl);

        $http_code = (int)$header["http_code"];
        if ($http_code == 302) {
            $redirect_url = $header["redirect_url"];
            $this->detect($redirect_url);
        } else if($http_code == 200) {
            $this->file_down_url = $header["url"];
            //获取文件的总大小
            $this->total_file_size = $header["download_content_length"];
            
            if (empty($dest_file)) {
                $file_name  = explode("/", $this->file_down_url);
                $file_name  = array_pop($file_name);
                $str_pos    = strpos($file_name,"?");
                $file_name  = substr($file_name, 0, $str_pos);
                $this->dest = "./test_".$file_name;
            }
            return;
        } else {
            echo PHP_EOL."file detect error";
        }
    }

    /**
     * 下载
     */
    function download()
    {
        $this->detect($this->file_url);

        $begin = file_get_contents("./cfg");
        if(!empty($begin))$begin = unserialize(base64_decode($begin));
        $begin = (int)($begin["downed"]);
        if($begin != 0)$begin += 1;

        echo PHP_EOL.'download start:'.((int)($begin * 100 / $this->total_file_size))."%";

        $buffer = '';
        while ($begin < $this->total_file_size) {
            $end = $begin + $this->block;
            if ($end >= $this->total_file_size)
                $end = $this->total_file_size;

            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, $this->file_down_url);
            curl_setopt($curl, CURLOPT_HEADER , false);
            curl_setopt($curl, CURLOPT_RANGE, $begin."-".$end);
            curl_setopt($curl, CURLOPT_BINARYTRANSFER, true);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLINFO_HEADER_OUT, true);

            //设置http头
            //$request_header = array('Accept-Ranges:bytes',"content-disposition:attachment; filename=".$url);
            //curl_setopt($curl, CURLOPT_HTTPHEADER, $request_header);

            $buffer     = curl_exec($curl);
            $httpCode   = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            if((int)$httpCode != 206)die("download file error");

            //将下载的文件写入本地
            $file = fopen($this->dest, 'a');
            if(!$file)die("open file error");
            fwrite($file, $buffer);
            fclose($file);
            flush();

            file_put_contents("./cfg", base64_encode(serialize(array("downed"=>$end, "totel"=>$this->total_file_size))));

            echo PHP_EOL.'download:'.((int)($end * 100 / $this->total_file_size))."%";

            $begin = $end + 1;
        }
        echo PHP_EOL.'download complete';
    }
}
