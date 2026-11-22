import { describe, expect, it } from 'vitest'
import { formatTime, nowHHMM, parseTime } from './time'

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

describe('nowHHMM', () => {
  it('writes the local time as hh:mm', () => {
    expect(nowHHMM(new Date(2026, 10, 22, 9, 5))).toBe('09:05')
    expect(nowHHMM(new Date(2026, 10, 22, 21, 21))).toBe('21:21')
  })
})
