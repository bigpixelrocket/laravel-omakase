# Comprehensive Testing Guide

## Table of Contents

1. [The Problem with Weak Tests](#the-problem-with-weak-tests)
2. [Common Weak Test Patterns to Avoid](#common-weak-test-patterns-to-avoid)
3. [Principles of Strong Tests](#principles-of-strong-tests)
4. [Test Data Generation](#test-data-generation)
5. [Unit Test Mocking Strategy](#unit-test-mocking-strategy)
6. [cURL Mocking Architecture](#curl-mocking-architecture)
7. [Real-World Examples](#real-world-examples)
8. [Testing Checklist](#testing-checklist)
9. [Anti-Patterns to Avoid](#anti-patterns-to-avoid)
10. [Remember](#remember)

## The Problem with Weak Tests

Weak tests give false confidence. They pass even when the code is broken, making them worse than having no tests at all. This guide shows how to write tests that actually protect your codebase.

## Common Weak Test Patterns to Avoid

### 1. Type-Only Assertions

```php
// ❌ WEAK - Only checks type
expect($user->company)->toBeInstanceOf(Company::class);

// ✅ STRONG - Verifies the actual relationship
expect($user->company->id)->toBe($expectedCompany->id)
    ->and($user->company->name)->toBe('Expected Company');
```

### 2. Generic Array Assertions

```php
// ❌ WEAK - Passes with any array
expect($response)->toBeArray();

// ✅ STRONG - Verifies structure and content
expect($response)
    ->toBeArray()
    ->toHaveCount(3)
    ->toHaveKeys(['id', 'name', 'status'])
    ->and($response['status'])->toBe('active');
```

### 3. Not-Null Assertions

```php
// ❌ WEAK - Only checks existence
expect($property->address)->not->toBeNull();

// ✅ STRONG - Verifies actual value
expect($property->address)->toBe('123 Main St, Dallas, TX 75001');
```

### 4. Boolean Assertions Without Context

```php
// ❌ WEAK - No context about why it should be true
expect($result)->toBeTrue();

// ✅ STRONG - Test the behavior that leads to the result
$user = User::factory()->create(['email_verified_at' => null]);
$user->markEmailAsVerified();
expect($user->hasVerifiedEmail())->toBeTrue()
    ->and($user->email_verified_at)->toBeInstanceOf(Carbon::class);
```

## Principles of Strong Tests

### 1. Test Behavior, Not Implementation

```php
// ❌ WEAK - Tests implementation details
$handler = new Handler();
expect($handler->getCurlOptions())->toContain(CURLOPT_RETURNTRANSFER);

// ✅ STRONG - Tests actual behavior
$response = $handler->fetchData('https://api.example.com/users');
expect($response)
    ->toHaveKey('users')
    ->and($response['users'])->toHaveCount(10);
```

### 2. Test Edge Cases and Error Conditions

```php
test('handles empty responses gracefully', function () {
    $handler = new DataHandler();

    // Test empty array
    $result = $handler->processData([]);
    expect($result)->toBe([]);

    // Test null
    expect(fn() => $handler->processData(null))
        ->toThrow(InvalidArgumentException::class, 'Data cannot be null');

    // Test malformed data
    $result = $handler->processData(['invalid' => 'structure']);
    expect($result)->toBe(['errors' => ['Invalid data structure']]);
});
```

### 3. Verify Both Directions of Relationships

```php
test('user belongs to correct team', function () {
    $team = Team::factory()->create(['name' => 'Engineering']);
    $user = User::factory()->create(['team_id' => $team->id]);

    // Test forward relationship
    expect($user->team->id)->toBe($team->id);

    // Test inverse relationship
    $team->load('users');
    expect($team->users->pluck('id'))->toContain($user->id);

    // Verify other teams don't have this user
    $otherTeam = Team::factory()->create();
    expect($otherTeam->users)->toBeEmpty();
});
```

### 4. Test State Transitions

```php
test('order status transitions correctly', function () {
    $order = Order::factory()->create(['status' => 'pending']);

    // Can transition from pending to processing
    $order->transitionTo('processing');
    expect($order->status)->toBe('processing')
        ->and($order->processed_at)->toBeInstanceOf(Carbon::class);

    // Cannot skip to completed
    $order = Order::factory()->create(['status' => 'pending']);
    expect(fn() => $order->transitionTo('completed'))
        ->toThrow(InvalidStateTransition::class);
});
```

### 5. Use Specific Test Data

```php
// ❌ WEAK - Generic test data
$user = User::factory()->create();

// ✅ STRONG - Specific data that tests boundaries
$user = User::factory()->create([
    'email' => 'test+special.chars@sub.example.com', // Tests email validation
    'name' => "O'Brien-Smith", // Tests special characters
    'age' => 17, // Tests age restrictions
]);
```

### 6. Test Inverse Operations

```php
test('encryption and decryption work correctly', function () {
    $original = 'sensitive data';
    $encrypted = encrypt($original);

    expect($encrypted)->not->toBe($original)
        ->and($encrypted)->toBeString();

    $decrypted = decrypt($encrypted);
    expect($decrypted)->toBe($original);

    // Test corrupted data
    expect(fn() => decrypt('invalid-encrypted-data'))
        ->toThrow(DecryptionException::class);
});
```

## Test Data Generation

### Using Faker in PEST Tests

When generating fake data in PEST tests, always use the `fake()` helper function instead of `$this->faker`:

```php
// ❌ INCORRECT - Don't use $this->faker
test('creates user with random data', function () {
    $user = User::factory()->create([
        'name' => $this->faker->name(),
        'email' => $this->faker->unique()->safeEmail(),
    ]);
});

// ✅ CORRECT - Use fake() helper
test('creates user with random data', function () {
    $user = User::factory()->create([
        'name' => fake()->name(),
        'email' => fake()->unique()->safeEmail(),
        'phone' => fake()->phoneNumber(),
        'address' => fake()->streetAddress(),
    ]);

    expect($user->name)->toBeString()
        ->and($user->email)->toContain('@');
});
```

The `fake()` helper provides access to the same Faker instance but works better with PEST's architecture.

## Testing Checklist

Before considering a test complete, verify:

- [ ] Does the test fail when the implementation is broken?
- [ ] Does it test specific values, not just types?
- [ ] Are edge cases covered (empty, null, invalid data)?
- [ ] Are relationships verified in both directions?
- [ ] Are error conditions tested?
- [ ] Would the test catch common bugs (off-by-one, null pointer, type mismatches)?
- [ ] Is the test data realistic and specific?
- [ ] Does the test name clearly describe what's being tested?
- [ ] Are external API calls properly mocked?
- [ ] Is `fake()` used instead of `$this->faker` for data generation?
- [ ] Are mock responses realistic and match actual API responses?

## Anti-Patterns to Avoid

1. **Over-mocking**: Mocking the very thing you're testing
2. **Testing Laravel/Framework code**: Don't test that Eloquent relationships work
3. **Snapshot tests without assertions**: Snapshots should supplement, not replace assertions
4. **Testing private methods directly**: Test through public interfaces
5. **Interdependent tests**: Each test should be independent
6. **Mystery guests**: Don't rely on data from other tests
7. **Real API calls in unit tests**: Always mock external services
8. **Using `$this->faker` instead of `fake()`**: Use the global helper
9. **Generic mock responses**: Use realistic data that matches actual API responses
10. **Not resetting mocks**: Always call `resetCurlMocks()` in `beforeEach`

## Remember

> "A test that never fails is not a test, it's a lie."

Strong tests should:

- Fail immediately when functionality breaks
- Give clear error messages that help fix the problem
- Test one specific behavior at a time
- Use realistic test data
- Cover both success and failure paths
- Be maintainable and easy to understand
- Mock external dependencies appropriately
- Never make real network calls

Write tests as if the person maintaining them will be you in 6 months with no memory of the code. Document why, not just what, especially for complex mocking scenarios.
