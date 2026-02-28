<?php

namespace App\Repository;

class AsistenciaRepository
{
    private $conn;

    public function __construct()
    {
        $dsn = "Driver={ODBC Driver 17 for SQL Server};Server=54.236.194.226,1433;Database=ivms;TrustServerCertificate=yes;";
        $this->conn = \odbc_connect($dsn, "sa", "Biometrico1588$");
        if (!$this->conn) {
            throw new \Exception("ODBC Connection failed: " . \odbc_errormsg());
        }
    }

    public function search(array $f): array
    {
        $page = max(1, (int)($f['page'] ?? 1));
        $pageSize = min(200, max(1, (int)($f['pageSize'] ?? 50)));
        $offset = ($page - 1) * $pageSize;

        // Orden seguro
        $orderBy = $f['orderBy'] ?? 'authDateTime';
        $orderDir = strtolower($f['orderDir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $allowedOrderBy = ['id','authDateTime','authDate','authTime','direction','deviceName','deviceSerial','PersonName','cardN'];
        if (!in_array($orderBy, $allowedOrderBy, true)) {
            $orderBy = 'authDateTime';
        }

        // Build WHERE conditions
        $conditions = ["1=1"];
        $params = [];
        
        if (!empty($f['id'])) {
            $conditions[] = "id LIKE ?";
            $params[] = '%' . $f['id'] . '%';
        }
        if (!empty($f['direction'])) {
            $conditions[] = "direction LIKE ?";
            $params[] = '%' . $f['direction'] . '%';
        }
        if (!empty($f['deviceName'])) {
            $conditions[] = "deviceName LIKE ?";
            $params[] = '%' . $f['deviceName'] . '%';
        }
        if (!empty($f['deviceSerial'])) {
            $conditions[] = "deviceSerial LIKE ?";
            $params[] = '%' . $f['deviceSerial'] . '%';
        }
        if (!empty($f['PersonName'])) {
            $conditions[] = "PersonName LIKE ?";
            $params[] = '%' . $f['PersonName'] . '%';
        }
        if (!empty($f['cardN'])) {
            $conditions[] = "cardN LIKE ?";
            $params[] = '%' . $f['cardN'] . '%';
        }
        if (!empty($f['authDateFrom'])) {
            $conditions[] = "authDate >= ?";
            $params[] = $f['authDateFrom'];
        }
        if (!empty($f['authDateTo'])) {
            $conditions[] = "authDate <= ?";
            $params[] = $f['authDateTo'];
        }

        $whereClause = implode(' AND ', $conditions);
        
        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM dbo.asistencia WHERE {$whereClause}";
        $stmt = \odbc_prepare($this->conn, $countSql);
        \odbc_execute($stmt, $params);
        $countRow = \odbc_fetch_array($stmt);
        $total = $countRow['total'];
        
        // Get paginated data
        $sql = "
            SELECT
              CONVERT(varchar(100), %%physloc%%) AS rowKey,
              id, authDateTime, authDate, authTime, direction, deviceName, deviceSerial, PersonName, cardN
            FROM dbo.asistencia
            WHERE {$whereClause}
            ORDER BY {$orderBy} {$orderDir}
            OFFSET {$offset} ROWS FETCH NEXT {$pageSize} ROWS ONLY
        ";

        $stmt = \odbc_prepare($this->conn, $sql);
        if (!$stmt) {
            throw new \Exception("Prepare failed: " . \odbc_errormsg($this->conn));
        }

        $result = \odbc_execute($stmt, $params);
        if (!$result) {
            throw new \Exception("Execute failed: " . \odbc_errormsg($this->conn));
        }

        $rows = [];
        while ($row = \odbc_fetch_array($stmt)) {
            $rows[] = $row;
        }

        return [
            'page' => $page,
            'pageSize' => $pageSize,
            'total' => $total,
            'pages' => ceil($total / $pageSize),
            'data' => $rows,
        ];
    }

    public function create(array $p): int
    {
        $sql = "
            INSERT INTO dbo.asistencia
            (id, authDateTime, authDate, authTime, direction, deviceName, deviceSerial, PersonName, cardN)
            VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";

        $params = [
            (string)($p['id'] ?? ''),
            $this->nullIfEmpty($p['authDateTime'] ?? null),
            $this->nullIfEmpty($p['authDate'] ?? null),
            $this->nullIfEmpty($p['authTime'] ?? null),
            (string)($p['direction'] ?? ''),
            $this->nullIfEmpty($p['deviceName'] ?? null),
            $this->nullIfEmpty($p['deviceSerial'] ?? null),
            $this->nullIfEmpty($p['PersonName'] ?? null),
            $this->nullIfEmpty($p['cardN'] ?? null)
        ];

        $stmt = \odbc_prepare($this->conn, $sql);
        if (!$stmt) {
            throw new \Exception("Prepare failed: " . \odbc_errormsg($this->conn));
        }

        $result = \odbc_execute($stmt, $params);
        if (!$result) {
            throw new \Exception("Execute failed: " . \odbc_errormsg($this->conn));
        }

        return \odbc_num_rows($stmt);
    }

    public function updateByRowKey(string $rowKey, array $p): int
    {
        $sql = "
            UPDATE dbo.asistencia
            SET
              id = ?,
              authDateTime = ?,
              authDate = ?,
              authTime = ?,
              direction = ?,
              deviceName = ?,
              deviceSerial = ?,
              PersonName = ?,
              cardN = ?
            WHERE CONVERT(varchar(100), %%physloc%%) = ?
        ";

        $params = [
            (string)($p['id'] ?? ''),
            $this->nullIfEmpty($p['authDateTime'] ?? null),
            $this->nullIfEmpty($p['authDate'] ?? null),
            $this->nullIfEmpty($p['authTime'] ?? null),
            (string)($p['direction'] ?? ''),
            $this->nullIfEmpty($p['deviceName'] ?? null),
            $this->nullIfEmpty($p['deviceSerial'] ?? null),
            $this->nullIfEmpty($p['PersonName'] ?? null),
            $this->nullIfEmpty($p['cardN'] ?? null),
            $rowKey
        ];

        $stmt = \odbc_prepare($this->conn, $sql);
        if (!$stmt) {
            throw new \Exception("Prepare failed: " . \odbc_errormsg($this->conn));
        }

        $result = \odbc_execute($stmt, $params);
        if (!$result) {
            throw new \Exception("Execute failed: " . \odbc_errormsg($this->conn));
        }

        return \odbc_num_rows($stmt);
    }

    public function deleteByRowKey(string $rowKey): int
    {
        $sql = "DELETE FROM dbo.asistencia WHERE CONVERT(varchar(100), %%physloc%%) = ?";
        
        $stmt = \odbc_prepare($this->conn, $sql);
        if (!$stmt) {
            throw new \Exception("Prepare failed: " . \odbc_errormsg($this->conn));
        }

        $result = \odbc_execute($stmt, [$rowKey]);
        if (!$result) {
            throw new \Exception("Execute failed: " . \odbc_errormsg($this->conn));
        }

        return \odbc_num_rows($stmt);
    }

    private function nullIfEmpty($v)
    {
        if ($v === null) return null;
        $v = trim((string)$v);
        return $v === '' ? null : $v;
    }
}