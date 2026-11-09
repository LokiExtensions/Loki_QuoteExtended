#!/bin/bash
# Install Loki_QuoteExtendedExample, which provides the field used by the Playwright tests (never in production)
set -e

composer show loki-checkout/magento2-quote-extended-example >/dev/null 2>&1 \
  || composer require -n --dev -- loki-checkout/magento2-quote-extended-example:@dev
bin/magento module:enable Loki_QuoteExtendedExample
