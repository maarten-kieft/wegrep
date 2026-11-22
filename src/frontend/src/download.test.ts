import { describe, expect, it } from 'vitest'
import { reportFileName } from './download'

describe('reportFileName', () => {
  it('makes a readable, safe name', () => {
    expect(reportFileName(199824, 'Dordrecht Lions', 'Smoke Eaters Geleen', 'xml'))
      .toBe('egrep-199824-dordrecht-lions-smoke-eaters-geleen.xml')
  })
  it('drops accents and odd characters, and copes with missing team names', () => {
    expect(reportFileName(5, 'Ünited / HC', '', 'hd')).toBe('egrep-5-united-hc.hd')
  })
})
