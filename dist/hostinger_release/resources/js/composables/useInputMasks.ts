const onlyDigits = (value: string): string => value.replace(/\D/g, '');

const formatCpf = (value: string): string => {
    const digits = onlyDigits(value).slice(0, 11);

    if (digits.length <= 3) return digits;
    if (digits.length <= 6) return `${digits.slice(0, 3)}.${digits.slice(3)}`;
    if (digits.length <= 9) return `${digits.slice(0, 3)}.${digits.slice(3, 6)}.${digits.slice(6)}`;

    return `${digits.slice(0, 3)}.${digits.slice(3, 6)}.${digits.slice(6, 9)}-${digits.slice(9)}`;
};

const formatCnpj = (value: string): string => {
    const digits = onlyDigits(value).slice(0, 14);

    if (digits.length <= 2) return digits;
    if (digits.length <= 5) return `${digits.slice(0, 2)}.${digits.slice(2)}`;
    if (digits.length <= 8) return `${digits.slice(0, 2)}.${digits.slice(2, 5)}.${digits.slice(5)}`;
    if (digits.length <= 12) return `${digits.slice(0, 2)}.${digits.slice(2, 5)}.${digits.slice(5, 8)}/${digits.slice(8)}`;

    return `${digits.slice(0, 2)}.${digits.slice(2, 5)}.${digits.slice(5, 8)}/${digits.slice(8, 12)}-${digits.slice(12)}`;
};

const formatCpfCnpj = (value: string): string => {
    const digits = onlyDigits(value);
    return digits.length <= 11 ? formatCpf(digits) : formatCnpj(digits);
};

const formatPhone = (value: string): string => {
    const digits = onlyDigits(value).slice(0, 11);

    if (digits.length <= 2) return digits;
    if (digits.length <= 6) return `(${digits.slice(0, 2)}) ${digits.slice(2)}`;
    if (digits.length <= 10) return `(${digits.slice(0, 2)}) ${digits.slice(2, 6)}-${digits.slice(6)}`;

    return `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7)}`;
};

const formatCurrencyInput = (value: string | number): string => {
    const digits = onlyDigits(String(value));
    if (!digits) return '';

    const normalized = Number(digits) / 100;

    return normalized.toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
};

const parseCurrencyInput = (value: string | number): number => {
    if (typeof value === 'number') return Number.isFinite(value) ? value : 0;

    const normalized = value.replace(/\./g, '').replace(',', '.').trim();
    const parsed = Number(normalized);

    return Number.isFinite(parsed) ? parsed : 0;
};

export function useInputMasks() {
    return {
        formatCpf,
        formatCnpj,
        formatCpfCnpj,
        formatPhone,
        formatCurrencyInput,
        parseCurrencyInput,
    };
}
