---
name: webo-mcp-ability-woocommerce
description: >-
  Documents the optional WEBO MCP WooCommerce addon and its 10 unified webo/woo-*
  query/mutate abilities for products, orders, customers, coupons, and store settings.
  Use when an MCP client connected through webo-mcp needs WooCommerce catalog/order/
  customer/coupon/store automation via tools/call, including variable product creation,
  variation management, order status updates, coupon lifecycle, and payment gateway config.
---

# WEBO MCP - WooCommerce addon

## Instructions

1. **Prerequisites:** [`webo-mcp-guide`](../webo-mcp-guide/SKILL.md). Woo tools exist only when the optional `webo-mcp-woocommerce` addon is installed and active together with WooCommerce.
2. **Tool namespace:** addon tools are exposed as **`webo/woo-*`** via the Abilities bridge.
3. **Pattern:** Each unified ability receives a required `action` field that routes to the underlying handler via `match()`.

### Unified abilities (10 total)

| `name` | Permission | Actions |
|--------|------------|---------|
| `webo/woo-query-products` | `edit_products` | `get`, `list`, `list-categories`, `get-variations` |
| `webo/woo-mutate-products` | `edit_products` | `create`, `update`, `delete`, `create-category`, `create-variable`, `add-variation` |
| `webo/woo-query-orders` | `manage_woocommerce` | `get`, `list` |
| `webo/woo-mutate-orders` | `manage_woocommerce` | `create`, `update`, `delete` |
| `webo/woo-query-customers` | `list_users` | `get`, `list` |
| `webo/woo-mutate-customers` | `edit_users` | `update` |
| `webo/woo-query-coupons` | `manage_woocommerce` | `get`, `list` |
| `webo/woo-mutate-coupons` | `manage_woocommerce` | `create`, `update`, `delete` |
| `webo/woo-query-store` | `manage_woocommerce` | `get-settings`, `list-gateways` |
| `webo/woo-mutate-store` | `manage_woocommerce` | `update-settings`, `update-gateway` |

### Action reference

**Products:**
| Action | Required inputs | Notes |
|--------|----------------|-------|
| `get` | `id` | Full product details, taxonomy, images, attributes, meta |
| `list` | — | Filters: `status`, `type`, `category`, `tag`, `limit`, `offset`, `orderby`, `order` |
| `list-categories` | — | Filters: `search`, `parent_id`, `hide_empty`, `per_page` |
| `get-variations` | `product_id` | Variable product only; returns all variation details |
| `create` | `name` | `type` = simple/grouped/external/variable; supports attributes, images, meta |
| `update` | `id` | Partial update; supports all product fields |
| `delete` | `id` | `force=true` for permanent delete |
| `create-category` | `name` | Optional: `slug`, `description`, `parent_id` |
| `create-variable` | `name`, `attributes` | Creates parent + optional `variations` array |
| `add-variation` | `product_id`, `attributes` | `attributes` is `{"Size": "M", "Color": "Red"}` map |

**Orders:**
| Action | Required inputs | Notes |
|--------|----------------|-------|
| `get` | `id` | Full order with line items, totals, addresses, meta |
| `list` | — | Filters: `status`, `customer_id`, `limit`, `offset`, `orderby`, `order` |
| `create` | `line_items` | Each item needs `product_id` + `quantity`; `status` default `pending` |
| `update` | `id` | Partial: `status`, `billing`, `shipping`, `customer_note`, `meta_data` |
| `delete` | `id` | `force=true` for permanent delete |

**Customers:**
| Action | Required inputs | Notes |
|--------|----------------|-------|
| `get` | `id` | Profile, billing/shipping, order count, total spent |
| `list` | — | WP users with role=customer; `limit`, `offset`, `orderby`, `order` |
| `update` | `id` | `email`, `first_name`, `last_name`, `billing`, `shipping` |

**Coupons:**
| Action | Required inputs | Notes |
|--------|----------------|-------|
| `get` | `id` | Full coupon details |
| `list` | — | `limit`, `offset` |
| `create` | `code` | `discount_type`: percent/fixed_cart/fixed_product; `date_expires` = Y-m-d |
| `update` | `id` | `amount`, `usage_limit`, `date_expires`, `description` |
| `delete` | `id` | `force=true` for permanent delete |

**Store:**
| Action | Required inputs | Notes |
|--------|----------------|-------|
| `get-settings` | `group` | Groups: `general`, `account`, `products`, `advanced`; optional `ids` filter |
| `list-gateways` | — | Lists all installed payment gateways with enabled state |
| `update-settings` | `group`, `settings` | `settings` = `[{id, value}]` array |
| `update-gateway` | `gateway_id` | `enabled`, `title`, `description`, `instructions`, `settings` object |

4. **Safe workflow:**
   - Read first with a `query-*` ability
   - Then mutate with the smallest payload possible
   - Confirm destructive operations (`delete` with `force=true`) before execution

5. **MCP endpoint:**
Use the WEBO MCP router endpoint `POST /wp-json/mcp/v1/router` with JSON-RPC flow `initialize -> tools/list -> tools/call`.

## Examples

Create a variable product with attributes and variations:

```json
{
  "name": "webo/woo-mutate-products",
  "arguments": {
    "action": "create-variable",
    "name": "Premium Hoodie",
    "status": "publish",
    "attributes": [
      { "name": "Size", "options": ["S", "M", "L"], "visible": true },
      { "name": "Color", "options": ["Black", "Blue"], "visible": true }
    ],
    "variations": [
      { "attributes": { "Size": "M", "Color": "Black" }, "regular_price": "29.99", "stock_status": "instock" }
    ]
  }
}
```

List processing orders for one customer:

```json
{
  "name": "webo/woo-query-orders",
  "arguments": {
    "action": "list",
    "status": "processing",
    "customer_id": 42,
    "limit": 20,
    "order": "DESC"
  }
}
```

Update customer billing info:

```json
{
  "name": "webo/woo-mutate-customers",
  "arguments": {
    "action": "update",
    "id": 42,
    "billing": {
      "first_name": "John",
      "last_name": "Doe",
      "address_1": "123 Main St",
      "city": "Hanoi",
      "country": "VN"
    }
  }
}
```

Create a limited percent coupon:

```json
{
  "name": "webo/woo-mutate-coupons",
  "arguments": {
    "action": "create",
    "code": "SUMMER25",
    "discount_type": "percent",
    "amount": "25",
    "usage_limit": 100,
    "usage_limit_per_user": 1,
    "date_expires": "2026-08-31"
  }
}
```

Read general store settings:

```json
{
  "name": "webo/woo-query-store",
  "arguments": {
    "action": "get-settings",
    "group": "general"
  }
}
```