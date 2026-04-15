# Value object immutability in PHP

**Question:** How do you actually enforce immutability of a value object in PHP? Is `final` enough?

## TL;DR

**`final` is not enough.** `final` prevents subclassing, not mutation. Use `readonly` properties (PHP 8.1+) or `readonly class` (PHP 8.2+) to prevent property writes after construction. Combine `final readonly class` for the fully locked-down value object.

## Detailed

### What `final` actually does

`final` prevents *subclassing*. You cannot write `class FraudulentMoney extends Money`. That is its entire job. It does **not** prevent property assignment after construction.

A `final class` with public mutable properties can be mutated all day:

```php
final class NotImmutable {
    public int $amount;
}
$x = new NotImmutable();
$x->amount = 100;
$x->amount = 999;  // works fine — final does nothing here
```

### What actually enforces immutability

**`readonly` properties** (PHP 8.1+):

```php
final class Money
{
    public function __construct(
        public readonly int $minorAmount,
        public readonly Currency $currency,
    ) {}
}
```

Properties can only be assigned inside the constructor. Any later write throws `Error: Cannot modify readonly property`.

**`readonly class`** (PHP 8.2+):

```php
final readonly class Money
{
    public function __construct(
        public int $minorAmount,
        public Currency $currency,
    ) {}
}
```

Every property is implicitly readonly. Cleaner. **This is the modern form for value objects in PHP 8.2+.**

### Why combine `final` AND `readonly`

They solve different problems and you need both:

- `readonly` prevents *mutation* — properties can't change after construction
- `final` prevents *subclassing* that could break the equality contract, override methods to add side effects, or attach behavior the original VO never agreed to

Together you get a fully locked-down value object. Skip either one and you've left a door open.

### "Mutating" methods return a new instance

The whole point of immutability is that operations *return* new instances rather than modifying existing ones:

```php
public function add(Money $other): self
{
    if ($this->currency !== $other->currency) {
        throw CurrencyMismatchException::between($this->currency, $other->currency);
    }
    return new self($this->minorAmount + $other->minorAmount, $this->currency);
}
```

Test it:

```php
$a = Money::ofMinor(100, Currency::EUR);
$b = $a->add(Money::ofMinor(50, Currency::EUR));
// $a is still 100, $b is 150
expect($a->minorAmount())->toBe(100);
expect($b->minorAmount())->toBe(150);
```

This is the *behavior* an interviewer might quiz you on, not just the language feature.

### Update patterns: the "wither" method

For "change one field" operations on a VO, use `withX()` methods that return a new instance:

```php
public function withCurrency(Currency $c): self
{
    return new self($this->minorAmount, $c);
}
```

PHP 8.3 added `clone with` syntax that lets you create-and-modify in one expression even with readonly properties:

```php
$updated = clone $original with ['currency' => Currency::USD];
```

Most VOs don't need this; `withX()` methods are clearer.

### What about cloning?

`readonly` properties survive `clone` — they're still readonly on the cloned object. You can't `$clone->minorAmount = 999;` even after cloning.

## Interview script

> "Final readonly class. The `readonly` keyword does the work — properties become writable only inside the constructor, and any later write throws an Error. The `final` is orthogonal — it prevents anyone subclassing Money to add mutable behavior or break the equality contract. Together you get a fully locked-down value object. Operations that conceptually mutate, like `add` or `multiply`, return a new instance instead of modifying `$this`. That's the whole point of immutability — and it's what makes value-object equality safe to share across threads, share across method boundaries, and use as a hash map key. People often think `final` alone gives you immutability — it doesn't. `final` prevents inheritance, not assignment. Before PHP 8.1, you'd enforce immutability with private properties and getters. Since 8.1, `readonly` is idiomatic and self-documenting; since 8.2, `readonly class` is the cleanest form."

## Common follow-ups

**Q: Why not use private properties with getters?**
A: That works in PHP 8.0 and earlier, and is what you saw before `readonly` existed. In 8.1+, `readonly` is the modern, idiomatic answer — it documents intent right in the property declaration so anyone reading the class sees "this is immutable" without checking every method body.

**Q: Are readonly properties slower?**
A: No. They're a compile-time check — the PHP engine refuses to write to them — but runtime cost is identical to normal properties.

**Q: What if I need to "update" one field of a VO?**
A: Add a `withX()` method that returns a new instance: `withCurrency(Currency $c): self`. This is sometimes called the "wither" pattern. Or, in PHP 8.3+, use `clone with` syntax.

**Q: Does PHP have records like Java/C# / data classes like Kotlin?**
A: Not yet. `final readonly class` with promoted constructor properties is the closest equivalent. There's been talk of a `record` keyword but it's not in PHP 8.4.

**Q: Are readonly properties enforced through reflection?**
A: They can be bypassed via `ReflectionProperty::setValue()` (the docs explicitly note this). Same as `private` — Reflection sees through PHP visibility. It's a tool for serializers and ORMs, not an escape hatch for app code.

**Q: How does `readonly` interact with serialization (Doctrine, Eloquent, json_encode)?**
A: ORMs typically use Reflection to populate readonly properties from the database — that's the legitimate use of bypassing visibility. `json_encode` reads them like any other public property.

**Q: What about nested mutable state?**
A: `readonly` only prevents *reassignment* of the property. If a readonly property holds a mutable object (like an `ArrayObject`), the outer property can't be reassigned but the inner state can be mutated. For true deep immutability, every nested type must also be immutable. Best practice: use immutable types all the way down, or wrap mutable types in immutable containers.

**Q: Is comparing VOs by reference safe with readonly?**
A: PHP doesn't automatically compare value objects by value. You still need to write an `equals(self $other): bool` method that compares each property. `===` on objects compares identity (same instance), not value. This is a language quirk worth being explicit about in interviews.
