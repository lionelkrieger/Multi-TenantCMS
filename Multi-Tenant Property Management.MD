Complete System Specification: Multi-Tenant Property Management SaaS
Final Version with Security & Architecture Fixes

1. Overview
A comprehensive multi-tenant SaaS application with master admin capabilities, organization-level administration, public-facing property listing pages with custom domain mapping, and secure installation. The system enables organizations to manage properties and public-facing reservations while providing a unified dashboard for master administrators to oversee the entire platform.

Key Improvements in This Version:

✅ Secure installation without root MySQL credentials
✅ SSL certificate management for custom domains
✅ CSRF protection and secure session handling
✅ Cross-domain user flows using database persistence
✅ Secure file upload handling
✅ Proper multi-tenancy architecture
2. System Architecture

Directory Structure

/var/www/property-management/
├── app/                            # Application files (outside web root)
│   ├── config/
│   │   ├── app.php                # Application config
│   │   └── database.php           # DB config (generated)
│   ├── controllers/
│   │   ├── AuthController.php
│   │   ├── OrganizationController.php
│   │   ├── PropertyController.php
│   │   ├── UserController.php
│   │   ├── OrganizationSettingsController.php
│   │   ├── DashboardController.php
│   │   └── MasterAdminController.php
│   ├── includes/
│   │   ├── functions.php          # Core functions
│   │   ├── auth.php               # Authentication with CSRF
│   │   ├── database.php           # DB connection
│   │   ├── validation.php         # Input validation
│   │   ├── helpers.php            # Helper functions
│   │   ├── domain_routing.php     # Domain mapping
│   │   ├── user_flow.php          # Database-persistent user flows
│   │   └── public_functions.php   # Public-facing functions
│   ├── models/
│   │   ├── User.php
│   │   ├── Organization.php
│   │   ├── Property.php
│   │   └── UserAccess.php
│   ├── uploads/                   # File uploads (outside web root)
│   │   ├── logos/
│   │   └── documents/
│   └── logs/
│       └── application.log
├── public_html/                    # Web root
│   ├── install.php                # Secure installation script
│   ├── index.php                  # Main app entry
│   ├── login.php                  # Authentication
│   ├── register.php               # User registration
│   ├── search.php                 # Public property search
│   ├── serve-logo.php             # Secure file serving
│   ├── admin/                     # Master admin area
│   │   ├── dashboard.php
│   │   ├── organizations.php
│   │   ├── users.php
│   │   ├── analytics.php
│   │   └── settings.php
│   ├── org/                       # Organization context
│   │   ├── dashboard.php
│   │   ├── properties.php
│   │   ├── users.php
│   │   ├── settings.php
│   │   ├── property/
│   │   │   ├── view.php
│   │   │   ├── actions.php
│   │   │   ├── checkout.php
│   │   │   └── confirmation.php
│   │   └── order/
│   │       └── confirmation.php
│   ├── api/                       # REST API endpoints
│   └── assets/                    # Static assets
│       ├── css/
│       ├── js/
│       └── images/
├── cli/                           # Command-line tools
│   └── install.php                # Alternative CLI installer
└── views/                          # Templates
    ├── layouts/
    ├── admin/
    ├── public/
    └── auth/
	
Nginx Configuration Requirements
PHP 8.1+ with FPM
URL rewriting for clean URLs
SSL termination with wildcard certificate
Static asset serving
Security headers (CSP, HSTS, X-Frame-Options)
Rate limiting for public endpoints
3. Database Schema (MySQL)
Core Tables

-- users table
CREATE TABLE users (
    id VARCHAR(36) PRIMARY KEY NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(255),
    organization_id VARCHAR(36),
    user_type ENUM('master_admin', 'org_admin', 'employee', 'user') DEFAULT 'user',
    status ENUM('active', 'unassigned', 'deleted') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE SET NULL
);

