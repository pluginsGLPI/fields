# 📄 Using the Fields Plugin via GLPI REST API

## ⚠️ Note

The use of the Fields plugin via the High Level API is not yet available.
This documentation only concerns the Legacy API.

## Table of Contents

- [Overview](#overview)
- [Prerequisites](#prerequisites)
- [Authentication](#authentication)
- [Understanding Field Naming](#understanding-field-naming)
- [Container Types & Targeting](#container-types--targeting)
- [Creating Items with Custom Fields](#creating-items-with-custom-fields)
- [Updating Items with Custom Fields](#updating-items-with-custom-fields)
- [Field Types Reference](#field-types-reference)
- [Reading Custom Field Values](#reading-custom-field-values)
- [Searching Items by Custom Fields](#searching-items-by-custom-fields)
- [Multiple Values Fields](#multiple-values-fields)
- [Complete Workflow Examples](#complete-workflow-examples)
- [Troubleshooting](#troubleshooting)

---

## Overview

The Fields plugin allows custom fields to be created and updated via the GLPI REST API. Custom fields are passed **directly alongside native GLPI fields** in the `input` object of create (`POST`) and update (`PUT`) requests.

**Minimum versions required:**
- GLPI ≥ 11.0.2
- Fields plugin ≥ 1.23.3 (with fix [pluginsGLPI/fields#1154](https://github.com/pluginsGLPI/fields/pull/1154))

---

## Prerequisites

Before making API calls, ensure:

1. The Fields plugin is **installed and enabled**
2. The API is enabled in **Setup > General > API**
3. An API client exists (with valid IP range and optionally an App-Token)
4. The user/token has a **profile with Fields rights** (read/write on the relevant containers)
5. The containers are **active** and assigned to the target entity

---

## Authentication

### Init Session with credentials

```bash
curl -X GET \
  'https://glpi.example.com/apirest.php/initSession' \
  -H 'Content-Type: application/json' \
  -H 'Authorization: Basic BASE64_LOGIN_PASSWORD' \
  -H 'App-Token: YOUR_APP_TOKEN'
```

### Init Session with user token

```bash
curl -X GET \
  'https://glpi.example.com/apirest.php/initSession' \
  -H 'Content-Type: application/json' \
  -H 'Authorization: user_token YOUR_USER_TOKEN' \
  -H 'App-Token: YOUR_APP_TOKEN'
```

### Response

```json
{
  "session_token": "abc123def456"
}
```

> Use `session_token` in all subsequent requests via the `Session-Token` header.

---

## Understanding Field Naming

When a field is created in the Fields plugin, its **internal name** (used as the API key) is derived from the label:

1. The label is lowercased
2. The singular form is extracted
3. Only alphanumeric characters are kept
4. Digits are replaced by their English name (`0` → `zero`, `1` → `one`, etc.)
5. The suffix `field` is appended

**Examples:**

| Label | Internal Name (API key) |
|---|---|
| `Serial Number` | `serialnumberfield` |
| `Cost` | `costfield` |
| `Ref. 2024` | `reftwozerotwofourfield` |

The exact key to use depends on the **field type** — see [Field Types Reference](#field-types-reference).

> **Tip:** You can find the exact field names by querying the search options endpoint (see [Searching Items by Custom Fields](#searching-items-by-custom-fields)).

---

## Container Types & Targeting

The Fields plugin supports three container types:

| Type | Display | Description |
|---|---|---|
| `dom` | Injected in main form | Fields appear directly in the item's main form. **Only one `dom` container per itemtype.** |
| `tab` | Separate tab | Fields appear in a dedicated tab. Multiple `tab` containers are allowed. |
| `domtab` | Injected in a specific tab | Fields are injected inside an existing tab's form. |

### Auto-detection (recommended for `dom` containers)

When your itemtype has a **single `dom` container**, the plugin automatically detects it. You don't need to specify any container identifier — just pass the field keys directly:

```json
{
  "input": {
    "name": "My Ticket",
    "serialnumberfield": "SN-12345"
  }
}
```

### Explicit container targeting with `c_id`

When targeting a **`tab` or `domtab` container**, or when **multiple containers** exist for the same itemtype, you must specify the container ID using the `c_id` key:

```json
{
  "input": {
    "c_id": 5,
    "serialnumberfield": "SN-12345"
  }
}
```

> `c_id` is the `id` from the `glpi_plugin_fields_containers` table. You can find it in the Fields plugin configuration page URL, or by querying the `PluginFieldsContainer` itemtype via API.

### Updating multiple containers in one request

To update fields in **multiple containers** (e.g., one `dom` + one `tab`), you need **separate API calls**, one per container, each with its own `c_id`.

---

## Creating Items with Custom Fields

### Ticket with `dom` container fields (auto-detect)

```bash
curl -X POST \
  'https://glpi.example.com/apirest.php/Ticket' \
  -H 'Content-Type: application/json' \
  -H 'Session-Token: YOUR_SESSION_TOKEN' \
  -H 'App-Token: YOUR_APP_TOKEN' \
  -d '{
    "input": {
      "name": "Server disk failure",
      "content": "<p>Disk /dev/sda1 is failing on srv-prod-01</p>",
      "entities_id": 0,
      "type": 1,
      "urgency": 4,
      "impact": 4,
      "serialnumberfield": "SRV-2024-0042",
      "costfield": "1500.00"
    }
  }'
```

### Ticket with `tab` container fields (explicit `c_id`)

```bash
curl -X POST \
  'https://glpi.example.com/apirest.php/Ticket' \
  -H 'Content-Type: application/json' \
  -H 'Session-Token: YOUR_SESSION_TOKEN' \
  -H 'App-Token: YOUR_APP_TOKEN' \
  -d '{
    "input": {
      "name": "Network outage",
      "content": "<p>Network is down in building B</p>",
      "entities_id": 0,
      "c_id": 7,
      "locationdetailfield": "Building B, Floor 3",
      "affectedusercountfield": "150"
    }
  }'
```

### Computer with custom fields

```bash
curl -X POST \
  'https://glpi.example.com/apirest.php/Computer' \
  -H 'Content-Type: application/json' \
  -H 'Session-Token: YOUR_SESSION_TOKEN' \
  -H 'App-Token: YOUR_APP_TOKEN' \
  -d '{
    "input": {
      "name": "SRV-PROD-01",
      "entities_id": 0,
      "states_id": 1,
      "assettagnumberfield": "ASSET-2024-001",
      "purchasedatefield": "2024-03-15"
    }
  }'
```

### Response

```json
{
  "id": 42,
  "message": ""
}
```

---

## Updating Items with Custom Fields

### Update native + plugin fields together (dom, auto-detect)

```bash
curl -X PUT \
  'https://glpi.example.com/apirest.php/Ticket/42' \
  -H 'Content-Type: application/json' \
  -H 'Session-Token: YOUR_SESSION_TOKEN' \
  -H 'App-Token: YOUR_APP_TOKEN' \
  -d '{
    "input": {
      "id": 42,
      "name": "Server disk failure [UPDATED]",
      "urgency": 5,
      "serialnumberfield": "SRV-2024-0042-R1",
      "costfield": "2500.00"
    }
  }'
```

### Update only plugin fields (tab container, explicit `c_id`)

```bash
curl -X PUT \
  'https://glpi.example.com/apirest.php/Ticket/42' \
  -H 'Content-Type: application/json' \
  -H 'Session-Token: YOUR_SESSION_TOKEN' \
  -H 'App-Token: YOUR_APP_TOKEN' \
  -d '{
    "input": {
      "id": 42,
      "c_id": 7,
      "locationdetailfield": "Building B, Floor 3, Room 301"
    }
  }'
```

### Update only native fields (plugin fields are NOT erased)

```bash
curl -X PUT \
  'https://glpi.example.com/apirest.php/Ticket/42' \
  -H 'Content-Type: application/json' \
  -H 'Session-Token: YOUR_SESSION_TOKEN' \
  -H 'App-Token: YOUR_APP_TOKEN' \
  -d '{
    "input": {
      "id": 42,
      "urgency": 5
    }
  }'
```

> Updating only native fields will **not** erase existing plugin field values. Plugin fields are stored in a separate table.

### Bulk update

```bash
curl -X PUT \
  'https://glpi.example.com/apirest.php/Ticket' \
  -H 'Content-Type: application/json' \
  -H 'Session-Token: YOUR_SESSION_TOKEN' \
  -H 'App-Token: YOUR_APP_TOKEN' \
  -d '{
    "input": [
      { "id": 42, "serialnumberfield": "SN-001" },
      { "id": 43, "serialnumberfield": "SN-002" }
    ]
  }'
```

---

## Field Types Reference

### Text (`text`)

Single-line text field.

```json
{ "input": { "id": 42, "mytextfield": "Hello World" } }
```

### Textarea (`textarea`)

Multi-line plain text field.

```json
{ "input": { "id": 42, "descriptionfield": "Line 1\nLine 2\nLine 3" } }
```

### Rich Text (`richtext`)

HTML content field. Supports standard HTML tags.

```json
{ "input": { "id": 42, "detailfield": "<p>This is <strong>rich</strong> text</p>" } }
```

### Number (`number`)

Numeric value stored as string. Commas are automatically converted to dots.

```json
{ "input": { "id": 42, "costfield": "1500.50" } }
```

### URL (`url`)

URL field.

```json
{ "input": { "id": 42, "documentationlinkfield": "https://wiki.example.com/kb/12345" } }
```

### Date (`date`)

Date in `YYYY-MM-DD` format.

```json
{ "input": { "id": 42, "deadlinefield": "2025-12-31" } }
```

### Date & Time (`datetime`)

Date and time in `YYYY-MM-DD HH:MM:SS` format.

```json
{ "input": { "id": 42, "scheduledatfield": "2025-06-15 14:30:00" } }
```

### Yes/No (`yesno`)

Boolean field. `0` = No, `1` = Yes.

```json
{ "input": { "id": 42, "isurgentfield": 1 } }
```

### Custom Dropdown (`dropdown`)

A dropdown specific to the Fields plugin. The value is the **ID** of the dropdown entry in the plugin's own dropdown table (`glpi_plugin_fields_{fieldname}dropdowns`).

The API key uses the format: **`plugin_fields_{fieldname}dropdowns_id`**

```json
{ "input": { "id": 42, "plugin_fields_criticalityfieldropdowns_id": 3 } }
```

> To list available values, query the dynamic class via API:
> ```bash
> curl -X GET \
>   'https://glpi.example.com/apirest.php/PluginFieldsCriticalityfieldDropdown' \
>   -H 'Session-Token: YOUR_SESSION_TOKEN' \
>   -H 'App-Token: YOUR_APP_TOKEN'
> ```

### GLPI Dropdown (`dropdown-User`, `dropdown-Computer`, etc.)

A dropdown pointing to an existing GLPI itemtype. The value is the **ID** of the referenced item.

The API key uses the format: **`{foreignkey}_{fieldname}`**

Where `{foreignkey}` is the standard GLPI foreign key for the itemtype:
- `User` → `users_id`
- `Computer` → `computers_id`
- `Location` → `locations_id`
- `Group` → `groups_id`
- `Supplier` → `suppliers_id`

**Example: dropdown-User field (label "Manager")**

```json
{ "input": { "id": 42, "users_id_managerfield": 5 } }
```

**Example: dropdown-Location field (label "Site")**

```json
{ "input": { "id": 42, "locations_id_sitefield": 12 } }
```

**Example: dropdown-Computer field (label "Related Asset")**

```json
{ "input": { "id": 42, "computers_id_relatedassetfield": 7 } }
```

### GLPI Item (`glpi_item`)

A polymorphic field that can reference any GLPI item. It requires **two keys**:
- `itemtype_{fieldname}` — the class name of the referenced itemtype
- `items_id_{fieldname}` — the ID of the referenced item

```json
{
  "input": {
    "id": 42,
    "itemtype_relateditemfield": "Computer",
    "items_id_relateditemfield": 15
  }
}
```

Valid itemtype values include any `CommonDBTM` subclass: `Computer`, `Monitor`, `NetworkEquipment`, `Printer`, `Phone`, `Software`, `User`, `Group`, etc.

---

## Reading Custom Field Values

### Via Search API (recommended)

Plugin fields are registered as **search options** on the parent itemtype. Use the `listSearchOptions` endpoint to discover them:

```bash
curl -X GET \
  'https://glpi.example.com/apirest.php/listSearchOptions/Ticket' \
  -H 'Session-Token: YOUR_SESSION_TOKEN' \
  -H 'App-Token: YOUR_APP_TOKEN'
```

Fields plugin options appear with IDs starting at **8100+**. Example response excerpt:

```json
{
  "8101": {
    "name": "My Container > Serial Number",
    "table": "glpi_plugin_fields_ticketmycontainers",
    "field": "serialnumberfield",
    "datatype": "string",
    "nosearch": false
  }
}
```

Then use the `searchItems` endpoint with `forcedisplay` to retrieve those values:

```bash
curl -X GET \
  'https://glpi.example.com/apirest.php/search/Ticket?forcedisplay[0]=1&forcedisplay[1]=2&forcedisplay[2]=8101' \
  -H 'Session-Token: YOUR_SESSION_TOKEN' \
  -H 'App-Token: YOUR_APP_TOKEN'
```

### Via direct subitem query

The dynamically generated container class can be queried directly:

```bash
# List all plugin field values for a given container type
curl -X GET \
  'https://glpi.example.com/apirest.php/PluginFieldsTicketmycontainer/?searchText[items_id]=42' \
  -H 'Session-Token: YOUR_SESSION_TOKEN' \
  -H 'App-Token: YOUR_APP_TOKEN'
```

The class name follows the pattern: `PluginFields{Itemtype}{containername}` (without trailing 's')

Examples:
- Container "Extra Info" on Ticket → `PluginFieldsTicketextrainfo`
- Container "Asset Details" on Computer → `PluginFieldsComputerassetdetail`

---

## Searching Items by Custom Fields

Use the search option IDs discovered via `listSearchOptions`:

```bash
# Find tickets where custom field "serialnumberfield" (search option 8101) equals "SRV-2024-0042"
curl -X GET \
  'https://glpi.example.com/apirest.php/search/Ticket?criteria[0][field]=8101&criteria[0][searchtype]=equals&criteria[0][value]=SRV-2024-0042&forcedisplay[0]=8101' \
  -H 'Session-Token: YOUR_SESSION_TOKEN' \
  -H 'App-Token: YOUR_APP_TOKEN'
```

---

## Multiple Values Fields

Fields marked as "multiple" accept **arrays of IDs**:

### Custom dropdown (multiple)

```json
{
  "input": {
    "id": 42,
    "plugin_fields_tagfieldropdowns_id": [1, 3, 5]
  }
}
```

### GLPI dropdown (multiple, e.g. dropdown-User)

```json
{
  "input": {
    "id": 42,
    "users_id_assignedtofield": [2, 7, 12]
  }
}
```

Values are stored as a JSON array internally. On update, by default, the new array **replaces** the existing values entirely.

---

## Complete Workflow Examples

### Example 1: Full lifecycle — Create and update a Ticket with all field types

**Setup assumed:**  
- Container "Incident Details" (type `dom`, id `5`) on `Ticket` with fields:
  - `referencefield` (text)
  - `costfield` (number)
  - `isescalatedfield` (yesno)
  - `duedatefield` (date)
  - `plugin_fields_priorityfieldropdowns_id` (custom dropdown)
  - `users_id_approverfield` (dropdown-User)
  - `itemtype_relateditemfield` + `items_id_relateditemfield` (glpi_item)

**Step 1: Init Session**

```bash
curl -X GET \
  'https://glpi.example.com/apirest.php/initSession' \
  -H 'Content-Type: application/json' \
  -H 'Authorization: user_token abc123def456' \
  -H 'App-Token: YOUR_APP_TOKEN'
```

**Step 2: Create Ticket with all custom fields**

```bash
curl -X POST \
  'https://glpi.example.com/apirest.php/Ticket' \
  -H 'Content-Type: application/json' \
  -H 'Session-Token: YOUR_SESSION_TOKEN' \
  -H 'App-Token: YOUR_APP_TOKEN' \
  -d '{
    "input": {
      "name": "Production server failure",
      "content": "<p>Server srv-prod-01 is unresponsive since 14:00</p>",
      "entities_id": 0,
      "type": 1,
      "urgency": 5,
      "impact": 5,
      "referencefield": "INC-2025-0042",
      "costfield": "3500.00",
      "isescalatedfield": 0,
      "duedatefield": "2025-03-05",
      "plugin_fields_priorityfieldropdowns_id": 2,
      "users_id_approverfield": 5,
      "itemtype_relateditemfield": "Computer",
      "items_id_relateditemfield": 15
    }
  }'
```

**Step 3: Update — escalate the ticket**

```bash
curl -X PUT \
  'https://glpi.example.com/apirest.php/Ticket/42' \
  -H 'Content-Type: application/json' \
  -H 'Session-Token: YOUR_SESSION_TOKEN' \
  -H 'App-Token: YOUR_APP_TOKEN' \
  -d '{
    "input": {
      "id": 42,
      "urgency": 5,
      "isescalatedfield": 1,
      "plugin_fields_priorityfieldropdowns_id": 4,
      "users_id_approverfield": 8
    }
  }'
```

**Step 4: Kill Session**

```bash
curl -X GET \
  'https://glpi.example.com/apirest.php/killSession' \
  -H 'Session-Token: YOUR_SESSION_TOKEN' \
  -H 'App-Token: YOUR_APP_TOKEN'
```

### Example 2: Ticket with both `dom` and `tab` container fields

When your Ticket has:
- A `dom` container (id `5`): "Incident Details" with `referencefield`
- A `tab` container (id `7`): "SLA Info" with `slatargetfield`

You need **two separate update calls**:

```bash
# Update dom container fields (auto-detected, no c_id needed)
curl -X PUT \
  'https://glpi.example.com/apirest.php/Ticket/42' \
  -H 'Content-Type: application/json' \
  -H 'Session-Token: YOUR_SESSION_TOKEN' \
  -H 'App-Token: YOUR_APP_TOKEN' \
  -d '{
    "input": {
      "id": 42,
      "referencefield": "INC-2025-0042"
    }
  }'
```

```bash
# Update tab container fields (explicit c_id required)
curl -X PUT \
  'https://glpi.example.com/apirest.php/Ticket/42' \
  -H 'Content-Type: application/json' \
  -H 'Session-Token: YOUR_SESSION_TOKEN' \
  -H 'App-Token: YOUR_APP_TOKEN' \
  -d '{
    "input": {
      "id": 42,
      "c_id": 7,
      "slatargetfield": "2025-03-10 18:00:00"
    }
  }'
```

### Example 3: Ticket with multiple `tab` containers

When your Ticket has several `tab` containers, each must be updated with a **separate API call** using its `c_id`:

- Tab container **"SLA Info"** (id `7`): fields `slatargetfield`, `slastatusfield`
- Tab container **"Finance"** (id `9`): fields `costcenterfield`, `budgetcodefield`
- Tab container **"External Ref"** (id `12`): fields `vendorreffield`

```bash
# Update "SLA Info" tab (c_id = 7)
curl -X PUT \
  'https://glpi.example.com/apirest.php/Ticket/42' \
  -H 'Content-Type: application/json' \
  -H 'Session-Token: YOUR_SESSION_TOKEN' \
  -H 'App-Token: YOUR_APP_TOKEN' \
  -d '{
    "input": {
      "id": 42,
      "c_id": 7,
      "slatargetfield": "2025-03-10 18:00:00",
      "slastatusfield": "On Track"
    }
  }'
```

```bash
# Update "Finance" tab (c_id = 9)
curl -X PUT \
  'https://glpi.example.com/apirest.php/Ticket/42' \
  -H 'Content-Type: application/json' \
  -H 'Session-Token: YOUR_SESSION_TOKEN' \
  -H 'App-Token: YOUR_APP_TOKEN' \
  -d '{
    "input": {
      "id": 42,
      "c_id": 9,
      "costcenterfield": "CC-4200",
      "budgetcodefield": "BUD-2025-Q1"
    }
  }'
```

```bash
# Update "External Ref" tab (c_id = 12)
curl -X PUT \
  'https://glpi.example.com/apirest.php/Ticket/42' \
  -H 'Content-Type: application/json' \
  -H 'Session-Token: YOUR_SESSION_TOKEN' \
  -H 'App-Token: YOUR_APP_TOKEN' \
  -d '{
    "input": {
      "id": 42,
      "c_id": 12,
      "vendorreffield": "VENDOR-TKT-98765"
    }
  }'
```

> **Important:** Each API call targets **one container only** via `c_id`. You cannot mix fields from different containers in the same request. If you omit `c_id` when multiple `tab` containers exist, the plugin will attempt to auto-detect a `dom` container first, then fall back to the first matching `tab` — which may not be the one you intend.

### Example 4: Working with custom dropdown values

**Step 1: List available dropdown values**

```bash
curl -X GET \
  'https://glpi.example.com/apirest.php/PluginFieldsPriorityfieldDropdown?range=0-50' \
  -H 'Session-Token: YOUR_SESSION_TOKEN' \
  -H 'App-Token: YOUR_APP_TOKEN'
```

Response:
```json
[
  { "id": 1, "name": "Low", "comment": "", "completename": "Low" },
  { "id": 2, "name": "Medium", "comment": "", "completename": "Medium" },
  { "id": 3, "name": "High", "comment": "", "completename": "High" },
  { "id": 4, "name": "Critical", "comment": "", "completename": "Critical" }
]
```

**Step 2: Create a new dropdown value**

```bash
curl -X POST \
  'https://glpi.example.com/apirest.php/PluginFieldsPriorityfieldDropdown' \
  -H 'Content-Type: application/json' \
  -H 'Session-Token: YOUR_SESSION_TOKEN' \
  -H 'App-Token: YOUR_APP_TOKEN' \
  -d '{
    "input": {
      "name": "Blocker",
      "comment": "Production blocker",
      "entities_id": 0,
      "is_recursive": 1
    }
  }'
```

**Step 3: Use the value in a ticket**

```json
{ "input": { "id": 42, "plugin_fields_priorityfieldropdowns_id": 5 } }
```

---

## Troubleshooting

### Custom fields are silently ignored

- **Check plugin version**: Ensure you're running Fields plugin ≥ 1.23.3 with the API fix ([#1154](https://github.com/pluginsGLPI/fields/pull/1154))
- **Check container is active**: Disabled containers are ignored
- **Check entity visibility**: The container must be visible in the entity of the target item
- **Check profile rights**: The API user's profile must have read/write access to the container in the Fields plugin profile settings
- **Check field names**: Use `listSearchOptions` to verify the exact field names

### Error "field c_id is not allowed"

This error does not happen — `c_id` is silently consumed by the plugin and removed from the input before GLPI processes it. If you see unexpected errors, verify the field names are spelled correctly.

### How to find the container ID (`c_id`)

```bash
curl -X GET \
  'https://glpi.example.com/apirest.php/PluginFieldsContainer?range=0-50' \
  -H 'Session-Token: YOUR_SESSION_TOKEN' \
  -H 'App-Token: YOUR_APP_TOKEN'
```

Look for the `id`, `name`, `label`, `type`, and `itemtypes` fields in the response.

### How to find the exact field key name

```bash
curl -X GET \
  'https://glpi.example.com/apirest.php/PluginFieldsField?searchText[plugin_fields_containers_id]=5&range=0-50' \
  -H 'Session-Token: YOUR_SESSION_TOKEN' \
  -H 'App-Token: YOUR_APP_TOKEN'
```

The `name` column in the response is the base key to use in API calls. For custom dropdown fields, prefix it as `plugin_fields_{name}dropdowns_id`.