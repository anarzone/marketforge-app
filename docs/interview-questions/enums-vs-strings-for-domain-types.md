# Enums vs strings for domain types (e.g. Currency, Status, Role)

**Question:** Should a domain type like `Currency` (or `OrderStatus`, `Role`, `Country`) be a backed enum or a string?

## TL;DR

Backed enum in the domain. Strings live only at the persistence boundary. Type safety, IDE autocomplete, single source of truth for type-attached behavior, and refactor safety all argue for the enum.

## Detailed

### Why backed enum

**Type safety at compile/parse time.**
`Money::ofMinor(100, "EU")` type-checks if Currency is a string — bug ships to prod. `Money::ofMinor(100, Currency::EU)` doesn't compile because the case doesn't exist. Bugs caught at write time.

**Autocomplete and refactor safety.**
The IDE knows every valid currency. Renaming a case touches every call site automatically. With strings, renaming requires grepping the world.

**Methods on the enum carry behavior.**
This is the killer feature for senior code. `Currency::USD->exponent()` keeps the knowledge of "USD has 2 decimal places" attached to the currency itself. Without it, you end up with `CurrencyExponent::lookup($currencyString)` scattered around the codebase — a code smell and a refactor hazard.

```php
enum Currency: string
{
    case EUR = 'EUR';
    case USD = 'USD';
    case JPY = 'JPY';

    public function exponent(): int
    {
        return match ($this) {
            self::EUR, self::USD => 2,
            self::JPY => 0,
        };
    }
}
```

The exponent lives where it belongs. Adding a new currency = adding one case + one match arm. The compiler reminds you if you forget the arm.

**Constraint by construction.**
Only valid currencies exist as values. You cannot construct an invalid one. The set of legal values is enumerated *in the type*.

### Why "string in the domain" is the wrong answer

- No type safety: `"USD"`, `"usd"`, `"USDD"`, `"$"` all type-check
- Behavior gets scattered: every "what's the exponent of X" needs a separate lookup function
- Refactor-hostile: rename "EUR" to anything → grep everywhere
- No way to attach methods or metadata to the type

### Where strings DO belong

At the persistence and integration boundaries.

- **Database column**: store `'EUR'` as a `VARCHAR(3)` or use a Postgres ENUM type. Schemas outlive PHP enum definitions; other systems read your DB and don't have your enum.
- **API request/response**: serialize to ISO 4217 codes. Other clients (mobile, third parties, your own JavaScript) don't share your PHP enum.
- **Logs**: print the string value, not the PHP class identifier.

The pattern: convert at the boundary. Read `'EUR'` from the DB → `Currency::from('EUR')` → use as enum throughout the domain → cast back to `$currency->value` when writing.

## Interview script

> "Backed enum in the domain, string only at the boundary. Three reasons. First, type safety — invalid currencies don't compile, so I catch typos at write time instead of in production. Second, the enum can carry behavior. The fact that USD has two decimal places lives as a method on the enum itself — `Currency::USD->exponent()` returns 2 — instead of being scattered in lookup tables across the codebase. Third, refactor safety — renaming a case touches every call site through the IDE automatically. I keep strings only at the database boundary where I serialize to ISO 4217 codes, and at the API boundary where other clients can't share my PHP enum. The conversion is one line at each boundary: `Currency::from($string)` going in, `$currency->value` going out. Persistent storage outlives any enum, but the domain shouldn't pay that tax."

## Common follow-ups

**Q: What about currencies that don't exist in the enum yet?**
A: Adding a case is one line. If you need *truly dynamic* currency support — user-defined tokens, arbitrary crypto — then enum doesn't fit and you need a `Currency` class with an internal registry. For an e-commerce marketplace dealing with ISO 4217, enum is right.

**Q: Why backed (`enum Currency: string`) and not pure?**
A: Backed enums let you `Currency::from('EUR')` for parsing and `$currency->value` for serialization. Pure enums don't serialize cleanly. **Always back enums that cross system boundaries.**

**Q: Doesn't this just push the validation problem to the boundary?**
A: Yes, and that's correct. Validation belongs at the boundary — at the perimeter where untrusted input enters. The domain inside should be able to assume everything is valid. Failing fast at the boundary with `Currency::tryFrom()` and a 422 response is much better than failing deep inside business logic.

**Q: Does this work for things other than currency?**
A: Yes — order status, payment status, role, country, anything with a finite known set. Anything that's a "type code" or "kind" in your domain. `OrderStatus::PLACED`, `Role::SELLER`, `PaymentMethod::CARD` — all enum candidates.

**Q: What if the set isn't really finite — like "tags" on a product?**
A: Then it's not an enum. Tags are user-generated open sets; they're a relationship, not a type. Use a separate table.

**Q: How do you handle persistence in Eloquent?**
A: Cast it: `protected $casts = ['currency' => Currency::class];`. Eloquent handles the conversion both ways.

**Q: Do enums support inheritance or interfaces?**
A: Enums can implement interfaces (very useful for polymorphism) but cannot extend classes or other enums. Implementing an interface is a common pattern when multiple enums share behavior.
