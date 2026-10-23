import {Field, PaymentMethod, PlaceOrderButton, ShippingMethod, SuccessPage} from '@loki-checkout/checkout-objects';
import {setupCheckout} from '@loki/setup-checkout';
import {test, expect} from '@loki/test';
import moduleConfig, {themes, withTheme} from './config/config';

// The field itself is provided by Loki_QuoteExtendedExample and stored in the column `example_field`
const FIELD_ID = 'loki-checkout-customer-example-field-field';

const addressFields = {
    'customer.email': 'jane@example.com',
    'shipping.firstname': 'Jane',
    'shipping.lastname': 'Doe',
    'shipping.city': 'Baarn',
    'shipping.street0': 'Amalialaan',
    'shipping.street1': '126',
    'shipping.telephone': '0123456789',
};

async function placeOrder(page, fields: Record<string, string>) {
    for (const [fieldName, fieldValue] of Object.entries(fields)) {
        const field = new Field(page, fieldName);
        await field.fill(fieldValue);
        await field.expectValue(fieldValue);
    }

    await (new ShippingMethod(page, 'flatrate_flatrate')).select();
    await (new PaymentMethod(page, 'checkmo')).select();
    await (new PlaceOrderButton(page)).click();

    await new SuccessPage(page).expectToBeLoaded();
}

test.describe('Loki_QuoteExtended test', () => {
    for (const theme of themes) {
        test(`stores the value with the quote (${theme.name})`, async ({page, context}) => {
            await setupCheckout(page, context, withTheme(moduleConfig, theme));

            await new Field(page, FIELD_ID).expectValue('');
            await new Field(page, FIELD_ID).fill('Stored value');

            await page.reload();

            await new Field(page, FIELD_ID).expectValue('Stored value');
        });

        test(`overwrites an existing value (${theme.name})`, async ({page, context}) => {
            await setupCheckout(page, context, withTheme(moduleConfig, theme));

            await new Field(page, FIELD_ID).fill('First value');
            await new Field(page, FIELD_ID).fill('Second value');

            await page.reload();

            await new Field(page, FIELD_ID).expectValue('Second value');
        });

        test(`places an order with a value (${theme.name})`, async ({page, context}) => {
            await setupCheckout(page, context, withTheme(moduleConfig, theme));

            await placeOrder(page, {...addressFields, [FIELD_ID]: 'Order value'});
        });

        test(`places an order without a value (${theme.name})`, async ({page, context}) => {
            await setupCheckout(page, context, withTheme(moduleConfig, theme));

            await placeOrder(page, addressFields);
        });

        test(`does not copy the value into a new quote after an order (${theme.name})`, async ({page, context}) => {
            await setupCheckout(page, context, withTheme(moduleConfig, theme));

            await placeOrder(page, {...addressFields, [FIELD_ID]: 'Previous order value'});

            await setupCheckout(page, context, withTheme(moduleConfig, theme));

            const field = new Field(page, FIELD_ID);
            await expect(page.getById(FIELD_ID)).toBeVisible();
            await field.expectValue('');
        });
    }
});
