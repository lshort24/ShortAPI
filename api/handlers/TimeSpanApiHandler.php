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
                ts.name,
                ts.start,
                tl.id AS timeline_id,
                tl.name AS timeline_name
            FROM
                {$this->table_name} ts
            INNER JOIN timeline tl
            ON ts.timeline_id = tl.id
            {$where}
            ORDER BY
                tl.name, ts.start
MySQL;
        return [$query, $params];
    }

    public function process_row($row) {
        $id = null;
        $name = null;
        $start = null;
        $timeline_id = null;
        $timeline_name = null;

        extract($row);

        $record = [
            "id" => $id,
            "name" => $name,
            "start" => $start,
            "timeline" => [
                "id" => $timeline_id,
                "name" => $timeline_name
            ],
        ];

        return $record;
    }
}