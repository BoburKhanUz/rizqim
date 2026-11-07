/// Rizqim POS lokal ko'prigi (docs/POS_SPEC.md 5.5, `README.md` — shartnoma), Flutter'siz (`dart test`):
///
/// * [LanCredentials] — bootstrap'ning `lan` qismi (kalitlar, markaz, grant);
/// * [LanHub] — asosiy kassadagi HTTP + WebSocket server va UDP beacon: har kadr tekshiriladi (filial qurilmasi,
///   imzo, kalit versiyasi, vaqt oynasi, `(device, seq)`), amallar [PosOfflineLanApplier] orqali kassa navbatiga;
/// * [LanClient] — ofitsiant, KDS va mijoz ekrani tomoni;
/// * [LanDiscovery], [LanBeacon] — markazni topish (beacon yoki IP/QR);
/// * [LanModeRule] — `cloud` / `lan` / `alone`;
/// * [LanLink] — ofitsiant, KDS va mijoz ekranining umumiy boshqaruvchisi (topish, ulanish, rejim, qayta ulanish),
///   [LanPosRoute] — ofitsiant ilovasining `pos_data` repozitoriylari uchun markaz yo'li.
library;

export 'src/beacon.dart';
export 'src/client.dart';
export 'src/credentials.dart';
export 'src/crypto.dart' show LanKeys, LanChannelKey, lanCanonicalJson;
export 'src/frame.dart';
export 'src/grant.dart';
export 'src/hub.dart';
export 'src/link.dart';
export 'src/messages.dart';
export 'src/pos_offline_applier.dart';
export 'src/pos_route.dart';
