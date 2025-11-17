<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Organization;
use App\Models\Property;
use App\Services\PropertyService;
use App\Support\RequestValidator;
use Throwable;

final class PropertyController extends Controller
{
    public function __construct(private readonly PropertyService $properties)
    {
    }

    public function list(string $organizationId, array $query = []): void
    {
        $this->ensureAuthenticated();

        $organization = $this->resolveOrganization($organizationId);
        if ($organization === null) {
            return;
        }

        $properties = $this->properties->list($organization->id);

        $this->render('org/properties', [
            'organization' => $organization,
            'properties' => $properties,
            'flash' => $this->buildFlashMessages($query),
            'csrfToken' => \CSRF::token(),
        ]);
    }

    public function create(string $organizationId, array $input = []): void
    {
        $this->ensureAuthenticated();

        $organization = $this->resolveOrganization($organizationId);
        if ($organization === null) {
            return;
        }

        $values = $this->buildFormValues($input);
        $errors = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $errors = $this->validateCsrf($input);
            if ($errors === []) {
                [$payload, $fieldErrors] = $this->validatePropertyInput($input);
                $errors = $fieldErrors;

                if ($errors === []) {
                    try {
                        $this->properties->create(
                            $organization->id,
                            $payload['name'],
                            $payload['description'],
                            $payload['address']
                        );

                        $this->redirect('/org/properties.php?id=' . urlencode($organization->id) . '&created=1');
                        return;
                    } catch (Throwable $throwable) {
                        logger('Property creation failed.', [
                            'organization_id' => $organization->id,
                            'error' => $throwable->getMessage(),
                        ]);
                        $errors['general'] = 'Unable to create the property right now. Please try again.';
                    }
                }
            }

            $values = $this->buildFormValues($input);
        }

