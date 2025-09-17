### WC Product Enhancer

A simple WooCommerce plugin that adds a custom BOGO (Buy One, Get One) offer tab to product pages.

This plugin allows store owners to create flexible BOGO deals, displaying them directly on the product page to encourage sales.

---

### 🚀 Features

- **Custom Product Tab:** Adds a dedicated tab to the product page titled "BOGO Offer" for eligible products.
- **Flexible BOGO Rules:** Easily set up a "Buy X, Get Y" rule for any product.
- **Multiple Discount Types:** Supports offers with a percentage discount, a fixed amount discount, or a completely free item.
- **Internationalization Ready:** All user-facing strings are prepared for translation using WordPress's standard i18n functions and the `wc-product-enhancer` text domain.
- **Secure Output:** All dynamic content is properly sanitized and escaped to prevent security vulnerabilities like XSS.

---

### 🛠️ Installation

1.  Download the plugin files and compress them into a `.zip` archive.
2.  Navigate to **Plugins** > **Add New** in your WordPress dashboard.
3.  Click **Upload Plugin**, select the `.zip` file, and click **Install Now**.
4.  After the installation is complete, click \*\*Activate Plugin`.

Alternatively, you can upload the unzipped plugin folder to the `/wp-content/plugins/` directory via FTP.

---

### 📖 Usage

To set up a BOGO offer for a product:

1.  Go to the **Products** section in your WordPress dashboard and edit the product you want to apply the offer to.
2.  In the **Product data** panel, locate the BOGO offer settings (this assumes a corresponding admin interface exists to set the `_bogo_` meta fields).
3.  Enable the BOGO rule, set the "buy" and "get" quantities, and choose your preferred discount type and value.
4.  **Update** the product.

The "BOGO Offer" tab will automatically appear on the front-end product page, displaying the offer details to your customers.

---

### 🌐 Internationalization

This plugin is fully internationalization-ready. The code uses WordPress's `__()` and `esc_html__` functions to make all text strings translatable.

- **Text Domain:** `wc-product-enhancer`

You can use tools like **Poedit** or **WP-CLI** to generate a `.pot` file and create translations in any language.

---

### 🛡️ Security

The plugin is developed with security in mind. Key measures include:

- **Input Validation & Sanitation:** All meta data retrieved from the database is sanitized using functions like `absint()`, `floatval()`, and `wp_kses_post()` before being used.
- **Secure Output:** All dynamic output is properly escaped and sanitized using `wp_kses_post()` to prevent malicious code injection. The final output is secured at the point of `echo`.

---

### 📄 Changelog

#### `1.0.0`

- Initial release.
- Adds the BOGO product tab with support for percentage, fixed, and free discounts.
- Includes robust internationalization and security measures.

---

### 📜 License

This project is licensed under the MIT License.
