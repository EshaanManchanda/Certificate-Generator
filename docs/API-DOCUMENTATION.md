# GEMA API Documentation

This document describes all API endpoints for the GEMA ecosystem including subscription, payments, and license verification.

## Base URLs

| Service | URL |
|---------|-----|
| Backend (Node.js) | `http://localhost:5000` |
| WordPress | `https://your-wordpress-site.com` |

---

## Backend Endpoints

Base URL: `http://localhost:5000`

### Payment Endpoints

#### GET /api/payments/plans

Get available subscription plans.

**Auth:** None (public)

**Response:**
```json
{
  "success": true,
  "plans": [
    {
      "id": "free",
      "name": "Free",
      "price": 0,
      "price_display": "Free",
      "certificates": 100,
      "features": ["Basic PDF certificates", "Manual certificate issue", "Search shortcode"]
    },
    {
      "id": "pro",
      "name": "Pro",
      "price": 499,
      "price_display": "₹499/month",
      "certificates": 1000,
      "popular": true,
      "features": ["1000 certificates/month", "CSV import", "Bulk ZIP download", "API access"]
    },
    {
      "id": "business",
      "name": "Business",
      "price": 999,
      "price_display": "₹999/month",
      "certificates": 0,
      "features": ["Unlimited certificates", "Remove branding", "Commercial license"]
    }
  ]
}
```

---

#### POST /api/payments/create-link

Create Razorpay payment link for subscription.

**Auth:** JWT (Bearer token)

**Headers:**
```
Authorization: Bearer <jwt_token>
Content-Type: application/json
```

**Body:**
```json
{
  "plan": "pro"
}
```

**Valid plans:** `pro`, `business`

**Response:**
```json
{
  "success": true,
  "payment_link_id": "plink_xxxxxxxx",
  "short_url": "https://razorpay.in/xxxxx"
}
```

---

#### GET /api/payments/subscription

Get current user's subscription status.

**Auth:** JWT (Bearer token)

**Headers:**
```
Authorization: Bearer <jwt_token>
```

**Response:**
```json
{
  "plan": "pro",
  "status": "active",
  "expiry": "2026-12-31",
  "usage": 150,
  "limit": 1000,
  "license_key": "PRO-XXXXX",
  "is_pro": true,
  "is_business": false
}
```

---

#### POST /api/payments/verify-license

Verify a license key validity.

**Auth:** None (public)

**Headers:**
```
Content-Type: application/json
```

**Body:**
```json
{
  "license_key": "PRO-XXXXX"
}
```

**Response (valid):**
```json
{
  "success": true,
  "valid": true,
  "message": "License is valid",
  "plan": "pro",
  "expiry": "2026-12-31",
  "is_expired": false,
  "usage": 150,
  "limit": 1000,
  "is_pro": true,
  "is_business": false
}
```

**Response (invalid):**
```json
{
  "success": false,
  "valid": false,
  "message": "Invalid license key"
}
```

---

#### POST /api/payments/webhook

Handle Razorpay payment webhook events.

**Auth:** Signature verification

**Headers:**
```
X-Razorpay-Signature: <razorpay_signature>
Content-Type: application/json
```

**Body:** (Razorpay webhook payload)
```json
{
  "event": "payment.captured",
  "payload": {
    "payment": {
      "entity": {
        "id": "pay_123456",
        "amount": 49900,
        "status": "captured"
      }
    }
  }
}
```

**Response:**
```json
{
  "received": true
}
```

---

### Resume Endpoints

#### GET /api/resumes/templates

Get available resume templates.

**Auth:** None (public)

**Response:**
```json
{
  "success": true,
  "templates": [
    {
      "name": "modern",
      "display_name": "Modern",
      "category": "Professional",
      "description": "A clean and contemporary design.",
      "ats_friendly": true
    },
    {
      "name": "classic",
      "display_name": "Classic",
      "category": "Traditional",
      "description": "A timeless and elegant layout.",
      "ats_friendly": true
    },
    {
      "name": "creative",
      "display_name": "Creative",
      "category": "Creative",
      "description": "A unique design for artistic fields.",
      "ats_friendly": false
    },
    {
      "name": "professional",
      "display_name": "Professional",
      "category": "Professional",
      "description": "A structured and formal template.",
      "ats_friendly": true
    }
  ]
}
```

---

### Health Check

#### GET /health

Check backend and service health.

**Auth:** None (public)

**Response:**
```json
{
  "status": "ok",
  "services": {
    "backend": "healthy",
    "database": "connected",
    "aiService": "healthy"
  },
  "timestamp": "2026-04-20T12:00:00.000Z"
}
```

---

## WordPress Plugin Endpoints

Base URL: `https://your-wordpress-site.com`

### Namespace: cg/v1

#### GET /wp-json/cg/v1/payment/plans

Get subscription plans from WordPress.

