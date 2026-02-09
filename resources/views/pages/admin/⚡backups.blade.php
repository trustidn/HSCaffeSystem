<?php

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Backup Database')] class extends Component {
    use WithFileUploads;

    public string $backupType = 'full';
    public string $selectedTenantId = '';
    public bool $isProcessing = false;

    // Upload restore
    public $uploadedBackupFile = null;

    // Restore
    public bool $showRestoreModal = false;
    public string $restorePath = '';
    public string $restoreFilename = '';
    public string $restoreType = '';
    public string $restoreConfirmText = '';
    public string $restoreTenantId = '';
    public bool $isUploadedFile = false;

    /** @var array<int, array{filename: string, size: string, date: string, type: string, path: string}> */
    public array $backupHistory = [];

    public function mount(): void
    {
        $this->loadBackupHistory();
    }

    #[Computed]
    public function tenants()
    {
        return Tenant::query()->orderBy('name')->get();
    }

    public function loadBackupHistory(): void
    {
        $disk = Storage::disk('local');
        $files = $disk->files('backups');

        $this->backupHistory = collect($files)
            ->filter(fn (string $f) => str_ends_with($f, '.sqlite') || str_ends_with($f, '.json') || str_ends_with($f, '.sql'))
            ->map(function (string $file) use ($disk) {
                $filename = basename($file);
                $size = $disk->size($file);
                $lastModified = $disk->lastModified($file);

                // Determine type from filename
                $type = str_contains($filename, '_full_') ? 'Seluruh Database' : 'Per Cafe';

                // Extract tenant name from filename if per-tenant
                if (str_contains($filename, '_tenant_')) {
                    $parts = explode('_tenant_', $filename);
                    if (isset($parts[1])) {
                        $tenantSlug = explode('_', $parts[1])[0];
                        $tenant = Tenant::where('slug', $tenantSlug)->first();
                        $type = 'Cafe: ' . ($tenant?->name ?? $tenantSlug);
                    }
                }

                return [
                    'filename' => $filename,
                    'size' => $this->formatBytes($size),
                    'date' => date('d M Y H:i', $lastModified),
                    'timestamp' => $lastModified,
                    'type' => $type,
                    'path' => $file,
                ];
            })
            ->sortByDesc('timestamp')
            ->values()
            ->toArray();
    }

    public function createBackup(): void
    {
        if ($this->backupType === 'tenant' && ! $this->selectedTenantId) {
            $this->addError('selectedTenantId', 'Pilih cafe terlebih dahulu.');

            return;
        }

        $this->isProcessing = true;

        try {
            if ($this->backupType === 'full') {
                $this->createFullBackup();
            } else {
                $this->createTenantBackup((int) $this->selectedTenantId);
            }

            \App\Models\AuditLog::record('backup_create', 'Membuat backup: ' . $this->backupType, [
                'type' => $this->backupType,
                'tenant_id' => $this->backupType === 'tenant' ? (int) $this->selectedTenantId : null,
            ]);

            session()->flash('success', 'Backup berhasil dibuat!');
        } catch (\Throwable $e) {
            session()->flash('error', 'Gagal membuat backup: ' . $e->getMessage());
        } finally {
            $this->isProcessing = false;
            $this->loadBackupHistory();
        }
    }

    protected function createFullBackup(): void
    {
        $disk = Storage::disk('local');
        $disk->makeDirectory('backups');

        $driver = $this->getDbDriver();
        $timestamp = now()->format('Y-m-d_His');

        match ($driver) {
            'sqlite' => $this->createFullBackupSqlite($timestamp),
            'mysql' => $this->createFullBackupMysql($timestamp),
            default => throw new \RuntimeException("Database driver [{$driver}] tidak didukung untuk backup."),
        };
    }

    protected function createFullBackupSqlite(string $timestamp): void
    {
        $filename = "backup_full_{$timestamp}.sqlite";
        $sourcePath = database_path('database.sqlite');
        $destPath = storage_path("app/private/backups/{$filename}");

        if (! file_exists($sourcePath)) {
            throw new \RuntimeException('Database file SQLite tidak ditemukan.');
        }

        $source = new \SQLite3($sourcePath, SQLITE3_OPEN_READONLY);
        $dest = new \SQLite3($destPath);
        $source->backup($dest);
        $source->close();
        $dest->close();
    }

    protected function createFullBackupMysql(string $timestamp): void
    {
        $filename = "backup_full_{$timestamp}.sql";
        $destPath = storage_path("app/private/backups/{$filename}");

        $config = config('database.connections.mysql');
        $host = $config['host'];
        $port = $config['port'] ?? 3306;
        $database = $config['database'];
        $username = $config['username'];
        $password = $config['password'] ?? '';

        $command = sprintf(
            'mysqldump --host=%s --port=%s --user=%s %s %s > %s',
            escapeshellarg($host),
            escapeshellarg((string) $port),
            escapeshellarg($username),
            $password ? '--password=' . escapeshellarg($password) : '',
            escapeshellarg($database),
            escapeshellarg($destPath),
        );

        $result = Process::run($command);

        if (! $result->successful()) {
            throw new \RuntimeException('mysqldump gagal: ' . $result->errorOutput());
        }

        if (! file_exists($destPath) || filesize($destPath) === 0) {
            throw new \RuntimeException('File backup MySQL kosong atau tidak terbuat.');
        }
    }

    protected function createTenantBackup(int $tenantId): void
    {
        $tenant = Tenant::findOrFail($tenantId);
        $disk = Storage::disk('local');
        $disk->makeDirectory('backups');

        $timestamp = now()->format('Y-m-d_His');
        $filename = "backup_tenant_{$tenant->slug}_{$timestamp}.json";

        $data = [
            'metadata' => [
                'type' => 'tenant_backup',
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'tenant_slug' => $tenant->slug,
                'created_at' => now()->toIso8601String(),
                'app_version' => config('app.name'),
            ],
            'tenant' => $tenant->toArray(),
            'users' => DB::table('users')->where('tenant_id', $tenantId)->get()->toArray(),
            'categories' => DB::table('categories')->where('tenant_id', $tenantId)->get()->toArray(),
            'menu_items' => $this->getTenantMenuItems($tenantId),
            'menu_variants' => $this->getTenantMenuVariants($tenantId),
            'menu_modifiers' => DB::table('menu_modifiers')->where('tenant_id', $tenantId)->get()->toArray(),
            'menu_item_modifiers' => $this->getTenantMenuItemModifiers($tenantId),
            'tables' => DB::table('tables')->where('tenant_id', $tenantId)->get()->toArray(),
            'customers' => DB::table('customers')->where('tenant_id', $tenantId)->get()->toArray(),
            'orders' => $this->getTenantOrders($tenantId),
            'order_items' => $this->getTenantOrderItems($tenantId),
            'order_item_modifiers' => $this->getTenantOrderItemModifiers($tenantId),
            'payments' => DB::table('payments')->where('tenant_id', $tenantId)->get()->toArray(),
            'ingredients' => DB::table('ingredients')->where('tenant_id', $tenantId)->get()->toArray(),
            'recipes' => $this->getTenantRecipes($tenantId),
            'stock_movements' => DB::table('stock_movements')->where('tenant_id', $tenantId)->get()->toArray(),
            'subscriptions' => DB::table('subscriptions')->where('tenant_id', $tenantId)->get()->toArray(),
        ];

        $disk->put("backups/{$filename}", json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return array<int, object>
     */
    protected function getTenantMenuItems(int $tenantId): array
    {
        return DB::table('menu_items')->where('tenant_id', $tenantId)->get()->toArray();
    }

    /**
     * @return array<int, object>
     */
    protected function getTenantMenuVariants(int $tenantId): array
    {
        return DB::table('menu_variants')
            ->whereIn('menu_item_id', DB::table('menu_items')->where('tenant_id', $tenantId)->pluck('id'))
            ->get()->toArray();
    }

    /**
     * @return array<int, object>
     */
    protected function getTenantMenuItemModifiers(int $tenantId): array
    {
        return DB::table('menu_item_modifier')
            ->whereIn('menu_item_id', DB::table('menu_items')->where('tenant_id', $tenantId)->pluck('id'))
            ->get()->toArray();
    }

    /**
     * @return array<int, object>
     */
    protected function getTenantOrders(int $tenantId): array
    {
        return DB::table('orders')->where('tenant_id', $tenantId)->get()->toArray();
    }

    /**
     * @return array<int, object>
     */
    protected function getTenantOrderItems(int $tenantId): array
    {
        return DB::table('order_items')
            ->whereIn('order_id', DB::table('orders')->where('tenant_id', $tenantId)->pluck('id'))
            ->get()->toArray();
    }

    /**
     * @return array<int, object>
     */
    protected function getTenantOrderItemModifiers(int $tenantId): array
    {
        $orderIds = DB::table('orders')->where('tenant_id', $tenantId)->pluck('id');
        $orderItemIds = DB::table('order_items')->whereIn('order_id', $orderIds)->pluck('id');

        return DB::table('order_item_modifiers')->whereIn('order_item_id', $orderItemIds)->get()->toArray();
    }

    /**
     * @return array<int, object>
     */
    protected function getTenantRecipes(int $tenantId): array
    {
        return DB::table('recipes')
            ->whereIn('menu_item_id', DB::table('menu_items')->where('tenant_id', $tenantId)->pluck('id'))
            ->get()->toArray();
    }

    public function downloadBackup(string $path): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        if (! $this->isValidBackupPath($path)) {
            session()->flash('error', 'Path backup tidak valid.');

            return response()->streamDownload(fn () => null, 'error.txt');
        }

        $disk = Storage::disk('local');

        if (! $disk->exists($path)) {
            session()->flash('error', 'File backup tidak ditemukan.');

            return response()->streamDownload(fn () => null, 'error.txt');
        }

        return response()->streamDownload(function () use ($disk, $path) {
            echo $disk->get($path);
        }, basename($path));
    }

    public function deleteBackup(string $path): void
    {
        if (! $this->isValidBackupPath($path)) {
            session()->flash('error', 'Path backup tidak valid.');

            return;
        }

        $disk = Storage::disk('local');

        if ($disk->exists($path)) {
            \App\Models\AuditLog::record('backup_delete', 'Menghapus backup: ' . basename($path));

            $disk->delete($path);
            session()->flash('success', 'Backup berhasil dihapus.');
        } else {
            session()->flash('error', 'File backup tidak ditemukan.');
        }

        $this->loadBackupHistory();
    }

    /**
     * Validate that a backup path is within the backups directory (prevent path traversal).
     */
    protected function isValidBackupPath(string $path): bool
    {
        // Must start with 'backups/' and not contain path traversal
        if (! str_starts_with($path, 'backups/') || str_contains($path, '..') || str_contains($path, "\0")) {
            return false;
        }

        // Must be a direct child of backups/ (no subdirectories)
        return basename($path) === substr($path, strlen('backups/'));
    }

    /**
     * Open the restore confirmation modal from an existing server backup.
     */
    public function openRestoreModal(string $path): void
    {
        if (! $this->isValidBackupPath($path)) {
            session()->flash('error', 'Path backup tidak valid.');

            return;
        }

        $disk = Storage::disk('local');
        if (! $disk->exists($path)) {
            session()->flash('error', 'File backup tidak ditemukan.');

            return;
        }

        $this->resetRestoreState();
        $this->restorePath = $path;
        $this->restoreFilename = basename($path);
        $this->isUploadedFile = false;

        if (str_ends_with($path, '.sqlite') || str_ends_with($path, '.sql')) {
            $this->restoreType = 'full';
        } else {
            $this->restoreType = 'tenant';

            // Pre-parse tenant info from backup JSON metadata
            $content = $disk->get($path);
            $data = json_decode($content, true);
            if (isset($data['metadata']['tenant_slug'])) {
                $tenant = Tenant::where('slug', $data['metadata']['tenant_slug'])->first();
                $this->restoreTenantId = $tenant ? (string) $tenant->id : '';
            }
        }

        $this->showRestoreModal = true;
    }

    /**
     * Upload and prepare a local backup file for restore.
     */
    public function uploadAndRestore(): void
    {
        $this->validate([
            'uploadedBackupFile' => ['required', 'file', 'max:512000'], // 500MB max
        ], [
            'uploadedBackupFile.required' => 'Pilih file backup terlebih dahulu.',
            'uploadedBackupFile.max' => 'Ukuran file maksimal 500MB.',
        ]);

        $file = $this->uploadedBackupFile;
        $originalName = basename($file->getClientOriginalName()); // strip path traversal
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        $allowedExtensions = ['sqlite', 'sql', 'json'];
        if (! in_array($extension, $allowedExtensions, true)) {
            $this->addError('uploadedBackupFile', 'Format file harus .sqlite, .sql, atau .json');

            return;
        }

        // Validate file content matches extension
        if (! $this->validateBackupFileContent($file, $extension)) {
            return;
        }

        // Store with sanitized name — never trust original filename
        $disk = Storage::disk('local');
        $disk->makeDirectory('backups');

        $safeName = Str::slug(pathinfo($originalName, PATHINFO_FILENAME));
        $storedName = 'upload_' . now()->format('Y-m-d_His') . '_' . ($safeName ?: 'backup') . '.' . $extension;
        $storedPath = 'backups/' . $storedName;

        $disk->put($storedPath, file_get_contents($file->getRealPath()));

        // Open restore modal with the uploaded file
        $this->resetRestoreState();
        $this->restorePath = $storedPath;
        $this->restoreFilename = $originalName;
        $this->isUploadedFile = true;

        if (in_array($extension, ['sqlite', 'sql'], true)) {
            $this->restoreType = 'full';
        } else {
            $this->restoreType = 'tenant';

            // Parse tenant info from JSON
            $content = $disk->get($storedPath);
            $data = json_decode($content, true);

            if (! $data || ! isset($data['metadata']['type']) || $data['metadata']['type'] !== 'tenant_backup') {
                $disk->delete($storedPath);
                $this->addError('uploadedBackupFile', 'File JSON bukan backup per cafe yang valid.');

                return;
            }

            if (isset($data['metadata']['tenant_slug'])) {
                $tenant = Tenant::where('slug', $data['metadata']['tenant_slug'])->first();
                $this->restoreTenantId = $tenant ? (string) $tenant->id : '';
            }
        }

        $this->uploadedBackupFile = null;
        $this->showRestoreModal = true;
        $this->loadBackupHistory();
    }

    /**
     * Validate that the uploaded file content matches the expected format.
     */
    protected function validateBackupFileContent($file, string $extension): bool
    {
        $realPath = $file->getRealPath();

        if ($extension === 'json') {
            $content = file_get_contents($realPath);
            $data = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->addError('uploadedBackupFile', 'File bukan JSON yang valid.');

                return false;
            }

            if (! isset($data['metadata'])) {
                $this->addError('uploadedBackupFile', 'File JSON tidak memiliki metadata backup yang valid.');

                return false;
            }

            return true;
        }

        if ($extension === 'sqlite') {
            // SQLite files start with "SQLite format 3\000"
            $header = file_get_contents($realPath, false, null, 0, 16);
            if (! str_starts_with($header, 'SQLite format 3')) {
                $this->addError('uploadedBackupFile', 'File bukan database SQLite yang valid.');

                return false;
            }

            return true;
        }

        if ($extension === 'sql') {
            // SQL dumps are text files — check for common SQL keywords
            $header = file_get_contents($realPath, false, null, 0, 4096);
            if ($header === false || $header === '') {
                $this->addError('uploadedBackupFile', 'File SQL kosong atau tidak dapat dibaca.');

                return false;
            }

            $hasSqlContent = preg_match('/\b(CREATE|INSERT|DROP|ALTER|SET|USE|--)\b/i', $header);
            if (! $hasSqlContent) {
                $this->addError('uploadedBackupFile', 'File bukan SQL dump yang valid.');

                return false;
            }

            return true;
        }

        return false;
    }

    protected function resetRestoreState(): void
    {
        $this->restorePath = '';
        $this->restoreFilename = '';
        $this->restoreType = '';
        $this->restoreConfirmText = '';
        $this->restoreTenantId = '';
        $this->isUploadedFile = false;
        $this->resetErrorBag();
    }

    /**
     * Execute the restore process.
     */
    public function executeRestore(): void
    {
        if ($this->restoreConfirmText !== 'RESTORE') {
            $this->addError('restoreConfirmText', 'Ketik RESTORE untuk konfirmasi.');

            return;
        }

        if ($this->restoreType === 'tenant' && ! $this->restoreTenantId) {
            $this->addError('restoreTenantId', 'Pilih cafe tujuan restore terlebih dahulu.');

            return;
        }

        $disk = Storage::disk('local');
        if (! $disk->exists($this->restorePath)) {
            session()->flash('error', 'File backup tidak ditemukan.');
            $this->showRestoreModal = false;

            return;
        }

        $this->isProcessing = true;

        try {
            if ($this->restoreType === 'full') {
                $this->restoreFullBackup();
            } else {
                $this->restoreTenantBackup();
            }

            \App\Models\AuditLog::record('backup_restore', 'Restore dari: ' . $this->restoreFilename, [
                'type' => $this->restoreType,
                'file' => $this->restoreFilename,
                'tenant_id' => $this->restoreType === 'tenant' ? (int) $this->restoreTenantId : null,
            ]);

            $this->showRestoreModal = false;
            session()->flash('success', 'Restore berhasil! Data telah dipulihkan.');
        } catch (\Throwable $e) {
            session()->flash('error', 'Gagal restore: ' . $e->getMessage());
        } finally {
            $this->isProcessing = false;
        }
    }

    /**
     * Restore the full database from a backup file.
     * Creates an auto-backup of the current database before overwriting.
     */
    protected function restoreFullBackup(): void
    {
        $driver = $this->getDbDriver();

        // Auto-backup before restore
        $autoTimestamp = now()->format('Y-m-d_His');
        match ($driver) {
            'sqlite' => $this->createFullBackupSqlite("auto-before-restore_{$autoTimestamp}"),
            'mysql' => $this->createFullBackupMysql("auto-before-restore_{$autoTimestamp}"),
            default => throw new \RuntimeException("Database driver [{$driver}] tidak didukung untuk restore."),
        };

        $sourcePath = storage_path('app/private/' . $this->restorePath);

        if (! file_exists($sourcePath)) {
            throw new \RuntimeException('File backup tidak ditemukan.');
        }

        $extension = pathinfo($sourcePath, PATHINFO_EXTENSION);

        match ($extension) {
            'sqlite' => $this->restoreFullSqlite($sourcePath),
            'sql' => $this->restoreFullMysql($sourcePath),
            default => throw new \RuntimeException("Format backup [{$extension}] tidak didukung untuk restore."),
        };

        $this->loadBackupHistory();
    }

    protected function restoreFullSqlite(string $sourcePath): void
    {
        $dbPath = database_path('database.sqlite');

        DB::disconnect();

        $backupDb = new \SQLite3($sourcePath, SQLITE3_OPEN_READONLY);
        $targetDb = new \SQLite3($dbPath);
        $backupDb->backup($targetDb);
        $backupDb->close();
        $targetDb->close();

        DB::reconnect();
    }

    protected function restoreFullMysql(string $sourcePath): void
    {
        $config = config('database.connections.mysql');
        $host = $config['host'];
        $port = $config['port'] ?? 3306;
        $database = $config['database'];
        $username = $config['username'];
        $password = $config['password'] ?? '';

        $command = sprintf(
            'mysql --host=%s --port=%s --user=%s %s %s < %s',
            escapeshellarg($host),
            escapeshellarg((string) $port),
            escapeshellarg($username),
            $password ? '--password=' . escapeshellarg($password) : '',
            escapeshellarg($database),
            escapeshellarg($sourcePath),
        );

        $result = Process::run($command);

        if (! $result->successful()) {
            throw new \RuntimeException('mysql import gagal: ' . $result->errorOutput());
        }
    }

    /**
     * Restore a single tenant's data from a JSON backup.
     * Deletes existing tenant data first, then re-imports from the backup.
     */
    protected function restoreTenantBackup(): void
    {
        $disk = Storage::disk('local');
        $content = $disk->get($this->restorePath);
        $data = json_decode($content, true);

        if (! $data || ! isset($data['metadata']['type']) || $data['metadata']['type'] !== 'tenant_backup') {
            throw new \RuntimeException('File backup tidak valid atau bukan backup per cafe.');
        }

        $tenantSlug = $data['metadata']['tenant_slug'];

        // Find or determine target tenant
        $targetTenantId = $this->restoreTenantId
            ? (int) $this->restoreTenantId
            : Tenant::where('slug', $tenantSlug)->value('id');

        if (! $targetTenantId) {
            throw new \RuntimeException('Cafe target tidak ditemukan. Pilih cafe yang ada atau buat cafe baru terlebih dahulu.');
        }

        DB::transaction(function () use ($data, $targetTenantId) {
            // 1. Delete existing data for this tenant (reverse order of dependencies)
            $this->deleteTenantData($targetTenantId);

            // 2. Build ID mapping for re-import
            $tenantData = (array) $data['tenant'];

            // Update tenant record
            DB::table('tenants')->where('id', $targetTenantId)->update(
                collect($tenantData)->except(['id', 'created_at', 'updated_at'])->toArray()
            );

            // 3. Import users (re-map IDs)
            $userIdMap = $this->importRecords('users', $data['users'] ?? [], 'tenant_id', $targetTenantId);

            // 4. Import categories
            $categoryIdMap = $this->importRecords('categories', $data['categories'] ?? [], 'tenant_id', $targetTenantId);

            // 5. Import menu items (remap category_id)
            $menuItemIdMap = $this->importWithRemap('menu_items', $data['menu_items'] ?? [], [
                'tenant_id' => $targetTenantId,
                'category_id' => $categoryIdMap,
            ]);

            // 6. Import menu variants (remap menu_item_id)
            $menuVariantIdMap = $this->importWithRemap('menu_variants', $data['menu_variants'] ?? [], [
                'menu_item_id' => $menuItemIdMap,
            ]);

            // 7. Import menu modifiers
            $menuModifierIdMap = $this->importRecords('menu_modifiers', $data['menu_modifiers'] ?? [], 'tenant_id', $targetTenantId);

            // 8. Import menu_item_modifier pivot
            foreach ($data['menu_item_modifiers'] ?? [] as $row) {
                $row = (array) $row;
                $newMenuItemId = $menuItemIdMap[$row['menu_item_id']] ?? null;
                $newModifierId = $menuModifierIdMap[$row['menu_modifier_id']] ?? null;
                if ($newMenuItemId && $newModifierId) {
                    DB::table('menu_item_modifier')->insert([
                        'menu_item_id' => $newMenuItemId,
                        'menu_modifier_id' => $newModifierId,
                    ]);
                }
            }

            // 9. Import tables
            $tableIdMap = $this->importRecords('tables', $data['tables'] ?? [], 'tenant_id', $targetTenantId);

            // 10. Import customers
            $customerIdMap = $this->importRecords('customers', $data['customers'] ?? [], 'tenant_id', $targetTenantId);

            // 11. Import orders (remap table_id, customer_id, user_id)
            $orderIdMap = [];
            foreach ($data['orders'] ?? [] as $row) {
                $row = (array) $row;
                $oldId = $row['id'];
                unset($row['id']);

                $row['tenant_id'] = $targetTenantId;
                $row['table_id'] = isset($row['table_id']) ? ($tableIdMap[$row['table_id']] ?? null) : null;
                $row['customer_id'] = isset($row['customer_id']) ? ($customerIdMap[$row['customer_id']] ?? null) : null;
                $row['user_id'] = isset($row['user_id']) ? ($userIdMap[$row['user_id']] ?? null) : null;

                $newId = DB::table('orders')->insertGetId($row);
                $orderIdMap[$oldId] = $newId;
            }

            // 12. Import order items (remap order_id, menu_item_id, menu_variant_id)
            $orderItemIdMap = [];
            foreach ($data['order_items'] ?? [] as $row) {
                $row = (array) $row;
                $oldId = $row['id'];
                unset($row['id']);

                $row['order_id'] = $orderIdMap[$row['order_id']] ?? null;
                $row['menu_item_id'] = $menuItemIdMap[$row['menu_item_id']] ?? $row['menu_item_id'];
                $row['menu_variant_id'] = isset($row['menu_variant_id']) ? ($menuVariantIdMap[$row['menu_variant_id']] ?? null) : null;

                if ($row['order_id']) {
                    $newId = DB::table('order_items')->insertGetId($row);
                    $orderItemIdMap[$oldId] = $newId;
                }
            }

            // 13. Import order item modifiers (remap order_item_id, menu_modifier_id)
            foreach ($data['order_item_modifiers'] ?? [] as $row) {
                $row = (array) $row;
                unset($row['id']);

                $row['order_item_id'] = $orderItemIdMap[$row['order_item_id']] ?? null;
                $row['menu_modifier_id'] = $menuModifierIdMap[$row['menu_modifier_id']] ?? $row['menu_modifier_id'];

                if ($row['order_item_id']) {
                    DB::table('order_item_modifiers')->insert($row);
                }
            }

            // 14. Import payments (remap order_id, received_by)
            foreach ($data['payments'] ?? [] as $row) {
                $row = (array) $row;
                unset($row['id']);

                $row['tenant_id'] = $targetTenantId;
                $row['order_id'] = $orderIdMap[$row['order_id']] ?? null;
                $row['received_by'] = isset($row['received_by']) ? ($userIdMap[$row['received_by']] ?? null) : null;

                if ($row['order_id']) {
                    DB::table('payments')->insert($row);
                }
            }

            // 15. Import ingredients
            $ingredientIdMap = $this->importRecords('ingredients', $data['ingredients'] ?? [], 'tenant_id', $targetTenantId);

            // 16. Import recipes (remap menu_item_id, ingredient_id)
            foreach ($data['recipes'] ?? [] as $row) {
                $row = (array) $row;
                unset($row['id']);

                $row['menu_item_id'] = $menuItemIdMap[$row['menu_item_id']] ?? null;
                $row['ingredient_id'] = $ingredientIdMap[$row['ingredient_id']] ?? null;

                if ($row['menu_item_id'] && $row['ingredient_id']) {
                    DB::table('recipes')->insert($row);
                }
            }

            // 17. Import stock movements (remap ingredient_id, user_id)
            foreach ($data['stock_movements'] ?? [] as $row) {
                $row = (array) $row;
                unset($row['id']);

                $row['tenant_id'] = $targetTenantId;
                $row['ingredient_id'] = $ingredientIdMap[$row['ingredient_id']] ?? null;
                $row['user_id'] = isset($row['user_id']) ? ($userIdMap[$row['user_id']] ?? null) : null;

                if ($row['ingredient_id']) {
                    DB::table('stock_movements')->insert($row);
                }
            }

            // 18. Import subscriptions
            foreach ($data['subscriptions'] ?? [] as $row) {
                $row = (array) $row;
                unset($row['id']);
                $row['tenant_id'] = $targetTenantId;
                DB::table('subscriptions')->insert($row);
            }
        });
    }

    /**
     * Delete all data belonging to a tenant.
     */
    protected function deleteTenantData(int $tenantId): void
    {
        // Order matters due to foreign key constraints
        $orderIds = DB::table('orders')->where('tenant_id', $tenantId)->pluck('id');
        $orderItemIds = DB::table('order_items')->whereIn('order_id', $orderIds)->pluck('id');

        DB::table('order_item_modifiers')->whereIn('order_item_id', $orderItemIds)->delete();
        DB::table('order_items')->whereIn('order_id', $orderIds)->delete();
        DB::table('payments')->where('tenant_id', $tenantId)->delete();
        DB::table('orders')->where('tenant_id', $tenantId)->delete();

        DB::table('stock_movements')->where('tenant_id', $tenantId)->delete();

        $menuItemIds = DB::table('menu_items')->where('tenant_id', $tenantId)->pluck('id');
        DB::table('recipes')->whereIn('menu_item_id', $menuItemIds)->delete();
        DB::table('menu_item_modifier')->whereIn('menu_item_id', $menuItemIds)->delete();
        DB::table('menu_variants')->whereIn('menu_item_id', $menuItemIds)->delete();
        DB::table('menu_items')->where('tenant_id', $tenantId)->delete();

        DB::table('menu_modifiers')->where('tenant_id', $tenantId)->delete();
        DB::table('ingredients')->where('tenant_id', $tenantId)->delete();
        DB::table('categories')->where('tenant_id', $tenantId)->delete();
        DB::table('tables')->where('tenant_id', $tenantId)->delete();
        DB::table('customers')->where('tenant_id', $tenantId)->delete();
        DB::table('subscriptions')->where('tenant_id', $tenantId)->delete();
        DB::table('users')->where('tenant_id', $tenantId)->delete();
    }

    /**
     * Import records into a table and return an ID mapping (old => new).
     *
     * @return array<int, int>
     */
    protected function importRecords(string $table, array $records, string $tenantField, int $tenantId): array
    {
        $idMap = [];

        foreach ($records as $row) {
            $row = (array) $row;
            $oldId = $row['id'];
            unset($row['id']);
            $row[$tenantField] = $tenantId;

            $newId = DB::table($table)->insertGetId($row);
            $idMap[$oldId] = $newId;
        }

        return $idMap;
    }

    /**
     * Import records with multiple field remappings.
     *
     * @param  array<string, int|array<int, int>>  $remaps  Key = field, Value = new value or ID map
     * @return array<int, int>
     */
    protected function importWithRemap(string $table, array $records, array $remaps): array
    {
        $idMap = [];

        foreach ($records as $row) {
            $row = (array) $row;
            $oldId = $row['id'];
            unset($row['id']);

            foreach ($remaps as $field => $value) {
                if (is_array($value)) {
                    // It's an ID map
                    $row[$field] = $value[$row[$field]] ?? $row[$field];
                } else {
                    // It's a direct value
                    $row[$field] = $value;
                }
            }

            $newId = DB::table($table)->insertGetId($row);
            $idMap[$oldId] = $newId;
        }

        return $idMap;
    }

    /**
     * Get the current database driver name.
     */
    protected function getDbDriver(): string
    {
        return config('database.connections.' . config('database.default') . '.driver', 'sqlite');
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        }
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' B';
    }
}; ?>