-- organizations table
CREATE TABLE organizations (
    id VARCHAR(36) PRIMARY KEY NOT NULL,
    name VARCHAR(255) NOT NULL,
    created_by VARCHAR(36) NOT NULL,
    logo_url VARCHAR(512) DEFAULT NULL,
    primary_color VARCHAR(7) DEFAULT '#0066cc',
    secondary_color VARCHAR(7) DEFAULT '#f8f9fa',
    accent_color VARCHAR(7) DEFAULT '#dc3545',
    font_family VARCHAR(50) DEFAULT 'Roboto, sans-serif',
    show_branding BOOLEAN DEFAULT TRUE,
    custom_css TEXT DEFAULT NULL,
    custom_domain VARCHAR(255) DEFAULT NULL,
    domain_verified BOOLEAN DEFAULT FALSE,
    domain_verification_token VARCHAR(64) DEFAULT NULL,
    domain_verified_at TIMESTAMP NULL,
    ssl_certificate_status ENUM('none', 'pending', 'active', 'failed') DEFAULT 'none',
    ssl_certificate_expires TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- properties table
CREATE TABLE properties (
    id VARCHAR(36) PRIMARY KEY NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    address VARCHAR(512),
    organization_id VARCHAR(36) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    INDEX idx_org_id (organization_id)
);

-- user_property_access table
CREATE TABLE user_property_access (
    id VARCHAR(36) PRIMARY KEY NOT NULL,
    user_id VARCHAR(36) NOT NULL,
    property_id VARCHAR(36) NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_property (user_id, property_id)
);

-- user_invites table
CREATE TABLE user_invites (
    id VARCHAR(36) PRIMARY KEY NOT NULL,
    email VARCHAR(255) NOT NULL,
    organization_id VARCHAR(36),
    inviter_user_id VARCHAR(36) NOT NULL,
    invite_type ENUM('org_admin', 'employee', 'user') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (inviter_user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- employee_org_assignments table
CREATE TABLE employee_org_assignments (
    id VARCHAR(36) PRIMARY KEY NOT NULL,
    employee_user_id VARCHAR(36) NOT NULL,
    organization_id VARCHAR(36) NOT NULL,
    assigned_by VARCHAR(36) NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_employee_org (employee_user_id, organization_id)
);

-- user_flows table (for cross-domain flows)
CREATE TABLE user_flows (
    id VARCHAR(36) PRIMARY KEY NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    org_id VARCHAR(36) NOT NULL,
    property_id VARCHAR(36) NOT NULL,
    user_id VARCHAR(36) NULL,
    current_step VARCHAR(50) NOT NULL DEFAULT 'actions',
    completed_steps JSON DEFAULT NULL,
    expires_at TIMESTAMP DEFAULT (DATE_ADD(NOW(), INTERVAL 1 HOUR)),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (org_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

4. User Roles & Permissions
Master Admin
Create/manages all organizations
Assign employees to organizations
View all system data
Invite organization admins
System-wide analytics
Platform configuration
Security management
SSL certificate management
Organization Admin
Full control of their organization
Create/manage properties
Invite/manage team members
Configure branding & domain
Access all organization properties
Organization-level analytics
SSL certificate requests for custom domains
Employee
Access only assigned organizations
Manage properties in assigned orgs
Manage users in assigned orgs
Cannot create organizations
Limited to assigned scope
User (Regular)
Access only assigned properties
Navigate public-facing system
Access user action flows
Property-specific access only
Cannot create organizations or invite users
Guest (Public)
View public organization landing pages
View public property detail pages
Access user action flows
No administrative access
No authentication required
5. Secure Installation System
install.php - Secure Installation Script
Location: public_html/install.php
URL Access: https://www.ourapplication.co.za/install.php

Core Capabilities:

Existing user validation - Uses existing MySQL user with proper permissions
Database creation - Creates application database
Application user creation - Creates dedicated app user with limited permissions
Secure password generation - Auto-generates strong passwords
Table creation - Creates all core tables with relationships
Master admin setup - Creates initial master admin account
Configuration generation - Creates secure config file
Installation prevention - Blocks re-installation if config exists
Installation Form Fields
Database Host (default: localhost)
Existing MySQL Username (with CREATE, GRANT permissions)
Existing MySQL Password
Application Database Name (default: property_management)
Application Database Username (default: prop_mgmt_user)
Application Database Password (auto-generated if empty)
Master Admin Email (required)
Master Admin Password (required, 8+ chars)
Secure Installation Process

// install.php - Secure Logic
1. Check if config exists → block if yes
2. Validate existing user has required permissions
3. Create application database
4. Create application user with limited permissions:
   GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, ALTER, INDEX 
   ON `property_management`.* TO 'prop_mgmt_user'@'localhost';
5. Create all tables with proper indexes
6. Create master admin with hashed password
7. Generate config/database.php with 600 permissions
8. Redirect to login with success message

Generated Secure Configuration
<?php
// app/config/database.php - Auto-generated
return [
    'host' => 'localhost',
    'database' => 'property_management',
    'username' => 'prop_mgmt_user',
    'password' => 'auto_generated_32_char_secure_password',
    'charset' => 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
];
?>

profile
Qwen3-Max
10:47 am
Complete System Specification: Multi-Tenant Property Management SaaS
Final Version with Security & Architecture Fixes

1. Overview
A comprehensive multi-tenant SaaS application with master admin capabilities, organization-level administration, public-facing property listing pages with custom domain mapping, and secure installation. The system enables organizations to manage properties and public-facing reservations while providing a unified dashboard for master administrators to oversee the entire platform.

Key Improvements in This Version:

✅ Secure installation without root MySQL credentials
✅ SSL certificate management for custom domains
✅ CSRF protection and secure session handling
✅ Cross-domain user flows using database persistence
✅ Secure file upload handling
✅ Proper multi-tenancy architecture
2. System Architecture
Directory Structure


1
2
3
4
5
6
7
8
9
10
11
12
13
14
15
16
17
18
19
20
21
22
23
24
25
26
27
28
29
30
31
32
33
34
35
36
37
38
39
40
41
42
43
44
45
46
47
48
49
50
51
52
53
54
55
56
57
58
59
60
61
62
63
64
65
66
67
68
69
/var/www/property-management/
├── app/                            # Application files (outside web root)
│   ├── config/
│   │   ├── app.php                # Application config
│   │   └── database.php           # DB config (generated)
│   ├── controllers/
│   │   ├── AuthController.php
│   │   ├── OrganizationController.php
│   │   ├── PropertyController.php
│   │   ├── UserController.php
│   │   ├── OrganizationSettingsController.php
│   │   ├── DashboardController.php
│   │   └── MasterAdminController.php
│   ├── includes/
│   │   ├── functions.php          # Core functions
│   │   ├── auth.php               # Authentication with CSRF
│   │   ├── database.php           # DB connection
│   │   ├── validation.php         # Input validation
│   │   ├── helpers.php            # Helper functions
│   │   ├── domain_routing.php     # Domain mapping
│   │   ├── user_flow.php          # Database-persistent user flows
│   │   └── public_functions.php   # Public-facing functions
│   ├── models/
│   │   ├── User.php
│   │   ├── Organization.php
│   │   ├── Property.php
│   │   └── UserAccess.php
│   ├── uploads/                   # File uploads (outside web root)
│   │   ├── logos/
│   │   └── documents/
│   └── logs/
│       └── application.log
├── public_html/                    # Web root
│   ├── install.php                # Secure installation script
│   ├── index.php                  # Main app entry
│   ├── login.php                  # Authentication
│   ├── register.php               # User registration
│   ├── search.php                 # Public property search
│   ├── serve-logo.php             # Secure file serving
│   ├── admin/                     # Master admin area
│   │   ├── dashboard.php
│   │   ├── organizations.php
│   │   ├── users.php
│   │   ├── analytics.php
│   │   └── settings.php
│   ├── org/                       # Organization context
│   │   ├── dashboard.php
│   │   ├── properties.php
│   │   ├── users.php
│   │   ├── settings.php
│   │   ├── property/
│   │   │   ├── view.php
│   │   │   ├── actions.php
│   │   │   ├── checkout.php
│   │   │   └── confirmation.php
│   │   └── order/
│   │       └── confirmation.php
│   ├── api/                       # REST API endpoints
│   └── assets/                    # Static assets
│       ├── css/
│       ├── js/
│       └── images/
├── cli/                           # Command-line tools
│   └── install.php                # Alternative CLI installer
└── views/                          # Templates
    ├── layouts/
    ├── admin/
    ├── public/
    └── auth/
Nginx Configuration Requirements
PHP 8.1+ with FPM
URL rewriting for clean URLs
SSL termination with wildcard certificate
Static asset serving
Security headers (CSP, HSTS, X-Frame-Options)
Rate limiting for public endpoints
3. Database Schema (MySQL)
Core Tables
sql


1
2
3
4
5
6
7
8
9
10
11
12
13
14
15
16
17
18
19
20
21
22
23
24
25
26
27
28
29
30
31
32
33
34
35
36
37
38
39
40
41
42
43
44
45
46
47
48
49
50
51
52
53
54
55
56
57
58
59
60
61
62
63
64
65
66
67
68
69
70
71
72
73
74
75
76
77
78
79
80
81
82
83
84
85
86
87
88
89
90
91
92
93
94
95
96
97
98
99
100
⌄
⌄
⌄
⌄
⌄
⌄
⌄
-- users table
CREATE TABLE users (
    id VARCHAR(36) PRIMARY KEY NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(255),
    organization_id VARCHAR(36),
    user_type ENUM('master_admin', 'org_admin', 'employee', 'user') DEFAULT 'user',
    status ENUM('active', 'unassigned', 'deleted') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE SET NULL
);

-- organizations table
CREATE TABLE organizations (
    id VARCHAR(36) PRIMARY KEY NOT NULL,
    name VARCHAR(255) NOT NULL,
    created_by VARCHAR(36) NOT NULL,
    logo_url VARCHAR(512) DEFAULT NULL,
    primary_color VARCHAR(7) DEFAULT '#0066cc',
    secondary_color VARCHAR(7) DEFAULT '#f8f9fa',
    accent_color VARCHAR(7) DEFAULT '#dc3545',
    font_family VARCHAR(50) DEFAULT 'Roboto, sans-serif',
    show_branding BOOLEAN DEFAULT TRUE,
    custom_css TEXT DEFAULT NULL,
    custom_domain VARCHAR(255) DEFAULT NULL,
    domain_verified BOOLEAN DEFAULT FALSE,
    domain_verification_token VARCHAR(64) DEFAULT NULL,
    domain_verified_at TIMESTAMP NULL,
    ssl_certificate_status ENUM('none', 'pending', 'active', 'failed') DEFAULT 'none',
    ssl_certificate_expires TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- properties table
CREATE TABLE properties (
    id VARCHAR(36) PRIMARY KEY NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    address VARCHAR(512),
    organization_id VARCHAR(36) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    INDEX idx_org_id (organization_id)
);

-- user_property_access table
CREATE TABLE user_property_access (
    id VARCHAR(36) PRIMARY KEY NOT NULL,
    user_id VARCHAR(36) NOT NULL,
    property_id VARCHAR(36) NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_property (user_id, property_id)
);

-- user_invites table
CREATE TABLE user_invites (
    id VARCHAR(36) PRIMARY KEY NOT NULL,
    email VARCHAR(255) NOT NULL,
    organization_id VARCHAR(36),
    inviter_user_id VARCHAR(36) NOT NULL,
    invite_type ENUM('org_admin', 'employee', 'user') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (inviter_user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- employee_org_assignments table
CREATE TABLE employee_org_assignments (
    id VARCHAR(36) PRIMARY KEY NOT NULL,
    employee_user_id VARCHAR(36) NOT NULL,
    organization_id VARCHAR(36) NOT NULL,
    assigned_by VARCHAR(36) NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_employee_org (employee_user_id, organization_id)
);

-- user_flows table (for cross-domain flows)
CREATE TABLE user_flows (
    id VARCHAR(36) PRIMARY KEY NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    org_id VARCHAR(36) NOT NULL,
    property_id VARCHAR(36) NOT NULL,
    user_id VARCHAR(36) NULL,
    current_step VARCHAR(50) NOT NULL DEFAULT 'actions',
    completed_steps JSON DEFAULT NULL,
    expires_at TIMESTAMP DEFAULT (DATE_ADD(NOW(), INTERVAL 1 HOUR)),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (org_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);
4. User Roles & Permissions
Master Admin
Create/manages all organizations
Assign employees to organizations
View all system data
Invite organization admins
System-wide analytics
Platform configuration
Security management
SSL certificate management
Organization Admin
Full control of their organization
Create/manage properties
Invite/manage team members
Configure branding & domain
Access all organization properties
Organization-level analytics
SSL certificate requests for custom domains
Employee
Access only assigned organizations
Manage properties in assigned orgs
Manage users in assigned orgs
Cannot create organizations
Limited to assigned scope
User (Regular)
Access only assigned properties
Navigate public-facing system
Access user action flows
Property-specific access only
Cannot create organizations or invite users
Guest (Public)
View public organization landing pages
View public property detail pages
Access user action flows
No administrative access
No authentication required
5. Secure Installation System
install.php - Secure Installation Script
Location: public_html/install.php
URL Access: https://www.ourapplication.co.za/install.php

Core Capabilities:

Existing user validation - Uses existing MySQL user with proper permissions
Database creation - Creates application database
Application user creation - Creates dedicated app user with limited permissions
Secure password generation - Auto-generates strong passwords
Table creation - Creates all core tables with relationships
Master admin setup - Creates initial master admin account
Configuration generation - Creates secure config file
Installation prevention - Blocks re-installation if config exists
Installation Form Fields
Database Host (default: localhost)
Existing MySQL Username (with CREATE, GRANT permissions)
Existing MySQL Password
Application Database Name (default: property_management)
Application Database Username (default: prop_mgmt_user)
Application Database Password (auto-generated if empty)
Master Admin Email (required)
Master Admin Password (required, 8+ chars)
Secure Installation Process
php


1
2
3
4
5
6
7
8
9
10
11
// install.php - Secure Logic
1. Check if config exists → block if yes
2. Validate existing user has required permissions
3. Create application database
4. Create application user with limited permissions:
   GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, ALTER, INDEX 
   ON `property_management`.* TO 'prop_mgmt_user'@'localhost';
5. Create all tables with proper indexes
6. Create master admin with hashed password
7. Generate config/database.php with 600 permissions
8. Redirect to login with success message
Generated Secure Configuration
php


1
2
3
4
5
6
7
8
9
10
11
12
13
14
15
⌄
⌄
<?php
// app/config/database.php - Auto-generated
return [
    'host' => 'localhost',
    'database' => 'property_management',
    'username' => 'prop_mgmt_user',
    'password' => 'auto_generated_32_char_secure_password',
    'charset' => 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
];
?>
6. SSL Certificate Management for Custom Domains
Certificate Management Strategy
Let's Encrypt Integration: Automatic SSL provisioning
Subdomain-only Support: No apex domain support (RFC compliance)
Certificate Status Tracking: Database tracking of SSL status
Automatic Renewal: Background renewal process
Domain Setup Process
Organization enters custom subdomain (e.g., reservations.company.com)
System validates CNAME record points to platform
System adds TXT verification record requirement
On verification, system requests Let's Encrypt certificate
Certificate status tracked in organizations table
Automatic renewal 30 days before expiration
SSL Certificate Endpoints
POST /api/request-ssl - Request SSL certificate for domain
GET /api/ssl-status - Check certificate status and expiration
POST /api/renew-ssl - Manual renewal request
7. Public-Facing Pages (External Interaction Layer)
URL Structure
Organization Landing: /org/{org_id} OR https://{custom_domain}/
Property Detail: /org/{org_id}/property/{prop_id} OR https://{custom_domain}/property/{prop_id}
User Actions: /org/{org_id}/property/{prop_id}/actions?flow_token={token} OR https://{custom_domain}/property/{prop_id}/actions?flow_token={token}
Checkout: /org/{org_id}/property/{prop_id}/checkout?flow_token={token} OR https://{custom_domain}/property/{prop_id}/checkout?flow_token={token}
Confirmation: /org/order/{order_id}/confirmation?flow_token={token} OR https://{custom_domain}/order/{order_id}/confirmation?flow_token={token}
👉 Core currently ships only lightweight stubs for the actions/checkout/confirmation endpoints so extensions can take full ownership of the journey without modifying core files.
Domain Mapping System
HTTP_HOST routing for automatic domain detection
Database lookup for organization by custom domain
SSL status validation before serving custom domain content
Fallback to standard URLs if domain not verified
8. Cross-Domain User Action Flow Architecture
Flow Steps (Database-Persistent)
Property Detail → Generate flow token, redirect with token
User Actions → Validate flow token from URL parameter
Checkout → Validate flow token, maintain flow state in database
Confirmation → Complete flow, show confirmation
Flow State Management
Database storage instead of sessions
URL parameters for flow token transmission
1-hour expiration for security
Automatic cleanup of expired flows
CSRF protection on all flow forms

Flow Persistence Model

// Flow creation
$flow = [
    'id' => bin2hex(random_bytes(16)),
    'token' => bin2hex(random_bytes(32)),
    'org_id' => $org_id,
    'property_id' => $property_id,
    'user_id' => $user_id ?? null,
    'current_step' => 'actions',
    'expires_at' => date('Y-m-d H:i:s', strtotime('+1 hour'))
];

9. Organization Configuration System
Configuration Tabs
Branding: Logo upload, organization name, show/hide options
Domain: Custom domain setup, DNS verification, SSL status
Appearance: Color scheme, font selection, custom CSS
Security: 2FA, audit logging, session timeout
Secure File Upload Implementation
Content-type validation (not just extension)
File stored outside web root (/app/uploads/logos/)
Served through PHP script (/serve-logo.php)
Image integrity validation with getimagesize()
Safe filename generation with random strings
Domain Setup Process
Enter custom subdomain (e.g., reservations.company.com)
Add CNAME record: reservations.company.com → platform.propertyhub.com
Add TXT verification record with provided token
Click verify to activate domain and request SSL certificate
Automatic routing with HTTPS enforcement
10. Master Admin Dashboard
Dashboard Overview
Centralized control panel for platform management with SSL certificate oversight.

Key Features
Quick Stats: Organizations, users, properties, SSL certificates
Recent Activity: System-wide activity including SSL requests
Organizations Table: Filterable by SSL status
SSL Management: Certificate status, expiration, renewal
System Health: Database, server, SSL certificate health
Quick Actions: Create organization, manage SSL certificates
SSL Certificate Management Panel
Certificate Status: Active, Pending, Failed, Expired
Expiration Dates: Visual indicators for upcoming renewals
Manual Renewal: Force certificate renewal
Domain Validation: Re-validate domain ownership
11. Security Architecture
Authentication & Session Security
CSRF Protection: Token-based validation on all forms
Secure Sessions: HttpOnly, Secure, SameSite=Strict cookies
Session Regeneration: After login and privilege changes
Password Hashing: bcrypt with cost factor 12
Brute Force Protection: Login attempt limiting
CSRF Implementation

// CSRF token generation and validation
class CSRF {
    public static function token() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    public static function validate($token) {
        return isset($_SESSION['csrf_token']) && 
               hash_equals($_SESSION['csrf_token'], $token);
    }
}

Authorization & Data Protection
Role-Based Access Control: Strict permission checking
Multi-Tenancy Isolation: Organization-level data separation
Prepared Statements: All database queries
Output Escaping: All user data with htmlspecialchars()
Input Validation: Server and client-side validation
Secure File Handling
Upload Directory: Outside web root (/app/uploads/)
Content Validation: MIME type and file integrity checks
Secure Serving: Through PHP proxy script
File Permissions: 600 for uploaded files
Size Limits: 5MB maximum for logo uploads
12. API Endpoints
Organization Management
POST /api/organization-config.php - Update organization configuration
POST /api/verify-domain.php - Verify custom domain
POST /api/request-ssl.php - Request SSL certificate
GET /api/ssl-status.php - Check SSL certificate status
User Flow Management
POST /api/start-flow.php - Initialize database-persistent user flow
POST /api/complete-order.php - Complete checkout process with CSRF
GET /api/flow-status.php - Check current flow status
Property Management
POST /api/create-property.php - Create new property with CSRF
POST /api/update-property.php - Update property details with CSRF
POST /api/delete-property.php - Delete property with CSRF
13. Frontend Requirements
CSS Architecture
CSS Variables: Dynamic theming with organization colors
Responsive Design: Mobile-first with flexbox/grid
Component System: Reusable UI components
Security: CSP-compatible styling
JavaScript Features
CSRF Token Handling: Automatic token inclusion in AJAX requests
Cross-Domain Flows: URL parameter management
Domain Verification: Real-time DNS verification status
File Upload Validation: Client-side file type checking
SSL Status Updates: Real-time certificate status
Template Security
Layout Inheritance: Secure template inheritance
Output Escaping: Automatic escaping in templates
CSRF Tokens: Automatic inclusion in all forms
Content Security Policy: Compatible template structure
14. Performance & Scalability
Database Optimization
Proper Indexing: Foreign keys and frequently queried fields
Connection Pooling: Efficient database connections
Query Optimization: Prepared statements with caching
Frontend Optimization
Asset Minification: CSS and JavaScript compression
Image Optimization: WebP format with fallbacks
Lazy Loading: Non-critical content loading
CDN Integration: Static asset delivery
Caching Strategy
Redis Integration: Session and frequently accessed data
Database Query Caching: Result set caching
HTTP Caching: Proper cache headers for static assets
SSL Certificate Caching: Certificate status caching
15. Deployment & Environment Requirements
Server Requirements
Web Server: Nginx 1.20+ with HTTP/2 support
PHP: 8.1+ with extensions (pdo_mysql, openssl, gd, curl, json)
Database: MySQL 8.0+ or MariaDB 10.6+
Memory: 2GB RAM minimum (4GB recommended)
Storage: 20GB available space minimum
SSL: Wildcard certificate for platform domains
SSL Requirements
Wildcard Certificate: *.propertyhub.com for platform
Let's Encrypt Integration: Automatic certificate management
HTTPS Enforcement: All traffic redirected to HTTPS
HSTS Headers: Strict transport security
File Permissions
Configuration Files: 600 (readable only by web server user)
Upload Directories: 755 with proper ownership
Log Files: 644 with log rotation
Application Code: 644 for files, 755 for directories
16. Error Handling & Monitoring
Error Handling
Graceful Degradation: User-friendly error messages
Security Logging: Suspicious activity detection
SSL Error Handling: Certificate-related error management
Cross-Domain Flow Recovery: Flow state recovery mechanisms
Monitoring & Logging
Application Logging: Structured logging with context
SSL Certificate Monitoring: Expiration alerts
Security Event Logging: Authentication and authorization events
Performance Monitoring: Response time and error rate tracking
Health Checks
Database Connectivity: Database health verification
SSL Certificate Status: Certificate validity checking
File System Permissions: Upload directory accessibility
External Dependencies: Let's Encrypt API availability
This comprehensive specification provides a production-ready, security-first architecture for a multi-tenant property management SaaS system with custom domain support, secure installation, proper SSL management, and cross-domain user flows. The system is designed to be extensible, scalable, and secure from day one, with special emphasis on the critical security vulnerabilities identified in earlier versions.

Extension System Architecture for Multi-Tenant Property Management SaaS
Core Philosophy
Core remains dumb: Only handles multi-tenancy, auth, org management, and extension framework
Extensions handle business logic: All domain-specific functionality lives in extensions
Zero knowledge assumption: Core doesn't know what extensions will do
Isolated but integrated: Extensions can interact with core and each other safely
1. Database Architecture for Extensions
Extension Registration Table

-- extensions table (core)
CREATE TABLE extensions (
    id VARCHAR(36) PRIMARY KEY NOT NULL,
    name VARCHAR(100) NOT NULL UNIQUE,           -- 'car-rentals', 'hotel-bookings'
    display_name VARCHAR(100) NOT NULL,          -- 'Car Rentals', 'Hotel Bookings'
    version VARCHAR(20) NOT NULL,
    author VARCHAR(100),
    description TEXT,
    status ENUM('active', 'inactive', 'disabled') DEFAULT 'inactive',
    requires_core_version VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- extension_settings table (core)
CREATE TABLE extension_settings (
    id VARCHAR(36) PRIMARY KEY NOT NULL,
    extension_id VARCHAR(36) NOT NULL,
    organization_id VARCHAR(36) NOT NULL,
    settings JSON NOT NULL,                      -- Extension-specific configuration
    enabled BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (extension_id) REFERENCES extensions(id) ON DELETE CASCADE,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    UNIQUE KEY unique_org_extension (organization_id, extension_id)
);

Extension Data Tables (Managed by Extensions)
Core never creates these tables directly:

-- Example: car-rentals extension tables (created by extension install)
CREATE TABLE car_rental_locations (
    id VARCHAR(36) PRIMARY KEY NOT NULL,
    organization_id VARCHAR(36) NOT NULL,
    name VARCHAR(255) NOT NULL,
    address VARCHAR(512),
    extension_data JSON,                         -- Extension-specific fields
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
);

CREATE TABLE car_rental_vehicles (
    id VARCHAR(36) PRIMARY KEY NOT NULL,
    location_id VARCHAR(36) NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    extension_data JSON,                         -- Vehicle-specific data (make, model, etc.)
    pricing_data JSON,                          -- Flexible pricing structure
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (location_id) REFERENCES car_rental_locations(id) ON DELETE CASCADE
);

-- Example: hotel-bookings extension tables
CREATE TABLE hotel_properties (
    id VARCHAR(36) PRIMARY KEY NOT NULL,
    organization_id VARCHAR(36) NOT NULL,
    name VARCHAR(255) NOT NULL,
    address VARCHAR(512),
    extension_data JSON,                         -- Hotel-specific fields (stars, amenities)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
);

CREATE TABLE hotel_rooms (
    id VARCHAR(36) PRIMARY KEY NOT NULL,
    hotel_id VARCHAR(36) NOT NULL,
    room_type VARCHAR(100) NOT NULL,
    description TEXT,
    extension_data JSON,                         -- Room-specific data (bed count, view, etc.)
    pricing_data JSON,                          -- Flexible pricing (seasonal rates, etc.)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hotel_id) REFERENCES hotel_properties(id) ON DELETE CASCADE
);

Key Database Principles:
Core owns only core tables: organizations, users, properties (generic), extensions, extension_settings
Extensions own their data: Each extension manages its own tables
JSON for flexibility: extension_data and pricing_data allow arbitrary data structures
Foreign key to organization_id: Ensures multi-tenancy isolation
No core schema changes: Extensions never modify core tables

2. Extension Directory Structure

/var/www/property-management/
├── app/
│   ├── core/                    # Core system (unchanged)
│   ├── extensions/              # Extension directory
│   │   ├── car-rentals/         # Individual extension
│   │   │   ├── extension.json   # Extension manifest
│   │   │   ├── install.php      # Database installation
│   │   │   ├── uninstall.php    # Database cleanup
│   │   │   ├── controllers/
│   │   │   ├── models/
│   │   │   ├── views/
│   │   │   ├── public/          # Public-facing templates
│   │   │   └── assets/          # Extension-specific assets
│   │   ├── hotel-bookings/
│   │   ├── restaurant-reservations/
│   │   └── coworking-spaces/
│   └── framework/               # Extension framework
│       ├── ExtensionManager.php
│       ├── ExtensionInterface.php
│       ├── HookSystem.php
│       └── EventDispatcher.php
└── public_html/
    ├── extensions/              # Public extension assets
    │   ├── car-rentals/
    │   └── hotel-bookings/
    └── [core public files...]
	
	{
    "name": "car-rentals",
    "displayName": "Car Rentals",
    "version": "1.0.0",
    "author": "Your Company",
    "description": "Car rental management extension",
    "requiresCoreVersion": "2.0.0",
    "hooks": [
        "org_landing_page_render",
        "property_detail_page_render",
        "user_action_flow_start",
        "checkout_process_start"
    ],
    "permissions": [
        "manage_car_rentals",
        "view_car_rentals",
        "book_car_rentals"
    ],
    "publicRoutes": [
        "/car-rentals/locations",
        "/car-rentals/vehicles/{id}"
    ],
    "adminRoutes": [
        "/admin/car-rentals/locations",
        "/admin/car-rentals/vehicles"
    ]
}

profile
Qwen3-Max
10:56 am
You're absolutely right about the modular/extension architecture—this is critical for long-term maintainability and scalability. Let me design an agnostic extension system that can handle any future functionality (car rentals, hotels, restaurants, co-working spaces, etc.) without breaking the core.

Extension System Architecture for Multi-Tenant Property Management SaaS
Core Philosophy
Core remains dumb: Only handles multi-tenancy, auth, org management, and extension framework
Extensions handle business logic: All domain-specific functionality lives in extensions
Zero knowledge assumption: Core doesn't know what extensions will do
Isolated but integrated: Extensions can interact with core and each other safely
1. Database Architecture for Extensions
Extension Registration Table
sql


1
2
3
4
5
6
7
8
9
10
11
12
13
14
15
16
17
18
19
20
21
22
23
24
25
26
27
⌄
⌄
-- extensions table (core)
CREATE TABLE extensions (
    id VARCHAR(36) PRIMARY KEY NOT NULL,
    name VARCHAR(100) NOT NULL UNIQUE,           -- 'car-rentals', 'hotel-bookings'
    display_name VARCHAR(100) NOT NULL,          -- 'Car Rentals', 'Hotel Bookings'
    version VARCHAR(20) NOT NULL,
    author VARCHAR(100),
    description TEXT,
    status ENUM('active', 'inactive', 'disabled') DEFAULT 'inactive',
    requires_core_version VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- extension_settings table (core)
CREATE TABLE extension_settings (
    id VARCHAR(36) PRIMARY KEY NOT NULL,
    extension_id VARCHAR(36) NOT NULL,
    organization_id VARCHAR(36) NOT NULL,
    settings JSON NOT NULL,                      -- Extension-specific configuration
    enabled BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (extension_id) REFERENCES extensions(id) ON DELETE CASCADE,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    UNIQUE KEY unique_org_extension (organization_id, extension_id)
);
Extension Data Tables (Managed by Extensions)
Core never creates these tables directly:

sql


1
2
3
4
5
6
7
8
9
10
11
12
13
14
15
16
17
18
19
20
21
22
23
24
25
26
27
28
29
30
31
32
33
34
35
36
37
38
39
40
41
42
43
⌄
⌄
⌄
⌄
-- Example: car-rentals extension tables (created by extension install)
CREATE TABLE car_rental_locations (
    id VARCHAR(36) PRIMARY KEY NOT NULL,
    organization_id VARCHAR(36) NOT NULL,
    name VARCHAR(255) NOT NULL,
    address VARCHAR(512),
    extension_data JSON,                         -- Extension-specific fields
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
);

CREATE TABLE car_rental_vehicles (
    id VARCHAR(36) PRIMARY KEY NOT NULL,
    location_id VARCHAR(36) NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    extension_data JSON,                         -- Vehicle-specific data (make, model, etc.)
    pricing_data JSON,                          -- Flexible pricing structure
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (location_id) REFERENCES car_rental_locations(id) ON DELETE CASCADE
);

-- Example: hotel-bookings extension tables
CREATE TABLE hotel_properties (
    id VARCHAR(36) PRIMARY KEY NOT NULL,
    organization_id VARCHAR(36) NOT NULL,
    name VARCHAR(255) NOT NULL,
    address VARCHAR(512),
    extension_data JSON,                         -- Hotel-specific fields (stars, amenities)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
);

CREATE TABLE hotel_rooms (
    id VARCHAR(36) PRIMARY KEY NOT NULL,
    hotel_id VARCHAR(36) NOT NULL,
    room_type VARCHAR(100) NOT NULL,
    description TEXT,
    extension_data JSON,                         -- Room-specific data (bed count, view, etc.)
    pricing_data JSON,                          -- Flexible pricing (seasonal rates, etc.)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hotel_id) REFERENCES hotel_properties(id) ON DELETE CASCADE
);
Key Database Principles:
Core owns only core tables: organizations, users, properties (generic), extensions, extension_settings
Extensions own their data: Each extension manages its own tables
JSON for flexibility: extension_data and pricing_data allow arbitrary data structures
Foreign key to organization_id: Ensures multi-tenancy isolation
No core schema changes: Extensions never modify core tables
2. Extension Directory Structure


1
2
3
4
5
6
7
8
9
10
11
12
13
14
15
16
17
18
19
20
21
22
23
24
25
26
/var/www/property-management/
├── app/
│   ├── core/                    # Core system (unchanged)
│   ├── extensions/              # Extension directory
│   │   ├── car-rentals/         # Individual extension
│   │   │   ├── extension.json   # Extension manifest
│   │   │   ├── install.php      # Database installation
│   │   │   ├── uninstall.php    # Database cleanup
│   │   │   ├── controllers/
│   │   │   ├── models/
│   │   │   ├── views/
│   │   │   ├── public/          # Public-facing templates
│   │   │   └── assets/          # Extension-specific assets
│   │   ├── hotel-bookings/
│   │   ├── restaurant-reservations/
│   │   └── coworking-spaces/
│   └── framework/               # Extension framework
│       ├── ExtensionManager.php
│       ├── ExtensionInterface.php
│       ├── HookSystem.php
│       └── EventDispatcher.php
└── public_html/
    ├── extensions/              # Public extension assets
    │   ├── car-rentals/
    │   └── hotel-bookings/
    └── [core public files...]
Extension Manifest (extension.json)
json


1
2
3
4
5
6
7
8
9
10
11
12
13
14
15
16
17
18
19
20
21
22
23
24
25
26
27
⌄
⌄
⌄
⌄
⌄
{
    "name": "car-rentals",
    "displayName": "Car Rentals",
    "version": "1.0.0",
    "author": "Your Company",
    "description": "Car rental management extension",
    "requiresCoreVersion": "2.0.0",
    "hooks": [
        "org_landing_page_render",
        "property_detail_page_render",
        "user_action_flow_start",
        "checkout_process_start"
    ],
    "permissions": [
        "manage_car_rentals",
        "view_car_rentals",
        "book_car_rentals"
    ],
    "publicRoutes": [
        "/car-rentals/locations",
        "/car-rentals/vehicles/{id}"
    ],
    "adminRoutes": [
        "/admin/car-rentals/locations",
        "/admin/car-rentals/vehicles"
    ]
}
3. Extension Framework & Hook System
Core Extension Interface

// app/framework/ExtensionInterface.php
interface ExtensionInterface {
    public function getName(): string;
    public function getDisplayName(): string;
    public function getVersion(): string;
    public function install(): bool;
    public function uninstall(): bool;
    public function activate(): bool;
    public function deactivate(): bool;
    public function getSettingsSchema(): array;
    public function getPublicRoutes(): array;
    public function getAdminRoutes(): array;
}

Hook System (Event-Driven Architecture)

// app/framework/HookSystem.php
class HookSystem {
    private static $hooks = [];
    
    public static function addHook(string $hookName, callable $callback, int $priority = 10) {
        if (!isset(self::$hooks[$hookName])) {
            self::$hooks[$hookName] = [];
        }
        self::$hooks[$hookName][] = ['callback' => $callback, 'priority' => $priority];
        usort(self::$hooks[$hookName], fn($a, $b) => $a['priority'] - $b['priority']);
    }
    
    public static function executeHook(string $hookName, $data = null) {
        if (!isset(self::$hooks[$hookName])) {
            return $data;
        }
        
        foreach (self::$hooks[$hookName] as $hook) {
            $data = $hook['callback']($data);
        }
        return $data;
    }
}

// Usage in core templates
// views/public/org_landing.php
$landingPageContent = HookSystem::executeHook('org_landing_page_render', [
    'organization' => $organization,
    'properties' => $properties,
    'html_content' => ''
]);
echo $landingPageContent['html_content'];

Available Core Hooks

// Organization Landing Page
'org_landing_page_render' - Modify organization landing page content
'org_landing_page_css' - Add custom CSS to landing page
'org_landing_page_js' - Add custom JavaScript to landing page

// Property Detail Page  
'property_detail_page_render' - Modify property detail page content
'property_detail_page_actions' - Add action buttons to property page
'property_detail_page_css' - Add custom CSS to property page
'property_detail_page_js' - Add custom JavaScript to property page

// User Action Flow
'user_action_flow_start' - Initialize extension-specific flow data
'user_action_flow_steps' - Add extension-specific flow steps
'checkout_process_start' - Add extension data to checkout
'order_confirmation_render' - Add extension data to confirmation

// Admin Areas
'org_admin_dashboard_widgets' - Add widgets to org admin dashboard
'org_admin_settings_tabs' - Add tabs to organization settings
'master_admin_extensions_list' - Add extension management to master admin

// System Events
'user_created' - Trigger when user is created
'organization_created' - Trigger when organization is created
'property_created' - Trigger when property is created

4. Public Page Extension Integration
Organization Landing Page (Extension-Enhanced)

<!-- views/public/org_landing.php -->
<div class="organization-landing">
    <header class="org-header">
        <!-- Core organization branding -->
        <?= renderOrganizationBranding($organization) ?>
    </header>
    
    <main class="org-content">
        <!-- Core properties section -->
        <?php if (!empty($properties)): ?>
            <section class="properties-section">
                <h2>Properties</h2>
                <?= renderPropertyCards($properties) ?>
            </section>
        <?php endif; ?>
        
        <!-- Extension content injected via hooks -->
        <?php
        $extensionContent = HookSystem::executeHook('org_landing_page_render', [
            'organization' => $organization,
            'html_content' => ''
        ]);
        echo $extensionContent['html_content'];
        ?>
    </main>
</div>

Property Detail Page (Extension-Enhanced)

<!-- views/public/property_detail.php -->
<div class="property-detail">
    <div class="property-header">
        <!-- Core property info -->
        <?= renderPropertyHeader($property) ?>
    </div>
    
    <div class="property-content">
        <div class="property-main">
            <!-- Core property details -->
            <?= renderPropertyDetails($property) ?>
        </div>
        
        <div class="property-sidebar">
            <!-- Core action buttons -->
            <?= renderCoreActionButtons($property) ?>
            
            <!-- Extension action buttons -->
            <?php
            $extensionActions = HookSystem::executeHook('property_detail_page_actions', [
                'property' => $property,
                'html_content' => ''
            ]);
            echo $extensionActions['html_content'];
            ?>
        </div>
    </div>
    
    <!-- Extension additional content -->
    <?php
    $extensionContent = HookSystem::executeHook('property_detail_page_render', [
        'property' => $property,
        'html_content' => ''
    ]);
    echo $extensionContent['html_content'];
    ?>
</div>

5. Extension Development Workflow
Creating a New Extension

// extensions/car-rentals/CarRentalsExtension.php
class CarRentalsExtension implements ExtensionInterface {
    public function getName(): string { return 'car-rentals'; }
    public function getDisplayName(): string { return 'Car Rentals'; }
    public function getVersion(): string { return '1.0.0'; }
    
    public function install(): bool {
        // Create extension-specific tables
        $sql = "
            CREATE TABLE car_rental_locations (...);
            CREATE TABLE car_rental_vehicles (...);
        ";
        // Execute SQL
        return true;
    }
    
    public function getSettingsSchema(): array {
        return [
            'enabled' => ['type' => 'boolean', 'default' => false],
            'location_display' => ['type' => 'string', 'default' => 'list'],
            'booking_flow' => ['type' => 'string', 'default' => 'standard']
        ];
    }
    
    public function getPublicRoutes(): array {
        return [
            '/car-rentals/locations' => 'CarRentalController::showLocations',
            '/car-rentals/vehicles/{id}' => 'CarRentalController::showVehicle'
        ];
    }
}

Extension Registration and Activation

// Extension installation process
$extensionManager = new ExtensionManager();
$extension = $extensionManager->loadExtension('car-rentals');

// Install database tables
if ($extension->install()) {
    // Register in extensions table
    $extensionManager->registerExtension($extension);
    
    // Activate for specific organization
    $extensionManager->activateForOrganization($extension->getName(), $orgId);
	
	// Organization-specific extension settings
$carRentalSettings = ExtensionManager::getExtensionSettings('car-rentals', $orgId);
if ($carRentalSettings && $carRentalSettings['enabled']) {
    // Show car rental content on landing page
    HookSystem::addHook('org_landing_page_render', function($data) use ($carRentalSettings) {
        $locations = CarRentalModel::getLocationsForOrg($data['organization']['id']);
        $data['html_content'] .= CarRentalView::renderLocations($locations, $carRentalSettings);
        return $data;
    });
}
}

6. Core System Impact and Benefits
Minimal Core Changes Required
Core remains focused: Multi-tenancy, auth, org management, extension framework
No business logic in core: All domain-specific logic lives in extensions
Easy maintenance: Core updates don't break extensions (with proper versioning)
Flexible scaling: Disable extensions per organization without affecting others
Extension Isolation Benefits
Organization-specific enablement: Org A can have car rentals, Org B can have hotels
Independent development: Teams can work on different extensions simultaneously
Easy updates: Update extensions without touching core
Marketplace potential: Third-party developers can create extensions
Performance Considerations
Lazy loading: Only load active extensions for current organization
Caching: Cache extension settings and hook registrations
Database isolation: Extension tables are organization-scoped
Asset optimization: Load only extension-specific assets when needed
7. Extension Security Architecture
Permission System Integration

// Extension permissions automatically integrated with core RBAC
$permissions = [
    'car-rentals' => ['manage_car_rentals', 'view_car_rentals', 'book_car_rentals'],
    'hotel-bookings' => ['manage_hotels', 'view_hotels', 'book_hotels']
];

// Core permission checking
if (Auth::hasPermission('manage_car_rentals')) {
    // Allow car rental management
}

Input Validation and Sanitization
Extension-specific validation: Each extension handles its own data validation
Core sanitization: Core provides consistent output escaping
SQL injection prevention: Extensions must use prepared statements
XSS prevention: Core handles output escaping, extensions provide clean data
File and Code Security
Extension directory permissions: 755 for directories, 644 for files
Code signing: Optional extension signature verification
Sandboxing: Extensions run in same PHP process but with isolated data
Update security: Secure extension update mechanism with integrity checks
8. Extension Marketplace and Distribution
Extension Distribution Formats
ZIP packages: Compressed extension directories
Git repositories: Direct installation from source control
Marketplace: Centralized extension directory with reviews and ratings
Private extensions: Organization-specific custom extensions
Version Management
Semantic versioning: Proper version compatibility checking
Core version requirements: Extensions declare minimum core version
Automatic updates: Secure update mechanism with rollback capability
Dependency management: Extensions can depend on other extensions
This extension architecture provides the modularity and extensibility you need while keeping the core system lean, secure, and maintainable. Organizations can enable only the functionality they need, and new business models can be added without touching the core codebase.