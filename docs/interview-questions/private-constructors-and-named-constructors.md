# Private constructors + named constructors (static factories)

**Question:** Why make a value object's constructor private and force callers through static factory methods?

## TL;DR

Four reasons: (1) intent-revealing call sites, (2) multiple construction paths from one class, (3) per-path validation, (4) future-proofing without breaking callers.

## Detailed

### 1. Intent-revealing call sites

`Money::ofMinor(1999, Currency::EUR)` and `Money::ofMajor("19.99", Currency::EUR)` are unambiguous. `new Money(1999, Currency::EUR)` requires the reader to **know that Money stores minor units internally** — that's leakage of internal representation into every single call site. Named constructors put the unit in the *name*.

### 2. Multiple construction paths

PHP allows exactly one `__construct`. Named constructors let you have many:

- `Money::ofMinor(int, Currency)` — already in minor units
- `Money::ofMajor(string, Currency)` — parse `"19.99"`
- `Money::fromString("19.99 EUR")` — parse amount + currency together
- `Money::zero(Currency)` — convenience for the common case
- `Money::fromStripeCharge(StripeCharge)` — adapter-style construction

You cannot express that variety with one constructor.

### 3. Per-path validation

Each named constructor validates differently:

- `ofMajor` parses a decimal string and rejects garbage (`"abc"`, `"1.2.3"`, `""`)
- `ofMinor` validates an int (basically just type-checks)
- `zero` needs no validation at all
- `fromStripeCharge` validates the Stripe DTO

A single `__construct` would need a fragile if/else over input shapes.

### 4. Future-proofing

Adding `Money::fromStripeCharge(StripeCharge $c)` tomorrow doesn't touch *any* existing call site. New paths are purely additive, never breaking.

### Bonus reason: better stack traces

`Money::ofMinor` shows up in stack traces and profilers as a distinct frame. `__construct` is just `__construct`. Easier to see where a Money was actually created.

## Interview script

> "I make the constructor private and expose static factory methods because of four things. First, it makes call sites self-documenting — `Money::ofMinor(1999, EUR)` tells the reader the unit at the call site, while `new Money(1999, EUR)` requires them to know the internal representation. Second, PHP only allows one constructor, but value objects often have multiple legitimate construction paths — from minor units, from a major decimal string, from a Stripe charge — and named constructors let me have all of them. Third, each path can have its own validation logic without polluting one constructor with type checks. And fourth, adding new construction paths later is purely additive, so it never breaks existing callers. The pattern is sometimes called the named constructor pattern; Martin Fowler and Misko Hevery both write about it, and Effective Java item 1 makes the same argument: 'consider static factory methods instead of constructors.'"

## Common follow-ups

**Q: Why not just overload the constructor?**
A: PHP doesn't support overloading. Even in languages that do, overloading by argument type is fragile — you have to remember which signature does what. Named constructors put the intent in the *name*, not the argument list.

**Q: Doesn't this add boilerplate?**
A: A few extra static methods, yes. But they collapse complexity at every call site — more code in *one* place in exchange for more readable code in *a hundred* places. That's a great trade.

**Q: When wouldn't you use this pattern?**
A: For dumb DTOs with no invariants and no parsing — say a `RequestPayload` with public fields. Named constructors shine when there's *meaning* attached to construction: invariants, parsing, multiple representations.

**Q: What's the relationship to the Effective Java "static factory methods" item?**
A: Same idea, different name. Effective Java item 1 makes nearly identical arguments — readable names, multiple instantiation paths, ability to return cached instances or subtypes.

**Q: Can a static factory return a cached instance?**
A: Yes — that's the flyweight optimization. `Money::zero(EUR)` could return the same instance every time since it's immutable. We don't need it for Money in this project, but it's a senior point worth knowing.

**Q: Does this pattern conflict with PHP 8 constructor property promotion?**
A: No — they compose. Make the constructor `private` and promoted, then expose public static factories that call `new self(...)`. You get the conciseness of property promotion *and* the flexibility of named constructors.
