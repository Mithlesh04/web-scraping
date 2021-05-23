<?php
    error_reporting(0);
    require 'phpQuery-onefile.php';

class Reuters {
    protected $ricCode = "";
    protected $isRicCode = false;
    protected $profile = "";

    protected $financial = array(
        "status" => array(
            "code" => 100 // ric code not found
        )
    );

    public function __construct(&$ricCode){
        $this->ricCode = $ricCode;
        if(isset($ricCode) && gettype($ricCode)==='string'){
            $this->isRicCode = true;
            $d = json_decode( $this->Fetch("https://www.reuters.com/companies/api/getFetchCompanyFinancials/$ricCode") , true);
            $code = $d['status']['code'];

            $this->financial = array(
                "status"=>$d['status'],
                "ric" => $d['ric'],
                "company_name"=> $code==200 ? $d['market_data']['company_name'] : '',
                "financial_statements" => $code==200 ? $d["market_data"]["financial_statements"] : array()
            );
            if($this->financial['status']['code']==200){
                $this->profile = $this->Fetch("https://www.reuters.com/companies/$ricCode/profile");
            }
        }
    }

    protected function Fetch($url){
        $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => 1,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
            ));
        return curl_exec($curl);
    }

    public function Profile(){
        $main = pq(phpQuery::newDocument($this->profile)->find("body > div#__next > div > div.TwoColumnsLayout-body-86gsE > div.TwoColumnsLayout-left-column-CYquM > div.CompanyQuotePage-subpage-1HdPF > div.WithLoading-container-1Cf3g > div.container > div.Profile-about-keystats-2oYI9"));
        $Pdetails = $main->find("div.Profile-details-qAn0C > div.About-group-NiEhz ");
        $contactInfo = $Pdetails->find("div.About-column-2kncC > div.About-contact-2q4JN");    
        $officers = array(
             // array( "name" => "" , "title" => ""),
             // ...
        );
        foreach($Pdetails->find("div.About-column-2kncC > div.officers > div.About-officer-2NMAZ") as $div){
            $p = pq($div);
            $name = trim($p->find(".About-officer-name-kEDtg")->text());
            $title = trim($p->find(".About-officer-title-3h_8L")->text());
            if($name){
                array_push($officers,array( "name" => $name , "title" => $title));
            }
        }
        return array(
            "status"=>$this->financial["status"],
            "about"=>trim($main->find("div.Profile-about-1d-H- > p.TextLabel__text-label___3oCVw")->text()),
            "industry"=>trim($Pdetails->find("div.About-column-2kncC > div.industry > p.About-value-3oDGk")->text()),
            "address"=>trim($contactInfo->find("div.About-address-AiNm9 > .About-value-3oDGk")->text()),
            "phone"=>trim($contactInfo->find("p.About-phone-2No5Q")->text()),
            "website"=>$contactInfo->find("a.website")->attr("href"),
            "officers"=>$officers
        );
     
    }

    public function Financial(){
        return $this->financial;
    }

}


$stockCode = "RELI.NS";
$reuters= new Reuters($stockCode);

$f = $reuters->Financial();
$p = $reuters->Profile();

print_r($p);
print_r($f);