        $this->render('org/property/form', [
            'organization' => $organization,
            'mode' => 'create',
            'form' => [
                'values' => $values,
                'errors' => $errors,
            ],
            'csrfToken' => \CSRF::token(),
            'formAction' => '/org/property/create.php?id=' . urlencode($organization->id),
            'submitLabel' => 'Create property',
        ]);
    }

    public function edit(string $organizationId, string $propertyId, array $input = []): void
    {
        $this->ensureAuthenticated();

        $organization = $this->resolveOrganization($organizationId);
        if ($organization === null) {
            return;
        }

        $property = $this->resolveProperty($organization, $propertyId);
        if ($property === null) {
            return;
        }

        $values = $this->buildFormValues($input, $property);
        $errors = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $errors = $this->validateCsrf($input);
            if ($errors === []) {
                [$payload, $fieldErrors] = $this->validatePropertyInput($input);
                $errors = $fieldErrors;

                if ($errors === []) {
                    try {
                        $this->properties->update($organization->id, $property->id, $payload);
                        $this->redirect('/org/properties.php?id=' . urlencode($organization->id) . '&updated=1');
                        return;
                    } catch (Throwable $throwable) {
                        logger('Property update failed.', [
                            'organization_id' => $organization->id,
                            'property_id' => $property->id,
                            'error' => $throwable->getMessage(),
                        ]);
                        $errors['general'] = 'Unable to update this property right now. Please try again.';
                    }
                }
            }

            $values = $this->buildFormValues($input, $property);
        }

        $this->render('org/property/form', [
            'organization' => $organization,
            'mode' => 'edit',
            'property' => $property,
            'form' => [
                'values' => $values,
                'errors' => $errors,
            ],
            'csrfToken' => \CSRF::token(),
            'formAction' => '/org/property/edit.php?id=' . urlencode($organization->id) . '&property=' . urlencode($property->id),
            'submitLabel' => 'Save changes',
        ]);
    }

    public function delete(string $organizationId, array $input = []): void
    {
        $this->ensureAuthenticated();

        $organization = $this->resolveOrganization($organizationId);
        if ($organization === null) {
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/org/properties.php?id=' . urlencode($organization->id));
        }

        if (!\CSRF::validate($input['csrf_token'] ?? '')) {
            $this->redirect('/org/properties.php?id=' . urlencode($organization->id) . '&error=invalid_csrf');
        }

        $propertyId = isset($input['property_id']) ? trim((string) $input['property_id']) : '';
        if ($propertyId === '') {
            $this->redirect('/org/properties.php?id=' . urlencode($organization->id) . '&error=missing_property');
        }

        $property = $this->properties->findById($organization->id, $propertyId);
        if ($property === null) {
            $this->redirect('/org/properties.php?id=' . urlencode($organization->id) . '&error=not_found');
        }

        try {
            $this->properties->delete($organization->id, $property->id);
            $this->redirect('/org/properties.php?id=' . urlencode($organization->id) . '&deleted=1');
        } catch (Throwable $throwable) {
            logger('Property delete failed.', [
                'organization_id' => $organization->id,
                'property_id' => $property->id,
                'error' => $throwable->getMessage(),
            ]);
            $this->redirect('/org/properties.php?id=' . urlencode($organization->id) . '&error=server');
        }
    }

    private function ensureAuthenticated(): void
    {
        if (!\Auth::check()) {
            $this->redirect('/login.php');
        }
    }

    private function resolveOrganization(string $organizationId): ?Organization
    {
        $organizationId = trim($organizationId);
        if ($organizationId === '') {
            $this->render('org/not-found', ['message' => 'Organization not specified.']);
            return null;
        }

        $organization = $this->properties->organization($organizationId);
        if ($organization === null) {
            $this->render('org/not-found', ['message' => 'Organization not found.']);
            return null;
        }

        return $organization;
    }

    private function resolveProperty(Organization $organization, string $propertyId): ?Property
    {
        $propertyId = trim($propertyId);
        if ($propertyId === '') {
            $this->render('public/property/not-found', ['organization' => $organization]);
            return null;
        }

        $property = $this->properties->findById($organization->id, $propertyId);
        if ($property === null) {
            $this->render('public/property/not-found', ['organization' => $organization]);
            return null;
        }

        return $property;
    }

    /**
     * @param array<string, mixed> $query
     * @return array{success: ?string, error: ?string}
     */
    private function buildFlashMessages(array $query): array
    {
        $flash = ['success' => null, 'error' => null];

        if (isset($query['created'])) {
            $flash['success'] = 'Property created successfully.';
        } elseif (isset($query['updated'])) {
            $flash['success'] = 'Property updated successfully.';
        } elseif (isset($query['deleted'])) {
            $flash['success'] = 'Property deleted successfully.';
        }

        if (isset($query['error'])) {
            $flash['error'] = match ((string) $query['error']) {
                'invalid_csrf' => 'Your session expired. Please try the action again.',
                'missing_property' => 'Select a property before attempting that action.',
                'not_found' => 'The selected property could not be found.',
                default => 'We were unable to complete that action. Please try again.',
            };
        }

        return $flash;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    private function validateCsrf(array $input): array
    {
        if (!\CSRF::validate($input['csrf_token'] ?? '')) {
            return ['general' => 'Invalid security token. Please refresh and try again.'];
        }

        return [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{0: array{name: string, description: ?string, address: ?string}, 1: array<string, string>}
     */
    private function validatePropertyInput(array $input): array
    {
        $errors = [];

        $name = RequestValidator::stringOrNull($input['name'] ?? null, 1, 255);
        if ($name === null) {
            $errors['name'] = 'Property name is required.';
        }

        $description = RequestValidator::stringOrNull($input['description'] ?? null, 0, 4000);
        $address = RequestValidator::stringOrNull($input['address'] ?? null, 0, 500);

        if ($errors !== []) {
            return [
                ['name' => $name ?? '', 'description' => $description, 'address' => $address],
                $errors,
            ];
        }

        return [
            [
                'name' => $name,
                'description' => $description,
                'address' => $address,
            ],
            $errors,
        ];
    }

    /**
     * @param array<string, mixed> $input
     */
    private function buildFormValues(array $input = [], ?Property $property = null): array
    {
        return [
            'name' => (string) ($input['name'] ?? $property?->name ?? ''),
            'description' => (string) ($input['description'] ?? $property?->description ?? ''),
            'address' => (string) ($input['address'] ?? $property?->address ?? ''),
        ];
    }
}
