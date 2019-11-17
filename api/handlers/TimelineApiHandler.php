<?php

require_once "ApiHandler.php";

class TimelineApiHandler extends ApiHandler {
    private $table_name = "timeline";

    /**
     * Read all or one timeline
     *
     * @param int $id
     * @return array
     */
    public function get_read_query($id = null) {
        $params = [];
        $where = "";

        if ($id) {
            $where = "WHERE id = :id";
            $params[':id'] = [
                "value" => $id,
                "type" => PDO::PARAM_INT
            ];
        }

        $query = <<< MySQL
            SELECT
                t.id,
                t.name
            FROM
                {$this->table_name} t
            {$where}
            ORDER BY
                t.name
MySQL;
        return [$query, $params];
    }

    public function process_row($row) {
        $id = null;
        $name = null;

        // extract row
        // this will make $row['name'] to
        // just $name only
        extract($row);

        $record = [
            "id" => $id,
            "name" => $name
        ];

        return $record;
    }
}