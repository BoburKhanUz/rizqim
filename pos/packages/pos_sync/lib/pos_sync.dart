/// Rizqim POS internetsiz cheklangan rejimi (docs/POS_SPEC.md 5-bo'lim), Flutter'siz (`dart test`):
///
/// * [SyncStore] — diskdagi amallar navbati (har yozuv `fsync` bilan, chala qator kesib tashlanadi), `op_id` va
///   `seq`, lokal buyurtma raqami (`K1-017`), navbat raqami (kassa bloklaridan, kun ichida takrorlanmaydi), server
///   uuid'lari va serverdan olingan nusxalar (yoshi bilan);
/// * [SyncSender] — navbatni `POST /pos/sync` ga tartib bilan, paket-paket yuboradi, xatoda kutib qayta urinadi;
/// * [OfflineLimits], [OfflineStatus] — 15 daqiqa / 2 soat ogohlantirish, 500 buyurtma chegarasi, 24 soatlik menyu va
///   aloqa sharti, fiskal navbatning 24 / 40 / 48 soatlik ogohlantirishi;
/// * [MirroredSyncStorage] — jurnalning ikkinchi nusxasi ilova papkasidan tashqarida (M5 S4), ishga tushganda
///   yo'qolgan amallarni imzosi tekshirilib tiklaydi; [SyncExporter] — "Navbatni faylga saqlash" (`.rzq` fayli).
///
/// Serverga ulanish (`SyncTransport`) va buyurtmalarning lokal ko'rinishi — `pos_data` da.
library;

export 'src/export.dart';
export 'src/limits.dart';
export 'src/mirror_storage.dart';
export 'src/operation.dart';
export 'src/queue_numbers.dart';
export 'src/sender.dart';
export 'src/signature.dart';
export 'src/storage.dart';
export 'src/store.dart';
export 'src/uuid.dart' show newUuid;
