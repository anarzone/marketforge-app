# Laravel Internals — Interview Knowledge Base

Based on mock interview gaps identified on April 9, 2026.

---

## Laravel Request → Response Lifecycle

### 1. Client → Nginx → PHP-FPM
Nginx receives HTTP request. If static file, serves directly. If PHP, forwards to PHP-FPM via FastCGI. PHP-FPM picks an idle worker from its pool.

### 2. public/index.php
Single entry point for ALL requests. Requires Composer autoloader, creates Application instance from `bootstrap/app.php`.

### 3. Bootstrap & Service Providers
Application boots up:
- Loads .env variables
- Loads all config files
- Registers error handlers
- Registers Facades
- **Registers and boots ALL service providers** — this is where every Laravel feature gets initialized (database, queue, cache, auth, routing)

**This happens EVERY request.** That's why `config:cache`, `route:cache`, and OPcache matter.

### 4. Global Middleware Pipeline
Request passes through middleware layers (onion model). Each can modify request, reject early, or pass through. Response passes back through in REVERSE order.

### 5. Router
Matches URL + HTTP method to route. Runs route-specific middleware. Resolves controller from container (dependency injection via constructor type hints).

### 6. Controller
Executes business logic. Returns Response.

### 7. Response back through Middleware
Response travels through middleware in reverse. Middleware can add headers, cookies.

### 8. Send & Terminate
`$response->send()` outputs to client. THEN `$kernel->terminate()` runs cleanup (session saving, logging) — client already has the response.

**Key insight:** Everything rebuilds from scratch every request. No persistent state between requests (unlike Node.js/Java).

---

## Service Container

### The Problem It Solves
Without it, every class that needs `PaymentService` must know how to build it AND all its dependencies. Change a constructor? Update 20 files. Swap implementations? Update 20 files.

### How It Works
A registry that knows HOW to build objects. You tell it once: "When someone asks for `PaymentGatewayPort`, give them `StripeAdapter`." The container builds the whole dependency tree automatically.

### Auto-wiring
Laravel reads constructor type hints and resolves dependencies automatically:
```
class PaymentController {
    public function __construct(private PaymentService $service) {}
}
// Container sees "needs PaymentService" → resolves it and all ITS dependencies
```

### Binding Types
```php
// Simple binding — new instance each time
$this->app->bind(PaymentGatewayPort::class, StripeAdapter::class);

// Singleton — same instance reused within one request lifecycle
$this->app->singleton(PaymentGatewayPort::class, StripeAdapter::class);

// Complex binding with config
$this->app->bind(PaymentGatewayPort::class, function ($app) {
    return new StripeAdapter(
        apiKey: config('services.stripe.secret'),
        httpClient: $app->make(HttpClient::class),
    );
});
```

**bind vs singleton:** `bind` = new instance every time. `singleton` = one instance per request. PSP adapters = singleton (don't need 5 Stripe connections per request).

---

## Service Providers: register() vs boot()

### The Problem
~30 providers need to initialize. Some depend on things OTHER providers register. One-phase init = ordering problems.

### Two Phases

**Phase 1: `register()` — ONLY bind things into the container.**
All providers' `register()` runs first. Rule: NEVER use other services here.
```php
public function register(): void
{
    $this->app->singleton(PaymentGatewayPort::class, StripeAdapter::class);
    // Don't resolve services, don't define routes, don't register listeners
}
```

**Phase 2: `boot()` — use anything you need.**
All providers' `boot()` runs after all `register()` calls complete. The container is fully populated.
```php
public function boot(): void
{
    Route::middleware('api')->group(/* routes */);
    Event::listen(PaymentCaptured::class, SendNotification::class);
}
```

**Analogy:** `register()` = deliver all building materials to the site. `boot()` = assemble them. You can't install plumbing if the pipes haven't arrived yet.

---

## Middleware

### What It Is
Code that sits between request and controller. Like security checkpoints at an airport — each can inspect, modify, or reject.

### How the Pipeline Works
Each middleware receives `$request` and a `$next` closure:
```php
public function handle(Request $request, Closure $next): Response
{
    // BEFORE controller (request going in)
    // ... check something, modify request
    
    $response = $next($request);  // Pass to next middleware → eventually controller
    
    // AFTER controller (response coming back)
    // ... modify response, add headers
    
    return $response;
}
```

### Three Levels
- **Global:** Runs on every request (CORS, maintenance mode, CSRF)
- **Route group:** Applied to groups (`api` has throttling, `web` has sessions)
- **Route-specific:** Individual routes (`auth:sanctum`, `throttle:60,1`)

### Idempotency Middleware Example (Payment Systems)
Prevents duplicate payment processing. Checks `Idempotency-Key` header:
1. Extract key from header
2. Redis SETNX: try to acquire the key atomically
3. Key doesn't exist → first request, let it through, cache the response
4. Key exists with "processing" → duplicate while first is running, return 409
5. Key exists with stored response → return cached response, controller never called

This is a cross-cutting concern — middleware is the right place because it applies uniformly across all payment mutation endpoints.

---

## N+1 Query Problem

### What It Is
1 query to fetch N records, then N additional queries for each record's relationship. 100 orders = 101 queries instead of 2.

### Fix: Eager Loading
```php
// BAD — 101 queries
$orders = Order::all();
foreach ($orders as $order) {
    echo $order->customer->name; // Triggers a query each iteration
}

// GOOD — 2 queries
$orders = Order::with('customer')->get();
// Query 1: SELECT * FROM orders
// Query 2: SELECT * FROM customers WHERE id IN (1, 2, 3, ...)
```

### Detection Methods
- **Laravel Debugbar:** Shows all queries per request
- **`Model::preventLazyLoading()`:** Throws exception in development when lazy loading happens

### Variations
```php
// Nested eager loading
Order::with('customer.address')->get();

// Eager load with constraints
Order::with(['items' => fn($q) => $q->where('status', 'shipped')])->get();

// Count without loading
Order::withCount('items')->get(); // Adds items_count attribute
```

---

## Authentication vs Authorization

### Authentication = WHO are you?
Verifying identity. Laravel Sanctum for API tokens:
- User logs in → receives API token
- Subsequent requests include `Authorization: Bearer token`
- Sanctum validates token, resolves user

### Authorization = WHAT can you do?
Checking permissions AFTER knowing who they are.

**Three approaches in Laravel:**

**1. Middleware (role-based, coarse):**
```php
Route::middleware(['auth:sanctum', 'role:seller'])->group(function () {
    // Only sellers can access these routes
});
```

**2. Policies (resource-level, fine-grained):**
```php
class ProductPolicy {
    public function update(User $user, Product $product): bool {
        if ($user->role === 'seller') {
            return $product->seller_id === $user->id; // Own products only
        }
        return $user->role === 'admin'; // Admins can edit any
    }
}

// In controller:
$this->authorize('update', $product); // Returns 403 if unauthorized
```

**3. Gates (non-resource actions):**
```php
Gate::define('process-payouts', fn(User $user) => $user->role === 'admin');
```

**Critical rule:** Never trust the client. Always verify permissions server-side on every mutation endpoint.
