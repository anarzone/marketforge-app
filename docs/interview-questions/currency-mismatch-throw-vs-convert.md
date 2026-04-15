# Currency mismatch in arithmetic: throw, don't convert

**Question:** What should `Money::add(Money $other)` do when `$this->currency !== $other->currency`?

## TL;DR

**Throw a `CurrencyMismatchException`.** Conversion is a separate, explicit operation that needs an exchange rate, a timestamp, and an audit trail. Doing it implicitly inside an arithmetic operator is wrong on every level — pure-function violation, hidden business decision, masked bugs, and unauditable.

## Detailed

### The tempting wrong answer

"Convert to the system's default currency and add." Sounds helpful. **It's a senior-interview red flag.** If you give this answer the interviewer will smile politely and move on, and you'll be wondering why you didn't get the job.

### Why silent conversion is wrong

**1. Where does the rate come from?**
A pure value object can't call an FX API or query a database. If you embed a fixed conversion table, it's wrong by tomorrow. If you inject a rate provider into the VO, the VO is no longer pure — it depends on I/O, can't be tested in isolation, and breaks the value-object contract.

**2. Conversion is a business operation, not a math operation.**
It changes the *meaning* of the value, not just the units. Converting €100 to dollars at one rate and back at another rate doesn't return €100 — it returns less, because of the spread. That asymmetry is the entire foreign exchange business. You don't bury that in `add()`.

**3. Silent conversion masks bugs.**
If a `Cart` somehow ends up with line items in two currencies, that's a programming error upstream — maybe a seller misconfigured their account, maybe checkout used the wrong default currency, maybe an import from a third-party feed had wrong metadata. **You want it to scream so you find the bug**, not silently produce a plausible-looking total.

**4. Audit and regulatory compliance.**
In a payments domain, every currency conversion has to be logged with rate, timestamp, source, and (in regulated markets) the spread and any fees. None of that fits inside `Money::add()`. Conversion is a regulated event; arithmetic isn't.

**5. Reproducibility.**
Tests that depend on `add()` would suddenly depend on FX rates that change by the second. Test results would be non-deterministic. That's a nightmare.

### The right architecture

`Money::add(Money $other)` checks `$this->currency === $other->currency` and throws if not. Simple, pure, deterministic.

Conversion is a **separate, explicit operation**:

```php
final readonly class ExchangeRate
{
    public function __construct(
        public Currency $from,
        public Currency $to,
        public string $rate,        // BCMath string for precision
        public \DateTimeImmutable $asOf,
        public string $source,      // "ECB", "Stripe", "OpenExchangeRates"
    ) {}
}

interface CurrencyConverter
{
    public function convert(Money $source, Currency $target, ExchangeRate $rate): Money;
}
```

The `CurrencyConverter` is an application service or a domain service — never on `Money` itself. Every conversion goes through this one interface, so every conversion is auditable. The `ExchangeRate` VO carries everything you need for the audit log.

### Where in the layers does this live?

- `Money` — Domain layer. Pure, throws on mismatch.
- `ExchangeRate` — Domain layer. Pure VO.
- `CurrencyConverter` interface — Domain layer (it's a port).
- `EcbCurrencyConverter` implementation — Infrastructure layer (it's an adapter that calls the ECB API).
- The thing that decides *when* to convert — Application layer (a use case like `CalculatePayoutInSellerCurrency`).

This is hexagonal architecture in miniature.

## Interview script

> "Throw a CurrencyMismatchException. Money is a value object so its operations have to be pure and deterministic. Currency conversion isn't pure — it depends on a rate that changes by the second and comes from outside the system. Beyond the purity argument, conversion is a business operation, not a math operation: it has to be logged with rate, timestamp, and source for audit and compliance. So `add` throws on currency mismatch, and conversion lives in an explicit `CurrencyConverter` service that takes an `ExchangeRate` value object as input. The ExchangeRate carries the rate, the timestamp, and the source — everything an audit log needs. That keeps the domain pure, makes every conversion auditable, and surfaces upstream bugs that silent conversion would paper over. If a Cart somehow ends up with line items in two currencies, I want that to scream so I can find the bug, not silently produce a plausible total."

## Common follow-ups

**Q: But isn't throwing inconvenient for the caller?**
A: It's *correct*. The caller has to make a decision — which rate? as of when? — and that decision belongs to the caller, not to Money. Pushing the decision up is the right architecture; it's not inconvenience, it's clarity.

**Q: What if I really do want a permissive `addOrConvert`?**
A: Build it in the application layer, not in Money. The application service injects a `CurrencyConverter`, fetches a current rate, performs the conversion explicitly, and then calls `Money::add` with two now-matching currencies. Logged, audited, testable.

**Q: What exception base class — `\DomainException`, `\RuntimeException`, `\InvalidArgumentException`?**
A: `\DomainException` from the SPL, or a custom subclass extending it. It's a domain rule violation, not a programming-argument bug. `RuntimeException` is too generic. `InvalidArgumentException` is for "you passed me garbage", but here both arguments are valid Money — the rule violation is about their *combination*.

**Q: How do you test the throw case?**
A: `expect(fn() => Money::ofMinor(100, EUR)->add(Money::ofMinor(50, USD)))->toThrow(CurrencyMismatchException::class);` in Pest, or `$this->expectException(...)` in PHPUnit.

**Q: What if the system only supports one currency?**
A: Then there's no mismatch to handle. But — and this is the senior point — building in the constraint costs you nothing now and saves you a complete rewrite the day the business says "we're launching in Germany next month." Cheap insurance.

**Q: Could you return `null` instead of throwing?**
A: You could, but it propagates a "maybe Money" through the entire codebase and forces every caller to null-check. Throwing surfaces the error immediately and lets normal flow stay clean. Use exceptions for exceptional things; mismatched currencies are exceptional in a well-designed system.

**Q: What about comparison operators like `isGreaterThan` — same rule?**
A: Same rule. You cannot meaningfully compare €100 to $100 without a rate. Throw on mismatch. Be consistent across the entire VO.
