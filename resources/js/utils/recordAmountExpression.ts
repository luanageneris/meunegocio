const numberPattern = '(?:\\d{1,3}(?:\\.\\d{3})+,\\d+|\\d+(?:[,.]\\d+)?)';
const expressionPattern = new RegExp(`^\\s*(${numberPattern})?\\s*([+\\-*/])?\\s*(${numberPattern})?\\s*$`);

type Fraction = { numerator: bigint; denominator: bigint };

function parseNumber(value: string): Fraction {
    const normalized = value.includes(',') ? value.replace(/\./g, '').replace(',', '.') : value;
    const [whole, decimal = ''] = normalized.split('.');
    return { numerator: BigInt(whole + decimal), denominator: 10n ** BigInt(decimal.length) };
}

export function calculateAmountCents(expression: string, currentCents: number): number {
    if (expression.length > 100 || !Number.isSafeInteger(currentCents) || currentCents < 0) {
        throw new Error('O valor informado está fora do limite permitido.');
    }
    const match = expressionPattern.exec(expression);
    if (!match) throw new Error('Informe um valor ou uma operação válida, como + 10 ou 100,00 * 2.');

    const [, first, operator, second] = match;
    if ((!operator && (!first || second)) || (operator && !second)) {
        throw new Error('Informe um valor ou uma operação válida, como + 10 ou 100,00 * 2.');
    }

    const left = first ? parseNumber(first) : { numerator: BigInt(currentCents), denominator: 100n };
    const right = second ? parseNumber(second) : { numerator: 0n, denominator: 1n };
    let result = left;
    if (operator === '+' || operator === '-') {
        const rightNumerator = right.numerator * left.denominator;
        result = {
            numerator: left.numerator * right.denominator + (operator === '+' ? rightNumerator : -rightNumerator),
            denominator: left.denominator * right.denominator,
        };
    }
    if (operator === '*') result = { numerator: left.numerator * right.numerator, denominator: left.denominator * right.denominator };
    if (operator === '/') {
        if (right.numerator === 0n) throw new Error('Não é possível dividir por zero.');
        result = { numerator: left.numerator * right.denominator, denominator: left.denominator * right.numerator };
    }

    if (result.numerator < 0n) {
        throw new Error('O resultado deve ser um valor não negativo e dentro do limite permitido.');
    }
    const cents = (result.numerator * 200n + result.denominator) / (result.denominator * 2n);
    if (cents > BigInt(Number.MAX_SAFE_INTEGER)) {
        throw new Error('O resultado deve ser um valor não negativo e dentro do limite permitido.');
    }
    return Number(cents);
}
