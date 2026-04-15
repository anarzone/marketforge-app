# PHP Internals & Language — Interview Knowledge Base

Based on mock interview gaps identified on April 9, 2026.

---

## PHP Execution Pipeline

When a PHP file executes, it goes through these steps:

### 1. Lexer (Tokenizer)
Reads raw PHP source code character by character and chops it into **tokens** — the smallest meaningful pieces. `$payment->capture()` becomes tokens: T_VARIABLE (`$payment`), T_OBJECT_OPERATOR (`->`), T_STRING (`capture`), `(`, `)`. At this stage PHP doesn't understand what anything means — just splitting text.

### 2. Parser (AST Builder)
Takes tokens and builds an **Abstract Syntax Tree** — a tree representing the logical structure of your code. Now PHP understands that `capture` is a method call on `$payment`. **Syntax errors are caught here.**

### 3. Compiler (AST → Opcodes)
Walks the AST and produces **opcodes** — low-level instructions for the Zend Virtual Machine. These are NOT machine code — they're bytecode that only PHP's Zend Engine understands (like Java bytecode for the JVM).

### 4. Zend VM (Execution)
Reads opcodes one by one and executes them. Manages memory (reference counting, GC), calls functions, interacts with extensions (PDO, Redis, curl), produces output.

### OPcache — Where It Fits
Without OPcache, steps 1-3 happen on EVERY request. OPcache stores compiled opcodes in shared memory.
- **First request:** Lex → Parse → Compile → Cache → Execute
- **Second request:** Cache hit → Execute (steps 1-3 skipped!)

This gives **2-3x performance improvement** on Laravel apps. Non-negotiable in production.

**OPcache preloading (PHP 7.4+):** Load and compile specific files at PHP-FPM startup. Classes are permanently in memory, shared across all workers. Trade-off: must restart PHP-FPM on code changes.

### JIT (PHP 8.0+)
Sits on top of OPcache. Watches frequently executed opcodes ("hot paths") and compiles them into **actual native machine code**. CPU runs them directly instead of Zend VM interpreting.

**Barely matters for web apps:** Typical Laravel requests spend most time waiting for DB/Redis/HTTP. JIT speeds up CPU-bound work — image processing, math, data crunching. For a 200ms web request where 180ms is I/O, JIT saves maybe 2ms.

---

## Abstract Class vs Interface

### Interface
- Defines a **contract** — WHAT methods must exist
- A class can implement **MULTIPLE** interfaces
- Since PHP 8.0, interfaces CAN have default method implementations
- Used for: defining ports in hexagonal architecture

**Payment example:** `PaymentGatewayPort` interface — defines `authorize()`, `capture()`, `refund()`. The domain depends on this interface.

### Abstract Class
- Can have concrete methods WITH bodies (shared logic) AND abstract methods (children MUST implement)
- A class can extend only **ONE** abstract class
- Can have properties, constructors, visibility modifiers
- Used for: sharing common implementation between related classes

**Payment example:** `AbstractPspAdapter` implements `PaymentGatewayPort`, contains shared HTTP client setup, retry logic, response logging. `StripeAdapter extends AbstractPspAdapter` only implements PSP-specific parts.

### When to choose:
- **Interface:** When defining a contract/port that multiple unrelated classes implement
- **Abstract class:** When related classes share common implementation
- **Composition over inheritance:** Sometimes inject shared behavior as a dependency (Decorator pattern) instead of abstract class — more flexible

---

## == vs === (Strict Comparison)

`==` does **type juggling** — PHP converts types before comparing. Creates dangerous surprises:
- `0 == "free"` → true
- `null == 0` → true  
- `"" == 0` → true
- `"0" == false` → true

**Why critical for payments:**

A €0 authorization (card verification) could be treated as "no amount provided" because `"0" == false` is true. An empty discount string `""` equals `0` with loose comparison, silently applying zero discount instead of rejecting invalid input.

**Rule:** Always use `===` in payment systems. Use `declare(strict_types=1)` at the top of every file. PHPStan with strict rules flags `==` usage.

---

## Testing & TDD

### Red-Green-Refactor Cycle

**🔴 RED:** Write a test for behavior that doesn't exist. It MUST fail.

**🟢 GREEN:** Write the MINIMUM code to make the test pass. No edge cases, no perfect code.

**🔵 REFACTOR:** Clean up the code. The test is your safety net.

Then repeat for the next behavior.

### When to TDD vs When Not

- **Domain logic: strict TDD.** Refund policies, state transitions, commission calculations.
- **Infrastructure code: tests after.** Stripe adapter, Eloquent repositories — fighting the framework isn't productive.
- **Controllers: feature tests.** Test the endpoint, not the thin controller method.
- **Exploring/prototyping: no tests.** Understand first, then rebuild with TDD.

### Testing Pyramid

```
         /\          E2E (few) — full checkout flow through HTTP
        /  \
       /----\        Integration (some) — repos with real DB, adapters with mocked HTTP
      /      \
     /--------\      Unit (many) — domain logic, VOs, state machines, policies
    /          \
```

### preventLazyLoading()
```php
// In AppServiceProvider::boot()
Model::preventLazyLoading(!$this->app->isProduction());
```
Throws exception on lazy loading in development. Catches N+1 problems before production.
