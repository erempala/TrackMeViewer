<?php
    require_once("tests/util.php");

    class RequestsTest extends DatabaseTestCase
    {

        public static function setUpBeforeClass()
        {
            self::defineGET();
            parent::setUpBeforeClass();
            $__norun = true;
            require_once("requests.php");
            unset($__norun);
        }

        private static function defineGET()
        {
            $_GET["db"] = 42;
            $_GET["u"] = "track";
            $_GET["p"] = "password";
            $_GET["tn"] = "Hi's Bars";
            $_GET["a"] = "noop";
        }

        private static function runRequests() {
            return run(self::$connection);
        }

        private function assertResult($result, $code=0, $values=0) {
            $parts = explode("|", $result);
            $this->assertEquals("Result:$code", $parts[0]);
            if ($values === true) {
                $this->assertGreaterThan(1, count($parts));
            } else {
                $this->assertEquals($values + 1, count($parts));
            }
            return $parts;
        }

        public function setUp()
        {
            self::defineGET();
        }

        public function testWrongDB()
        {
            $_GET["db"] = 0;
            $this->assertEquals("Result:5", self::runRequests());
        }

        public function testNoUser()
        {
            $_GET["u"] = "";
            $this->assertEquals("Result:3", self::runRequests());
        }

        public function testNoPassword()
        {
            $_GET["p"] = "";
            $this->assertEquals("Result:3", self::runRequests());
        }

        public function testInvalid()
        {
            $_GET["p"] = "invalid$_GET[p]";
            $this->assertEquals("Result:1", self::runRequests());
        }

        public function testNew()
        {
            $_GET["u"] = "track2";
            $db = connect(self::$connection);
            $db->exec_sql("DELETE FROM users WHERE username='track2'");
            $before = $db->exec_sql("SELECT username FROM users")->fetchAll(PDO::FETCH_COLUMN, 0);
            $this->assertEquals("Result:0", self::runRequests());
            $after = $db->exec_sql("SELECT username FROM users")->fetchAll(PDO::FETCH_COLUMN, 0);
            $before[] = "track2";
            $this->assertEquals(sort($after), sort($before));
            $db = null;
        }

        public function testDisabled() {
            $_GET["u"] = "Disabled";
            $this->assertEquals("User disabled. Please contact system administrator", self::runRequests());
        }

        public function testNoop()
        {
            $this->assertEquals("Result:0", self::runRequests());
        }

        public function testGetIconList()
        {
            $_GET["a"] = "geticonlist";
            $this->assertResult(self::runRequests(), 0, true);
        }

        public function testUpdatePositionData() {
            // "updateimageurl" is a synonym, should probably be tested as well
            $_GET["a"] = "updatepositiondata";
            // TODO: Verify the different parts
            // TODO: Test when the trip is missing

            $_GET["tn"] = "Locked";
            $_GET["id"] = "6000";
            $_GET["ignorelocking"] = "";
            assert(!isset($_GET["imageurl"]));
            assert(!isset($_GET["comments"]));
            $this->assertResult(self::runRequests(), 8);

            $this->markTestIncomplete("testing this could change data");
        }

        public function testDeletePositionByID() {
            $this->markTestIncomplete("testing this would remove data, make sure that this will not happen");
        }

        public function testFindClosestPositionByTime() {
            $_GET["a"] = "findclosestpositionbytime";
            $_GET["date"] = "2015-09-02 12:04:00";
            $result = self::runRequests();
            $result = $this->assertResult($result, 0, 2);
            $this->assertRegExp("/[1-9]\\d*/", $result[1]);
            // TODO: Verify second value?

            $_GET["u"] = "missing";
            $this->assertResult(self::runRequests(), 7);
        }

        public function testFindClosestPositionByPosition() {
            $_GET["a"] = "findclosestpositionbyposition";
            $_GET["lat"] = "58";
            $_GET["long"] = "12";
            $result = self::runRequests();
            $result = $this->assertResult($result, 0, 3);
            $this->assertRegExp("/[1-9]\\d*/", $result[1]);
            // TODO: Verify second value?
            $this->assertRegExp("/\\d+\\.\\d+/", $result[3]);

            $_GET["u"] = "missing";
            $this->assertResult(self::runRequests(), 7);
        }

        public function testGetTripInfo() {
            $_GET["a"] = "gettripinfo";
            $result = self::runRequests();
            $result = $this->assertResult($result, 0, 3);
            $this->assertRegExp("/\\d+/", $result[1]);
            $this->assertEquals("0", $result[2]);
            $this->assertEquals("\n", $result[3]);
        }

        public function testDeleteTrip() {
            $this->markTestIncomplete("testing this would remove data, make sure that this will not happen");
        }

    }
?>
