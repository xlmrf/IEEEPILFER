<?php
include_once('helpfile/simple_html_dom.php');

Database::selectDB();

class Database
{

    static protected $table;
    static protected $database;
    static public $list = [];

    static public function selectDB()
    {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $mysqli = new mysqli("localhost", "root", "", 'parser');
        self::$database = $mysqli;
    }

    //  checks if the table is created
    static public function checkTable($name)
    {
        self::$database->query("CREATE TABLE IF NOT EXISTS parser.".$name." (
        id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        title MEDIUMTEXT,
        issue VARCHAR(64),
        articleNumber VARCHAR(64),
        publicationYear VARCHAR(32),
        publicationNumber VARCHAR(64),
        confLocation VARCHAR(64),
        doi VARCHAR(64),
        authors JSON,
        email JSON
        )");
    }

    //  table is called
    static public function getTable($name)
    {
        self::checkTable($name);
        self::$table = self::$database->query("SELECT * FROM ".$name);
        echo "<h2 style='text-align: center'>{$name} table</h2>";
        while ($row = mysqli_fetch_assoc(self::$table)){
            array_push(self::$list,$row);

            echo "<div style='border: 1px solid #7e87c1; padding: 5px; '><h3>{$row['title']}</h3></br>
            <p>place: {$row['confLocation']}</p>
            <h4>year: {$row['publicationYear']}</h4>
            <small>doi: {$row['doi']}</small>
            </div>";
        }

    }

    //  document is loaded into $name table
    static function insertItem($name,$document)
    {
        self::checkTable($name);
        self::$database->query("INSERT INTO parser.".$name." 
        (title,issue,articleNumber,publicationYear,publicationNumber,confLocation,doi) VALUES 
        ('".$document['title']."','
        ".$document['issue']."','
        ".$document['articleNumber']."','
        ".$document['publicationYear']."','
        ".$document['publicationNumber']."','
        ".$document['confLocation']."','
        ".$document['doi']."')
        ");
    }
}


abstract class Site
{

    static $doi;
    static $title;
    public $url;
    public $webpage;
    public $headers = array(
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
        'Content-Type: application/json',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:94.0) Gecko/20100101 Firefox/94.0',
    );

    //  insert here a link for processing the site through curl
    public function __construct(string $url,array $headers = [])
    {
        $this->url = $url;
        if($headers){
            $this->headers = $headers;
        }
        $this->curlManager($this->url, $this->headers);
        $this->searcher();
    }

    public function curlManager(string $url, array $headers,array $postfields = []): string
    {
        if(!$url || !is_string($url) || ! preg_match('/^http(s)?:\/\/[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(\/.*)?$/i', $url)){
            return print_r(['error'=>'url not valid']);
        }
        $ch2 = curl_init($url);
        curl_setopt($ch2, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch2, CURLOPT_FOLLOWLOCATION, 1);
//        curl_setopt($ch2, CURLOPT_POST,$postfields);
//        curl_setopt($ch2, CURLOPT_NOBODY, 1);
        curl_setopt($ch2, CURLOPT_HTTPHEADER, $headers);
        $results = curl_exec($ch2); //get page
        if (curl_errno($ch2)) {
            return print_r(['error'=>curl_error($ch2)]);
        }
        curl_close($ch2);
        return $this->webpage = $results;
    }

    //  function for processing the site
    abstract function searcher();

}

class IEEE extends Site
{

    protected $document;
    protected $error;

    public function __construct(string $url, array $headers = [])
    {
        parent::__construct($url, $headers); // TODO: Change the autogenerated stub
        $this->getDocument();
    }
    public function searcher(): array
    {
        $document = [];
        $html = $this->webpage;
        $dom = str_get_html($html);
        $mainPreg = "/xplGlobal.document.metadata=[^*]+/";
        preg_match_all($mainPreg, $dom, $info);
        $info = $info[0][0];
        $pregDoi = '/10[.][0-9]{4,}\/[0-z]+\.[0-z]+?(\.[0-9]+)/';
        preg_match_all($pregDoi, $info, $doi);
        $document['doi'] = $doi[0][0];
        if (!isset($document['doi'])){
            return [$this->error=>'File not exist'];
        }
        parent::$doi = $document['doi'];
        preg_match_all('/"formulaStrippedArticleTitle":"[\w\W]*?"/',$info, $title);
        $document['title'] = str_replace('"', "", explode(':',$title[0][0])[1]);
        parent::$title = $document['title'];
        preg_match_all('/"articleNumber":"[\w\W]*?"/',$info, $articleNum);
        $document['articleNumber'] = str_replace('"', "", explode(':',$articleNum[0][0])[1]);
        preg_match_all('/"xplore-issue":"[\w\W]*?"/',$info, $issue);
        $document['issue'] = str_replace('"', "", explode(':',$issue[0][0])[1]);
        preg_match_all('/"confLoc":"[\w\W]*?"/',$info, $confLoc);
        $document['confLocation'] = str_replace('"', "", explode(':',$confLoc[0][0])[1]);
        preg_match_all('/"publicationNumber":"[\w\W]*?"/',$info, $publicationNum);
        $document['publicationNumber'] = str_replace('"', "", explode(':',$publicationNum[0][0])[1]);
        preg_match_all('/"publicationYear":"[\w\W]*?"/',$info, $year);
        $document['publicationYear'] = str_replace('"', "", explode(':',$year[0][0])[1]);
//        return print_r($info);
        return $this->document = $document;

    }
    public function insert2DB($name)
    {
        Database::insertItem($name,$this->document);
    }
    public function getSci(){
        if (!isset($this->error)){
            new SciHub();
        }
    }
    public function getDocument()
    {
        if (isset($this->error)){
            echo $this->error;
        }
        else {
            echo "<div style='border: 1px solid #212121; padding: 5px; '><h3>The document is {$this->document['title']}</h3></br>
                <p>place: {$this->document['confLocation']}</p>
                <h4>year: {$this->document['publicationYear']}</h4>
                <small>doi: {$this->document['doi']}</small>
            </div>";
        }
    }
}


class SciHub
{

    protected $hub = 'https://sci.bban.top/pdf/';
    protected $response;
    public $context;
    public $filename;

    function __construct()
    {
        $this->filename = Site::$title.'.pdf';
        $this->context = stream_context_create(
            array(
                "http" => array(
                    "header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.102 Safari/537.36"
                )
            )
        );
        $url = $this->hub.Site::$doi;
        $pdf = file_get_contents($url.".pdf#view=FitH", false, $this->context);
        $size = file_put_contents($this->filename, $pdf);
        if ($size == 0){
            $url = mb_strtolower($url).".pdf#view=FitH";
            $pdf = file_get_contents($url, false, $this->context);
            $size = file_put_contents($this->filename, $pdf);
            if ($size == 0) {
                return print_r('If the file is not found, try to find throw this link: ' . $url . '<br>');
            }
        }
        return print_r("<br>".$size.':file was saved');

    }
}
// new IEEE('https://ieeexplore.ieee.org/document/########')

// --- test ---
$test = new IEEE('https://ieeexplore.ieee.org/document/4280138');
$test->getDocument();
$test->insert2DB('mydb');
$test->getSci();
Database::getTable('mydb');
