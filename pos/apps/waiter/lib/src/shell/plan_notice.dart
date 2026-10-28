import 'dart:async';

import 'package:flutter/material.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_ui/pos_ui.dart';

/// P4-21: to'lanmagan hisob-faktura haqidagi lenta ofitsiant bosh sahifasi tepasida (oddiy so'zlar bilan). Ofitsiant
/// ilovasida tarifga yopiladigan amal yo'q (buyurtma olish, yuborish va xonalar hech qachon tarifga bog'lanmaydi): bu
/// faqat xabar. Xabar yo'q bo'lsa — bo'sh.
class PlanNoticeBanner extends StatelessWidget {
  const PlanNoticeBanner({super.key, required this.session, required this.now});

  final PosSession session;
  final DateTime Function() now;

  @override
  Widget build(BuildContext context) => StreamBuilder<PosSessionPhase>(
    stream: session.changes,
    initialData: session.phase,
    builder: (BuildContext context, AsyncSnapshot<PosSessionPhase> _) {
      final PosBillingNotice? notice = session.entitlements.notice;
      if (notice == null) return const SizedBox.shrink();
      return PosPlanNoticeBar(
        title: notice.title(now()),
        text: notice.text,
        locked: notice.locked,
      );
    },
  );
}

/// Ish ekrani ochiq turguncha tarifni vaqti-vaqti bilan qayta o'qiydi (ega to'lov qilsa lenta o'zi yo'qoladi).
/// Aloqa yo'q bo'lsa jim: oxirgi ma'lum holat qoladi.
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
