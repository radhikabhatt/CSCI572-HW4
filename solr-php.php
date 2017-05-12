<?php
ini_set('memory_limit', '1024M');
// make sure browsers see this page as utf-8 encoded HTML
header('Content-Type: text/html; charset=utf-8');


$max = 10;
$temp = 'solr';
$output = false;
$check = isset($_REQUEST['q']) ? $_REQUEST['q'] : false;

if ($check)
{
 // The Apache Solr Client library should be on the include path
 // which is usually most easily accomplished by placing in the
 // same directory as this script ( . or current directory is a default
 // php include path entry in the php.ini)
 require_once('Apache/Solr/Service.php');
 require_once('SpellCorrector.php');
 require_once('simple_html_dom.php');



 // create a new solr service instance - host, port, and corename
 // path (all defaults in this example)
 
 $solr = new Apache_Solr_Service('localhost', 8983, '/solr/myexample/');
 // if magic quotes is enabled then stripslashes will be neede  

 // if ($corrector == $check) {
 //  $corrector = "";
 // }

if (get_magic_quotes_gpc() == 1)
 {
 $check = stripslashes($check);
 }

 // in production code you'll always want to use a try /catch for any
 // possible exceptions emitted by searching (i.e. connection
 // problems or a query parsing error)
 
try
 {
  if(isset($_GET['methods'])&&($_GET['methods']=="solr"))
  {
    
  $output = $solr->search($check, 0, $max);
  $temp = 'solr';
}
  else{
  $set =array('sort'=>"python desc");
  $output=$solr->search($check,0,10,$set);
  $temp = 'pr';
}
}
 catch (Exception $e)
 {
 // in production you'd probably log or email this error to an admin
 // and then show a special message to the user but for this example
 // we're going to show the full exception
 die("<html><head><title>SEARCH EXCEPTION</title><body><pre>{$e->__toString()}</pre></body></html>");
 }
}
?>

<html>
 <head>
<style>
.center {
    margin: auto;
    width: 30%;
    padding: 10px;
    border: 1px solid black;
}
.button5 {
    border-radius: 3px;
    font-size: 20px;
    background-color: #e7e7e7;
    color: black;
}
.results {
    padding-left: 3px;
}
#q{
    width: 15%;
    height: 5%;
    font-size: large;
}
</style>
<link rel="stylesheet" href="http://code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.css">
  <script src="http://code.jquery.com/jquery-1.10.2.js"></script>
  <script src="http://code.jquery.com/ui/1.11.4/jquery-ui.js"></script>
 <title>PHP Solr Client Example</title>
 </head>
 <body>

 <form accept-charset="utf-8" method="get">
 <label for="q"><b>Solar Search:</b></label>
 <input id="q" name="q" type="text" value="<?php echo htmlspecialchars($check, ENT_QUOTES, 'utf-8'); ?>"/>

<br />
<br />
<br />

<div class = "center">
<br>


<input type="radio" name="methods" value="solr" <?php if ($temp=='solr') echo ' checked="checked"';?>/>Results from Solr
<br />
<input type="radio" name="methods" value="pr"<?php if ($temp=='pr') echo ' checked="checked"';?>/>Results from PageRank 
<br /><br />
<input type="submit" class = "button5"/>
</div>
</form>


