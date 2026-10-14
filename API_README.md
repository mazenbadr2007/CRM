# CRM REST API

Base URL: `http://<your-domain>/CRM/public/api/`

## Authentication

Every request must include an API key, generated from **Settings → API Access**
in the CRM (each key belongs to one company; a key can never see or modify
another company's data). Send it as either:

```
Authorization: Bearer crmapi_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```
or
```
X-API-Key: crmapi_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

Unauthenticated or invalid requests get `401`. A revoked key also returns `401`.
A key belonging to a company whose subscription is inactive returns `403`.

## Endpoints

### `GET /api/clients.php`
List this company's clients.

Query params: `limit` (default 50, max 200), `offset` (default 0).

```json
{
  "data": [ { "id": 1, "client_name": "...", "phone_number": "...", "...": "..." } ],
  "meta": { "total": 12, "limit": 50, "offset": 0 }
}
```

### `GET /api/clients.php?id=5`
Get a single client (must belong to the authenticated company). `404` if not found.

### `POST /api/clients.php`
Create a client. Accepts JSON body or `application/x-www-form-urlencoded`.

Required: `client_name`, `phone_number`.
Optional: `project_location`, `project_name`, `unit_area`, `the_role`, `receiving`,
`unit_price`, `down_payment`, `installment`, `installment_amount`, `visit`,
`call_outcome`, `visit_outcome`, `final_status`, `event_title`, `event_date`,
`event_time`, `note`, `lead_source` (one of Facebook, Instagram, TikTok, Website,
WhatsApp, Referral).

Returns `201` with the created client, or `422` if required fields are missing.

### `GET /api/properties.php`
List this company's properties (read-only). Same `limit`/`offset` params.

### `GET /api/properties.php?id=5`
Get a single property (must belong to the authenticated company). `404` if not found.

## Notes

- All data is automatically scoped to the company the API key belongs to.
- Keys can be revoked at any time from Settings → API Access; revoked keys stop
  working immediately.
- This API is additive — it does not change any existing CRM page, action, or
  behavior.
