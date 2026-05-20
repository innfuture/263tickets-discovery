/**
 * ISO-4217 codes commonly used by event organisers on this platform.
 * Order: defaults first (USD), then African (focus market), then other globals.
 */
export type CurrencyOption = {
    code: string;
    name: string;
    symbol: string;
};

export const SUPPORTED_CURRENCIES: CurrencyOption[] = [
    { code: 'USD', name: 'US Dollar', symbol: '$' },
    { code: 'EUR', name: 'Euro', symbol: '€' },
    { code: 'GBP', name: 'British Pound', symbol: '£' },

    { code: 'ZAR', name: 'South African Rand', symbol: 'R' },
    { code: 'ZIG', name: 'Zimbabwe Gold', symbol: 'ZiG' },
    { code: 'BWP', name: 'Botswana Pula', symbol: 'P' },
    { code: 'KES', name: 'Kenyan Shilling', symbol: 'KSh' },
    { code: 'UGX', name: 'Ugandan Shilling', symbol: 'USh' },
    { code: 'TZS', name: 'Tanzanian Shilling', symbol: 'TSh' },
    { code: 'NGN', name: 'Nigerian Naira', symbol: '₦' },
    { code: 'GHS', name: 'Ghanaian Cedi', symbol: '₵' },
    { code: 'EGP', name: 'Egyptian Pound', symbol: 'E£' },
    { code: 'MAD', name: 'Moroccan Dirham', symbol: 'د.م.' },
    { code: 'XAF', name: 'Central African CFA Franc', symbol: 'FCFA' },
    { code: 'XOF', name: 'West African CFA Franc', symbol: 'CFA' },

    { code: 'AED', name: 'UAE Dirham', symbol: 'د.إ' },
    { code: 'AUD', name: 'Australian Dollar', symbol: 'A$' },
    { code: 'CAD', name: 'Canadian Dollar', symbol: 'C$' },
    { code: 'CNY', name: 'Chinese Yuan', symbol: '¥' },
    { code: 'INR', name: 'Indian Rupee', symbol: '₹' },
    { code: 'JPY', name: 'Japanese Yen', symbol: '¥' },
    { code: 'BRL', name: 'Brazilian Real', symbol: 'R$' },
];

export function findCurrency(
    code: string | null | undefined,
): CurrencyOption | undefined {
    if (!code) {
        return undefined;
    }

    return SUPPORTED_CURRENCIES.find(
        (c) => c.code.toUpperCase() === code.toUpperCase(),
    );
}

export function formatPrice(amount: number, code: string): string {
    const currency = findCurrency(code);
    const symbol = currency?.symbol ?? code;

    return `${symbol}${amount.toFixed(2)}`;
}