<?php
if ($output)
{
 $total = (int) $output->response->numFound;
 $max_output = min($max, $total);
 $min_output = min(1, $total);
?>
<?php 

if ($total == ""):
  $uncorrect = explode(" ", $check);

  $corrected = "";
  
  foreach($uncorrect as $t){
    $corrected = $corrected.SpellCorrector::correct($t)." ";
  }
?>
  <p>Do you want to search: <a href="http://localhost:8000/solr-php.php?q=<?php echo trim($corrected);?>&methods=solr"><?php echo $corrected;?></a></p>
<?php endif; ?>
<div class='results'>Results <?php echo $min_output; ?> - <?php echo $max_output;?> of <?php echo $total; ?>:</div>


<ol><?php
$collection=array();
try
{
$csv=fopen("mergedcsv.csv","r");

while(!feof($csv)){
$readcsv=fgetcsv($csv,1024);
$collection[$readcsv[0]]=$readcsv[1];
}

fclose($csv);

}catch (Exception $err) 
{
 echo 'Error: ',  $err->getMessage(), "\n";
}

foreach ($output->response->docs as $page){
if(isset($page->dc_title)){
$title=$page->dc_title;
}
else{
$title="NA";
}

if(isset($page->description)){
$detail=$page->description;
}
else{
$detail="NA";
}

$id=$page->id;

$link = str_replace("/usr/local/Cellar/solr/6.2.1/server/solr/LATimesDownloadData/","",$id);


$file_dump = file_get_html($id)->plaintext;
$array = preg_split('/(\.)/', $file_dump);
// echo $array;
$snippet = " ";
$letters = split(" ",$check);

foreach($array as $sent)
{ 
  //echo $check;
  foreach($letters as $term){
    $regex = "/\b".$term."\b/i";
    
    if (preg_match($regex, $sent)) {
      //echo $sent;
      $snippet = trim($sent);
      break;
    }
  }

  if($snippet != " "){
    break;
  }
}

?>
<?php 
  if($snippet == " "){
    $snippet = $title;
  }else{
    $snippet = ".." . $snippet . "..";
  }
?>


<div>

<li>
<p><a href="<?php echo $collection[$link];?>"><?php echo $collection[$link];?></a></p>
<p>ID:<?php echo " ".$id; ?></p>
<!-- <p>Description:<?php echo " ".$detail; ?></p> -->
<p>Title:<a href="<?php echo " ".$collection[$link] ?>"><?php echo $title ?></a></p>
<p>Snippet:<?php echo $snippet; ?></p>
</li>
</div>

<?php } ?>
</ol>
<?php } ?>

