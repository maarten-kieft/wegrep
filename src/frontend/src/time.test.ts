import { describe, expect, it } from 'vitest'
import { formatTime, parseTime } from './time'

describe('formatTime', () => {
  it('formats seconds as mm:ss', () => {
    expect(formatTime(1512)).toBe('25:12')
    expect(formatTime(5)).toBe('00:05')
  })
  it('never goes below zero', () => {
    expect(formatTime(-3)).toBe('00:00')
  })
})

describe('parseTime', () => {
  it('reads the usual notations', () => {
    expect(parseTime('25:12')).toBe(1512)
    expect(parseTime('2512')).toBe(1512)
    expect(parseTime('25.12')).toBe(1512)
  })
  it('rejects seconds above 59 and garbage', () => {
    expect(parseTime('10:75')).toBeNull()
    expect(parseTime('abc')).toBeNull()
  })
})
