// Draws the app icon (puck in a blue ring on navy) as PNG without extra dependencies.
// Run: node scripts/make-icons.mjs  -> public/icon-192.png, icon-512.png, apple-touch-icon.png
import { writeFileSync } from 'node:fs'
import { deflateSync } from 'node:zlib'

const NAVY = [17, 26, 40], BLUE = [34, 112, 230], PUCK = [8, 10, 14], RIM = [70, 80, 96]

function crc32(buf) {
  let c, crc = 0xffffffff
  for (const b of buf) { c = (crc ^ b) & 0xff; for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1; crc = (crc >>> 8) ^ c }
  return (crc ^ 0xffffffff) >>> 0
}
function chunk(type, data) {
  const len = Buffer.alloc(4); len.writeUInt32BE(data.length)
  const td = Buffer.concat([Buffer.from(type), data]); const crc = Buffer.alloc(4); crc.writeUInt32BE(crc32(td))
  return Buffer.concat([len, td, crc])
}
const clamp = (v) => Math.max(0, Math.min(1, v))
const mix = (a, b, t) => a.map((v, i) => v + (b[i] - v) * t)

function draw(size) {
  const raw = Buffer.alloc(size * (size * 4 + 1))
  for (let y = 0; y < size; y++) {
    raw[y * (size * 4 + 1)] = 0
    for (let x = 0; x < size; x++) {
      const u = (x + 0.5) / size - 0.5, v = (y + 0.5) / size - 0.5
      const aa = 1.2 / size
      let c = NAVY
      // blue ring
      const r = Math.hypot(u, v)
      c = mix(c, BLUE, clamp((0.03 - Math.abs(r - 0.3)) / aa))
      // puck seen at an angle: top ellipse + side
      const top = Math.hypot(u / 0.17, (v + 0.02) / 0.07)
      const side = Math.abs(u) < 0.17 && v > -0.02 && v < 0.05 ? 1 : 0
      const bottom = Math.hypot(u / 0.17, (v - 0.05) / 0.07)
      c = mix(c, PUCK, Math.max(side * clamp((0.17 - Math.abs(u)) / aa), clamp((1 - bottom) / (aa / 0.07))))
      c = mix(c, RIM, clamp((1 - top) / (aa / 0.07)))
      c = mix(c, PUCK, clamp((0.9 - top) / (aa / 0.07)))
      const o = y * (size * 4 + 1) + 1 + x * 4
      raw[o] = c[0]; raw[o + 1] = c[1]; raw[o + 2] = c[2]; raw[o + 3] = 255
    }
  }
  const ihdr = Buffer.alloc(13); ihdr.writeUInt32BE(size, 0); ihdr.writeUInt32BE(size, 4); ihdr[8] = 8; ihdr[9] = 6
  return Buffer.concat([Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]), chunk('IHDR', ihdr), chunk('IDAT', deflateSync(raw)), chunk('IEND', Buffer.alloc(0))])
}

for (const [name, size] of [['icon-192.png', 192], ['icon-512.png', 512], ['apple-touch-icon.png', 180], ['favicon-32.png', 32]]) {
  writeFileSync(new URL(`../public/${name}`, import.meta.url), draw(size))
}
console.log('icons written')
