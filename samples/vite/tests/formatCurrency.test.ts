import { expect, test } from 'vitest'
import { formatCurrency } from '../src/formatCurrency'

test('formats cents as dollars', () => {
  expect(formatCurrency(1234)).toBe('$12.34')
})
