# Symfony Internals — Interview Knowledge Base

Based on mock interview gaps identified on April 9, 2026.

---

## Symfony DI Container vs Laravel

### Key Difference: Compile-Time vs Runtime

**Symfony:** Container is **compiled** at build time into an optimized PHP class. When you run `cache:clear` or on first request:
1. Scans all service classes
2. Reads constructor type hints
3. Builds complete dependency graph
4. Compiles into a single PHP class (`var/cache/prod/Container*.php`)
5. At runtime: just calls `new StripeAdapter($arg1, $arg2)` — no reflection

**Laravel:** Container resolves dependencies at **runtime** using reflection on every request.

### Comparison Table

| Aspect | Laravel | Symfony |
|---|---|---|
| Resolution | Runtime (every request) | Compile-time (pre-built) |
| Configuration | PHP code in ServiceProviders | YAML/XML/PHP + attributes |
| Autowiring | Runtime reflection | Compile-time, generates optimized code |
| Default sharing | `bind()` = new instance, opt-in to singleton | All services shared by default |
| Performance | Slightly slower (runtime reflection) | Faster (compiled, no reflection) |

### Three Ways to Configure Symfony Services

**YAML (traditional):**
```yaml
services:
    App\Payment\Domain\Port\PaymentGatewayPort:
        class: App\Payment\Infrastructure\StripeAdapter
        arguments:
            $apiKey: '%env(STRIPE_SECRET)%'
```

**PHP Attributes (modern):**
```php
#[AsAlias(PaymentGatewayPort::class)]
class StripeAdapter implements PaymentGatewayPort {}
```

**PHP configuration:**
```php
$services->set(PaymentGatewayPort::class, StripeAdapter::class);
```

### Compiler Passes
Symfony's power feature with no Laravel equivalent. Hook into container compilation to modify service definitions programmatically. Example: auto-find all classes implementing `PaymentGatewayPort` and register them in a PSP registry.

### Key Takeaway for Interviews
"At plusForta, I worked with Symfony's compiled DI container. I configured services via YAML and autowiring, and understood the compile-time vs runtime trade-off compared to Laravel's approach. Symfony's compiled container is faster at runtime because all resolution work is done once during compilation."
