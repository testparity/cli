import { test } from '@japa/runner'
import { displayName } from '../../../app/services/user_presenter'

test.group('user_presenter', () => {
  test('formats display name', ({ assert }) => {
    assert.equal(displayName({ firstName: 'Ada', lastName: 'Lovelace' }), 'Ada Lovelace')
  })
})
