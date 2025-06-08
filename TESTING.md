# Comprehensive Laravel Testing Guide with Pest

## Table of Contents

1. [The Problem with Weak Tests](#the-problem-with-weak-tests)
2. [Common Weak Test Patterns to Avoid](#common-weak-test-patterns-to-avoid)
3. [Principles of Strong Tests](#principles-of-strong-tests)
4. [Modern Pest Features](#modern-pest-features)
5. [Laravel Testing Features](#laravel-testing-features)
6. [Test Data Generation](#test-data-generation)
7. [Mocking Best Practices](#mocking-best-practices)
8. [Architectural Testing](#architectural-testing)
9. [Testing Checklist](#testing-checklist)
10. [Anti-Patterns to Avoid](#anti-patterns-to-avoid)
11. [Remember](#remember)

## The Problem with Weak Tests

Weak tests give false confidence. They pass even when the code is broken, making them worse than having no tests at all. This guide shows how to write tests that actually protect your codebase using modern Pest and Laravel features.

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

## Modern Pest Features

### 1. Test Organization with `describe()` Blocks

```php
describe('OmakaseCommand', function () {
    beforeEach(function () {
        // Setup that runs before each test in this describe block
        $this->artisan = spy(Artisan::class);
    });

    describe('composer installation', function () {
        it('installs packages when --composer flag is set', function () {
            // Test implementation
        });

        it('skips installation when --skip-composer is set', function () {
            // Test implementation
        });
    });

    describe('file operations', function () {
        beforeEach(function () {
            // Additional setup for file tests
            Storage::fake('local');
        });

        it('copies configuration files', function () {
            // Test implementation
        });
    });
});
```

### 2. Higher-Order Expectations

```php
// Chain expectations on object properties and methods
expect($user)
    ->name->toBe('Nuno')
    ->email->toContain('@example.com')
    ->isAdmin()->toBeTrue();

// Work with arrays
expect(['name' => 'Pest', 'version' => 3])
    ->name->toBe('Pest')
    ->version->toBeGreaterThanOrEqual(3);
```

### 3. Custom Expectations

```php
// Define in Pest.php
expect()->extend('toBeWithinRange', function (int $min, int $max) {
    return $this->toBeGreaterThanOrEqual($min)
                ->toBeLessThanOrEqual($max);
});

// Use in tests
test('validates age', function () {
    expect($user->age)->toBeWithinRange(18, 65);
});
```

### 4. Dataset Testing

```php
it('validates emails', function (string $email, bool $expected) {
    expect(Validator::isEmail($email))->toBe($expected);
})->with([
    ['valid@example.com', true],
    ['invalid.email', false],
    ['test@sub.domain.com', true],
    ['@example.com', false],
]);
```

### 5. Hooks and Lifecycle

```php
// Global hooks in Pest.php
beforeEach(function () {
    // Runs before every test
    RefreshDatabase::class;
});

afterEach(function () {
    // Cleanup after each test
    Mockery::close();
});

// Test-specific hooks
it('processes data')
    ->before(function () {
        // Setup for this specific test
    })
    ->after(function () {
        // Cleanup for this specific test
    });
```

## Laravel Testing Features

### 1. HTTP Testing

```php
it('creates a new post', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson('/api/posts', [
            'title' => 'Test Post',
            'content' => 'Content here'
        ]);

    $response->assertCreated()
        ->assertJson([
            'data' => [
                'title' => 'Test Post',
                'author' => $user->name
            ]
        ]);
});
```

### 2. Database Testing

```php
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('stores user preferences', function () {
    $user = User::factory()->create();
    $user->updatePreferences(['theme' => 'dark']);

    // Laravel's database assertions
    $this->assertDatabaseHas('user_preferences', [
        'user_id' => $user->id,
        'theme' => 'dark'
    ]);

    $this->assertDatabaseCount('user_preferences', 1);
});
```

### 3. Time Manipulation

```php
it('locks accounts after failed attempts', function () {
    $user = User::factory()->create();

    // Attempt login 5 times
    foreach (range(1, 5) as $attempt) {
        $user->recordFailedLogin();
    }

    expect($user->isLocked())->toBeTrue();

    // Travel 30 minutes into the future
    $this->travel(30)->minutes();

    expect($user->isLocked())->toBeFalse();
});

it('expires tokens after one hour', function () {
    $token = Token::create(['expires_at' => now()->addHour()]);

    expect($token->isValid())->toBeTrue();

    // Freeze time and travel
    $this->freezeTime();
    $this->travel(61)->minutes();

    expect($token->isValid())->toBeFalse();
});
```

### 4. Mocking Laravel Services

```php
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Process;

it('sends welcome email on registration', function () {
    Mail::fake();

    $this->post('/register', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password',
        'password_confirmation' => 'password'
    ]);

    Mail::assertSent(WelcomeEmail::class, function ($mail) {
        return $mail->hasTo('john@example.com');
    });
});

it('processes files from external API', function () {
    Http::fake([
        'api.example.com/*' => Http::response(['file' => 'content'], 200)
    ]);

    Storage::fake('local');

    $this->artisan('import:files')
        ->assertSuccessful();

    Storage::disk('local')->assertExists('imports/file.txt');
    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.example.com/files';
    });
});

it('runs system commands', function () {
    Process::fake([
        'ls *' => Process::result('file1.txt file2.txt'),
        'rm *' => Process::result('', 0),
    ]);

    $service = new FileService();
    $files = $service->listFiles();

    expect($files)->toBe(['file1.txt', 'file2.txt']);

    Process::assertRan('ls *');
});
```

### 5. Testing Artisan Commands

```php
it('imports data with progress bar', function () {
    $this->artisan('import:data')
        ->expectsQuestion('Which source?', 'api')
        ->expectsOutput('Starting import...')
        ->expectsTable(
            ['ID', 'Status'],
            [
                [1, 'Success'],
                [2, 'Success'],
                [3, 'Failed'],
            ]
        )
        ->assertSuccessful();
});
```

## Test Data Generation

### Using Faker in Pest Tests

```php
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

// ❌ INCORRECT - Don't use $this->faker
test('creates user with random data', function () {
    $user = User::factory()->create([
        'name' => $this->faker->name(), // This doesn't work in Pest
    ]);
});
```

## Mocking Best Practices

### 1. Use Laravel Test Helpers

```php
// ✅ GOOD - Use Laravel's test helpers
$mock = $this->mock(PaymentGateway::class, function ($mock) {
    $mock->shouldReceive('charge')
        ->once()
        ->with(100, 'USD')
        ->andReturn(new PaymentResult(true));
});

// ✅ GOOD - Use spy for assertions after execution
$spy = $this->spy(Logger::class);

// Execute code...

$spy->shouldHaveReceived('log')
    ->once()
    ->with('Payment processed', ['amount' => 100]);
```

### 2. Mock at System Boundaries

```php
// ✅ GOOD - Mock external dependencies
Http::fake([
    'payment.api/*' => Http::response(['status' => 'success'], 200)
]);

Process::fake([
    'convert image.pdf output.jpg' => Process::result('', 0)
]);

// ❌ BAD - Don't mock what you're testing
$service = Mockery::mock(OrderService::class)->makePartial();
$service->shouldReceive('calculateTotal')->andReturn(100);
```

### 3. Use Correct Mockery Syntax

```php
// ✅ CORRECT Mockery syntax
$mock->shouldReceive('method')->once()->andReturn($value);
$mock->shouldReceive('method')->times(2)->andReturn($value);
$mock->shouldReceive('method')->never();
$mock->shouldReceive('method')->atLeast()->once();

// ❌ INCORRECT - These don't exist
$mock->shouldHaveReceived('method')->once();
$mock->shouldHaveReceived('method')->twice();
```

## Architectural Testing

### 1. Enforce Laravel Conventions

```php
// Apply Laravel preset
arch()->preset()->laravel();

// Custom architectural rules
arch('controllers')
    ->expect('App\Http\Controllers')
    ->toHavePrefix('Controller')
    ->toExtend('App\Http\Controllers\Controller')
    ->not->toHavePublicProperties();

arch('models use soft deletes')
    ->expect('App\Models')
    ->toUseTrait('Illuminate\Database\Eloquent\SoftDeletes');

arch('actions are invokable')
    ->expect('App\Actions')
    ->toBeClasses()
    ->toHaveMethod('__invoke');
```

### 2. Dependency Rules

```php
arch('domain layer')
    ->expect('App\Domain')
    ->not->toUse('App\Http')
    ->not->toUse('App\Console');

arch('no debug statements')
    ->expect('App')
    ->not->toUse(['dd', 'dump', 'var_dump', 'print_r']);
```

## Testing Checklist

Before considering a test complete, verify:

- [ ] Does the test fail when the implementation is broken?
- [ ] Does it test specific values, not just types?
- [ ] Are edge cases covered (empty, null, invalid data)?
- [ ] Are relationships verified in both directions?
- [ ] Are error conditions tested?
- [ ] Does the test use Pest's expressive syntax?
- [ ] Are Laravel's testing helpers utilized?
- [ ] Is the test organized with `describe()` blocks if complex?
- [ ] Are external services properly mocked?
- [ ] Is `fake()` used instead of `$this->faker` for data generation?
- [ ] Are assertions specific and meaningful?
- [ ] Does the test name clearly describe what's being tested?
- [ ] Are time-dependent tests using time manipulation?
- [ ] Is the test independent and can run in isolation?

## Anti-Patterns to Avoid

### 1. Over-Mocking

```php
// ❌ BAD - Mocking the very thing you're testing
$command = Mockery::mock(OmakaseCommand::class)
    ->shouldAllowMockingProtectedMethods()
    ->makePartial();

// ✅ GOOD - Mock only external dependencies
Process::fake();
Storage::fake('local');
```

### 2. Testing Framework Code

```php
// ❌ BAD - Testing that Laravel relationships work
test('user has posts relationship', function () {
    expect($user->posts())->toBeInstanceOf(HasMany::class);
});

// ✅ GOOD - Test your business logic using relationships
test('user can publish posts', function () {
    $user = User::factory()->create();
    $user->publishPost(['title' => 'Test', 'content' => 'Content']);

    expect($user->posts)->toHaveCount(1)
        ->and($user->posts->first()->is_published)->toBeTrue();
});
```

### 3. Brittle Assertions

```php
// ❌ BAD - Too specific, breaks with minor changes
expect($response->json())->toBe([
    'id' => 1,
    'created_at' => '2024-01-01T00:00:00.000000Z',
    'updated_at' => '2024-01-01T00:00:00.000000Z',
]);

// ✅ GOOD - Test what matters
expect($response->json())
    ->toHaveKey('id')
    ->data->toMatchArray([
        'title' => 'Expected Title',
        'status' => 'published'
    ]);
```

### 4. Test Duplication

```php
// ❌ BAD - Same test with different data inline
test('validates email domain gmail', function () {
    expect(EmailValidator::isAllowed('test@gmail.com'))->toBeTrue();
});

test('validates email domain yahoo', function () {
    expect(EmailValidator::isAllowed('test@yahoo.com'))->toBeTrue();
});

// ✅ GOOD - Use datasets
it('validates allowed email domains', function ($email) {
    expect(EmailValidator::isAllowed($email))->toBeTrue();
})->with([
    'gmail' => ['test@gmail.com'],
    'yahoo' => ['test@yahoo.com'],
    'outlook' => ['test@outlook.com'],
]);
```

### 5. Missing Cleanup

```php
// ❌ BAD - Not cleaning up after tests
it('creates temporary files', function () {
    $file = '/tmp/test-file.txt';
    file_put_contents($file, 'content');
    // Test implementation...
});

// ✅ GOOD - Ensure cleanup
it('creates temporary files', function () {
    $file = sys_get_temp_dir().'/test-'.uniqid().'.txt';
    file_put_contents($file, 'content');

    // Test implementation...

})->after(function () use ($file) {
    if (file_exists($file)) {
        unlink($file);
    }
});
```

### Key Principles:

1. **Write tests with Pest's expressive syntax** for better readability
2. **Use Laravel's built-in testing features** (fakes, assertions, helpers)
3. **Mock only at system boundaries** (external APIs, filesystem, processes)
4. **Test behavior, not implementation**
5. **Organize complex tests with `describe()` blocks**
6. **Use architectural testing** to enforce conventions
7. **Keep tests simple and focused** - one concept per test
8. **Leverage time manipulation** for time-dependent tests
9. **Use datasets** to avoid test duplication
10. **Ensure proper cleanup** in `afterEach()` or `after()` hooks

## Remember

> "A test that never fails is not a test, it's a lie."

Strong tests should:

- Fail immediately when functionality breaks
- Use Pest's expressive API for clarity
- Give clear error messages that help fix the problem
- Test one specific behavior at a time
- Use realistic test data with `fake()` helper
- Cover both success and failure paths
- Be maintainable and easy to understand
- Mock external dependencies appropriately
- Never make real network calls
- Run fast and in isolation

Write tests as if the person maintaining them will be you in 6 months with no memory of the code. Use descriptive test names, organize with `describe()` blocks, and leverage Pest's powerful features to create a test suite that truly protects your application.
