import 'dart:async';

import 'package:flutter/material.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../features/billing/cashier_ui.dart';
import '../features/order/order_dialogs.dart';
import 'pos_scope.dart';

/// "Ishga keldim" / "Ishdan ketdim" (P6-23, POS_SPEC §17.7): kirgan xodim o'z PIN sessiyasidan ish vaqtini belgilaydi.
/// Faqat bootstrap `staff_pay.clock` bo'lsa ko'rinadi. Internetsiz belgilash yo'q (sinxronlash amali yo'q): "internet
/// kerak" deydi.
///
/// Tor (telefon) ekranda tugma yuqori panelga sig'maydi: xodim menyusidagi "Ish vaqti" bandi [showClockDialog] ni
/// ochadi.
class ClockButton extends StatefulWidget {
  const ClockButton({super.key});

  @override
  State<ClockButton> createState() => _ClockButtonState();
}

class _ClockButtonState extends State<ClockButton> {
  int? _staffId;
  PosWorkSession? _session;
  bool _busy = false;

  @override
  Widget build(BuildContext context) {
    final PosScope scope = PosScope.of(context);
    final PosSession session = scope.session;
    final PosStaffPayBlock? block = session.bootstrap?.staffPay;
    if (block == null || !block.clock) return const SizedBox.shrink();
    if (_staffId != session.staffId) {
      // Another staff member signed in: their own open session from the bootstrap
      _staffId = session.staffId;
      _session = block.session;
    }
    final PosWorkSession? open = _session;
    final String label = open == null
        ? 'Ishga keldim'
        : 'Ishda · ${hm(open.clockInAt.toLocal())} dan';
    final Icon icon = Icon(
      open == null ? Icons.login_rounded : Icons.badge_outlined,
      color: open == null ? null : context.rz.successSoftFg,
    );
    final VoidCallback? onPressed = _busy
        ? null
        : () => unawaited(_toggle(scope, open));
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 4),
      child: Tooltip(
        message: open == null
            ? 'Ish vaqtini boshlash'
            : 'Bosing: «Ishdan ketdim»',
        child: TextButton.icon(
          key: const ValueKey<String>('clock-button'),
          onPressed: onPressed,
          icon: icon,
          label: Text(label),
        ),
      ),
    );
  }

  Future<void> _toggle(PosScope scope, PosWorkSession? open) async {
    if (scope.data.offline?.active ?? false) {
      showPosMessage(
        context,
        "Internet kerak: ish vaqtini internet tiklangach belgilang.",
      );
      return;
    }
    if (open != null) {
      final bool ok = await confirmDialog(
        context,
        title: 'Ishdan ketdingizmi?',
        message:
            'Ish vaqti ${hm(open.clockInAt.toLocal())} dan hozirgacha yoziladi.',
        confirmLabel: 'Ishdan ketdim',
        cancelLabel: 'Qolish',
      );
      if (!ok || !mounted) return;
    }
    setState(() => _busy = true);
    try {
      final PosClockState state = open == null
          ? await scope.data.staffClock.clockIn()
          : await scope.data.staffClock.clockOut();
      if (!mounted) return;
      setState(() => _session = state.session);
      showPosMessage(
        context,
        open == null
            ? 'Ishga keldingiz: ${hm(scope.services.now().toLocal())}.'
            : 'Ishdan ketdingiz. Bugun: ${_hours(state.todayMinutes)}.',
      );
    } on PosConflict catch (e) {
      // Marked meanwhile on another terminal: show what the server says now
      if (!mounted) return;
      showPosMessage(context, e.message);
      try {
        final PosClockState now = await scope.data.staffClock.state();
        if (mounted) setState(() => _session = now.session);
      } on PosException {
        // the button stays as it was
      }
    } on PosNetworkException {
      if (mounted) {
        showPosMessage(
          context,
          "Internet kerak: ish vaqtini internet tiklangach belgilang.",
        );
      }
    } on Object catch (e) {
      if (mounted) showPosError(context, e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  static String _hours(int minutes) => minutes < 60
      ? '$minutes daq'
      : '${minutes ~/ 60} soat ${minutes % 60} daq';
}

/// Ish vaqti oynasi (telefonda xodim menyusidan): hozirgi holat (`GET /pos/clock`) va "Ishga keldim" / "Ishdan ketdim".
Future<void> showClockDialog(BuildContext context) async {
  final PosScope scope = PosScope.of(context);
  if (scope.data.offline?.active ?? false) {
    showPosMessage(
      context,
      "Internet kerak: ish vaqtini internet tiklangach belgilang.",
    );
    return;
  }
  final PosClockState state;
  try {
    state = await scope.data.staffClock.state();
  } on Object catch (e) {
    if (context.mounted) showPosError(context, e);
    return;
  }
  if (!context.mounted) return;
  final PosWorkSession? open = state.session;
  final bool? go = await showDialog<bool>(
    context: context,
    builder: (BuildContext context) => AlertDialog(
      title: const Text('Ish vaqti'),
      content: Text(
        open == null
            ? 'Siz hali ishga kelganingizni belgilamagansiz.'
            : 'Ishdasiz: ${hm(open.clockInAt.toLocal())} dan.',
      ),
      actions: <Widget>[
        TextButton(
          onPressed: () => Navigator.pop(context, false),
          child: const Text('Yopish'),
        ),
        FilledButton(
          key: const ValueKey<String>('clock-dialog-action'),
          onPressed: () => Navigator.pop(context, true),
          child: Text(open == null ? 'Ishga keldim' : 'Ishdan ketdim'),
        ),
      ],
    ),
  );
  if (go != true || !context.mounted) return;
  try {
    final PosClockState next = open == null
        ? await scope.data.staffClock.clockIn()
        : await scope.data.staffClock.clockOut();
    if (context.mounted) {
      showPosMessage(
        context,
        next.clockedIn ? 'Ishga keldingiz.' : 'Ishdan ketdingiz.',
      );
    }
  } on Object catch (e) {
    if (context.mounted) showPosError(context, e);
  }
}
