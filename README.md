
# ot_formprefill

**Form Prefill for TYPO3 Frontend Users** – A TYPO3 v13 extension that automatically fills in form fields for logged‑in frontend users (`fe_users`).
Works out‑of‑the‑box with the native **Form Framework** (EXT:form) and any form that follows the `tx_form_formframework[<formIdentifier>][<field>]` naming scheme.
Field mapping is fully configurable via FlexForm or `config.yaml` – no TypoScript required.

---

## Features

- Prefills input and textarea fields on the client side via JavaScript.
- Secure PSR‑15 middleware that exposes **only whitelisted `fe_users` fields** as JSON (`/prefill-user.json`).
- Flexible one‑to‑one mapping between `fe_users` columns and form field keys (configured per content element).
- Multiple forms per page supported.
- Implemented as a dedicated **`CType`** (`formprefill`) – ready for TYPO3 v14 (no `list_type`).
- Zero database changes and no modifications to existing forms.

---

## Requirements

| | |
|---|---|
| TYPO3 | **13.4 LTS** (13.4.14 +) |
| PHP | 8.1 + |
| Composer | 2.x |

---

## Installation

```bash
composer require oliverthiele/ot-formprefill
```

* Flush caches.

---

## Configuration

### Site Configuration (`config.yaml`)

```yaml
otFormprefill:
  allowedFields:
    - name
    - email
    - company
  formMappings:
    contactForm-42:
      email: email
      fullName: name
```

### Extension Configuration (`ext_conf_template.txt`)

```text
# cat=basic/enable; type=string; label=Allowed FE User fields
allowedFields = username,name,title,first_name,middle_name,last_name,company,address,zip,city,country,telephone,fax,email,www
```

Fields defined here are used as a fallback when no SiteConfig is present.

---

## TypoScript Setup

A full TypoScript setup is provided.

### Option 1: Manual import

If you manage TypoScript manually (e.g. in your sitepackage), include it like this:

```typo3_typoscript
@import 'EXT:ot_formprefill/Configuration/TypoScript/setup.typoscript'
```

### Option 2: TYPO3 v13 Set autoloading

If you're using TYPO3 v13's new "Site sets" system, you can add the dependency:

```yaml
name: example/my-site-package
label: 'My Site Package'
dependencies:
  - oliverthiele/ot-formprefill
```

---

## Usage

### 1. Add the content element

Insert this content element on the same page as the form element

### 2. Configure the FlexForm

   | Field               | Description                                                                                      |
   |---------------------|--------------------------------------------------------------------------------------------------|
   | **Form Identifier** | Optional. Unique prefix for the form, e.g. `contactForm-42`. Required when multiple forms exist. |
   | **Mapping**         | One mapping per line: `fe_usersField:formFieldKey`, e.g.`company:firma``email:email`             |

If no mapping is configured, automatic mapping is used: `fe_users.column` → identical form field name.

### 3. Result

After rendering, the ViewHelper will inject the mapping into the page as JavaScript:

```html
<script>
window.formPrefillMappings = {
  "contactForm-42": {
    "email": "email",
    "fullName": "name"
  }
}
</script>
```

---

## Middleware: `/prefill-user.json`

When a frontend user is logged in, the middleware returns a JSON payload of allowed fields:

```json
{
  "name": "Max Mustermann",
  "email": "max@example.com"
}
```

The allowed fields are taken from (in order):

1. `site/config.yaml` → `otFormprefill.allowedFields`
2. Extension settings (`ext_conf_template.txt`)
3. Fallback default list

---

## Developer Notes

- JavaScript is located in `Resources/Public/JavaScript/FormPrefill.js`, embedded via `<f:asset.script defer="true">`
- Middleware route defined in `Configuration/RequestMiddlewares.php`
- CType registered in `TCA/Overrides/tt_content.php`
- Icon registration: `Configuration/Icons.php`
- Form detection logic in ViewHelper resolves `persistenceIdentifier` + content element UID as fallback identifier

---

## Roadmap

- [ ] Unit tests for the middleware.

---

## License

GPL‑2.0‑or‑later – see `LICENSE`

---

## Author

**Oliver Thiele** – [oliver-thiele.de](https://www.oliver-thiele.de)
Mail: <mail@oliver-thiele.de>
