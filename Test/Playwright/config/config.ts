import coreConfig from '@loki-checkout/config';
import merge from '@loki/util/merge';

export type Theme = {
    name: string;
    theme: string;
};

export const themes: Theme[] = [
    {name: 'Luma', theme: 'Magento/luma'},
    {name: 'Hyvä', theme: 'Loki/hyva-dev'},
];

export const withTheme = (config: any, theme: Theme) => merge(config, {theme: theme.theme});

export default merge(coreConfig, {
    modules: [
        'Loki_QuoteExtended',
        'Loki_QuoteExtendedExample',
    ],
    config: {
        'customer/address/gender_show': 'opt',
    }
});
