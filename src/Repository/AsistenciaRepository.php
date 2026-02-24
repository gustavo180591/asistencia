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
        if (!empty($f['authDateTimeFrom'])) {
            $conditions[] = "authDateTime >= ?";
            $params[] = $f['authDateTimeFrom'];
        }
        if (!empty($f['authDateTimeTo'])) {
            $conditions[] = "authDateTime <= ?";
            $params[] = $f['authDateTimeTo'];
        }
        if (!empty($f['authDateFrom'])) {
            $conditions[] = "authDate >= ?";
            $params[] = $f['authDateFrom'];
        }
        if (!empty($f['authDateTo'])) {
            $conditions[] = "authDate <= ?";
            $params[] = $f['authDateTo'];
        }
        if (!empty($f['authTimeFrom'])) {
            $conditions[] = "authTime >= ?";
            $params[] = $f['authTimeFrom'];
        }
        if (!empty($f['authTimeTo'])) {
            $conditions[] = "authTime <= ?";
            $params[] = $f['authTimeTo'];
        }

        $whereClause = implode(' AND ', $conditions);
        
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
            'data' => $rows,
        ];
    }

    public function exportData(array $f): array
    {
        // Build WHERE conditions
        $conditions = ["1=1"];
        $params = [];
        
        if (!empty($f['authDateFrom'])) {
            $conditions[] = "authDate >= ?";
            $params[] = $f['authDateFrom'];
        }
        if (!empty($f['authDateTo'])) {
            $conditions[] = "authDate <= ?";
            $params[] = $f['authDateTo'];
        }
        if (!empty($f['PersonName'])) {
            $conditions[] = "PersonName LIKE ?";
            $params[] = '%' . $f['PersonName'] . '%';
        }
        if (!empty($f['id'])) {
            $conditions[] = "id LIKE ?";
            $params[] = '%' . $f['id'] . '%';
        }

        $whereClause = implode(' AND ', $conditions);
        
        // Get all data for the date range
        $sql = "
            SELECT 
                id, 
                PersonName, 
                authDate, 
                authTime, 
                direction,
                authDateTime
            FROM dbo.asistencia
            WHERE {$whereClause}
            ORDER BY id, authDate, authTime
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

        // Group by person and date to calculate entry/exit times
        $grouped = [];
        foreach ($rows as $row) {
            $key = $row['id'] . '|' . $row['PersonName'] . '|' . $row['authDate'];
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'id' => $row['id'],
                    'PersonName' => $row['PersonName'],
                    'authDate' => $row['authDate'],
                    'entradas' => [],
                    'salidas' => []
                ];
            }
            
            if ($row['direction'] === 'IN') {
                $grouped[$key]['entradas'][] = $row['authTime'];
            } elseif ($row['direction'] === 'OUT') {
                $grouped[$key]['salidas'][] = $row['authTime'];
            }
        }

        // Calculate entry, exit, and average times
        $exportData = [];
        foreach ($grouped as $group) {
            $entrada = !empty($group['entradas']) ? min($group['entradas']) : '';
            $salida = !empty($group['salidas']) ? max($group['salidas']) : '';
            
            // Calculate average time (difference between first entry and last exit)
            $promedio = '';
            if ($entrada && $salida) {
                $entradaTime = strtotime($entrada);
                $salidaTime = strtotime($salida);
                if ($salidaTime > $entradaTime) {
                    $diff = $salidaTime - $entradaTime;
                    $hours = floor($diff / 3600);
                    $minutes = floor(($diff % 3600) / 60);
                    $promedio = sprintf("%02d:%02d", $hours, $minutes);
                }
            }
            
            // Clean time format (remove microseconds)
            $entrada = $entrada ? substr($entrada, 0, 8) : '';
            $salida = $salida ? substr($salida, 0, 8) : '';
            
            $exportData[] = [
                'id' => $group['id'],
                'PersonName' => $group['PersonName'],
                'authDate' => $group['authDate'],
                'entrada' => $entrada,
                'salida' => $salida,
                'promedio_tiempo' => $promedio
            ];
        }

        // Sort by date and then by name
        usort($exportData, function($a, $b) {
            $dateCompare = strcmp($a['authDate'], $b['authDate']);
            if ($dateCompare !== 0) return $dateCompare;
            return strcmp($a['PersonName'], $b['PersonName']);
        });

        return $exportData;
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
