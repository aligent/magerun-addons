# Aligent Magerun Addons

This package is intended to provide a home for additional n98 commands and overrides to extend the functionality for Aligent specific use cases as we find them.

## Commands

### dev:media:pull

Used to pull/synchronise cherry picked product images from a remote base URL to the local environment where they don't already exist.

#### Parameters/options:

- Base URL.  This is the first parameter and is required.  The base domain, including protocol, from which to pull media.  E.g `https://www.stagingsite.com.au/`
- `--category` A valid Magento Category ID.  Pull all media for products within this category.  (Does not check subcategories) 
- `--sku` A valid Magento SKU.  Pull all media for this SKU.

#### Examples:

Media for all products in category 20:
- `n98magerun.phar dev:media:pull https://www.stagingsite.com.au/ --category=20`

Media for the SKU ABC123:
- `n98magerun.phar dev:media:pull https://www.stagingsite.com.au/ --sku=ABC123`

Media for all products in category 20, plus media for SKU ABC123 :
- `n98magerun.phar dev:media:pull https://www.stagingsite.com.au/ --category=20 --sku=ABC123`

