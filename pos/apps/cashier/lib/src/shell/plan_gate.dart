import 'dart:async';

import 'package:flutter/material.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_ui/pos_ui.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import 'pos_scope.dart';

/// P4-21: tarif qulflari. Kassa bu yerda faqat **yangi yozuvni** yopadi (yangi bron, bo'nak, nasiya hisobi, xizmat haqini
/// qaytarish): to'lov qabul qilish, schyot yopish, chek, smena va qaytarish hech qachon tarifga bog'lanmaydi.
/// Hal qilishni backend qiladi (403 `plan_feature_missing`); bu yerda faqat yashiriladi va "Asosiy tarifda bor" deb
/// tushuntiriladi.

/// Joriy tarif ([PosSession.entitlements]) o'zgarganda (bootstrap, to'lov qilinganda yangilash) qayta chiziladi.
class PlanBuilder extends StatelessWidget {
  const PlanBuilder({super.key, required this.builder});

  final Widget Function(BuildContext context, PosEntitlements plan) builder;

  @override
  Widget build(BuildContext context) {
    final PosSession session = PosScope.of(context).session;
    return StreamBuilder<PosSessionPhase>(
      stream: session.changes,
      initialData: session.phase,
      builder: (BuildContext context, AsyncSnapshot<PosSessionPhase> _) =>
          builder(context, session.entitlements),
    );
  }
}

/// Funksiya hozir tarifda yo'qmi (qulflangan). Tarif noma'lum bo'lsa — yo'q.
bool planLocked(BuildContext context, PosFeature feature) =>
    !PosScope.of(context).session.entitlements.has(feature);

/// Yangi yozuv tugmasi: tarifda bo'lsa oddiy tugma, bo'lmasa o'chirilgan tugma va "Asosiy tarifda bor".
class PlanButton extends StatelessWidget {
  const PlanButton({
    super.key,
    required this.feature,
    required this.label,
    required this.onPressed,
    this.icon,
    this.variant = RzButtonVariant.primary,
    this.size = RzButtonSize.md,
    this.fullWidth = false,
  });

  final PosFeature feature;
  final String label;
  final VoidCallback? onPressed;
  final IconData? icon;
  final RzButtonVariant variant;
  final RzButtonSize size;
  final bool fullWidth;

  @override
  Widget build(BuildContext context) => PlanBuilder(
    builder: (BuildContext context, PosEntitlements plan) => PosPlanButton(
      label: label,
      icon: icon,
      variant: variant,
      size: size,
      fullWidth: fullWidth,
      locked: !plan.has(feature),
      lockLabel: plan.lockLabel(feature),
      onPressed: onPressed,
    ),
  );
}

/// [PlanButton] ning matnli (TextButton) ko'rinishi: kartochka sarlavhasi yonidagi "Avans olish", to'lov oynasidagi "Yangi
/// hisob ochish".
class PlanTextButton extends StatelessWidget {
  const PlanTextButton({
    super.key,
    required this.feature,
    required this.label,
    required this.icon,
    required this.onPressed,
  });

  final PosFeature feature;
  final String label;
  final IconData icon;
  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) => PlanBuilder(
    builder: (BuildContext context, PosEntitlements plan) {
      final TextButton button = TextButton.icon(
        onPressed: plan.has(feature) ? onPressed : null,
        icon: Icon(plan.has(feature) ? icon : Icons.lock_outline_rounded),
        label: Text(label),
      );
      if (plan.has(feature)) return button;
      return Wrap(
        crossAxisAlignment: WrapCrossAlignment.center,
        spacing: 8,
        children: <Widget>[
          button,
          PosLockChip(label: plan.lockLabel(feature)),
        ],
      );
    },
  );
}

/// Yopiq funksiyaning yangi yozuvi bosildi: tushuntirish (snackbar). `true` — ochiq, davom eting.
bool ensurePlan(BuildContext context, PosFeature feature) {
  final PosEntitlements plan = PosScope.of(context).session.entitlements;
  if (plan.has(feature)) return true;
  ScaffoldMessenger.maybeOf(context)
    ?..hideCurrentSnackBar()
    ..showSnackBar(SnackBar(content: Text(plan.lockReason(feature))));
  return false;
}

/// Kassa bosh sahifasi tepasidagi lenta: to'lanmagan hisob-faktura haqidagi eslatma yoki "Sozlamalar yopiq" (oddiy
/// so'zlar bilan; kassa ishlashda davom etishi aytiladi). Xabar yo'q bo'lsa — bo'sh.
class PlanNoticeBanner extends StatelessWidget {
  const PlanNoticeBanner({super.key});

  @override
  Widget build(BuildContext context) => PlanBuilder(
    builder: (BuildContext context, PosEntitlements plan) {
      final PosBillingNotice? notice = plan.notice;
      if (notice == null) return const SizedBox.shrink();
      final DateTime now = PosScope.of(context).services.now();
      return PosPlanNoticeBar(
        title: notice.title(now),
        text: notice.text,
        locked: notice.locked,
      );
    },
  );
}

/// Tarifni ogohlantirish va yangilash: HomeShell ochiq turguncha har [every] da tarif qayta o'qiladi (egasi to'lov
/// qilsa qulf va lenta o'zi yo'qoladi). Aloqa yo'q bo'lsa jim: oxirgi ma'lum holat qoladi.
class PlanRefresher {
  PlanRefresher(this.session, {this.every = const Duration(minutes: 10)});

  final PosSession session;
  final Duration every;
  Timer? _timer;

  void start() {
    _timer ??= Timer.periodic(
      every,
      (_) => unawaited(session.refreshEntitlements()),
    );
  }

  void dispose() {
    _timer?.cancel();
    _timer = null;
  }
}
