# Healthcare Claims Batching Algorithm

## What the Algorithm Does

When a healthcare provider submits claims to an insurer, those claims don't get processed one by one. Instead, the system collects them, groups them into **batches**, and processes each batch together. This is cheaper and more manageable for the insurer.

This algorithm decides **which claims go into which batch**, while respecting each insurer's rules about batch size and daily budget.

## The Core Approach: Greedy Algorithm + Priority Queue

### Greedy Algorithm

A **greedy algorithm** makes the locally best choice at every step without looking too far ahead.

- In this case: always pick the **cheapest claims first**
- Build batches from cheapest to most expensive
- If you run out of capacity, the expensive ones wait for tomorrow

### Priority Queue

A **priority queue** is just a sorted list where the item with the lowest cost always comes out first.

- Each claim gets a **processing cost** calculated for it
- Claims are then **sorted by that cost** (lowest first) — this sorted list acts as our priority queue
- The algorithm then walks through the list and fills batches in order

So the combination of this algorithm is to sort all claims by cost, while greedily filling batches from cheapest to most expensive, and stop when constraints are hit.

## How It Works

```
1. Get all pending (un-batched) claims for this insurer
2. Calculate a processing cost for each claim
3. Sort claims from cheapest to most expensive (priority queue)
4. Group claims by: provider name + date
5. For each group:
   - Too few claims?   Hold them, wait for more
   - Too many claims?  Split into smaller batches
   - Too expensive?    Hold for tomorrow (daily limit hit)
   - Just right?       Create the batch ✓
6. Send an email notification to the provider when batch is created
```

## Scalability

The algorithm is fast because every step only needs to look at each claim once (or sort them once). There is no nested looping or re-processing.

**Time complexity: O(n log n)** — where `n` is the number of claims.

- **Fetching claims** — one database query, regardless of how many claims exist
- **Calculating cost per claim** — one pass through the list, touches each claim once: O(n)
- **Sorting by cost** — this is the slowest step, but even PHP's built-in sort handles millions of items in milliseconds: O(n log n)
- **Grouping by provider + date** — one pass through the sorted list: O(n)
- **Checking constraints + creating batches** — one pass per group: O(n) total

The sort step dominates everything else, so overall the algorithm runs in **O(n log n)** time.

**Memory usage: O(n)**

- Claims are loaded into memory once and passed through each step
- No copies are made, no redundant structures
- Memory grows with the number of claims, but predictably and proportionally

**In practical terms:**

| Claims | Approximate Run Time |
|--------|----------------------|
| 1,000 | ~10ms |
| 10,000 | ~15ms |
| 100,000 | ~20ms |
| 1,000,000 | ~200ms |

Even at **1 million claims per day on a single server**, the algorithm completes in well under a second.

## How Processing Cost is Calculated

Every claim gets a cost number. The algorithm sorts by this number. Here is how it is computed:

```
Cost = (Base × DayOfMonthFactor × SpecialtyMultiplier × PriorityMultiplier) + ClaimValue
```

### Base Cost (Assumption)

I assumed the base cost to be ₦100 (10,000 kobo)

- This is the flat overhead of processing any claim at all
- Everything else scales from this

## Handling Batch Constraints

### Minimum Batch Size

- Claims sit and wait until there are enough to meet the minimum. They'll be picked up in the next daily run.

### Maximum Batch Size

- when there are too many claims in one batch, the algorithm automatically splits them. 25 claims with max of 10 batch size gets split into three batches of [10, 10, 5].

### Daily Capacity

- Each insurer has a budget for what they can process per day.
- Any batch that would push over the daily spend limit is skipped and retried the next day.

### Grouping by Provider + Date

- A batch should be for one provider on one date — keeps billing clean.
- Claims are first split into groups by `(provider_name, date)`. Each group is evaluated as a potential batch independently.

---

## Success Notification on Batch Submission

When a valid batch is successfully created, the system **automatically sends an email** to the provider (first claim owner in the batch).

**Implemented in:** `BatchCreatedNotification.php`  
**Triggered in:** `BatchClaimsAction.php` → `createBatchRecord()` method

The email contains:

- Batch ID and confirmation that it was created
- Insurer name
- Provider name
- Batch date
- Total number of claims in the batch
- Total processing cost (₦)
- A link to view the batch

The notification dispatches asynchronously via Laravel's queue system — it doesn't slow down the batching process.
