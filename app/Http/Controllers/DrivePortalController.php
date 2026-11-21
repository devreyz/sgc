<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\TenantCloudStorageConnection;
use App\Models\TenantUser;
use App\Services\DrivePortalAccess;
use App\Services\DrivePortalFiles;
use App\Services\GoogleDriveClientFactory;
use App\Services\TenantGoogleDriveService;
use Google\Service\Exception as DriveException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class DrivePortalController extends Controller
{
    private const ID = 'regex:/\A[A-Za-z0-9_-]+\z/';
    private const NAME = 'regex:~\A[^\x00-\x1f\x7f/\\\\]+\z~u';

    public function __construct(
        private readonly DrivePortalAccess $access,
        private readonly GoogleDriveClientFactory $clients,
        private readonly TenantGoogleDriveService $storage,
    ) {}

    public function index(Request $request, Tenant $tenant)
    {
        $level = $this->authorizeAccess($request, $tenant);
        $connection = $this->connection($tenant);

        return response()->view('drive.index', [
            'tenant' => $tenant,
            'canManage' => $level === 'manage',
            'connected' => (bool) $connection?->root_folder_id,
            'account' => $connection?->google_account_email,
            'canConnect' => ! $connection?->root_folder_id && TenantUser::query()->where('tenant_id', $tenant->id)
                ->where('user_id', $request->user()->id)->where('status', true)->where('is_admin', true)->exists(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function files(Request $request, Tenant $tenant)
    {
        $this->authorizeAccess($request, $tenant);
        $data = $request->validate([
            'folder' => ['nullable', 'string', 'max:200', self::ID], 'trash' => 'nullable|boolean',
            'search' => 'nullable|string|max:120', 'page' => 'nullable|string|max:2048',
        ]);

        return $this->withDrive($tenant, fn (DrivePortalFiles $files) => $this->json($files->listing(
            $data['folder'] ?? null, (bool) ($data['trash'] ?? false), $data['search'] ?? null, $data['page'] ?? null,
        )));
    }

    public function store(Request $request, Tenant $tenant)
    {
        $this->authorizeAccess($request, $tenant, true);
        $data = $request->validate([
            'kind' => 'required|in:folder,file', 'folder' => ['nullable', 'string', 'max:200', self::ID],
            'name' => ['exclude_unless:kind,folder', 'required', 'string', 'max:180', 'not_in:.,..', self::NAME],
            'file' => 'exclude_unless:kind,file|required|file|max:20480',
        ]);

        return $this->withDrive($tenant, fn (DrivePortalFiles $files) => $this->json(
            $data['kind'] === 'folder'
                ? $files->createFolder($data['folder'] ?? null, $data['name'])
                : $files->upload($data['folder'] ?? null, $data['file']),
            201
        ), true);
    }

    public function update(Request $request, Tenant $tenant, string $file)
    {
        $this->authorizeAccess($request, $tenant, true);
        $data = $request->validate([
            'action' => 'required|in:rename,move,trash,restore',
            'name' => ['exclude_unless:action,rename', 'required', 'string', 'max:180', 'not_in:.,..', self::NAME],
            'destination' => ['exclude_unless:action,move', 'nullable', 'string', 'max:200', self::ID],
        ]);

        return $this->withDrive($tenant, fn (DrivePortalFiles $files) => $this->json($files->change($file, $data)), true);
    }

    public function download(Request $request, Tenant $tenant, string $file)
    {
        $this->authorizeAccess($request, $tenant);

        return $this->withDrive($tenant, function (DrivePortalFiles $files) use ($request, $file) {
            $data = $files->download($file);
            $stream = $data['stream'];
            $name = $data['name'];
            $fallback = preg_replace('~[^\x20-\x7e]|[%/\\\\]~', '-', Str::ascii($name)) ?: 'arquivo';
            $disposition = $request->boolean('inline') && $data['mime'] !== 'application/octet-stream' ? 'inline' : 'attachment';

            return response()->stream(function () use ($stream) {
                try {
                    while (! $stream->eof()) {
                        echo $stream->read(65536);
                    }
                } finally {
                    $stream->close();
                }
            }, 200, [
                'Content-Type' => $data['mime'],
                'Content-Disposition' => HeaderUtils::makeDisposition($disposition, $name, $fallback),
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
                'Content-Security-Policy' => "sandbox; default-src 'none'",
                'X-Frame-Options' => 'SAMEORIGIN',
            ] + ($data['size'] > 0 ? ['X-SGC-Content-Length' => (string) $data['size']] : []));
        });
    }

    private function authorizeAccess(Request $request, Tenant $tenant, bool $write = false): string
    {
        $level = $this->access->level($request->user(), $tenant);
        abort_unless($level && (! $write || $level === 'manage'), 403);

        return $level;
    }

    private function connection(Tenant $tenant, bool $lock = false): ?TenantCloudStorageConnection
    {
        return TenantCloudStorageConnection::query()->where('tenant_id', $tenant->id)->where('provider', 'google_drive')
            ->where('status', 'active')->when($lock, fn ($query) => $query->lockForUpdate())->first();
    }

    private function withDrive(Tenant $tenant, callable $operation, bool $write = false)
    {
        $run = function () use ($tenant, $operation, $write) {
            $connection = $this->connection($tenant, $write);
            abort_unless($connection?->root_folder_id, 409, 'Conecte o Google Drive da organização.');
            $drive = $this->clients->forPortal($connection);
            $root = $this->storage->ownedDestination($drive, $connection->root_folder_id, $tenant);
            abort_unless($root?->getId() === $connection->root_folder_id, 409, 'Reconecte o Google Drive para liberar a pasta da organização.');

            return $operation(new DrivePortalFiles($drive, $root, $tenant->id));
        };

        try {
            return $write ? DB::transaction($run) : $run();
        } catch (HttpExceptionInterface $exception) {
            throw $exception;
        } catch (DriveException $exception) {
            $status = (int) $exception->getCode();
            abort($status === 429 ? 429 : (in_array($status, [403, 404], true) ? $status : 502), match ($status) {
                403 => 'O Google Drive recusou esta operação. Confira o acesso ao item.',
                404 => 'O item não está disponível nesta pasta.',
                429 => 'O Drive está ocupado. Tente novamente em instantes.',
                default => 'O Drive não respondeu à operação. Tente novamente.',
            });
        } catch (RuntimeException) {
            abort(502, 'A comunicação com o Drive falhou. Tente novamente ou reconecte a conta.');
        }
    }

    private function json(array $data, int $status = 200)
    {
        return response()->json($data, $status)->header('Cache-Control', 'private, no-store');
    }
}
