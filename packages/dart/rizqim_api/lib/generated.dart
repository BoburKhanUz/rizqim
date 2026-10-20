/// OpenAPI'dan yaratilgan klientlar va modellar (`tool/generate.sh`, `packages/api-spec/openapi.json`).
///
/// Nomlar `rizqim_core` bilan to'qnashadi (`OrderStatus`, `OrderType`): prefiks bilan import qiling
/// (`import 'package:rizqim_api/generated.dart' as gen;`).
library;

export 'src/generated/export.dart';
export 'src/generated/rizqim_api.dart';
