<?php
declare(strict_types=1);

require __DIR__ . '/../app/includes/bootstrap.php';

use App\Repositories\OrganizationRepository;
use finfo;

$organizationId = isset($_GET['org']) ? trim((string) $_GET['org']) : '';
if ($organizationId === '') {
	http_response_code(404);
	exit('Not found');
}

$connection = Database::connection();
$repository = new OrganizationRepository($connection);
$organization = $repository->findById($organizationId);

if ($organization === null || empty($organization->logoUrl)) {
	http_response_code(404);
	exit('Not found');
}

$logoPath = storage_path($organization->logoUrl);
if ($logoPath === '' || !is_file($logoPath)) {
	http_response_code(404);
	exit('Not found');
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($logoPath) ?: 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($logoPath));
header('Cache-Control: public, max-age=3600');
readfile($logoPath);
exit;
