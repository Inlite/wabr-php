<?php
class WABarcodeReader
{
    public $_serverUrl = "https://wabr.inliteresearch.com";
    private $_authorization = "";
    public $showDiag = false;
    
    public function __construct($serverUrl="", $auth="") {
        if ($serverUrl <> "")
            $this->_serverUrl = $serverUrl;
        $this->_authorization = $auth;
    }
    
    public $types = "";
    public $dir = "";
    public $tbr = 0;
    public function Read($image, $types="", $directions="", $tbr_code=0)
    {
        if ($this->showDiag)
            print ("================= PROCESSING: ". WAUtils::signature($image) . "\n");

        $names = explode ( "|", $image);
        $urls = array(); $files = array(); $images = array();
        foreach ($names as $name)
        {
            $name = trim($name);
            $s = strtolower($name);
            $exists = file_exists($name);
            if (WAUtils::StartsWith($s,"http://") || WAUtils::StartsWith($s,"https://") || 
                WAUtils::StartsWith($s,"ftp://") || WAUtils::StartsWith($s,"file://"))
                array_push($urls, $name);
            else if ($exists)
                array_push($files, $name);
            else if (WAUtils::StartsWith($name, "data:") || WAUtils::isBase64($name))
                array_push($images, $name);
            else
            {
                $msg = "Invalid image source: " . substr($name, 0, min(strlen($name), 256));
                throw new Exception($msg);
            }
        }
        return $this->ReadLocal($urls, $files, $images, $types, $directions, $tbr_code);
    }
    
    public function ParseResponse ($txtResponse)
    {
        $barcodes = array();
        $ind =  strpos ($txtResponse, "<Results");
        if ($ind !== false) 
        {
            $doc = new DOMDocument();
            $doc->loadXML($txtResponse);
            $nodeBarcodes = $doc->getElementsByTagName("Barcode");
            
            foreach ($nodeBarcodes AS $nodeBarcode) {
                $barcode = new WABarcode();
                $i = 3;
                // XML text is encoded inside of Text node
                $str = WAUtils::nodeValue($nodeBarcode, "Text", "");
                $barcode->Text = htmlspecialchars_decode($str);
                $barcode->Left = WAUtils::nodeValueInt($nodeBarcode, "Left", 0);
                $barcode->Right = WAUtils::nodeValueInt($nodeBarcode, "Right", 0);
                $barcode->Top = WAUtils::nodeValueInt($nodeBarcode, "Top", 0);
                $barcode->Bottom = WAUtils::nodeValueInt($nodeBarcode, "Bottom", 0);
                $barcode->Length = WAUtils::nodeValueInt($nodeBarcode, "Length", 0);
                $barcode->Page = WAUtils::nodeValueInt($nodeBarcode, "Page", 0);
                $barcode->File = WAUtils::nodeValue($nodeBarcode, "File", "");
                $barcode->Data = WAUtils::nodeValueDecodeBase64($nodeBarcode, "Data");
                $barcode->Meta = WAUtils::nodeValueXml($nodeBarcode, "Meta");
                
                $barcode->Type = WAUtils::nodeValue($nodeBarcode, "Type", "");
                $barcode->Rotation = WAUtils::nodeValue($nodeBarcode, "Rotation", "");
                
                $values= WAUtils::nodeValueXml($nodeBarcode, "Values");
                if ($values != null)
                {
                    $a = $values->saveXML();
                    $children = $values->documentElement->childNodes; 
                    foreach ($children as $child) { 
                        if ($child->nodeName[0] != "#")
                            $barcode->Values[$child->nodeName] = $child->nodeValue; 
                    }
                }
                
                array_push($barcodes, $barcode);
            }
        }
        return $barcodes;
    }
    
