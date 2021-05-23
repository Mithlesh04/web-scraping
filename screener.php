<?php
   error_reporting(0);
   require 'phpQuery-onefile.php';

class Screener {
    protected $nseCode = "";
    protected $isNseCode = false;
    protected $HTML = "";

    public $status = array( "code" => 100 ); //ric code not found
    public $companId = "";
    public $warehouseId = "";

    public function __construct(&$nseCode){
        $this->nseCode = $nseCode;

        if(isset($nseCode) && gettype($nseCode)==='string'){
            $d = phpQuery::newDocument( $this->Fetch("https://www.screener.in/company/$nseCode/consolidated") );

            if(strtolower(str_replace(" ","",trim($d->find("body > main > div.container > div > h2")->text())))=="error404:pagenotfound"){
                $this->status['code'] = 101 ; //  data not found
            }else {
                $this->status['code'] = 200;
                $this->HTML = $d->find("body > main.container");
                $this->companId = $d->find("div#company-info")->attr("data-company-id");
                $this->warehouseId = $d->find("div#company-info")->attr("data-warehouse-id");
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

    /**
     * @param $section = 'quarters' | 'profit-loss' | 'balance-sheet' | 'cash-flow' | 'ratios' | 'shareholding'
     * @param $expendLength = "none" , "all" , 1,2,3....nth = (how many expended data you want)
     * @param default $expendLength='all'
     * @return ["head"=>...,"data"=>...]
     */
    protected function TableData($section,$expendLength='all'){
        $qp = $this->HTML->find("section#$section > div.responsive-holder > table.data-table ");
        $head = array(); $data = array();
        foreach($qp->find(" thead > tr:first-child() > th") as $th){
            array_push($head,trim(pq($th)->text()));
        }
        foreach($qp->find(" tbody > tr ") as $tr){
            $trArr = array();
            $expended = array();
                foreach(pq($tr)->find("td") as $k => $td){
                    $pqtd = pq($td);
                    array_push($trArr,$pqtd->text());
                    $onClickTxt = $pqtd->find("button.button-plain")->attr("onclick");
                    if($onClickTxt){
                        $explodeTxt = explode("," ,trim(str_replace(array('Company.showSchedule', 'Company.showShareholders' ,'(', ')','this',"'"),'',trim($onClickTxt))));

                        if( count($explodeTxt) && $expendLength !="none"){
                            $lstele = trim($explodeTxt[1]);
                            $explOne = trim(str_replace(" ","+",$explodeTxt[0]));
                            $ExpUrl = "https://www.screener.in/api/" .(!$lstele ? $this->companId."/investors/$explOne/" : "company/".($this->companId)."/schedules/?parent=$explOne&section=$lstele&consolidated=");
                            $FexUrl = $this->Fetch($ExpUrl);
                            if($FexUrl){
                                $FexUrlJson = json_decode($FexUrl,true);
                                if(gettype($FexUrlJson)==="array"){
                                    $i = 1;
                                    foreach($FexUrlJson as $ke => $json){
                                        if(gettype($json)==="array"){
                                            $jsonValues = array();
                                                foreach($head as $kev => $val){
                                                    if($val){
                                                        $tmval = trim($val);
                                                        if(array_key_exists($tmval,$json)){
                                                            if(gettype($json[$tmval])!="array"){
                                                                array_push($expended,$json[$tmval]);
                                                            }
                                                        }else {
                                                            array_push($expended,"");
                                                        }
                                                    }else if(!$kev) {
                                                        array_push($expended,$ke);
                                                    }
                                                }
                                            if($i==$expendLength){
                                                break;
                                            }
                                            ++$i;
                                        }
                                    }
                                    }
                            }
                        }
                        
                    }
                }
                array_push($data,$trArr);

                if(count($expended)){
                    array_push($data,$expended);
                }
        }

        return ["head"=>$head,"data"=>$data];
    }

    public function Profile(){
        $top = $this->HTML->find("div#top");

        $scrape = array(
            "status"=>$this->status,
            "company_name"=> trim($top->find("div:first-child > h1")->text()),
            "company_links"=>array(
               //  array("name"=>"","link"=>"")
               //  ...
            ),
            "company_profile"=>array(
               //  array("title"=>"","sub"=>"")
               //  ...
            ),
            "top_ratios"=>array(
               // array("name"=>"","val"=>"")
               // ...
            )
        );

        foreach( $top->find("div.company-info > div.company-profile > div.company-links > a") as $a){
            $ae = pq($a);
            $txt = trim($ae->text());
            $link = $ae->attr("href");
            if($txt && $link){
                array_push($scrape['company_links'],array("name"=>$txt,"link"=>$link));
            }
        }
        $company_profile = "div.company-info > div.company-profile > div.flex-column";
        foreach( $top->find("$company_profile > div.title") as $key => $div){
            $tit = pq($div)->children()->remove()->end()->text();
            $sub = $top->find("$company_profile > div.sub")->eq($key)->text();
            if($tit && $sub){
                array_push($scrape['company_profile'],array("title"=>$tit,"sub"=>$sub));
            }
        }
        foreach( $top->find("div.company-info > div.company-ratios > ul#top-ratios > li") as $li){
            $spn = pq($li);
            $name = $spn->find(".name")->text();
            $val = $spn->find(".value")->text();
            if($name && $val){
                array_push($scrape['top_ratios'],array("name"=>$name,"val"=>$val));   
            }
        }
        return $scrape;
    }

    public function Analysis(){
        $scrape = array();
        $analysis = $this->HTML->find("section#analysis > div > div");
        foreach( $analysis as $key => $div){
            $d = pq($div);
            $title1 = strtolower($d->find("p.title")->text());
            if($title1){
                $arr = array();
                foreach( $d->find(" ul > li") as $li){
                    $txt1 = pq($li)->text();
                    if($txt1){
                        array_push($arr,$txt1);
                    }
                }
                array_push($scrape,array("title"=>$title1,"val"=>$arr));   
            }
        }
        return $scrape;
    }

    public function Peers(){
        $peers = $this->HTML->find("section#peers");
        $peersHeading = $peers->find("div:first-child > div:first-child");
        $pPeers = explode(":",$peersHeading->find("p.sub")->clone()->children()->remove()->end()->text());
        $peerHeadArr = array();
        foreach($peersHeading->find("p.sub > a") as $key=>$a){
            $ah = pq($a); $href0 = $ah->attr("href"); $atext = $ah->text();
            if($pPeers[$key] && $atext){
            array_push($peerHeadArr,array("name"=>$pPeers[$key],"type"=>$atext,"link"=>$href0));
            }
        }

        $scrape = array(
            "title"=>$peersHeading->find("h2")->text(),
            "head"=>$peerHeadArr,
            "table"=>array(
                "head"=>array(),
                "value"=>array()
            )
          );

                      
            $perTable = (phpQuery::newDocument($this->Fetch("https://www.screener.in/api/company/".($this->warehouseId)."/peers/")))->find(" div.responsive-holder > table.data-table > tbody > tr");
         
               $isSerialKeyRemove = false;
                  foreach($perTable as $k => $tr){
                      $trpq = pq($tr);
                      if(!$k){
                          foreach($trpq->find("th") as $ke=>$th){
                              $thpq = pq($th); $mTxt = $thpq->clone()->children()->remove()->end()->text();
                              $txt = str_replace(".","",strtolower(trim($mTxt)));
                              $chTxt = trim($thpq->children()->text());
                              if(!$ke){
                                  $isSerialKeyRemove = $txt=="sno";
                                  if(!$isSerialKeyRemove){
                                      array_push($scrape["table"]["head"],$mTxt.($chTxt ? "($chTxt)" : ''));
                                  }
                              }else{
                                  array_push($scrape["table"]["head"],$mTxt.($chTxt ? "($chTxt)" : ''));
                              }
                          }
                      }else{ 
                          foreach($trpq->find("td") as $key=>$td){
                              $tdpq = pq($td);
                               $txt = trim($tdpq->text());
                              if(!$key){
                                  if(!$isSerialKeyRemove){
                                     array_push($scrape["table"]["value"],$txt);
                                  }
                              }else{
                                  $lnk = $tdpq->find("a")->attr("href");
                                   array_push($scrape["table"]["value"], ($tdpq->attr("class")=='text' && $lnk) ? array("name"=>$txt,"link"=>$lnk) : $txt);                        
                                }
                            }
                         }
                     }
         
         return $scrape;
    }

    public function Quarters($expand = 'all'){
        return $this->TableData('quarters',$expand);
    }

    public function ProfitLoss($expand = 'all'){
        return $this->TableData('profit-loss',$expand);
    }

    public function BalanceSheet($expand = 'all'){
        return $this->TableData('balance-sheet',$expand);
    }

    public function CashFlow($expand = 'all'){
        return $this->TableData('cash-flow',$expand);
    }

    public function Ratios($expand = 'all'){
        return $this->TableData('ratios',$expand);
    }

    public function Shareholding($expand = 'all'){
        return $this->TableData('shareholding',$expand);
    }

    public function Documents() {
        $scrape = array();
        $doc = $this->HTML->find("section#documents > div.flex-row > div.documents");
        foreach($doc as $docVal){
            $pqVl = pq($docVal);
            $docArr = array(
                "head"=>trim($pqVl->find("h3")->text()),
                "data"=>array(
                    // array("text"=>"","link"=>"","sub"=>""),
                    // ...
                )
            );
            foreach($pqVl->find("ul.list-links > li") as $li){
                $pqli = pq($li);
                $a = $pqli->find("a");
                $txt = trim($a->clone()->children()->remove()->end()->text());
                $chtxt = trim($a->find(".smaller")->text());
                $lnk = $a->attr("href");
                if($txt){
                    array_push($docArr,array("text"=>$txt,"link"=>$lnk,"sub"=>$chtxt));
                }
            }
            array_push($scrape,$docArr);
        }

        return $scrape;
    }
    
}


$stockCode = "RELIANCE";
$screener = new Screener($stockCode);

// Profile , Analysis , Peers , Quarters , ProfitLoss , BalanceSheet , CashFlow , Ratios , Shareholding , Documents
$p = $screener->Profile();

print_r($p);
