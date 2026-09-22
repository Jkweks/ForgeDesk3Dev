# tiger-bridge

Small local service that owns the two pieces of hardware Laravel can't reach
directly: the TigerStop's serial port and the Zebra printer's socket. Runs on
the same PC the TigerStop is physically wired into.

This source lives inside the ForgeDesk repo (absorbed from the standalone
CutFlow deployment) but is **not** part of the ForgeDesk docker-compose
stack in normal operation — the shop tablet with the serial device isn't the
same host that runs ForgeDesk's containers. Deploy it by copying this
`tiger-bridge/` directory to the shop PC and running it there (`npm install`
+ `npm start`, or `docker build`/`docker run` locally on that PC). ForgeDesk
reaches it over plain LAN HTTP via `TIGER_BRIDGE_URL`. The root
`docker-compose.yml` does include an opt-in `bridge` service (profile
`local-bridge`) for the rare case dev/testing happens on a machine that has
the serial device attached directly.

## Why this exists

A browser (and a Laravel app, if it's ever hosted anywhere but localhost)
can't open a COM port or a raw TCP socket. This tiny service can, because it
runs as a normal process on the shop-floor PC. Laravel calls it over
`http://127.0.0.1:9111` for every hardware action.

## Setup

```bash
npm install
cp .env.example .env
# edit .env: set SERIAL_PORT to the TigerStop's COM port, and PRINTER_HOST
# to the Zebra's IP
npm start
```

## Endpoints

- `POST /move` — body `{ "inches": 48.375 }`. Sends `MG48.375\r` to the
  TigerStop and resolves once it acks `MGF` (move finished). Also resets the
  cut-sensor stub back to `idle` (see below) since a new move starts a new cut.
- `POST /print` — builds a 1"x4" ZPL label and streams it to the printer on
  port 9100. Body:
  - Part cut: `{ "job", "part", "partUse", "elevation", "size", "uuid" }`
    (`part` is "part number · finish", `size` and `elevation` are already
    formatted strings, `uuid` is the CutLogEntry's uuid — encoded as a QR
    code on the label's right side).
  - Manual entry: `{ "size", "operator", "timestamp", "uuid" }` — no job/part
    fields, per the "manual sticker has only size + cut record data" spec.
- `GET /sensor/status` — `{ "status": "idle" | "cutting" | "complete" }`.
  Polled by Laravel's `nextCut()`/`checkSensor()` flow while
  `cut_sensor_active` is on in Settings.
- `POST /sensor/trigger` — body `{ "status": "idle" | "cutting" | "complete" }`.
  **Dev/test only** — simulates the physical sensor firing until real
  hardware is wired in (see Notes).
- `GET /status` — current serial connection state, for a health check.

## Notes

- Requires TigerSET enabled on the TigerStop amp (firmware v5.60+) — without
  it the serial port doesn't respond to outside commands.
- `SERIAL_PORT` will look like `COM3` on Windows or `/dev/ttyUSB0` on Linux.
- If the TigerStop is reset or unplugged, this reconnects automatically every
  3 seconds.
- The ZPL label layout in `buildZpl()` matches the spec in
  `../docs/cutlist.txt` (1"x4", job/part/use top-left, elevation-length
  bottom-left, QR right side) — adjust dot offsets for your actual printer's
  DPI if labels don't line up.
- **The cut-complete sensor has no real hardware yet.** `sensorState` is an
  in-memory variable a real integration (another serial line, a GPIO/relay
  board, whatever gets picked) should write to instead of `/sensor/trigger`.
  `GET /sensor/status` is the contract Laravel depends on; keep that
  response shape when wiring in real hardware.
