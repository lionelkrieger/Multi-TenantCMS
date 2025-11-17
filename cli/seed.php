<?php

declare(strict_types=1);

use App\Models\User;
use App\Repositories\OrganizationRepository;
use App\Repositories\PropertyRepository;
use App\Repositories\UserRepository;
use App\Services\OrganizationService;
use App\Services\PropertyService;

require __DIR__ . '/../app/includes/bootstrap.php';

$connection = Database::connection();

$userRepository = new UserRepository($connection);
$organizationRepository = new OrganizationRepository($connection);
$propertyRepository = new PropertyRepository($connection);
$organizationService = new OrganizationService($organizationRepository, $userRepository);
$propertyService = new PropertyService($propertyRepository, $organizationRepository);

$masterEmail = 'master@demo.local';
$masterUser = $userRepository->findByEmail($masterEmail);

if ($masterUser === null) {
    $masterUser = new User(
        generate_uuid_v4(),
        $masterEmail,
        \Auth::hashPassword('Password!123'),
        'Demo Master',
        null,
        'master_admin',
        'active',
        date('Y-m-d H:i:s')
    );
    $userRepository->create($masterUser);
    echo "Created master admin: {$masterEmail}" . PHP_EOL;
} else {
    echo "Master admin already exists: {$masterEmail}" . PHP_EOL;
}

$demoOrg = null;
$organizations = $organizationRepository->listAll(1, 0, 'Demo Estates');
foreach ($organizations as $organization) {
    if (strtolower($organization->name) === 'demo estates') {
        $demoOrg = $organization;
        break;
    }
}

if ($demoOrg === null) {
    $demoOrg = $organizationService->createOrganization('Demo Estates', $masterUser->id, [
        'primary_color' => '#0052CC',
        'secondary_color' => '#F4F6FA',
        'accent_color' => '#FF5630',
        'custom_domain' => null,
    ]);
    echo "Created organization: Demo Estates" . PHP_EOL;
} else {
    echo "Organization already exists: Demo Estates" . PHP_EOL;
}

$orgAdminEmail = 'owner@demo.local';
$orgAdmin = $userRepository->findByEmail($orgAdminEmail);
if ($orgAdmin === null) {
    $orgAdmin = new User(
        generate_uuid_v4(),
        $orgAdminEmail,
        \Auth::hashPassword('Password!123'),
        'Demo Owner',
        $demoOrg->id,
        'org_admin',
        'active',
        date('Y-m-d H:i:s')
    );
    $userRepository->create($orgAdmin);
    echo "Created organization admin: {$orgAdminEmail}" . PHP_EOL;
} else {
    echo "Organization admin already exists: {$orgAdminEmail}" . PHP_EOL;
}

$properties = $propertyRepository->listByOrganization($demoOrg->id, 1, 0);
if ($properties === []) {
    $propertyService->create(
        $demoOrg->id,
        'Downtown Loft',
        'Modern loft apartment with skyline views.',
        '101 Market Street, Springfield'
    );
    $propertyService->create(
        $demoOrg->id,
        'Coastal Retreat',
        'Beachfront property perfect for vacations.',
        '500 Ocean Drive, Springfield'
    );
    echo "Created demo properties." . PHP_EOL;
} else {
    echo "Properties already exist for Demo Estates; skipping property seed." . PHP_EOL;
}

echo "Seeding complete." . PHP_EOL;
