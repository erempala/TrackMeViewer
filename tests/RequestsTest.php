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

        private function assertResult($result, $code=0, $values=0, $splitNewline=false) {
            if ($splitNewline) {
                $lines = explode("\n", $result);
            } else {
                $lines = array($result);
            }
            $parts = array();
            foreach ($lines as $line) {
                $parts = array_merge($parts, explode("|", $line));
            }
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

        public function testDelete() {
            $this->markTestIncomplete("testing this would remove data, make sure that this will not happen");
            $_GET["a"] = "delete";
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

        public function testGetTripFull() {
            // "gettriphighlights" is a synonym, should probably be tested as well
            $_GET["a"] = "gettripfull";
            $result = $this->assertResult(self::runRequests(), 0, true, true);
            // It returns 10 elements per marker, so it should be a multiple of 10
            // +1 for the header and +1 that there is an empty string at the end because it ends with \n
            $this->assertEquals(2, count($result) % 10);
            // The two +1 are the reason why it begins at 1 and has count - 1
            for ($i = 1; $i < count($result) - 1; $i += 10) {
                $this->assertRegExp("/\\d+\\.\\d+/", $result[$i]);
                $this->assertRegExp("/\\d+\\.\\d+/", $result[$i + 1]);
                $this->assertRegExp("/\\d+/", $result[$i + 6]);
                // TODO: Verify the different parts more closely?
            }
            $this->assertEquals("", $result[count($result) - 1]);

            $_GET["tn"] = "Missing";
            $this->assertResult(self::runRequests(), 7);

            $_GET["tn"] = "Locked";
            $this->assertResult(self::runRequests(), 0, 10);
        }

        public function testDeleteTrip() {
            $this->markTestIncomplete("testing this would remove data, make sure that this will not happen");
        }

    }
?>
