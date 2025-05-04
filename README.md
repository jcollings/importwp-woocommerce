# WooCommerce Product Importer

Requires Import WP: 2.11.6

**Version: 2.3.6**

![WooCommerce Product Importer](./assets/iwp-addon-woocommerce.png)

## Description

Import WP WooCommerce Product Importer Addon adds a new Template allowing you to import WooCommerce Variable, Simple, Grouped and External Products. Combine this with the power of Import WP PRO allowing you to import custom fields and many other features.

### Import WooCommerce Simple Products

Import WP WooCommerce Product Importer gives you control over what fields are imported when importing Simple Products. Import General settings allowing you to import the price, promotional/sale price and any tax settings. Import shipping settings such as product dimensions and shipping class. Import Linked products such as Upsells and Cross-sells products. Import Product Attributes using Global or local attributes from existing or new attribute terms. Import attachments such as Feature Image and Product Gallery Images. Import Taxonomies such as Product Categories, Tags and any custom taxonomies. Import custom fields declared by your theme or plugins.

### Import WooCommerce External Products

External products can be imported by setting the Product Type to external and configuring the External Product fields to set the product url and button text along with any of the simple product fields which form the base of most product types.

### Import WooCommerce Grouped Products

Grouped products are imported by setting the Product Type to grouped, setting the products which are to be grouped from a list of products, along with any of the simple product fields to populate the grouped products details.

### Import WooCommerce Variable Products and Product Variations

Importing Variable Products and their product variations can be done by first importing the parent variable product with all the product attributes and attribute terms needed to create the relationships with its product variations. Next With the variable products created importing the product variations need to be linked to the parent variable product, also setting there unique product attributes that create the variation.

### Permissions

| Section             | Field key             |
| ------------------- | --------------------- |
| Product Gallery     | product_gallery.\*    |
| Product Downloads   | product_downloads.\*  |
| Product Attributes  | product_attributes.\* |
| Product Upsells     | product_upsell        |
| Product Cross-sells | product_crosssell     |
| Product Grouped     | product_grouped       |

## Installation

The WooCommerce Product Importer Addon can currently only be installed by downloading from [github.com](https://github.com/jcollings/importwp-woocommerce) via the Releases tab of the repository.

1. Download the latest version via the [Releases page on github](https://github.com/jcollings/importwp-woocommerce/releases).
1. Upload ‘importwp-woocommerce’ to the ‘/wp-content/plugins/’ directory
1. Activate the plugin through the ‘Plugins’ menu in WordPress
1. When creating an importer, a new template should appear on the template dropdown.

## Frequently Asked Questions

## Screenshots

## Changelog

### 2.3.7

- ADD - Allow GTIN field to be used as a unique identifier using value `_global_unique_id`

### 2.3.6

- FIX - Skip attribute if no attribute name is passed.

### 2.3.5

- ADD - Add new field for "GTIN, UPC, EAN, or ISBN".
- FIX - stop empty local attributes being added.

### 2.3.4

- ADD - Add featured product field to advanced section.
- FIX - Fix issue where adding a new attribute onto a product would not set used for variations unless updated.

### 2.3.3

- FIX - Fix issue with blank screen caused by filtering array values, and then the javascript treating the array as an object.

### 2.3.2

- FIX - Fix updater script to get rid of php warning message.
- ADD - Add new option to allow the ability to append product attributes instead of clearing previous.
- ADD - Update exporter to export all core WooCommerce product types.
- ADD - Auto populate field map in importer when using a default export file.

### 2.3.1

- FIX - Product variations now add required attributes to its parent variable product.

### 2.3.0

- ADD - Add Product fields to ImportWP new Permission field Interface.

### 2.2.1

- ADD - Add filter `iwp/wc_ignore_empty_variable_attributes` to exclude empty terms when importing variable products.
- ADD - Add list of unique fields to new dropdown in ImportWP v2.8.2

### 2.2.0

- FIX - Update exporter to work with Import WP 2.7.0
- FIX - fix product attribute permissions
- ADD - new filter `iwp/woocommerce/product_attributes/keep_existing` to allow appending of attributes.

### 2.1.2

- ADD - New Attribute "Used for variations" field.
