# Architecture Patterns — Interview Knowledge Base

Based on mock interview gaps identified on April 9, 2026.

---

## Entity vs Value Object

### The Core Distinction: IDENTITY

**Entity = has identity.** Two entities with same data but different IDs are DIFFERENT.
- Payment(id=abc) and Payment(id=xyz) are different payments even with same amount
- **Mutable** — state changes over time (pending → authorized → captured)
- Has its own database table with primary key

**Value Object = no identity.** Two VOs with same data ARE equal.
- Money(5000, EUR) equals any other Money(5000, EUR)
- **Immutable** — once created, never changes. Need different value? Create new VO.
- No own table — embedded as columns in entity's table

### VOs Are NOT Just Single Fields
VOs can have multiple fields:
- `Money(amount, currency)` — 2 fields
- `Address(street, city, postalCode, country)` — 4 fields
- `Commission(rate, fixedFee)` — 2 fields
- `PaymentMethod(type, last4, brand)` — 3 fields

What makes them VOs: no identity + immutable + equality by values.

### Payment System Examples
**Entities:** Payment, Order, Seller, Product (have lifecycle, unique ID, tracked over time)
**Value Objects:** Money, Commission, Address, PaymentMethod, Email, Quantity (immutable, validated at creation)

---

## Repository Pattern

### When It's Worth It (Not Always)
Taylor Otwell is right that simple CRUD repos wrapping Eloquent are noise. But for complex domains:

**Interface in Domain (the port):**
```php
interface PaymentRepositoryPort {
    public function findById(PaymentId $id): Payment;
    public function save(Payment $payment): void;
    public function findByMerchant(MerchantId $id, Status $status): PaymentCollection;
}
```

**Implementation in Infrastructure (the adapter):**
```php
class EloquentPaymentRepository implements PaymentRepositoryPort {
    public function findById(PaymentId $id): Payment {
        $model = PaymentModel::findOrFail($id->toString());
        return $this->toDomainEntity($model);
    }
}
```

### Three Benefits
1. **Testability:** Inject `InMemoryPaymentRepository` in tests — no database, millisecond tests
2. **Query encapsulation:** Complex queries live in ONE place with descriptive method names
3. **Domain/persistence separation:** Eloquent model shaped for DB, domain entity shaped for business logic

---

## Modular Monolith

### What Makes It Different From a Reorganized Monolith

It's NOT just a different folder structure. It's **strict rules of communication:**

**Rule 1: Modules NEVER import each other's internal classes.**
```php
// BAD — breaks boundary
use App\Modules\Payment\Domain\Payment;

// GOOD — communicate through events
class WhenPaymentCaptured {
    public function handle(PaymentWasCaptured $event): void {
        $this->orderService->markAsPaid($event->orderId);
    }
}
```

**Rule 2: Two communication mechanisms only:**
- **Domain Events (async):** Module publishes event with primitive data, other modules listen
- **Public Interfaces (sync):** Module exposes a simple interface, other modules depend on the interface (resolved via DI)

**Rule 3: Each module owns its database tables.**
No other module queries those tables directly.

### Why Not Microservices
"Microservices add enormous complexity — network communication, distributed transactions, separate deployments. A modular monolith gives strict boundaries without the operational overhead. If each module owns its data and communicates through events and interfaces, you can extract any module later when scale demands it."

### Comparison
```
Modular Monolith:                  Microservices:
Single deployment                  Multiple deployments
In-process communication           Network calls (latency)
Shared DB (simpler transactions)   Distributed transactions (sagas)
Simple to operate                  Complex infrastructure
```

---

## Domain Events

### What They Are
A fact about something that happened in your business domain. Past tense, business language:
- OrderWasPlaced
- PaymentWasCaptured
- ProductPriceChanged

### Domain Event vs Laravel Event
**Laravel Event:** Framework mechanism — a class you dispatch and listeners react to. Technical tool.
**Domain Event:** Business concept. Framework-agnostic. Carries primitive data, no Eloquent models.

```php
// Domain event (in Domain layer — no Laravel imports)
class OrderWasPlaced {
    public function __construct(
        public readonly string $orderId,
        public readonly string $buyerId,
        public readonly int $totalAmount,
        public readonly string $currency,
        public readonly DateTimeImmutable $placedAt,
    ) {}
}
```

### How They Flow
1. Entity records event internally: `$this->domainEvents[] = new OrderWasPlaced(...)`
2. Application service saves entity, then dispatches events
3. Other modules react independently:
   - Payment module → creates payment, initiates authorization
   - Notification module → sends confirmation email
   - Analytics module → updates daily stats
4. Order module doesn't know these modules exist — loose coupling

### Connects To
- **Outbox pattern:** Save entity + event in one transaction, publish later
- **Saga:** Compensating events when steps fail
- **CQRS:** Events trigger projection updates for read models
- **Event sourcing:** Store events as the source of truth
