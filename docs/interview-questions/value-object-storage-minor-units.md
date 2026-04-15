# Value object storage: integer minor units vs float vs string

**Question:** When designing a Money value object, how do you store the amount internally, and why?

## TL;DR

Integer minor units (e.g. cents for USD). Avoids float precision drift, makes equality exact, serializes cleanly. The trade-off is that the VO must know each currency's *exponent*. On 64-bit PHP, `PHP_INT_MAX ≈ 9.2 × 10¹⁸` is more than enough headroom for any commerce system.

## Detailed

### Why not float?

`0.1 + 0.2 === 0.30000000000000004` in PHP, JavaScript, Python, and every other IEEE 754 language. Binary floats cannot represent decimal fractions exactly. Any system that does arithmetic on money in floats will eventually lose cents. This is rule #1 of money handling — every PoEAA, Vaughn Vernon, and DDD reference says it.

### Why integer minor units?

Store the amount as the smallest indivisible unit of the currency:

| Currency | Exponent | 1 minor unit | "19.99" stored as |
|---|---|---|---|
| USD | 2 | $0.01 (cent) | `1999` |
| EUR | 2 | €0.01 | `1999` |
| JPY | 0 | ¥1 (no fractional unit) | `1999` (=¥1999) |
| BHD | 3 | 0.001 BHD | `19990` |
| KWD | 3 | 0.001 KWD | `19990` |

Arithmetic is exact integer math; equality is `===`; serialization is just an int.

### The "currency exponent" requirement

Conversion between major and minor units depends on the currency:

- USD/EUR exponent = 2 → divide minor by 100 to get major
- JPY exponent = 0 → minor *is* major
- BHD/KWD exponent = 3 → divide minor by 1000

So the `Currency` type **must** know its exponent. Best place: a method on the `Currency` enum (not a global lookup table). Keeps the knowledge attached to the thing it describes.

### Overflow concerns

`PHP_INT_MAX` on 64-bit = 9,223,372,036,854,775,807. That's about $92 quadrillion in cents. You're safe even multiplying by reasonable quantities.

On 32-bit PHP, `PHP_INT_MAX ≈ 2.1B`, so anything above ~$21 million in cents overflows. **State explicitly that you assume 64-bit PHP** in interviews — it's a senior signal that you've thought about overflow.

### When to use BCMath / ext-decimal instead

Use arbitrary-precision libraries when:

- Crypto (18 decimal places for ETH wei)
- FX rates with 6+ decimal places
- Scientific or engineering domains
- Multi-step calculations where intermediate precision matters

For whole-cent commerce: integer minor units. BCMath is slower (function call overhead per op), more verbose (no operators, every operation is `bcadd($a, $b)`), and overkill.

## Interview script

> "Integer in the currency's smallest indivisible unit — cents for USD, no fractional unit for JPY. So $19.99 is stored as 1999. This avoids the IEEE 754 float precision problem completely; equality is exact integer comparison; serialization is trivial. The trade-off is the value object needs to know each currency's exponent — how many decimal places to shift — and I put that as a method on the Currency enum so the knowledge stays with the thing it describes. On 64-bit PHP, PHP_INT_MAX is around 9.2 quintillion, which is way more headroom than any e-commerce system will need even after multiplying by quantity. If we needed arbitrary precision for crypto, multi-decimal FX rates, or scientific calculations, I'd reach for BCMath or ext-decimal — but for whole-cent commerce that's overkill and slower."

## Common follow-ups

**Q: Why not use a string and BCMath everywhere — isn't that safer?**
A: Slower (function calls vs CPU integer ops), more verbose (no operators), and you don't need it for whole-cent arithmetic. Use the right tool for the precision you need.

**Q: How does the same Money class handle JPY (no decimals) and USD (2 decimals)?**
A: The Currency enum carries the exponent. Money is currency-agnostic in its arithmetic — it just adds integers — but uses the currency's exponent when converting to/from major-unit display.

**Q: Can integer overflow really happen in a real system?**
A: On 64-bit, no — not in any realistic e-commerce scenario. On 32-bit, yes — be explicit about your runtime. If you ran a stock-trading platform with multi-billion-dollar trades, you'd want to verify headroom.

**Q: What about negative values?**
A: Allow them. Refunds, discounts, and balance corrections need negative money. Don't artificially restrict to positive at the VO level — that's a separate domain rule callers can enforce via `Money::isNegative()`.

**Q: How do you handle parsing `"19.99"` into 1999 without using floats?**
A: Split on the decimal point, validate both parts are digits, multiply the integer part by `10^exponent`, then pad/truncate the fractional part to exactly `exponent` digits before adding. **Never** do `(int) ((float) "19.99" * 100)` — `19.99 * 100 = 1998.9999...` in float, and you'd lose a cent on round-trip.

**Q: How does this interact with database storage?**
A: Store as `BIGINT` (64-bit integer) in the DB. Don't use `DECIMAL` unless you have a reason — `BIGINT` is faster, smaller, and matches your in-memory representation exactly so there's no parsing on every read.
