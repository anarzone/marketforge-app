# Domain vs presentation: should Money format itself?

**Question:** Should `Money::format()` exist on the value object, or does formatting belong elsewhere?

## TL;DR

**Split it.** Money owns its **canonical** representation (`"19.99 EUR"`, locale-free, used for logs, debugging, and serialization) — that goes in `__toString()`. Money does NOT own **locale-specific** display (`"19,99 €"` vs `"$19.99"`). Locale-aware formatting lives in a separate `MoneyFormatter` that uses PHP's `ext-intl` `NumberFormatter`.

## Detailed

### The trap

"Of course Money should format itself, it's encapsulation!" — sounds right, breaks down on the first locale.

### Two different concerns

**Canonical representation** is locale-free, deterministic, and used for:

- Logging (`"order paid for 19.99 EUR"` in a log line)
- Debugging (`var_dump`, error messages, exception messages)
- Serialization to text formats (JSON, CSV)
- Equality checks in tests
- Whatever appears in a stack trace

This is *intrinsic* to the Money — every Money has exactly one canonical form, regardless of who's looking at it. It belongs on `__toString()`.

**Locale-aware display** depends on:

- The user's locale (`de-DE` vs `en-US` vs `fr-FR`)
- Currency-specific display rules (symbol prefix vs suffix, position relative to negative sign)
- Thousand and decimal separators (`,` vs `.` vs space)
- Currency symbol vs ISO code preference
- Sometimes: rounding to display-friendly precision

The same `Money(199900, EUR)` in different locales:

| Locale | Display |
|---|---|
| en-US | `€1,999.00` |
| de-DE | `1.999,00 €` |
| fr-FR | `1 999,00 €` |
| ja-JP | `€1,999.00` |

If `Money::format($locale)` lives on the VO, then your domain VO knows about locales. Locales are i18n; i18n is presentation; presentation does not belong in the domain.

### The clean split

```php
// Domain layer — pure, locale-free
final readonly class Money
{
    public function __toString(): string
    {
        // canonical: "19.99 EUR" or "100 JPY"
        $exponent = $this->currency->exponent();
        if ($exponent === 0) {
            return $this->minorAmount . ' ' . $this->currency->value;
        }
        // build a decimal string from minorAmount without using float
        // ...
    }
}

// Presentation layer — knows about locales
final class MoneyFormatter
{
    public function __construct(private string $locale) {}

    public function format(Money $money): string
    {
        $major = $money->minorAmount() / (10 ** $money->currency()->exponent());
        $f = new \NumberFormatter($this->locale, \NumberFormatter::CURRENCY);
        return $f->formatCurrency($major, $money->currency()->value);
    }
}
```

`MoneyFormatter` is a presentation-layer service. The domain `Money` never imports it. If you change the formatter, the domain doesn't recompile.

### Why this matters in an interview

The "where does this responsibility belong" question is exactly the kind of architectural decision senior interviews probe. The wrong answer ("Money formats itself") sounds reasonable but couples your domain to i18n. The right answer ("canonical on Money, locale-aware in a formatter") shows you understand layering and the difference between domain and presentation concerns.

It also connects to the "would this class need to change if you swapped PostgreSQL for MongoDB?" test from CLAUDE.md. Apply the same test here: would Money need to change if you launched in a new locale? If yes, formatting is in the wrong layer.

### Connection to the hexagonal architecture

The formatter is a *presentation adapter*. The domain core (Money) exposes raw, locale-free data through its public API. Adapters (formatters, JSON serializers, CSV exporters, REST resources) consume that raw data and shape it for whatever audience they serve. This is exactly the ports-and-adapters pattern, just in miniature.

## Interview script

> "I split it. Money owns its canonical representation — `__toString` returns something like `19.99 EUR`, locale-free, used for logs, debugging, and exception messages. It does NOT own locale-specific display. The reason is that human-facing formatting depends on the user's locale — German users see `19,99 €`, US users see `€19.99`, French users see `19 999,00 €` with a non-breaking space — and locale is a presentation concern, not a domain concern. If Money knew about locales, my domain VO would have to depend on `ext-intl`, which is a layering violation. So I put locale-aware formatting in a separate `MoneyFormatter` service that wraps PHP's `NumberFormatter` from `ext-intl`. Same Money, multiple formatters, no coupling between domain and presentation. There's a useful test for this: would Money need to change if I launched in a new locale tomorrow? If yes, I put formatting in the wrong layer. With the split, the answer is no — I just instantiate a new formatter."

## Common follow-ups

**Q: Isn't `__toString` already a presentation concern?**
A: It's a *debug* concern, not a user-presentation concern. The canonical form is what you see in logs, stack traces, and `var_dump`. It's not what an end user sees — the formatter handles that. Two different audiences, two different APIs, two different layers.

**Q: What if I just want a simple `format()` for a single-locale app?**
A: For a strictly single-locale app, you *could* put it on Money — but you'll regret it the day the business signs a customer in a new market. Better to start with the formatter pattern from day one. Cost is low; benefit is structural.

**Q: Does `ext-intl` need to be installed?**
A: Yes. It ships with most PHP distributions but is sometimes missing from minimal Docker images. Our project's Dockerfile installs `intl` explicitly. If `ext-intl` is missing, you fall back to manual formatting which is much more painful (and worse i18n).

**Q: What about other formats — tax invoices, email receipts, accounting exports?**
A: Same pattern, different formatter. `TaxInvoiceMoneyFormatter`, `EmailReceiptMoneyFormatter`, `AccountingExportFormatter` — each implements the same conceptual interface. The domain Money stays unaware of any of them. Formatters are cheap; domain coupling is expensive.

**Q: Where should `MoneyFormatter` live in the project structure?**
A: NOT in the domain layer. Either: (a) the module's `Http/` or `Presentation/` folder if it's HTTP-specific, (b) a shared `app/Modules/SharedKernel/Presentation/` if it's used across modules, or (c) injected as a service registered in a service provider. The key constraint: it must NOT be imported by anything in `Domain/`.

**Q: How does this interact with API responses?**
A: API responses are *machine-facing*, so they should usually return canonical form (`{"amount": 1999, "currency": "EUR"}` or `{"display": "19.99 EUR"}`). Locale-aware formatting is for *humans*, which means it belongs in views/templates/email bodies, not in API responses. Let the client decide how to display.

**Q: What if the same Money needs both a "display" form and a "for-database" form?**
A: That's a sign you have multiple concerns and you should probably have multiple methods or multiple types. The Money VO exposes the *raw data* (`minorAmount()`, `currency()`); each consumer formats it for its own purpose.