    private function  ReadLocal($urls, $files, $images, $types, $dir, $tbr)   
    {
        $server = $this->_serverUrl;
        if ($server == "")
            $server = "https://wabr.inliteresearch.com"; // default server
        $queries = array();
        
        $url=""; 
        foreach ($urls as $s)
        {
            if ($url !="") $url = $url . "|";
            $url = $url . $s;
        }
        if ($url != "")
            $queries["url"]= $url;
        
        
        $image="";
        foreach ($images as $s)
        {
            if ($image !="") $image = $image . "|";
            $image = $image . $s;
        }
        if ($image != "")
            $queries["image"] = $image;

        $queries["format"] = "xml";
        $queries["fields"] = "meta";
        if ($types != "")
            $queries["types"] = $types;
        if ($dir != "")
            $queries["options"] = $dir;
        if ($tbr != 0)
            $queries["tbr"] = (string) $tbr;

        $serverUrl = $server . "/barcodes";
        $barcodes = array();
        $txtResponse = "";
        try
        {
            $request = new WAHttpRequest ();
            $txtResponse = $request->ExecRequest($serverUrl, $this->_authorization, $files, $queries, 0);
            $barcodes = $this->ParseResponse ($txtResponse);
        }
        catch (Exception $ex)
        {
            throw $ex;
        }
        return $barcodes;
    }
}


class WABarcode
{
    public $Text ="";
    public $Data = null;
    public $Type = "";
    public $Length = 0;
    public $Page = 0;
    public $Rotation = "";
    public $Left = 0;
    public $Top = 0;
    public $Right = 0;
    public $Bottom = 0;
    public $File = "";
    public $Meta = null;
    public $Values =  array();
}

class WAHttpRequest
{
    function get_headers_from_curl_response($response)
    {
        $headers = array();
        $ind = strrpos($response, "HTTP/1.1");
        if ($ind === false)
            return null;
        $header_text = substr($response, $ind);

        foreach (explode("\r\n", $header_text) as $i => $line)
        {
            if ($i === 0)
                $headers['http_code'] = str_replace("HTTP/1.1 ", "", $line);
            else
            {
            $ind = strpos($line, ": ");
            if ($ind !== false)
                {
                list ($key, $value) = explode(': ', $line);
                $headers[$key] = $value;
                }
            }
        }
        return $headers;
    }
    
    
    public  function ExecRequest($serverUrl, $auth, $files, $queries, $retries)
    {
        $env_auth = 'WABR_AUTH' ;
        if ($auth == "" and getenv($env_auth) !== FALSE)
            $auth = getenv($env_auth);
        $errmsg = "";
        $url = $serverUrl; 
        $headers = array("Content-Type:multipart/form-data", "Authorization:"  . $auth);
        
        $postfields = $queries;
        $cnt = 0; // fields in PHP array require unique names 
        $version = explode('.', PHP_VERSION);
        foreach ($files as $file)
        {
            $cnt = $cnt + 1;
            if ($version[0] < 5 or ($version[0] == 5 and $version[1] <= 4))
              {$args['file' . $cnt] = '@' . $file  . ';filename=' . basename($file);}
            else
              {$args['file' . $cnt] = new CurlFile($file, '', basename($file));}
           $postfields = array_merge($postfields,  $args);
        }       
        $ch = curl_init();
        $options = array(
            CURLOPT_URL => $url,
            CURLOPT_HEADER => true,
            CURLOPT_POST => 1,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $postfields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_SSL_VERIFYPEER => false
        ); // cURL options
        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);
        $body  = "";
        if(!curl_errno($ch))
        {
            $info = curl_getinfo($ch);
            // Then, after your curl_exec call:
            $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $response_header = substr($response, 0, $header_size);
            $body = substr($response, $header_size);     
            $code = $info['http_code'];
            if (($code >= 300  && $code < 400) && ($retries < 2))
            {
                $redirect_url = $info['redirect_url'];
                return WAHttpRequest::ExecRequest($redirect_url, $auth, $files, $queries, $retries + 1);
            }            
            else if ($code!= 200)
            {
                $headers = $this->get_headers_from_curl_response($response_header);
                if ($headers['http_code'] != null && $headers['http_code'] != "")
                    $errmsg = $headers['http_code'] . " " . $body;
                else
                    $errmsg = "HTTP Error: " . $info['http_code'] . " " . $body;
            }
        }
        else
        {
            $errmsg = curl_error($ch);
        }
        curl_close($ch);
        if ($errmsg != "")
            throw new Exception($errmsg);
        return $body;
    }

}

