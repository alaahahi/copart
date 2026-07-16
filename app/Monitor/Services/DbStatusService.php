<?php

namespace App\Monitor\Services;

use Illuminate\Support\Facades\DB;

class DbStatusService
{
    protected ?array $cachedSnapshot = null;

    public function snapshot(bool $force = false): ?array
    {
        if ($this->cachedSnapshot !== null && !$force) {
            return $this->cachedSnapshot;
        }

        try {
            $connection = DB::connection();
            $database = $connection->getDatabaseName();

            $connectionId = $connection->selectOne('SELECT CONNECTION_ID() AS id');
            $statusKeys = [
                'Threads_connected',
                'Threads_running',
                'Connections',
                'Max_used_connections',
                'Aborted_clients',
                'Aborted_connects',
                'Uptime',
            ];

            $status = [];
            foreach ($statusKeys as $key) {
                $row = $connection->selectOne('SHOW STATUS LIKE ?', [$key]);
                $status[$key] = $row->Value ?? null;
            }

            $this->cachedSnapshot = [
                'database' => $database,
                'connection_id' => $connectionId->id ?? null,
                'threads_connected' => (int) ($status['Threads_connected'] ?? 0),
                'threads_running' => (int) ($status['Threads_running'] ?? 0),
                'connections' => (int) ($status['Connections'] ?? 0),
                'max_used_connections' => (int) ($status['Max_used_connections'] ?? 0),
                'aborted_clients' => (int) ($status['Aborted_clients'] ?? 0),
                'aborted_connects' => (int) ($status['Aborted_connects'] ?? 0),
                'uptime' => (int) ($status['Uptime'] ?? 0),
                'driver' => $connection->getDriverName(),
            ];

            return $this->cachedSnapshot;
        } catch (\Throwable) {
            return null;
        }
    }

    public function formatMemory(?int $bytes = null): string
    {
        $bytes = $bytes ?? memory_get_usage(true);
        return round($bytes / 1048576, 1) . 'MB';
    }
}
