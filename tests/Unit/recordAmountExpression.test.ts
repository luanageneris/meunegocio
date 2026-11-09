import { strict as assert } from 'node:assert';
import { test } from 'node:test';
import { calculateAmountCents } from '../../resources/js/utils/recordAmountExpression.ts';

test('accepts direct, relative and explicit arithmetic with Brazilian decimals', () => {
    assert.equal(calculateAmountCents('123,45', 10000), 12345);
    assert.equal(calculateAmountCents('1.234,56', 10000), 123456);
    assert.equal(calculateAmountCents('+ 10,50', 10000), 11050);
    assert.equal(calculateAmountCents('- 10', 10000), 9000);
    assert.equal(calculateAmountCents('* 2', 10000), 20000);
    assert.equal(calculateAmountCents('/ 4', 10000), 2500);
    assert.equal(calculateAmountCents('100,00 + 12,50', 0), 11250);
    assert.equal(calculateAmountCents('1 / 3', 0), 33);
    assert.equal(calculateAmountCents('10,075', 0), 1008);
    assert.equal(calculateAmountCents('0,29 * 100', 0), 2900);
});

test('rejects invalid expressions, negative results and division by zero', () => {
    for (const expression of ['', '100 +', '1 + 2 + 3', 'abc', '1 / 0', '- 101', '1 ^ 2']) {
        assert.throws(() => calculateAmountCents(expression, 10000));
    }
});
