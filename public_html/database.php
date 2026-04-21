class Database {
    public function __construct() {
        die('Init function error');
    }
    
    public static function dbConnect() {
        
        require_once("/home/ddnguyen/DbNguyen.php");  
        try {
            $mysqli = new PDO('mysql:host=' . DBHOST . ';dbname=' . DBNAME, USERNAME, PASSWORD);
            
        } catch (PDOException $e) {
            echo "Could not connect";
            die($e->getMessage());
        }
        return $mysqli;
    }
    
    public static function dbDisconnect($mysqli) {
        $mysqli = null;
    }
}
?>
