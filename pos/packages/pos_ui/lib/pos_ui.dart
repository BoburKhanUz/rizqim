/// Rizqim POS sensor ekran komponentlari (`rizqim_ui` mavzusi va tokenlari ustida): katta tugmalar, raqam va
/// kg/ulush klaviaturasi, miqdor stepper, zal xaritasi (xona, tapchan, stol holatlari bilan), xona hisobi kartochkasi,
/// tarif qulflari ("Asosiy tarifda bor" yorlig'i, yopiq amal tugmasi, to'lanmagan hisob-faktura lentasi), tez kassa
/// (P6-12: tez miqdor, telefondagi savat paneli, navbat raqami kartasi, "Tayyor" ro'yxati qatori).
///
/// PIN klaviaturasi va ekran qulfi uchun `rizqim_ui` dagi `RzPinEntry` / `RzPinPad` ishlatiladi (bu yerda takrorlanmaydi).
library;

export 'src/counter.dart';
export 'src/floor_map.dart';
export 'src/keypad.dart';
export 'src/modifier_picker.dart';
export 'src/order_line.dart';
export 'src/plan_lock.dart';
export 'src/quantity.dart';
export 'src/room_summary_card.dart';
export 'src/tile_button.dart';
