# Grant Factory Usage Examples

This document provides examples of how to use the GrantFactory for testing and seeding data.

## Basic Factory Usage

### Create a single grant
```php
$grant = Grant::factory()->create();
```

### Create multiple grants
```php
$grants = Grant::factory()->count(10)->create();
```

## State-based Factory Methods

### Create Active Grants
```php
// Active grants (no end date or future end date)
$activeGrants = Grant::factory()->count(5)->active()->create();
```

### Create Expired Grants
```php
// Grants that have already ended
$expiredGrants = Grant::factory()->count(3)->expired()->create();
```

### Create Grants Ending Soon
```php
// Grants ending within 30 days
$endingSoonGrants = Grant::factory()->count(2)->endingSoon()->create();
```

### Create Permanent Grants
```php
// Grants with no end date (permanent operational funding)
$permanentGrants = Grant::factory()->count(2)->permanent()->create();
```

## Grant Type Methods

### Create Research Grants
```php
$researchGrants = Grant::factory()->count(5)->research()->create();
```

### Create Operational Grants
```php
$operationalGrants = Grant::factory()->count(3)->operational()->create();
```

## Subsidiary-specific Grants

### Create grants for specific subsidiaries
```php
// SMRU grants
$smruGrants = Grant::factory()->count(3)->forSubsidiary('SMRU')->create();

// BHF grants
$bhfGrants = Grant::factory()->count(2)->forSubsidiary('BHF')->create();

// MORU grants
$moruGrants = Grant::factory()->count(4)->forSubsidiary('MORU')->create();

// OUCRU grants
$oucruGrants = Grant::factory()->count(2)->forSubsidiary('OUCRU')->create();
```

## Combining States

### Active research grants
```php
$activeResearch = Grant::factory()
    ->count(5)
    ->research()
    ->active()
    ->create();
```

### Expired operational grants for SMRU
```php
$expiredSmruOperational = Grant::factory()
    ->count(2)
    ->operational()
    ->expired()
    ->forSubsidiary('SMRU')
    ->create();
```

### Research grants ending soon for BHF
```php
$bhfEndingSoon = Grant::factory()
    ->count(3)
    ->research()
    ->endingSoon()
    ->forSubsidiary('BHF')
    ->create();
```

## Testing Examples

### Use in PHPUnit tests
```php
// In your test file
public function test_can_list_active_grants()
{
    // Arrange
    Grant::factory()->count(5)->active()->create();
    Grant::factory()->count(3)->expired()->create();
    
    // Act
    $response = $this->get('/api/grants');
    
    // Assert
    $response->assertStatus(200);
    // Additional assertions...
}

public function test_can_filter_grants_by_subsidiary()
{
    // Arrange
    Grant::factory()->count(3)->forSubsidiary('SMRU')->create();
    Grant::factory()->count(2)->forSubsidiary('BHF')->create();
    
    // Act
    $response = $this->get('/api/grants?filter_subsidiary=SMRU');
    
    // Assert
    $response->assertStatus(200);
    $response->assertJsonCount(3, 'data');
}
```

## Advanced Seeding Scenarios

### Create a complete test dataset
```php
// Create a diverse set of grants for comprehensive testing
Grant::factory()->count(10)->research()->active()->create();
Grant::factory()->count(5)->operational()->active()->create();
Grant::factory()->count(8)->expired()->create();
Grant::factory()->count(3)->endingSoon()->create();
Grant::factory()->count(4)->permanent()->create();

// Create subsidiary-specific grants
foreach (['SMRU', 'BHF', 'MORU', 'OUCRU'] as $subsidiary) {
    Grant::factory()
        ->count(2)
        ->forSubsidiary($subsidiary)
        ->research()
        ->active()
        ->create();
}
```

### Performance testing dataset
```php
// Create large dataset for performance testing
Grant::factory()->count(1000)->create();
```

## Data Characteristics

The GrantFactory generates realistic data with:

- **Grant Codes**: Following pattern like `S2023-NIH-1234`, `B2024-WHO-5678`
- **Names**: Research-focused or operational names with location suffixes
- **Subsidiaries**: SMRU, BHF, MORU, OUCRU (based on actual project structure)
- **Descriptions**: Contextual descriptions relevant to healthcare/research
- **End Dates**: Realistic date ranges based on grant type
- **Audit Fields**: Realistic created_by/updated_by values

## Quick Test Commands

### Artisan Tinker Examples
```bash
# Run these in `php artisan tinker`

# Create 5 random grants
Grant::factory()->count(5)->create();

# Show all grants with status
Grant::all()->map(fn($g) => [$g->code, $g->name, $g->status, $g->subsidiary]);

# Test model scopes
Grant::active()->count();
Grant::expired()->count();
Grant::endingSoon()->count();

# Test filtering
Grant::bySubsidiary('SMRU')->count();
Grant::search('research')->count();
```