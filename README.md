
# Healthcare Claims Batching System

A Laravel + Vue 3 web application that allows healthcare providers to submit medical claims to insurance companies.


## Core Features

✅ **Claim Submission Form:** Healthcare providers can submit medical claims through a simple Vue 3 form  
✅ **Optimized Fee Processing:** Automatic total calculation as claim items are added  



## Installation & Setup



### Step 1: Clone & Install Dependencies

```bash
# Clone the repository
git clone <repo-url>
cd laravel-engr-test

# Install PHP dependencies
composer install

# Install JavaScript dependencies
npm install
```

### Step 2: Environment Setup

```bash
# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate
```

### Step 3: Database Setup

```bash
# Create SQLite database
touch database/database.sqlite

# Run database migrations
php artisan migrate

# Seed with test insurers and data
php artisan db:seed
```

### Step 4: Run frontend server

```bash
# Development mode with hot reload
npm run dev
```

### Step 5: Start the Application

```bash
# In a terminal, run the Laravel server
php artisan serve

# Application available at: http://localhost:8000
```

**Note:** Claim submission requires authentication.

---

## Database Schema

The system uses five main tables to store information about claims, insurance companies, and healthcare providers:

### Users Table
Stores information about healthcare providers who submit claims. Each provider has a login account.

**What it contains:** Provider name, email, password, account creation date

### Insurers Table
Stores the rules and settings for each insurance company. This controls how claims are grouped together (batched) for processing.

**What it contains:**
- **Name** — Insurance company name (e.g., "HealthCare Plus")
- **Code** — Short identifier (e.g., "HC_PLUS")
- **Minimum batch size** — The fewest number of claims that can make a batch (e.g., at least 5 claims needed to create a batch)
- **Maximum batch size** — The most number of claims allowed in one batch (e.g., can't have more than 50 claims in one batch)
- **Daily capacity** — The maximum total value the insurer can process per day (e.g., can't exceed ₦500,000 per day)
- **Date preference** — Whether to group claims by when they were treated (encounter date) or when they were received (submission date)
- **Specialty costs** — Special multiplier costs for different medical specialties (e.g., cardiology costs 15% less, orthopedics costs 15% more)

### Claims Table
Stores individual medical claims submitted by providers. Think of this as the main claim record.

**What it contains:**
- **Claim ID** — Unique number for this claim
- **Provider ID** — Who submitted it (links to Users table)
- **Insurer ID** — Which insurance company it goes to (links to Insurers table)
- **Batch ID** — If grouped, which batch it belongs to (empty if not yet grouped)
- **Provider name** — Name of the hospital or clinic
- **Encounter date** — When the patient was treated
- **Submission date** — When the provider sent in the claim
- **Specialty** — Type of medical care (cardiology, orthopedics, etc.)
- **Priority level** — How urgent (1=low priority to 5=very urgent)
- **Total amount** — How much the claim is for (in Naira)
- **Processing cost** — System-calculated cost to handle this claim
- **Status** — Current state: pending (waiting to be grouped), batched (grouped and ready), processing, or completed

### Claim Items Table
Stores the individual services or procedures listed within a claim. A single claim can have multiple items.

**What it contains:** Item description (e.g., "emergency room visit"), how many times it was done, and the cost per item

### Batches Table
Stores grouped claims after the system has optimized them. Multiple related claims are combined into one batch for the insurance company to process together.

**What it contains:**
- **Batch ID** — Unique number for this batch
- **Insurer ID** — Which insurance company receives this batch
- **Provider name** — The hospital/clinic that submitted these claims
- **Batch date** — When the batch was created
- **Status** — pending, processing, or completed
- **Total claims** — How many claims are in this batch
- **Total cost** — Total processing cost for all claims combined

---


### Submit Claim

**To submit a claim, send a POST request to:** `/api/claims`


**Request body fields:**
- **insurer_id** — The ID number of the insurance company (required)
- **provider_name** — Name of your hospital or clinic (required)
- **encounter_date** — The date the patient was treated (required)
- **submission_date** — The date you're submitting the claim (required)
- **specialty** — Medical specialty like "cardiology" or "orthopedics" (required)
- **priority_level** — How urgent is this claim? 1=low, 5=very urgent (required)
- **items** — A list of services/procedures. Each item needs: description, quantity, and unit_price (required, at least one item)