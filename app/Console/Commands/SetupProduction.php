<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Comando de setup inicial para producción.
 * Detecta si la BD está vacía y ejecuta migraciones, seeders y configuración.
 * Seguro de ejecutar múltiples veces — solo aplica lo que falta.
 */
class SetupProduction extends Command
{
    protected $signature = 'app:setup
                            {--force : Forzar ejecución sin confirmación}
                            {--fresh : Ejecutar todo aunque ya exista (peligroso)}';

    protected $description = 'Setup inicial de producción: migraciones, seeders, permisos, webhooks';

    public function handle(): int
    {
        $this->info('');
        $this->info('==========================================');
        $this->info('  🚀 Setup de Producción - ASESCO BPO');
        $this->info('==========================================');
        $this->info('');

        $steps = [];

        // ============================================
        // 1. Verificar conexión a BD
        // ============================================
        $this->line('[1/7] 🔌 Verificando conexión a base de datos...');
        try {
            DB::connection()->getPdo();
            $this->info('  ✓ Conexión exitosa: ' . config('database.connections.mysql.database'));
        } catch (\Exception $e) {
            $this->error('  ✗ No se puede conectar a la base de datos: ' . $e->getMessage());
            return self::FAILURE;
        }

        // ============================================
        // 2. Migraciones
        // ============================================
        $this->line('[2/7] 🗄️  Ejecutando migraciones...');
        $this->call('migrate', ['--force' => true]);
        $steps[] = 'Migraciones';

        // ============================================
        // 3. Verificar si necesita seeders iniciales
        // ============================================
        $this->line('[3/7] 🌱 Verificando datos iniciales...');

        $needsRolesSeeder = !Schema::hasTable('roles') || DB::table('roles')->count() === 0;
        $needsAdminSeeder = !Schema::hasTable('users') || DB::table('users')->where('email', 'admin@asesco.com')->doesntExist();
        $needsLabelsSeeder = !Schema::hasTable('labels') || DB::table('labels')->count() === 0;

        if ($needsRolesSeeder) {
            $this->line('  → Ejecutando RolesAndPermissionsSeeder...');
            $this->call('db:seed', ['--class' => 'RolesAndPermissionsSeeder', '--force' => true]);
            $steps[] = 'Roles y permisos iniciales';
        } else {
            $this->info('  ✓ Roles ya existen (' . DB::table('roles')->count() . ' roles)');
        }

        if ($needsAdminSeeder) {
            $this->line('  → Creando usuario administrador...');
            $this->call('db:seed', ['--class' => 'AdminSeeder', '--force' => true]);
            $steps[] = 'Usuario admin creado';
            $this->warn('  ⚠ Credenciales: admin@asesco.com / password');
            $this->warn('  ⚠ Cambia la contraseña después del primer login');
        } else {
            $this->info('  ✓ Usuario admin ya existe');
        }

        if ($needsLabelsSeeder) {
            // Verificar si el seeder existe antes de ejecutarlo
            if (class_exists('Database\\Seeders\\LabelsSeeder')) {
                $this->line('  → Ejecutando LabelsSeeder...');
                $this->call('db:seed', ['--class' => 'LabelsSeeder', '--force' => true]);
                $steps[] = 'Etiquetas iniciales';
            }
        } else {
            $this->info('  ✓ Etiquetas ya existen (' . DB::table('labels')->count() . ' etiquetas)');
        }

        // ============================================
        // 4. Sincronizar módulos y permisos
        // ============================================
        $this->line('[4/7] 🔐 Sincronizando módulos y permisos...');
        $this->call('permissions:sync');
        $steps[] = 'Permisos sincronizados';

        // ============================================
        // 5. Storage link
        // ============================================
        $this->line('[5/7] 📁 Verificando storage link...');
        if (!file_exists(public_path('storage'))) {
            $this->call('storage:link');
            $steps[] = 'Storage link creado';
        } else {
            $this->info('  ✓ Storage link ya existe');
        }

        // ============================================
        // 6. Limpiar y cachear
        // ============================================
        $this->line('[6/7] ⚡ Optimizando caché...');
        $this->call('config:cache');
        $this->call('route:cache');
        $this->call('view:cache');
        $this->callSilently('event:cache');
        $steps[] = 'Caché optimizada';

        // ============================================
        // 7. Configurar webhooks de Evolution API
        // ============================================
        $this->line('[7/7] 🔗 Configurando webhooks de Evolution API...');
        $hasChannels = Schema::hasTable('channels') && DB::table('channels')->where('is_active', true)->exists();

        if ($hasChannels) {
            $this->call('channels:setup-webhooks', ['--full' => true]);
            $steps[] = 'Webhooks configurados';
        } else {
            $this->info('  ✓ No hay canales activos, saltando webhooks');
        }

        // ============================================
        // Resumen
        // ============================================
        $this->info('');
        $this->info('==========================================');
        $this->info('  ✅ Setup completado');
        $this->info('==========================================');
        foreach ($steps as $step) {
            $this->line("  • {$step}");
        }
        $this->info('');

        return self::SUCCESS;
    }
}