<script>
  $(function() {
    $("#q").autocomplete({
      source : function(request, response) {
        var prefix = "";
        var wrd = $("#q").val().toLowerCase().split(" ").pop(-1);
        var URL = "http://localhost:8983/solr/myexample/suggest?indent=on&q=" + wrd + "&wt=json";
        $.ajax({
            url : URL,
            success : function(data) {
                var wrd = $("#q").val().toLowerCase().split(" ").pop(-1);
                var suggestions = data.suggest.suggest[wrd].suggestions;
                suggestions = $.map(suggestions, function (value, index) {
                    
                    var query = $("#q").val();
                    var subset = query.split(" ");
                    
                    if (subset.length > 1) {
                        var position = query.lastIndexOf(" ");
                        prefix = query.substring(0, position + 1).toLowerCase();
                    }
                    if (!/^[a-zA-Z0-9]+$/.test(value.term)) {
                        return null;
                    }

                    if (checkForStopWord(value.term)) {
                        return null;
                    }
                    return prefix + value.term;
                });

                if($("#q").val().length == 1){
                    if(suggestions.length <= 10 && suggestions.length >= 5){
                        response(suggestions);
                    }else if(suggestions.length > 10){
                        response(suggestions.slice(0, 9));
                    }else{
                        response(suggestions);
                    }
                }else if($("#q").val().length == 2){
                    if(suggestions.length <= 7 && suggestions.length >=3){
                        response(suggestions);
                    }else if(suggestions.length > 7){
                        response(suggestions.slice(0, 6));
                    }else{
                        response(suggestions);
                    }
                }else{
                    // console.log($("#q").val().length);
                    response(suggestions.slice(0, 3));
                }
                console.log(suggestions);
                // response(suggestions.slice(0, 5));
            },
            dataType : 'jsonp',
            jsonp : 'json.wrf'
        });
    },
    minLength : 1
});
});


    function checkForStopWord(word)
    {
        var test = new RegExp("\\b"+word+"\\b","i");
        return stopWords.search(test) < 0 ? false : true;
    }


    var stopWords = "a,able,about,above,abst,accordance,according,accordingly,across,act,actually,added,adj,\
    affected,affecting,affects,after,afterwards,again,against,ah,all,almost,alone,along,already,also,although,\
    always,am,among,amongst,an,and,announce,another,any,anybody,anyhow,anymore,anyone,anything,anyway,anyways,\
    anywhere,apparently,approximately,are,aren,arent,arise,around,as,aside,ask,asking,at,auth,available,away,awfully,\
    b,back,be,became,because,become,becomes,becoming,been,before,beforehand,begin,beginning,beginnings,begins,behind,\
    being,believe,below,beside,besides,between,beyond,biol,both,brief,briefly,but,by,c,ca,came,can,cannot,can't,cause,causes,\
    certain,certainly,co,com,come,comes,contain,containing,contains,could,couldnt,d,date,did,didn't,different,do,does,doesn't,\
    doing,done,don't,down,downwards,due,during,e,each,ed,edu,effect,eg,eight,eighty,either,else,elsewhere,end,ending,enough,\
    especially,et,et-al,etc,even,ever,every,everybody,everyone,everything,everywhere,ex,except,f,far,few,ff,fifth,first,five,fix,\
    followed,following,follows,for,former,formerly,forth,found,four,from,further,furthermore,g,gave,get,gets,getting,give,given,gives,\
    giving,go,goes,gone,got,gotten,h,had,happens,hardly,has,hasn't,have,haven't,having,he,hed,hence,her,here,hereafter,hereby,herein,\
    heres,hereupon,hers,herself,hes,hi,hid,him,himself,his,hither,home,how,howbeit,however,hundred,i,id,ie,if,i'll,im,immediate,\
    immediately,importance,important,in,inc,indeed,index,information,instead,into,invention,inward,is,isn't,it,itd,it'll,its,itself,\
    i've,j,just,k,keep,keeps,kept,kg,km,know,known,knows,l,largely,last,lately,later,latter,latterly,least,less,lest,let,lets,like,\
    liked,likely,line,little,'ll,look,looking,looks,ltd,m,made,mainly,make,makes,many,may,maybe,me,mean,means,meantime,meanwhile,\
    merely,mg,might,million,miss,ml,more,moreover,most,mostly,mr,mrs,much,mug,must,my,myself,n,na,name,namely,nay,nd,near,nearly,\
    necessarily,necessary,need,needs,neither,never,nevertheless,new,next,nine,ninety,no,nobody,non,none,nonetheless,noone,nor,\
    normally,nos,not,noted,nothing,now,nowhere,o,obtain,obtained,obviously,of,off,often,oh,ok,okay,old,omitted,on,once,one,ones,\
    only,onto,or,ord,other,others,otherwise,ought,our,ours,ourselves,out,outside,over,overall,owing,own,p,page,pages,part,\
    particular,particularly,past,per,perhaps,placed,please,plus,poorly,possible,possibly,potentially,pp,predominantly,present,\
    previously,primarily,probably,promptly,proud,provides,put,q,que,quickly,quite,qv,r,ran,rather,rd,re,readily,really,recent,\
    recently,ref,refs,regarding,regardless,regards,related,relatively,research,respectively,resulted,resulting,results,right,run,s,\
    said,same,saw,say,saying,says,sec,section,see,seeing,seem,seemed,seeming,seems,seen,self,selves,sent,seven,several,shall,she,shed,\
    she'll,shes,should,shouldn't,show,showed,shown,showns,shows,significant,significantly,similar,similarly,since,six,slightly,so,\
    some,somebody,somehow,someone,somethan,something,sometime,sometimes,somewhat,somewhere,soon,sorry,specifically,specified,specify,\
    specifying,still,stop,strongly,sub,substantially,successfully,such,sufficiently,suggest,sup,sure,t,take,taken,taking,tell,tends,\
    th,than,thank,thanks,thanx,that,that'll,thats,that've,the,their,theirs,them,themselves,then,thence,there,thereafter,thereby,\
    thered,therefore,therein,there'll,thereof,therere,theres,thereto,thereupon,there've,these,they,theyd,they'll,theyre,they've,\
    think,this,those,thou,though,thoughh,thousand,throug,through,throughout,thru,thus,til,tip,to,together,too,took,toward,towards,\
    tried,tries,truly,try,trying,ts,twice,two,u,un,under,unfortunately,unless,unlike,unlikely,until,unto,up,upon,ups,us,use,used,\
    useful,usefully,usefulness,uses,using,usually,v,value,various,'ve,very,via,viz,vol,vols,vs,w,want,wants,was,wasn't,way,we,wed,\
    welcome,we'll,went,were,weren't,we've,what,whatever,what'll,whats,when,whence,whenever,where,whereafter,whereas,whereby,wherein,\
    wheres,whereupon,wherever,whether,which,while,whim,whither,who,whod,whoever,whole,who'll,whom,whomever,whos,whose,why,widely,\
    willing,wish,with,within,without,won't,words,world,would,wouldn't,www,x,y,yes,yet,you,youd,you'll,your,youre,yours,yourself,\
    yourselves,you've,z,zero";
    </script>
 </body>
</html>
