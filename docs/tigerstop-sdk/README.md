# TigerStop SDK reference

Verbatim copy of the public TigerStopSDK wiki (https://github.com/TigerStop/TigerStopSDK/wiki),
fetched 2026-10-03, for offline reference when working on `tiger-bridge/` and CutFlow.

- `RS-232-TigerStop-Serial.md` — full serial command spec (Comm Spec 4.0, amp v5.60)
- `Home.md`, `Scaling-TigerStops.md` — wiki home and scale/calibration notes

## Bits that matter for CutFlow

- `D10<cr>` reads the max travel limit, `D11<cr>` the min. Reply is an echo line then the value:
  `d10<cr>78.000<cr>`. (The spec's example values 78.0 / 6.0 are sample data.)
- `MG<inches><cr>` moves; acks `MGS xx.xxx` (started) then `MGF` (finished).
- Errors come back as `X EC= N TEXT`. Relevant codes: 6 = `ERR_MOVEMAX`, 7 = `ERR_MOVEMIN`,
  5 = `ERR_MOVEBUSY`, 30 = `ERR_ESTOP`.
- NEVER write D105-D108 (drive tuning).
