# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

This is a serverless PHP API deployed on AWS Lambda using the Bref framework. The API serves The Resilience Project (TRP) and integrates with Vtiger CRM to manage enquiries, registrations, confirmations, and resource ordering for Schools, Workplaces, and Early Years programs.

## Technology Stack

- **Runtime**: PHP 8.2 on AWS Lambda (via Bref)
- **Framework**: Serverless Framework
- **Deployment**: AWS Lambda with HTTP API Gateway
- **CRM Integration**: Vtiger CRM via REST API
- **Database**: MySQL (remote connection defined in `src/config.php`)

## Development Commands

### Deployment
```bash
# Deploy to AWS
serverless deploy

# Deploy a single function
serverless deploy function -f <function-name>
```

### Local Development
```bash
# Install dependencies
composer install

# Run PHP locally (if testing without serverless)
php -S localhost:8000 -t src/
```

### Logs
```bash
# View function logs
serverless logs -f <function-name> -t

# Example
serverless logs -f enquiry -t
```

## Project Architecture

### Directory Structure

```
src/
├── api/                          # API endpoint handlers
│   ├── classes/                  # Controller classes
│   │   ├── base.php             # Base VTController class
│   │   ├── school.php           # SchoolVTController
│   │   ├── workplace.php        # WorkplaceVTController
│   │   ├── early_years.php      # EarlyYearsVTController
│   │   ├── general.php          # GeneralVTController
│   │   └── traits/              # Reusable controller traits
│   │       ├── enquiry.php
│   │       ├── confirmation.php
│   │       ├── registration.php
│   │       ├── order_resources_26.php
│   │       ├── accept_dates.php
│   │       └── assess.php
│   ├── utils.php                # Request/response utilities
│   ├── api_helpers.php          # Controller class imports
│   └── [endpoint].php           # Individual endpoint files
├── lib/                         # Core library classes
│   ├── class_dhvt.php          # Vtiger REST API client
│   ├── class_dhrest.php        # Generic REST client wrapper
│   └── class_dhpdo.php         # PDO database wrapper
├── Invoices/                    # Invoice and shipment endpoints
├── Potentials/                  # CRM Potentials/Deals endpoints
├── Events/                      # Event-related endpoints
├── Webhooks/                    # Webhook handlers
├── Emails/                      # PHPMailer library
├── init.php                     # Bootstraps DB, Vtiger, and logging
├── config.php                   # Configuration (credentials, URLs)
├── functions.php                # Global helper functions
└── logs/                        # Log directory
```

### Core Components

#### Initialization Flow (`src/init.php`)
1. Loads `config.php` with database and Vtiger credentials
2. Initializes `dhpdo` database connection via `class_dhpdo.php`
3. Initializes `dhvt` Vtiger client via `class_dhvt.php`
4. Loads global `functions.php` helper functions
5. Provides logging functions: `log_debug()`, `log_info()`, `log_warning()`, `log_error()`, `log_exception()`

#### Controller Pattern
All API endpoints follow a controller-based pattern:

1. **Endpoint file** (e.g., `src/api/enquiry.php`):
   - Includes `utils.php`, `api_helpers.php`, and `init.php`
   - Gets HTTP method and request data via `get_method()` and `get_request_data()`
   - Instantiates appropriate controller based on `service_type` parameter
   - Calls controller method (e.g., `submit_enquiry()`)
   - Returns JSON response via `send_response()`

2. **Controller classes** (`src/api/classes/`):
   - Extend `VTController` base class
   - Use traits for shared functionality (e.g., `Enquiry`, `Confirmation`, `Registration`)
   - Handle Vtiger CRM operations via `$this->post_request_to_vt()`
   - Service types: "School", "Workplace", "Early Years", "Imperfects", or general

3. **Controller traits** (`src/api/classes/traits/`):
   - Encapsulate reusable business logic (enquiry submission, order processing, etc.)
   - Mixed into controller classes via PHP `use` statements
   - Example: `Enquiry` trait provides `submit_enquiry()` method

#### Vtiger Integration (`src/lib/class_dhvt.php`)
- Handles authentication with Vtiger CRM
- Methods: `retrieve()`, `create()`, `update()`, `query()`, `retrieveAllRelated()`, `addRelated()`
- Automatically manages session tokens via challenge-response authentication
- Global instance `$vtod` available after `init.php`

#### Logging
All endpoints should use the logging functions defined in `init.php`:
- `log_info()` - General information
- `log_debug()` - Debugging details
- `log_warning()` - Warning messages
- `log_error()` - Error messages
- `log_exception($exception, $context)` - Exception logging

Always include context arrays with relevant data (service type, organization name, etc.)

## Common Patterns

### Adding a New Endpoint

1. Create endpoint file in `src/api/` (e.g., `new_endpoint.php`)
2. Include required files:
   ```php
   require dirname(__FILE__)."/utils.php";
   require dirname(__FILE__)."/api_helpers.php";
   require dirname(__FILE__)."/../init.php";
   ```
3. Set CORS headers
4. Get method and data:
   ```php
   $method = get_method();
   $data = get_request_data();
   ```
5. Add logging throughout the process
6. Instantiate appropriate controller and call method
7. Return response via `send_response()`
8. Add function definition to `serverless.yml` under `functions:`

### Working with Vtiger CRM
```php
global $vtod; // Vtiger client instance

// Retrieve a record
$record = $vtod->retrieve('5x12345');

// Create a record
$data = ['field' => 'value'];
$result = $vtod->create('ModuleName', $data);

// Query records
$query = "SELECT * FROM Contacts WHERE email='test@example.com';";
$results = $vtod->query($query);

// Update a record
$record['field'] = 'new value';
$vtod->update($record);
```

### Error Handling
Always wrap operations in try-catch blocks and use logging:
```php
try {
    log_info("Starting operation", ['context_key' => 'value']);
    // ... operation code
    log_info("Operation completed successfully");
} catch (Exception $e) {
    log_exception($e, ['endpoint' => 'endpoint_name', 'additional_context' => 'value']);
    send_response(['status' => 'fail', 'message' => $e->getMessage()], 500);
}
```

## Important Notes

### Configuration
- **Never commit** `src/config.php` with real credentials - contains database passwords, Vtiger access keys, and mail server credentials
- The config file currently contains staging/development credentials

### Service Types
The API handles multiple service types with different controllers:
- **School**: School Partnership Programs
- **Workplace**: Workplace programs
- **Early Years**: Early Years programs
- **Imperfects**: Imperfects program
- **General**: Fallback for other enquiries

### Year Versioning
- Current year: **2026** (see `school.php` line 23)
- Previous year endpoints may exist for historical data (e.g., `order_resources_2026.php` vs `order_resources.php`)
- When creating new year versions, update deal names, quote names, and invoice names in controller classes

### Database Access
Global `$dbh` (dhpdo instance) available after `init.php` for direct database queries when needed

### Lambda Environment
- No persistent filesystem - logs go to CloudWatch via `error_log()`
- Use `/tmp` directory for temporary file storage (10GB, cleared between cold starts)
- Function timeout is managed by Bref and serverless.yml configuration
