<?php
// install.php
declare(strict_types=1);

use App\Extensions\ExtensionContext;

return static function (ExtensionContext $context): void {
    $pdo = $context->connection;

    try {
        // Create hotel_properties table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS hotel_properties (
                id VARCHAR(36) PRIMARY KEY,
                organization_id VARCHAR(36) NOT NULL,
                name VARCHAR(255) NOT NULL,
                description TEXT,
                address JSON NOT NULL,
                contact_info JSON,
                amenities JSON,
                check_in_time TIME DEFAULT '14:00:00',
                check_out_time TIME DEFAULT '10:00:00',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // Create room_types table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS room_types (
                id VARCHAR(36) PRIMARY KEY,
                property_id VARCHAR(36) NOT NULL,
                name VARCHAR(100) NOT NULL,
                short_description VARCHAR(200),
                description TEXT,
                base_price DECIMAL(10,2) NOT NULL,
                max_adults INT DEFAULT 2,
                max_children INT DEFAULT 1,
                total_units INT NOT NULL DEFAULT 1,
                status ENUM('active', 'inactive') DEFAULT 'active',
                sort_order INT DEFAULT 0,
                primary_image_path VARCHAR(255),
                gallery_images JSON,
                amenity_ids JSON,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (property_id) REFERENCES hotel_properties(id) ON DELETE CASCADE,
                INDEX idx_property_status (property_id, status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // Create hotel_reservations table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS hotel_reservations (
                id VARCHAR(36) PRIMARY KEY,
                organization_id VARCHAR(36) NOT NULL,
                property_id VARCHAR(36) NOT NULL,
                guest_id VARCHAR(36) NOT NULL, -- Foreign key to CRM system's guest table
                room_type_id VARCHAR(36) NOT NULL,
                check_in_date DATE NOT NULL,
                check_out_date DATE NOT NULL,
                num_adults INT DEFAULT 1,
                num_children INT DEFAULT 0,
                base_amount DECIMAL(10,2) NOT NULL,
                discount_amount DECIMAL(10,2) DEFAULT 0,
                final_amount DECIMAL(10,2) NOT NULL,
                payment_method ENUM('pay_on_arrival', 'payfast_online') DEFAULT 'pay_on_arrival',
                payment_status ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending',
                payfast_payment_id VARCHAR(50) NULL,
                status ENUM('draft', 'confirmed', 'checked_in', 'checked_out', 'completed', 'cancelled') DEFAULT 'confirmed',
                special_requests TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                confirmed_at TIMESTAMP NULL,
                checked_in_at TIMESTAMP NULL,
                checked_out_at TIMESTAMP NULL,
                paid_at TIMESTAMP NULL,
                cancelled_at TIMESTAMP NULL,
                FOREIGN KEY (organization_id) REFERENCES organizations(id),
                FOREIGN KEY (property_id) REFERENCES hotel_properties(id),
                FOREIGN KEY (guest_id) REFERENCES crm_customers(id), -- Links to CRM
                FOREIGN KEY (room_type_id) REFERENCES room_types(id),
                INDEX idx_guest_dates (guest_id, check_in_date, check_out_date),
                INDEX idx_property_dates (property_id, check_in_date, check_out_date),
                INDEX idx_payment_status (payment_status, payment_method),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // Create pos_categories table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS pos_categories (
                id VARCHAR(36) PRIMARY KEY,
                organization_id VARCHAR(36) NOT NULL,
                name VARCHAR(100) NOT NULL,
                description TEXT,
                sort_order INT DEFAULT 0,
                status ENUM('active', 'inactive') DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
                INDEX idx_org_sort (organization_id, sort_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // Create pos_items table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS pos_items (
                id VARCHAR(36) PRIMARY KEY,
                organization_id VARCHAR(36) NOT NULL,
                category_id VARCHAR(36) NOT NULL,
                name VARCHAR(100) NOT NULL,
                description TEXT,
                price DECIMAL(10,2) NOT NULL,
                image_path VARCHAR(255) NULL,
                sku VARCHAR(50) NULL,
                sort_order INT DEFAULT 0,
                status ENUM('active', 'inactive') DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
                FOREIGN KEY (category_id) REFERENCES pos_categories(id) ON DELETE CASCADE,
                INDEX idx_category_sort (category_id, sort_order),
                INDEX idx_org_status (organization_id, status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // Create folio_charges table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS folio_charges (
                id VARCHAR(36) PRIMARY KEY,
                organization_id VARCHAR(36) NOT NULL,
                reservation_id VARCHAR(36) NOT NULL,
                item_id VARCHAR(36) NOT NULL,
                category_name VARCHAR(100) NOT NULL,
                quantity INT NOT NULL DEFAULT 1,
                unit_price DECIMAL(10,2) NOT NULL,
                total_amount DECIMAL(10,2) NOT NULL,
                charged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                charged_by_user_id VARCHAR(36) NOT NULL,
                notes TEXT,
                status ENUM('active', 'voided') DEFAULT 'active',
                voided_at TIMESTAMP NULL,
                voided_by_user_id VARCHAR(36) NULL,
                FOREIGN KEY (organization_id) REFERENCES organizations(id),
                FOREIGN KEY (reservation_id) REFERENCES hotel_reservations(id) ON DELETE CASCADE,
                FOREIGN KEY (item_id) REFERENCES pos_items(id),
                FOREIGN KEY (charged_by_user_id) REFERENCES users(id),
                FOREIGN KEY (voided_by_user_id) REFERENCES users(id),
                INDEX idx_reservation_charged (reservation_id, charged_at),
                INDEX idx_charged_by (charged_by_user_id, charged_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        $context->logger->info('Hotel Manager extension tables created successfully.');
    } catch (Exception $e) {
        $context->logger->error('Error creating Hotel Manager tables: ' . $e->getMessage());
        throw $e;
    }
};