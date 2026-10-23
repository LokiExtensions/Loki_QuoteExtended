#!/bin/bash
# The Playwright tests use the field of Loki_QuoteExtendedExample, because this module has no frontend of its own
if [ "$CI_JOB_NAME" = "playwright" ]; then
    composer config repositories.loki-checkout-dev composer https://gitlab.yireo.com/api/v4/group/loki-checkout-dev/-/packages/composer/packages.json
    composer require -n --no-update -- loki-checkout/magento2-quote-extended-example:@dev
fi