**Auth:** None (public)

**Response:**
```json
{
  "plans": [...],
  "configured": true
}
```

---

#### POST /wp-json/cg/v1/payment/create-link

Create Razorpay payment link.

**Auth:** WordPress login required

**Body:**
```json
{
  "plan": "pro"
}
```

**Response:**
```json
{
  "success": true,
  "payment_link_id": "plink_xxxxx",
  "short_url": "https://razorpay.in/xxxxx"
}
```

---

#### GET /wp-json/cg/v1/payment/subscription

Get current subscription.

**Auth:** WordPress login required

**Response:**
```json
{
  "plan": "pro",
  "status": "active",
  "expiry": "2026-12-31",
  "usage": 150,
  "limit": 1000,
  "license_key": "PRO-XXXXX",
  "is_pro": true,
  "is_business": false
}
```

---

#### GET /wp-json/cg/v1/payment/history

Get payment history.

**Auth:** WordPress login required (admin capability)

**Response:**
```json
{
  "payments": [
    {
      "id": 1,
      "license_key": "PRO-XXXXX",
      "plan": "pro",
      "amount": 49900,
      "payment_date": "2026-01-15 10:00:00",
      "status": "captured",
      "razorpay_payment_id": "pay_xxxxx"
    }
  ]
}
```

---

#### POST /wp-json/cg/v1/verify-license

Verify a license key.

**Auth:** None (public)

**Body:**
```json
{
  "license_key": "PRO-XXXXX"
}
```

**Response:**
```json
{
  "success": true,
  "valid": true,
  "message": "License is valid",
  "plan": "pro",
  "expiry": "2026-12-31",
  "is_expired": false,
  "usage": 150,
  "limit": 1000,
  "is_pro": true,
  "is_business": false
}
```

---

#### POST /wp-json/cg/v1/activate-license

Activate a license key (called by backend after payment).

**Auth:** None (public)

**Body:**
```json
{
  "license_key": "PRO-XXXXX",
  "plan": "pro",
  "expiry": "2026-12-31",
  "user_email": "user@example.com"
}
```

**Response:**
```json
{
  "success": true,
  "message": "License activated",
  "plan": "pro",
  "expiry": "2026-12-31"
}
```

---

### Namespace: certificate-generator/v1

#### GET /wp-json/certificate-generator/v1/health

API health check.

**Auth:** None (public)

**Response:**
```json
{
  "status": "ok",
  "version": "7.0.0"
}
```

---

#### POST /wp-json/certificate-generator/v1/validate-key

Validate API key.

**Auth:** API key required

**Body:**
```json
{
  "api_key": "your_api_key"
}
```

**Response:**
```json
{
  "valid": true,
  "permissions": ["issue", "bulk"]
}
```

---

#### GET /wp-json/certificate-generator/v1/verify/{serial}

Verify certificate by serial number.

**Auth:** None (public)

**Response:**
```json
{
  "valid": true,
  "certificate": {
    "serial": "CERT-2026-001",
    "name": "John Doe",
    "course": "Web Development",
    "date": "2026-01-15"
  }
}
```

---

#### GET /wp-json/certificate-generator/v1/certificates-by-email

Get certificates for an email.

**Auth:** API key required

**Params:** `?email=user@example.com`

**Response:**
```json
{
  "certificates": [
    {
      "id": 1,
      "serial": "CERT-2026-001",
      "name": "John Doe",
      "course": "Web Development",
      "date": "2026-01-15"
    }
  ]
}
```

---

## Usage Flow

### 1. Purchase Subscription Flow

```
1. GET /api/payments/plans → View plans
2. POST /api/payments/create-link (auth) → Get payment link
3. User pays on Razorpay
4. POST /api/payments/webhook → Backend receives webhook
5. Backend POST /cg/v1/activate-license → WordPress activates license
6. GET /api/payments/subscription → Check status
```

### 2. Verify License Flow

```
1. POST /api/payments/verify-license
   OR
1. POST /wp-json/cg/v1/verify-license
2. Get license validity response
```

---

## Error Responses

**400 Bad Request:**
```json
{
  "success": false,
  "message": "Invalid plan. Use pro or business"
}
```

**401 Unauthorized:**
```json
{
  "success": false,
  "message": "Access token required"
}
```

**403 Forbidden:**
```json
{
  "success": false,
  "message": "Invalid or expired token"
}
```

**404 Not Found:**
```json
{
  "success": false,
  "message": "Route not found"
}
```

**500 Server Error:**
```json
{
  "success": false,
  "message": "Internal server error"
}
```

---

## Postman Collection

Import `postman-collection.json` for ready-to-use API requests.

## Environment Variables

| Variable | Description |
|----------|------------|
| `base_url` | Backend URL (e.g., `http://localhost:5000`) |
| `wp_url` | WordPress site URL |
| `auth_token` | JWT authentication token |