<div>
    @if (session('success'))
        <div class="mb-4">
            <flux:callout variant="success" icon="check-circle" dismissible>
                {{ session('success') }}
            </flux:callout>
        </div>
    @endif

    @if (session('error'))
        <div class="mb-4">
            <flux:callout variant="danger" icon="exclamation-triangle" dismissible>
                {{ session('error') }}
            </flux:callout>
        </div>
    @endif

    {{-- Create Backup Section --}}
    <div class="mb-6 rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
        <div class="mb-4 flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-100 dark:bg-indigo-900/30">
                <flux:icon.circle-stack class="size-5 text-indigo-600 dark:text-indigo-400" />
            </div>
            <div>
                <flux:heading size="lg">{{ __('Buat Backup') }}</flux:heading>
                <flux:text size="sm" class="text-zinc-500">
                    {{ __('Buat backup database untuk keamanan data.') }}
                    <flux:badge size="sm" class="ml-1" inset="top bottom">{{ strtoupper(config('database.connections.' . config('database.default') . '.driver', 'sqlite')) }}</flux:badge>
                </flux:text>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            {{-- Backup Type Selection --}}
            <div>
                <flux:select wire:model.live="backupType" :label="__('Tipe Backup')">
                    <option value="full">{{ __('Seluruh Database') }}</option>
                    <option value="tenant">{{ __('Per Cafe') }}</option>
                </flux:select>
            </div>

            {{-- Tenant Selection (shown when per-tenant) --}}
            <div>
                @if ($backupType === 'tenant')
                    <flux:select wire:model="selectedTenantId" :label="__('Pilih Cafe')">
                        <option value="">{{ __('-- Pilih Cafe --') }}</option>
                        @foreach ($this->tenants as $tenant)
                            <option value="{{ $tenant->id }}">{{ $tenant->name }}</option>
                        @endforeach
                    </flux:select>
                    @error('selectedTenantId')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                @else
                    <div class="flex h-full items-end">
                        <flux:text size="sm" class="rounded-lg bg-zinc-50 p-3 text-zinc-500 dark:bg-zinc-800">
                            <flux:icon.information-circle class="mr-1 inline size-4" />
                            {{ __('Backup seluruh database akan menyalin semua data termasuk semua cafe, pengguna, dan pengaturan.') }}
                        </flux:text>
                    </div>
                @endif
            </div>
        </div>

        <div class="mt-4">
            <flux:button
                variant="primary"
                wire:click="createBackup"
                wire:loading.attr="disabled"
                :disabled="$isProcessing"
            >
                <span wire:loading.remove wire:target="createBackup">
                    <flux:icon.arrow-down-tray class="mr-1 inline size-4" />
                    {{ __('Buat Backup Sekarang') }}
                </span>
                <span wire:loading wire:target="createBackup">
                    <flux:icon.arrow-path class="mr-1 inline size-4 animate-spin" />
                    {{ __('Memproses...') }}
                </span>
            </flux:button>
        </div>
    </div>

    {{-- Upload & Restore from Local File --}}
    <div class="mb-6 rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
        <div class="mb-4 flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-100 dark:bg-amber-900/30">
                <flux:icon.arrow-up-tray class="size-5 text-amber-600 dark:text-amber-400" />
            </div>
            <div>
                <flux:heading size="lg">{{ __('Restore dari File Lokal') }}</flux:heading>
                <flux:text size="sm" class="text-zinc-500">{{ __('Upload file backup (.sqlite atau .json) dari komputer Anda.') }}</flux:text>
            </div>
        </div>

        <div class="flex items-end gap-4">
            <div class="flex-1">
                <label class="mb-1.5 block text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('File Backup') }}</label>
                <input
                    type="file"
                    wire:model="uploadedBackupFile"
                    accept=".sqlite,.sql,.json"
                    class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-700 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-300 dark:file:bg-indigo-900/30 dark:file:text-indigo-400"
                />
                @error('uploadedBackupFile')
                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>
            <flux:button
                variant="primary"
                wire:click="uploadAndRestore"
                wire:loading.attr="disabled"
            >
                <span wire:loading.remove wire:target="uploadAndRestore,uploadedBackupFile">
                    <flux:icon.arrow-up-tray class="mr-1 inline size-4" />
                    {{ __('Upload & Restore') }}
                </span>
                <span wire:loading wire:target="uploadAndRestore,uploadedBackupFile">
                    <flux:icon.arrow-path class="mr-1 inline size-4 animate-spin" />
                    {{ __('Mengupload...') }}
                </span>
            </flux:button>
        </div>

        <flux:text size="sm" class="mt-3 text-zinc-400">
            <flux:icon.information-circle class="mr-1 inline size-4" />
            {{ __('File .sqlite/.sql untuk restore seluruh database, file .json untuk restore per cafe. Maks 500MB.') }}
        </flux:text>
    </div>

    {{-- Backup History --}}
    <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
        <div class="mb-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-100 dark:bg-emerald-900/30">
                    <flux:icon.clock class="size-5 text-emerald-600 dark:text-emerald-400" />
                </div>
                <div>
                    <flux:heading size="lg">{{ __('Riwayat Backup') }}</flux:heading>
                    <flux:text size="sm" class="text-zinc-500">{{ count($backupHistory) }} {{ __('backup tersimpan') }}</flux:text>
                </div>
            </div>
            <flux:button size="sm" wire:click="loadBackupHistory">
                <flux:icon.arrow-path class="size-4" />
            </flux:button>
        </div>

        @if (count($backupHistory) > 0)
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('File') }}</flux:table.column>
                    <flux:table.column>{{ __('Tipe') }}</flux:table.column>
                    <flux:table.column>{{ __('Ukuran') }}</flux:table.column>
                    <flux:table.column>{{ __('Tanggal') }}</flux:table.column>
                    <flux:table.column class="text-right">{{ __('Aksi') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($backupHistory as $backup)
                        <flux:table.row wire:key="backup-{{ $backup['filename'] }}">
                            <flux:table.cell>
                                <div class="flex items-center gap-2">
                                    @if (str_ends_with($backup['filename'], '.sqlite'))
                                        <flux:icon.circle-stack class="size-4 text-indigo-500" />
                                    @elseif (str_ends_with($backup['filename'], '.sql'))
                                        <flux:icon.server-stack class="size-4 text-blue-500" />
                                    @else
                                        <flux:icon.document-text class="size-4 text-amber-500" />
                                    @endif
                                    <span class="max-w-[200px] truncate text-sm font-medium" title="{{ $backup['filename'] }}">{{ $backup['filename'] }}</span>
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :variant="str_contains($backup['type'], 'Cafe') ? 'warning' : 'primary'">
                                    {{ $backup['type'] }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                <span class="text-sm text-zinc-500">{{ $backup['size'] }}</span>
                            </flux:table.cell>
                            <flux:table.cell>
                                <span class="text-sm text-zinc-500">{{ $backup['date'] }}</span>
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="flex items-center justify-end gap-1">
                                    <flux:button size="sm" variant="filled" wire:click="openRestoreModal('{{ $backup['path'] }}')" title="{{ __('Restore') }}">
                                        <flux:icon.arrow-uturn-left class="size-4" />
                                    </flux:button>
                                    <flux:button size="sm" wire:click="downloadBackup('{{ $backup['path'] }}')" title="{{ __('Download') }}">
                                        <flux:icon.arrow-down-tray class="size-4" />
                                    </flux:button>
                                    <flux:button size="sm" variant="danger" wire:click="deleteBackup('{{ $backup['path'] }}')" wire:confirm="{{ __('Yakin ingin menghapus backup ini?') }}" title="{{ __('Hapus') }}">
                                        <flux:icon.trash class="size-4" />
                                    </flux:button>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @else
            <div class="flex flex-col items-center justify-center py-12 text-zinc-400">
                <flux:icon.inbox class="size-12" />
                <flux:text class="mt-2">{{ __('Belum ada backup yang dibuat.') }}</flux:text>
            </div>
        @endif
    </div>

    {{-- Restore Confirmation Modal --}}
    <flux:modal wire:model="showRestoreModal" class="max-w-lg">
        <div class="space-y-4">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/30">
                    <flux:icon.exclamation-triangle class="size-5 text-amber-600 dark:text-amber-400" />
                </div>
                <flux:heading size="lg">{{ __('Konfirmasi Restore') }}</flux:heading>
            </div>

            <flux:callout variant="warning" icon="exclamation-triangle">
                @if ($restoreType === 'full')
                    {{ __('PERHATIAN: Restore seluruh database akan menggantikan SEMUA data saat ini. Backup otomatis akan dibuat sebelum proses restore.') }}
                @else
                    {{ __('PERHATIAN: Restore per cafe akan menghapus semua data cafe tujuan dan menggantinya dengan data dari backup. Pastikan Anda memilih cafe yang benar.') }}
                @endif
            </flux:callout>

            <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800">
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-zinc-500">{{ __('File') }}</span>
                        <span class="font-medium">{{ $restoreFilename }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-zinc-500">{{ __('Tipe') }}</span>
                        <flux:badge size="sm" :variant="$restoreType === 'full' ? 'primary' : 'warning'">
                            {{ $restoreType === 'full' ? __('Seluruh Database') : __('Per Cafe') }}
                        </flux:badge>
                    </div>
                </div>
            </div>

            @if ($restoreType === 'tenant')
                <div>
                    <flux:select wire:model="restoreTenantId" :label="__('Cafe Tujuan Restore')">
                        <option value="">{{ __('-- Pilih Cafe Tujuan --') }}</option>
                        @foreach ($this->tenants as $tenant)
                            <option value="{{ $tenant->id }}">{{ $tenant->name }}</option>
                        @endforeach
                    </flux:select>
                    @error('restoreTenantId')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>
                <flux:text size="sm" class="text-zinc-500">
                    <flux:icon.information-circle class="mr-1 inline size-4" />
                    {{ __('Data cafe tujuan akan dihapus dan diganti dengan data dari backup.') }}
                </flux:text>
            @endif

            <div>
                <flux:input
                    wire:model.live="restoreConfirmText"
                    :label="__('Ketik RESTORE untuk konfirmasi')"
                    :placeholder="__('Ketik RESTORE')"
                />
                @error('restoreConfirmText')
                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex gap-2 pt-2">
                <flux:button wire:click="$set('showRestoreModal', false)" class="flex-1">
                    {{ __('Batal') }}
                </flux:button>
                <flux:button
                    variant="primary"
                    wire:click="executeRestore"
                    wire:loading.attr="disabled"
                    class="flex-1"
                    :disabled="$restoreConfirmText !== 'RESTORE'"
                >
                    <span wire:loading.remove wire:target="executeRestore">
                        <flux:icon.arrow-uturn-left class="mr-1 inline size-4" />
                        {{ __('Restore Sekarang') }}
                    </span>
                    <span wire:loading wire:target="executeRestore">
                        <flux:icon.arrow-path class="mr-1 inline size-4 animate-spin" />
                        {{ __('Memproses...') }}
                    </span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
