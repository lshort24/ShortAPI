<?php

abstract class ApiHandler {
    /**
     * Process a row returned in the query results
     *
     * @param mixed     $row
     * @return mixed
     */
    abstract function process_row($row);

    /**
     * Handle an API Request
     *
     * @param string    $query      MySQL query string with placeholders
     * @param array     $params     array of variables to substitute in the query. The key for array entries is the place
     *                              name (with the preceding ":"). Each entry has a value and a PDO type
     * @return array
     */
    public function handle_request($query, $params) {
        $database = new Database();
        $conn = $database->getConnection();
        $stmt = $conn->prepare($query);

        foreach ($params as $name => $param) {
            $stmt->bindParam($name, $param["value"], $param["type"]);
        }

        $stmt->execute();
        $num = $stmt->rowCount();

        if ($num > 0) {
            // timelines array
            $results = [
                "status" => 200,
                "records" => []
            ];

            // retrieve our table contents
            // fetch() is faster than fetchAll()
            // http://stackoverflow.com/questions/2770630/pdofetchall-vs-pdofetch-in-a-loop
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                array_push($results["records"], $this->process_row($row));
            }
        }
        else {
            $results["status"] = 404;
            $results["message"] = "No records found.";
        }

        return $results;
    }
}