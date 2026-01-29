# Eloquent Models Implementation Summary

## Task 2.2: Buat Eloquent Models dengan relationships

### Overview
Successfully created all Eloquent models with complete relationships, fillable attributes, casts, hidden attributes, and encryption for password fields as specified in the design document.

### Models Created

#### 1. **Customer** (`app/Models/Customer.php`)
- **Fillable**: name, phone, address, ktp_number, ktp_path, latitude, longitude, status, tenant_id
- **Casts**: latitude (decimal:8), longitude (decimal:8)
- **Relationships**:
  - `hasMany` → Services
  - `hasMany` → Tickets

#### 2. **Service** (`app/Models/Service.php`)
- **Fillable**: customer_id, package_id, mikrotik_id, username_pppoe, password_encrypted, ip_address, mikrotik_user_id, status, activation_date, expiry_date
- **Hidden**: password_encrypted
- **Casts**: activation_date (date), expiry_date (date)
- **Encryption**: password_encrypted (using Laravel Crypt)
- **Relationships**:
  - `belongsTo` → Customer
  - `belongsTo` → Package
  - `belongsTo` → MikrotikRouter (as mikrotik_id)
  - `hasMany` → Invoices
  - `hasOne` → OdpPort

#### 3. **Package** (`app/Models/Package.php`)
- **Fillable**: name, speed, price, type, fup_threshold, fup_speed, is_active
- **Casts**: price (decimal:2), fup_threshold (integer), is_active (boolean)
- **Relationships**:
  - `hasMany` → Services

#### 4. **MikrotikRouter** (`app/Models/MikrotikRouter.php`)
- **Fillable**: name, ip_address, username, password_encrypted, api_port, snmp_community, is_active
- **Hidden**: password_encrypted
- **Casts**: api_port (integer), is_active (boolean)
- **Encryption**: password_encrypted (using Laravel Crypt)
- **Relationships**:
  - `hasMany` → Services
  - `hasMany` → DeviceMonitoring

#### 5. **Invoice** (`app/Models/Invoice.php`)
- **Fillable**: service_id, amount, status, invoice_date, due_date, payment_link, paid_at
- **Casts**: amount (decimal:2), invoice_date (date), due_date (date), paid_at (datetime)
- **Relationships**:
  - `belongsTo` → Service
  - `hasMany` → Payments

#### 6. **Payment** (`app/Models/Payment.php`)
- **Fillable**: invoice_id, payment_gateway, transaction_id, amount, status, metadata
- **Casts**: amount (decimal:2), metadata (array)
- **Relationships**:
  - `belongsTo` → Invoice

#### 7. **Ticket** (`app/Models/Ticket.php`)
- **Fillable**: customer_id, assigned_to, subject, description, status, priority
- **Relationships**:
  - `belongsTo` → Customer
  - `belongsTo` → User (as assigned_to)

#### 8. **Odp** (`app/Models/Odp.php`)
- **Fillable**: name, location, latitude, longitude
- **Casts**: latitude (decimal:8), longitude (decimal:8)
- **Relationships**:
  - `hasMany` → OdpPorts

#### 9. **OdpPort** (`app/Models/OdpPort.php`)
- **Fillable**: odp_id, port_number, service_id, status
- **Casts**: port_number (integer)
- **Relationships**:
  - `belongsTo` → Odp
  - `belongsTo` → Service

#### 10. **DeviceMonitoring** (`app/Models/DeviceMonitoring.php`)
- **Fillable**: router_id, cpu_usage, temperature, uptime, traffic_in, traffic_out, recorded_at
- **Casts**: cpu_usage (float), temperature (float), uptime (integer), traffic_in (integer), traffic_out (integer), recorded_at (datetime)
- **Timestamps**: Disabled (uses recorded_at instead)
- **Relationships**:
  - `belongsTo` → MikrotikRouter (as router_id)

#### 11. **AuditLog** (`app/Models/AuditLog.php`)
- **Fillable**: user_id, action, model_type, model_id, old_values, new_values, ip_address
- **Casts**: old_values (array), new_values (array)
- **Relationships**:
  - `belongsTo` → User

#### 12. **User** (`app/Models/User.php`) - Updated
- **Fillable**: Added role, tenant_id
- **New Relationships**:
  - `hasMany` → Tickets (as assignedTickets)
  - `hasMany` → AuditLogs

### Key Features Implemented

#### 1. **Encryption**
- Implemented Laravel Crypt encryption for sensitive password fields:
  - `Service.password_encrypted` (PPPoE passwords)
  - `MikrotikRouter.password_encrypted` (Router API passwords)
- Encryption is transparent using Eloquent Attribute accessors/mutators
- Passwords are automatically encrypted on set and decrypted on get

#### 2. **Hidden Attributes**
- Password fields are hidden from array/JSON serialization for security:
  - `Service.password_encrypted`
  - `MikrotikRouter.password_encrypted`

#### 3. **Type Casting**
- Proper type casting for all attributes:
  - Decimals for monetary values and coordinates
  - Dates and datetimes for temporal data
  - Booleans for flags
  - Arrays for JSON fields
  - Integers for numeric IDs and counts

#### 4. **Relationships**
- Complete bidirectional relationships between all models
- Proper foreign key naming conventions
- Cascade and restrict delete behaviors aligned with migrations

### Testing

Created comprehensive unit tests to verify:

#### **ModelRelationshipsTest** (22 tests)
- Verifies all relationships return correct Eloquent relationship types
- Tests hasMany, belongsTo, and hasOne relationships
- All 22 tests passing ✓

#### **EncryptionTest** (6 tests)
- Verifies password encryption/decryption works correctly
- Tests that encrypted values differ from plaintext
- Tests that passwords are hidden in array serialization
- Tests null password handling
- All 6 tests passing ✓

#### **ModelAttributesTest** (14 tests)
- Verifies fillable attributes are correctly defined
- Tests type casting for all models
- Verifies hidden attributes configuration
- All 14 tests passing ✓

**Total: 42 tests, 77 assertions - All passing ✓**

### Requirements Validated

✅ **Requirement 2.4**: Service provisioning creates complete record with relationships
✅ **Requirement 15.1**: Password encryption implemented for PPPoE and Mikrotik passwords

### Files Created

**Models:**
- `app/Models/Customer.php`
- `app/Models/Service.php`
- `app/Models/Package.php`
- `app/Models/MikrotikRouter.php`
- `app/Models/Invoice.php`
- `app/Models/Payment.php`
- `app/Models/Ticket.php`
- `app/Models/Odp.php`
- `app/Models/OdpPort.php`
- `app/Models/DeviceMonitoring.php`
- `app/Models/AuditLog.php`

**Tests:**
- `tests/Unit/Models/ModelRelationshipsTest.php`
- `tests/Unit/Models/EncryptionTest.php`
- `tests/Unit/Models/ModelAttributesTest.php`

**Updated:**
- `app/Models/User.php` (added relationships and fillable attributes)

### Next Steps

The models are now ready for use in:
- Task 2.3: Property tests for model relationships
- Task 3: Authentication and Authorization System
- Task 4: Customer Management Module
- All subsequent feature implementations

### Notes

- All models follow Laravel 12 best practices
- Encryption uses Laravel's built-in Crypt facade for security
- Relationships are properly typed for IDE support
- Models are fully tested and production-ready
