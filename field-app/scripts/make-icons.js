#!/usr/bin/env node
'use strict';
// Generates the PWA icons (brand-blue tile with a simple gauge mark) using only
// Node's zlib, so no image tooling is needed. Run: node scripts/make-icons.js
const fs = require('fs');
const path = require('path');
const zlib = require('zlib');

const crcTable = Array.from({ length: 256 }, (_, n) => { let c = n; for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1; return c >>> 0; });
const crc = (buf) => { let c = 0xffffffff; for (const b of buf) c = crcTable[(c ^ b) & 255] ^ (c >>> 8); return (c ^ 0xffffffff) >>> 0; };
const chunk = (type, data) => {
  const len = Buffer.alloc(4); len.writeUInt32BE(data.length);
  const body = Buffer.concat([Buffer.from(type), data]);
  const c = Buffer.alloc(4); c.writeUInt32BE(crc(body));
  return Buffer.concat([len, body, c]);
};

function png(size) {
  const bg = [0x1d, 0x4e, 0x89], fg = [0xff, 0xff, 0xff], accent = [0xf5, 0x9f, 0x00];
  const raw = Buffer.alloc((size * 4 + 1) * size);
  const cx = size / 2, cy = size * 0.56;
  for (let y = 0; y < size; y++) {
    raw[y * (size * 4 + 1)] = 0;
    for (let x = 0; x < size; x++) {
      const dx = x - cx, dy = y - cy, d = Math.hypot(dx, dy);
      let px = bg;
      const R = size * 0.30, T = size * 0.055;
      // gauge: top semicircle ring + needle
      if (d > R - T && d < R + T && dy <= 0) px = fg;
      if (d < size * 0.05) px = accent;
      const na = -Math.PI * 0.32;                             // needle pointing up-right
      const along = dx * Math.cos(na) + dy * Math.sin(na), across = -dx * Math.sin(na) + dy * Math.cos(na);
      if (along > 0 && along < R * 0.85 && Math.abs(across) < size * 0.022) px = accent;
      const o = y * (size * 4 + 1) + 1 + x * 4;
      raw[o] = px[0]; raw[o + 1] = px[1]; raw[o + 2] = px[2]; raw[o + 3] = 255;
    }
  }
  const ihdr = Buffer.alloc(13); ihdr.writeUInt32BE(size, 0); ihdr.writeUInt32BE(size, 4); ihdr[8] = 8; ihdr[9] = 6;
  return Buffer.concat([Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]), chunk('IHDR', ihdr), chunk('IDAT', zlib.deflateSync(raw, { level: 9 })), chunk('IEND', Buffer.alloc(0))]);
}

const dir = path.join(__dirname, '..', 'public', 'icons');
fs.mkdirSync(dir, { recursive: true });
for (const [name, size] of [['icon-192.png', 192], ['icon-512.png', 512], ['apple-touch-icon.png', 180]]) {
  fs.writeFileSync(path.join(dir, name), png(size));
  console.log('wrote', name);
}