class WAUtils
{
    public static function nodeValue($nodeParent, $name, $def)
    {
        $sout = $def;
        try{
            if  ($nodeParent == null) return $sout;
            if ( $nodeParent->getElementsByTagName($name)->length == 0) return $sout;
            $sout = $nodeParent->getElementsByTagName($name)->item(0)->nodeValue;
        }
        catch (Exception $exception) {}
        return $sout;
    }

   public static function signature($image)
        {
        if ($image == null || $image == "") return "";
        return  " [" . substr($image, 0, min(80, strlen($image))) .  "] ";
        }
        
    public static function nodeValueInnerXml($nodeParent, $name) { 
        $innerHTML= ''; 
        if  ($nodeParent != null &&  $nodeParent->getElementsByTagName($name)->length > 0)
        {
            $node = $nodeParent->getElementsByTagName($name)->item(0);
            if ($node != null) {
                $children = $node->childNodes; 
                if ($children != null)
                {
                    foreach ($children as $child) { 
                        $innerHTML .= $child->ownerDocument->saveXML( $child ); 
                    }
                } 
            }
        }
        return $innerHTML; 
    } 
    
    public static function  nodeValueXml($nodeParent, $name)
    {
        $sout = self::nodeValueInnerXml($nodeParent, $name);
        try{
            $doc = new DOMDocument();
            $sout = trim($sout);
            $ind =  strpos ($sout, "<");
			if ($ind == 0 && $ind !== false) 
            {
                $doc->loadXML($sout);
                return $doc;
            }
        }
        catch (Exception $exception) {;}
        return null;
    }

    public static function  nodeValueDecodeBase64($nodeParent, $name)
    {
        try {
            $encodedData = self::nodeValue($nodeParent, $name, "");
            $data = array();
            $decodedData = base64_decode($encodedData);
            for($i = 0; $i < strlen($decodedData); $i++){
                array_push($data, ord($decodedData[$i]));
            }
            return $data;
        } catch (Exception $e) {return null;}

    } 
    
    public static function nodeValueInt($nodeParent, $name, $def)
    {
        $nout = $def;
        $sout = self::nodeValue($nodeParent, $name, "");
        if ($sout != "")
            $nout = (int) $sout;
        return $nout;
    }
    
    public static function StartsWith($haystack, $needle) {
        return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== FALSE;
    }
    
    public static function isBase64($value)
    {
        $v = $value;
        // replace formating characters
        $v = str_replace("\r\n", "", $v);
        $v = str_replace("\r", "", $v);
        // remove reference file name, if  present
        $ind = strpos($v, ":::");
        if ($ind !== false)
            $v = substr($var, 0, ind);

        if (strlen ($v) == 0 || (strlen ($v) % 4) != 0)
            return false;
        $index = strlen ($v) - 1;
        if ($v[$index] == '=')
            $index--;
        if ($v[$index] == '=')
            $index--;
        for ($i = 0; $i <= $index; $i++)
            if (self::IsInvalidBase64char($v[$i]))
                return false;
        return true;
    }
    
    public static function eol()
    {
    if (!defined("STDIN")) 
        return ("<br>");
    else
        return PHP_EOL;
    }
    
    private static function IsInvalidBase64char($value)
    {
        $intValue = ord($value);
        if ($intValue >= 48 && $intValue <= 57)
            return false;
        if ($intValue >= 65 && $intValue <= 90)
            return false;
        if ($intValue >= 97 && $intValue <= 122)
            return false;
        return $intValue != 43 && $intValue != 47;
    }     
}

?>
