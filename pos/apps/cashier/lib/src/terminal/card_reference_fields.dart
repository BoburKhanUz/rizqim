import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_core/rizqim_core.dart';

import '../features/billing/cashier_ui.dart';
import '../shell/pos_scope.dart';

/// P6-16: alohida bank terminalida olingan kartaning ma'lumoti. Filial "faqat terminal" rejimida bo'lsa (terminal
/// bank tomonidan TXKM "faqat terminal" rejimiga o'tkazilgan) kassir chekdagi RRN'ni teradi va kartaning oxirgi 4
/// raqamini ixtiyoriy kiritadi; ikkalasi Rizqim chekining karta to'lovida chiqadi. To'lov hech qachon shu maydonlar
/// sabab to'xtamaydi (oxirgi 4 raqam ixtiyoriy).

/// Hozirgi terminalning karta oqimi: `terminal_card_flow` moduli yoqilgan bo'lsa, aks holda `null`.
PosTerminalFlow? cardFlowOf(BuildContext context) {
  final PosBootstrap? boot = PosScope.maybeOf(context)?.session.bootstrap;
  if (boot == null || !boot.modules.has(PosModule.terminalCardFlow)) {
    return null;
  }
  return boot.terminalFlow;
}

/// Oxirgi 4 raqam xatosi (bo'sh yoki to'g'ri bo'lsa `null`).
String? cardLast4Error(String text) {
  final String t = text.trim();
  if (t.isEmpty || RegExp(r'^\d{4}$').hasMatch(t)) return null;
  return "Kartaning oxirgi 4 raqamini kiriting (yoki bo'sh qoldiring).";
}

/// RRN maydonining yorlig'i.
String cardReferenceLabel(PosTerminalFlow? flow) =>
    flow != null && flow.asksCardReference
    ? 'RRN (terminal chekidagi tranzaksiya raqami)'
    : 'Tranzaksiya raqami (terminal chekidan)';

/// Kartaning oxirgi 4 raqami (ixtiyoriy).
class CardLast4Field extends StatelessWidget {
  const CardLast4Field({
    super.key,
    required this.controller,
    required this.onChanged,
  });

  final TextEditingController controller;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) => TextField(
    key: const ValueKey<String>('tender-last4'),
    controller: controller,
    keyboardType: TextInputType.number,
    maxLength: 4,
    inputFormatters: <TextInputFormatter>[
      FilteringTextInputFormatter.digitsOnly,
    ],
    decoration: InputDecoration(
      labelText: 'Karta oxirgi 4 raqami (ixtiyoriy)',
      counterText: '',
      errorText: cardLast4Error(controller.text),
    ),
    onChanged: onChanged,
  );
}

/// Smart-kassa rejimidagi eslatma: karta qurilmaning o'zida olinadi.
class SmartKassaCardHint extends StatelessWidget {
  const SmartKassaCardHint({super.key});

  @override
  Widget build(BuildContext context) => const NoticeBanner(
    key: ValueKey<String>('smart-kassa-hint'),
    message: "Smart-kassa: kartani qurilmada oling, chekdagi RRN'ni pastga kiriting. Chek provayderning shu qurilmaga bog'langan kassasi orqali chiqadi.",
    tone: StatusTone.info,
    icon: Icons.point_of_sale_rounded,
  );
}
