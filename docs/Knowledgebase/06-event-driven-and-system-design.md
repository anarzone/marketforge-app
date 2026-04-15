# Event-Driven Systems & System Design — Interview Knowledge Base

Based on mock interview gaps identified on April 9, 2026.

---

## Saga Pattern (Multi-Step Payment Flow)

### The Problem
Checkout involves multiple steps across modules. Can't use single DB transaction because each module owns its data + PSP calls are external HTTP. If step 3 fails, must undo steps 1-2.

### Orchestration Approach (Preferred for Payments)

A `CheckoutSaga` coordinates the flow:

**Happy path:**
```
1. Create Order (status: pending) ✓
2. Authorize Payment with PSP ✓ (hold on card, not charged)
3. Reserve Inventory per seller ✓
4. Capture Payment (actually charge) ✓
5. Create Sub-Orders per seller ✓
6. Dispatch events → notifications, analytics
```

**Failure path (inventory fails):**
```
1. Create Order ✓
2. Authorize Payment ✓
3. Reserve Inventory — Seller B out of stock ✗

COMPENSATE in reverse:
→ Release Seller A's reservation
→ Void payment authorization (free, instant)
→ Mark order as failed
→ Notify buyer
```

### Key Design Principle: Authorize Early, Capture Late
Authorization = hold on card (free to void). Capture = actual charge (refund is expensive, slow). So authorize BEFORE checking inventory, capture AFTER everything confirms.

### Compensation Table
| Step | Action | Compensation |
|---|---|---|
| 1 | Create order | Mark as failed |
| 2 | Authorize payment | Void authorization |
| 3 | Reserve inventory | Release reservations |
| 4 | Capture payment | Initiate refund |
| 5 | Create sub-orders | Cancel sub-orders |

### Critical Details
- Every step must be **idempotent** (safe to retry)
- Saga state persisted in database (survives crashes)
- **Timeouts** on each step (PSP doesn't respond in 30s → compensate)
- Dead letter handling if compensation also fails → alert humans

---

## Circuit Breaker Pattern

### The Problem (Cascading Failure)
Stripe goes down → every checkout waits 30s for timeout → PHP-FPM workers all stuck → entire marketplace goes down. One dependency killed everything.

### Three States

**CLOSED (normal):** Requests flow through. Quietly counting failures.

**OPEN (protection):** After threshold (e.g., 5 failures), NO requests sent. Fail immediately (2ms instead of 30s). Workers freed. Rest of app works fine.

**HALF-OPEN (testing):** After 30 seconds, allow ONE test request. Success → CLOSE. Failure → stay OPEN.

### Configuration
- **Failure threshold:** 5 consecutive OR 50% error rate in 60s window
- **Open duration:** 30-60 seconds
- **What counts as failure:** Timeouts + 5xx. NOT 4xx (client errors)

### Fallback Strategies
1. **Secondary PSP:** Stripe down → route to Adyen
2. **Queue for later:** "Order confirmed, payment processing shortly"
3. **Graceful error:** Clear message, cart saved, try again later

### Combine with Bulkhead
Isolate worker pools per PSP. Stripe gets 10 workers, Adyen gets 10. Stripe slowness only affects its 10 workers.

### Implementation: Store state in Redis
```
circuit:stripe:failures → integer count
circuit:stripe:state → "closed" | "open" | "half_open"
circuit:stripe:opened_at → timestamp
```

---

## Caching Strategy (Product Catalog)

### Layered Approach

**Level 1 — Individual products:**
```
Key: product:{id}
TTL: 10 minutes
Pattern: Cache-aside (check cache → miss → query DB → store → return)
```

**Level 2 — Listing pages:**
```
Key: catalog:electronics:price_asc:page_1
TTL: 5 minutes
Use Laravel cache tags for bulk invalidation
```

**Level 3 — Expensive aggregations:**
```
Key: stats:top_sellers:weekly
TTL: 30 minutes
```

### Event-Based Invalidation
Seller updates product → `ProductUpdated` event → listener:
- Deletes `product:{id}` key
- Flushes catalog tag group
- Deletes seller's product list cache

### Cache Stampede Prevention
Cache expires, 500 users hit DB simultaneously. Fix:
- **Locking:** First request acquires Redis lock, rebuilds cache, others wait
- **Stale-while-revalidate:** Serve expired data while ONE process refreshes

### Redis Data Structures for Caching
- **Strings + TTL:** Simple value caching, idempotency keys (`SET key NX EX 86400`)
- **Hashes:** Object fields without serialization (merchant config)
- **Sorted Sets:** Rate limiting (sliding window), leaderboards
- **Streams:** Lightweight event queue
- **SET with NX:** Distributed locks

---

## Webhook Security (HMAC Signatures)

### How It Works
1. Each seller gets unique webhook secret on onboarding
2. When sending webhook, platform signs: `HMAC-SHA256(timestamp + "." + payload, seller_secret)`
3. Signature sent in header: `X-Bazaar-Signature: sha256=abc123...`
4. Seller recomputes HMAC with their secret, compares with header
5. Match = authentic. No match = forged, reject.

### Replay Attack Prevention
Include timestamp in header. Seller rejects webhooks older than 5 minutes.

### Retry Strategy for Failed Delivery
```
Attempt 1: immediately
Attempt 2: 1 minute
Attempt 3: 5 minutes
Attempt 4: 30 minutes
Attempt 5: 2 hours
Attempt 6: 24 hours
After 6 failures → DLQ → alert seller
```

Each retry gets new timestamp + new signature.

---

## Docker vs Kubernetes (Simple Mental Model)

**Docker = one container.** Packages app + dependencies. Runs the same everywhere. Solves "works on my machine."

**Kubernetes = managing hundreds of containers across many servers.** Handles:
- **Scaling:** "Run 10 copies, auto-scale at 70% CPU"
- **Self-healing:** Container crashes → restart. Server dies → move containers.
- **Deployments:** Canary rollout, automatic rollback.
- **Service discovery:** Stable DNS names for inter-service communication.

**Minimum vocabulary:**
- **Pod:** One or more containers running together
- **Deployment:** "I want 5 copies of my PHP pod"
- **Service:** Stable network endpoint routing to pods
- **Ingress:** Routes external HTTP to correct Service
- **Helm:** Package manager for K8s YAML configs

**Interview answer:** "I've worked with Docker for development and CI/CD. I understand Kubernetes concepts — pods, deployments, services, scaling — and could work effectively in a K8s-based team. I haven't managed a production cluster myself."
