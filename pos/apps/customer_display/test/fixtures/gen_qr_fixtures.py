# Reference matrices for test/qr_encoder_test.dart, made by the python `qrcode` package (pip install qrcode):
#   python3 test/fixtures/gen_qr_fixtures.py     (from pos/apps/customer_display)
import json
import os
import qrcode
from qrcode.constants import ERROR_CORRECT_M, ERROR_CORRECT_L

cases = [
  ("hello world", "M", range(8)),
  ("https://pay.example/uz/abc", "M", range(8)),
  ("https://my.click.uz/services/pay?service_id=12345&merchant_id=9876&amount=69000&transaction_param=ORD-20261022-0017&return_url=https://rizqim.uz/r", "M", range(8)),
  ("https://checkout.paycom.uz/bWVyY2hhbnQ9NjQ1MzEyYWJjZGVmMDEyMzQ1Njc4OTAxO2FjY291bnRbb3JkZXJdPTE3O2Ftb3VudD02OTAwMDA7Y3VycmVuY3k9ODYwO2xhbmc9dXo7Y2FsbGJhY2s9aHR0cHM6Ly9yaXpxaW0udXovcGF5bWU=", "M", [0, 2, 5, 7]),
  ("Lag'mon uchun to'lov: 69 000 so'm - Rahmat! " * 3, "M", [1, 4, 6]),
  ("https://pay.example.uz/q/" + "abcdefghij0123456789" * 11, "L", [0, 3, 6]),
  ("a" * 14, "M", [0, 7]),   # exactly v1-M capacity
  ("b" * 15, "M", [0, 7]),   # v2
  ("é" * 20, "M", [2, 5]),   # utf-8
  ("pay/" + "x7" * 25, "M", [0, 4]),            # v4 (2 blocks)
  ("pay/" + "q3" * 35, "M", [1, 6]),            # v5
  ("pay/" + "k9" * 48, "M", [2, 7]),            # v6 (4 blocks)
  ("pay/" + "m5" * 58, "M", [3, 5]),            # v7 (version info)
  ("pay/" + "z1" * 84, "M", [0, 6]),            # v9
  ("pay/" + "w2" * 120, "L", [4, 7]),           # v8-L/v9-L
]
out = []
for text, level, masks in cases:
    for m in masks:
        qr = qrcode.QRCode(version=None, error_correction=ERROR_CORRECT_M if level == "M" else ERROR_CORRECT_L, mask_pattern=m, border=0)
        qr.add_data(text, optimize=0)
        qr.make(fit=True)
        rows = ["".join("#" if c else "." for c in row) for row in qr.get_matrix()]
        out.append({"text": text, "ecc": level, "mask": m, "version": qr.version, "rows": rows})
json.dump(out, open(os.path.join(os.path.dirname(os.path.abspath(__file__)), 'qr_expected.json'), 'w'))
print(len(out), "matrices", sorted(set(o["version"] for o in out)))
