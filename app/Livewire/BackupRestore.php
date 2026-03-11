<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\DB;

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

        // Sort by timestamp descending (newest first)
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
            $dbHost = config('database.connections.mysql.host');
            $dbPort = config('database.connections.mysql.port', '3306');
            $dbName = config('database.connections.mysql.database');
            $dbUser = config('database.connections.mysql.username');
            $dbPass = config('database.connections.mysql.password');

            $backupDir = storage_path('app/backups');
            if (!is_dir($backupDir)) {
                mkdir($backupDir, 0750, true);
            }

            $filename = 'backup_' . date('Y-m-d_His') . '.sql';
            $filePath = $backupDir . DIRECTORY_SEPARATOR . $filename;

            // Build mysqldump command
            $command = sprintf(
                'mysqldump --host=%s --port=%s --user=%s --password=%s --single-transaction --routines --triggers --add-drop-table %s',
                escapeshellarg($dbHost),
                escapeshellarg($dbPort),
                escapeshellarg($dbUser),
                escapeshellarg($dbPass),
                escapeshellarg($dbName)
            );

            $result = Process::run($command);

            if ($result->successful()) {
                file_put_contents($filePath, $result->output());
                $this->dispatch('toast', type: 'success', message: 'Backup creado exitosamente: ' . $filename);
            } else {
                $this->dispatch('toast', type: 'error', message: 'Error al crear backup: ' . $result->errorOutput());
            }
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
            echo file_get_contents($path);
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

            $dbHost = config('database.connections.mysql.host');
            $dbPort = config('database.connections.mysql.port', '3306');
            $dbName = config('database.connections.mysql.database');
            $dbUser = config('database.connections.mysql.username');
            $dbPass = config('database.connections.mysql.password');

            $command = sprintf(
                'mysql --host=%s --port=%s --user=%s --password=%s %s',
                escapeshellarg($dbHost),
                escapeshellarg($dbPort),
                escapeshellarg($dbUser),
                escapeshellarg($dbPass),
                escapeshellarg($dbName)
            );

            $result = Process::run($command . ' < ' . escapeshellarg($filePath));

            if ($result->successful()) {
                $this->dispatch('toast', type: 'success', message: 'Base de datos restaurada exitosamente desde: ' . $safeName);
            } else {
                $this->dispatch('toast', type: 'error', message: 'Error al restaurar: ' . $result->errorOutput());
            }
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Error: ' . $e->getMessage());
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
            'backupFile' => 'required|file|max:512000', // max 500MB
        ]);

        $this->isRestoring = true;

        try {
            $originalName = $this->backupFile->getClientOriginalName();

            // Validate file extension
            if (!str_ends_with(strtolower($originalName), '.sql')) {
                $this->dispatch('toast', type: 'error', message: 'Solo se permiten archivos .sql');
                return;
            }

            // Store the file
            $filename = 'restore_' . date('Y-m-d_His') . '.sql';
            $this->backupFile->storeAs('backups', $filename, 'local');
            $filePath = storage_path('app/backups/' . $filename);

            $dbHost = config('database.connections.mysql.host');
            $dbPort = config('database.connections.mysql.port', '3306');
            $dbName = config('database.connections.mysql.database');
            $dbUser = config('database.connections.mysql.username');
            $dbPass = config('database.connections.mysql.password');

            $command = sprintf(
                'mysql --host=%s --port=%s --user=%s --password=%s %s',
                escapeshellarg($dbHost),
                escapeshellarg($dbPort),
                escapeshellarg($dbUser),
                escapeshellarg($dbPass),
                escapeshellarg($dbName)
            );

            $result = Process::run($command . ' < ' . escapeshellarg($filePath));

            if ($result->successful()) {
                $this->dispatch('toast', type: 'success', message: 'Base de datos restaurada exitosamente desde archivo subido');
            } else {
                $this->dispatch('toast', type: 'error', message: 'Error al restaurar: ' . $result->errorOutput());
            }

            $this->backupFile = null;
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Error: ' . $e->getMessage());
        } finally {
            $this->isRestoring = false;
        }
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
