# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 13 November 2026
### Fixed
- Skip column lookups in admin labelled list when there is no row
- Cover cart merge on login and atomic value saving
- Keep extended quote data when a guest logs in during checkout
- Save checkout values atomically and validate the target column

### Added
- Add opt-in labelled list of values to admin sales pages
- Copy extended quote values when editing an order in the admin
- Add frontend view model for order view and success page
- Support shipment and credit memo emails and admin pages
- Expose extended quote values as order extension attribute

## [0.0.3] - 11 November 2026
### Fixed
- Fix trait `om()` used in other classes as well

## [0.0.2] - 29 October 2026
### Fixed
- Declare `Magento_Store` and phpunit dependencies

## [0.0.1] - 22 September 2026
- Initial release
