<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use PDO;

#[Layout('layouts.app')]
#[Title('Backup & Restore')]
class BackupRestore extends Component
{
    use WithFileUploads;

    public $backupFile;
    public bool $isBackingUp = false;
    public bool $isRestoring = false;

    public function mount(): void
    {
        if (auth()->user()->id !== 1) {
            abort(403, 'No tienes permiso para acceder a esta sección.');
        }
    }

    public function getBackupsProperty(): array
    {
        $disk = Storage::disk('local');
        $files = $disk->files('backups');

        $backups = [];
        foreach ($files as $file) {
            if (str_ends_with($file, '.sql')) {
                $backups[] = [
                    'name' => basename($file),
                    'path' => $file,
                    'size' => $this->formatBytes($disk->size($file)),
                    'date' => date('d/m/Y H:i:s', $disk->lastModified($file)),
                    'timestamp' => $disk->lastModified($file),
                ];
            }
        }

        usort($backups, fn($a, $b) => $b['timestamp'] - $a['timestamp']);

        return $backups;
    }

    public function createBackup(): void
    {
        if (auth()->user()->id !== 1) {
            $this->dispatch('toast', type: 'error', message: 'No autorizado');
            return;
        }

        $this->isBackingUp = true;

        try {
            $backupDir = storage_path('app/backups');
            if (!is_dir($backupDir)) {
                mkdir($backupDir, 0750, true);
            }

            $filename = 'backup_' . date('Y-m-d_His') . '.sql';
            $filePath = $backupDir . DIRECTORY_SEPARATOR . $filename;

            $pdo = DB::connection()->getPdo();
            $dbName = DB::getDatabaseName();

            $output = fopen($filePath, 'w');
            if ($output === false) {
                throw new \RuntimeException('No se pudo crear el archivo de backup');
            }

            // Header
            fwrite($output, "-- Backup generado: " . date('Y-m-d H:i:s') . "\n");
            fwrite($output, "-- Base de datos: {$dbName}\n");
            fwrite($output, "SET FOREIGN_KEY_CHECKS=0;\n");
            fwrite($output, "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n");
            fwrite($output, "SET AUTOCOMMIT=0;\n");
            fwrite($output, "START TRANSACTION;\n\n");

            // Get all tables
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

            foreach ($tables as $table) {
                // DROP + CREATE TABLE
                fwrite($output, "-- Tabla: `{$table}`\n");
                fwrite($output, "DROP TABLE IF EXISTS `{$table}`;\n");

                $createTable = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC);
                fwrite($output, $createTable['Create Table'] . ";\n\n");

                // Export data in batches
                $count = $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();

                if ($count > 0) {
                    $batchSize = 500;
                    $offset = 0;

                    // Get column names
                    $columns = $pdo->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(PDO::FETCH_COLUMN);
                    $columnList = implode('`, `', $columns);

                    while ($offset < $count) {
                        $rows = $pdo->query("SELECT * FROM `{$table}` LIMIT {$batchSize} OFFSET {$offset}")->fetchAll(PDO::FETCH_ASSOC);

                        if (empty($rows)) break;

                        $values = [];
                        foreach ($rows as $row) {
                            $rowValues = [];
                            foreach ($row as $value) {
                                if ($value === null) {
                                    $rowValues[] = 'NULL';
                                } else {
                                    $rowValues[] = $pdo->quote($value);
                                }
                            }
                            $values[] = '(' . implode(', ', $rowValues) . ')';
                        }

                        fwrite($output, "INSERT INTO `{$table}` (`{$columnList}`) VALUES\n" . implode(",\n", $values) . ";\n");

                        $offset += $batchSize;
                    }
                }

                fwrite($output, "\n");
            }

            fwrite($output, "SET FOREIGN_KEY_CHECKS=1;\n");
            fwrite($output, "COMMIT;\n");
            fclose($output);

            $this->dispatch('toast', type: 'success', message: 'Backup creado exitosamente: ' . $filename);
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Error: ' . $e->getMessage());
        } finally {
            $this->isBackingUp = false;
        }
    }

    public function downloadBackup(string $filename): mixed
    {
        if (auth()->user()->id !== 1) {
            abort(403);
        }

        $path = storage_path('app/backups/' . basename($filename));

        if (!file_exists($path)) {
            $this->dispatch('toast', type: 'error', message: 'Archivo no encontrado');
            return null;
        }

        return response()->streamDownload(function () use ($path) {
            readfile($path);
        }, basename($filename), [
            'Content-Type' => 'application/sql',
        ]);
    }

    public function deleteBackup(string $filename): void
    {
        if (auth()->user()->id !== 1) {
            $this->dispatch('toast', type: 'error', message: 'No autorizado');
            return;
        }

        $safeName = basename($filename);
        $path = 'backups/' . $safeName;

        if (Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
            $this->dispatch('toast', type: 'success', message: 'Backup eliminado: ' . $safeName);
        } else {
            $this->dispatch('toast', type: 'error', message: 'Archivo no encontrado');
        }
    }

    public function restoreBackup(string $filename): void
    {
        if (auth()->user()->id !== 1) {
            $this->dispatch('toast', type: 'error', message: 'No autorizado');
            return;
        }

        $this->isRestoring = true;

        try {
            $safeName = basename($filename);
            $filePath = storage_path('app/backups/' . $safeName);

            if (!file_exists($filePath)) {
                $this->dispatch('toast', type: 'error', message: 'Archivo no encontrado');
                return;
            }

            $this->executeSqlFile($filePath);
            $this->dispatch('toast', type: 'success', message: 'Base de datos restaurada exitosamente desde: ' . $safeName);
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Error al restaurar: ' . $e->getMessage());
        } finally {
            $this->isRestoring = false;
        }
    }

    public function uploadAndRestore(): void
    {
        if (auth()->user()->id !== 1) {
            $this->dispatch('toast', type: 'error', message: 'No autorizado');
            return;
        }

        $this->validate([
            'backupFile' => 'required|file|max:512000',
        ]);

        $this->isRestoring = true;

        try {
            $originalName = $this->backupFile->getClientOriginalName();

            if (!str_ends_with(strtolower($originalName), '.sql')) {
                $this->dispatch('toast', type: 'error', message: 'Solo se permiten archivos .sql');
                return;
            }

            $filename = 'restore_' . date('Y-m-d_His') . '.sql';
            $this->backupFile->storeAs('backups', $filename, 'local');
            $filePath = storage_path('app/backups/' . $filename);

            $this->executeSqlFile($filePath);
            $this->dispatch('toast', type: 'success', message: 'Base de datos restaurada exitosamente desde archivo subido');

            $this->backupFile = null;
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Error: ' . $e->getMessage());
        } finally {
            $this->isRestoring = false;
        }
    }

    private function executeSqlFile(string $filePath): void
    {
        $pdo = DB::connection()->getPdo();
        $sql = file_get_contents($filePath);

        if ($sql === false) {
            throw new \RuntimeException('No se pudo leer el archivo SQL');
        }

        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
        $pdo->exec("SET FOREIGN_KEY_CHECKS=0");

        // Split by semicolons respecting quotes
        $statements = $this->splitSqlStatements($sql);

        foreach ($statements as $statement) {
            $trimmed = trim($statement);
            if (empty($trimmed) || str_starts_with($trimmed, '--')) {
                continue;
            }
            $pdo->exec($trimmed);
        }

        $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
    }

    private function splitSqlStatements(string $sql): array
    {
        $statements = [];
        $current = '';
        $inString = false;
        $stringChar = '';
        $length = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];

            // Handle string literals
            if ($inString) {
                $current .= $char;
                if ($char === '\\' && $i + 1 < $length) {
                    $current .= $sql[++$i]; // skip escaped char
                } elseif ($char === $stringChar) {
                    $inString = false;
                }
                continue;
            }

            // Check for start of string
            if ($char === '\'' || $char === '"') {
                $inString = true;
                $stringChar = $char;
                $current .= $char;
                continue;
            }

            // Check for single-line comment
            if ($char === '-' && $i + 1 < $length && $sql[$i + 1] === '-') {
                $end = strpos($sql, "\n", $i);
                if ($end === false) break;
                $i = $end;
                continue;
            }

            // Semicolon = end of statement
            if ($char === ';') {
                $trimmed = trim($current);
                if (!empty($trimmed)) {
                    $statements[] = $trimmed;
                }
                $current = '';
                continue;
            }

            $current .= $char;
        }

        $trimmed = trim($current);
        if (!empty($trimmed)) {
            $statements[] = $trimmed;
        }

        return $statements;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function render()
    {
        return view('livewire.backup-restore', [
            'backups' => $this->backups,
        ]);
    }
}
