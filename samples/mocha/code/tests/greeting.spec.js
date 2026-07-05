const assert = require('node:assert/strict')
const { greeting } = require('../src/greeting')

describe('greeting', () => {
  it('greets by name', () => {
    assert.equal(greeting('Ada'), 'Hello, Ada')
  })
})
