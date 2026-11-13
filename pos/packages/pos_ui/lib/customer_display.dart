/// Mijoz ekrani ko'rinishlari (P6-14): kassa cheki ([CartView]), "Rahmat!" ([PaidView]), salomlashish
/// ([DisplayWelcome]), to'lov QR'i ([QrView], tashqi paketsiz [QrCode]) va yirik shriftlar ([DisplayType]).
///
/// Ikki joyda ishlatiladi: `pos/apps/customer_display` ilovasi va kassaning ikkinchi ekrani (Sunmi D2 turi, Android
/// `Presentation`; `pos_hardware` dagi `CustomerScreen`) — ikkalasi bir xil chizadi.
library;

export 'src/customer_display/cart_view.dart';
export 'src/customer_display/display_type.dart';
export 'src/customer_display/qr_encoder.dart';
export 'src/customer_display/qr_view.dart';
export 'src/customer_display/welcome_view.dart';
