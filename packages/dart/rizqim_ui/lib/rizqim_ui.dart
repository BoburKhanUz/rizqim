/// Rizqim Material 3 mavzusi va umumiy vidjetlar (terminal ilovalari uchun PIN klaviaturasi, xodim kartochkasi,
/// "Aloqa yo'q" lentasi va harakatsizlikda chiqarish ham).
///
/// Manba: docs/DESIGN.md (Mini App'dagi `customer/miniapp/src/index.css` tokenlari). Rang, radius va shrift o'lchamlari
/// shu yerda; ilovalar `RzTheme.light()` / `RzTheme.dark()` ni ulaydi va ranglarni `context.rz` orqali oladi.
library;

export 'src/theme/contrast.dart';
export 'src/theme/rz_colors.dart';
export 'src/theme/rz_theme.dart';
export 'src/widgets/empty_state.dart';
export 'src/widgets/idle_detector.dart';
export 'src/widgets/inline_error.dart';
export 'src/widgets/offline_banner.dart';
export 'src/widgets/pin_pad.dart';
export 'src/widgets/rz_button.dart';
export 'src/widgets/staff_tile.dart';
export 'src/widgets/status_badge.dart';
