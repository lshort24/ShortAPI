<?php

require_once "ApiHandler.php";

class TimeSpanApiHandler extends ApiHandler {
    private $table_name = "timespan";

    function get_read_query($id = null) {
        $params = [];
        $where = "";

        if ($id) {
            $where = "WHERE ts.id = :id";
            $params[":id"] = [
                "value" => $id,
                "type" => PDO::PARAM_INT
            ];
        }

        $query = <<< MySQL
            SELECT
                ts.id,
                tl.name AS timeline_name,
                ts.name
            FROM
                {$this->table_name} ts
            INNER JOIN timeline tl
            ON ts.timeline_id = tl.id
            {$where}
            ORDER BY
                ts.name
MySQL;
        return [$query, $params];
    }

    public function process_row($row) {
        $id = null;
        $timeline_name = null;
        $name = null;

        extract($row);

        $record = [
            "id" => $id,
            "timeline_name" => $timeline_name,
            "name" => $name
        ];

        return $record;
    }
}