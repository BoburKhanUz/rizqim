import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:rizqim_core/rizqim_core.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../../shell/pos_scope.dart';

/// P3-22 bo'limlari (schyot, smena, bron, nasiya, navbat, sozlamalar) uchun umumiy kichik vidjetlar va yordamchilar.

/// Sarlavhali kartochka (surface, 16px radius, chegara).
class PosCard extends StatelessWidget {
  const PosCard({
    super.key,
    required this.child,
    this.title,
    this.trailing,
    this.padding = const EdgeInsets.all(16),
    this.color,
  });

  final Widget child;
  final String? title;
  final Widget? trailing;
  final EdgeInsetsGeometry padding;
  final Color? color;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    // Material: ichidagi ListTile va InkWell bosilganda ko'rinadi
    return Material(
      color: color ?? c.surface,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(RzRadius.card),
        side: BorderSide(color: c.line),
      ),
      clipBehavior: Clip.antiAlias,
      child: Padding(
        padding: padding,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            if (title != null || trailing != null) ...<Widget>[
              Row(
                children: <Widget>[
                  if (title != null)
                    Expanded(
                      child: Text(
                        title!,
                        style: RzType.headline.copyWith(color: c.fg),
                      ),
                    )
                  else
                    const Spacer(),
                  ?trailing,
                ],
              ),
              const SizedBox(height: 12),
            ],
            child,
          ],
        ),
      ),
    );
  }
}

/// "Nom ........ summa" qatori.
class AmountRow extends StatelessWidget {
  const AmountRow({
    super.key,
    required this.label,
    required this.amount,
    this.bold = false,
    this.large = false,
    this.muted = false,
    this.negative = false,
    this.tone,
    this.trailing,
  });

  final String label;
  final PosMoney amount;
  final bool bold;
  final bool large;
  final bool muted;

  /// Chegirma kabi: "−" bilan.
  final bool negative;
  final StatusTone? tone;
  final String? trailing;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final Color color = tone != null
        ? toneColors(c, tone!).$2
        : (muted ? c.fgMuted : c.fg);
    final TextStyle base = (large ? RzType.title : RzType.body).copyWith(
      color: color,
      fontWeight: bold ? FontWeight.w700 : FontWeight.w500,
    );
    final String value = negative && !amount.isZero
        ? '−${amount.abs().format()}'
        : amount.format();
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.baseline,
        textBaseline: TextBaseline.alphabetic,
        children: <Widget>[
          Expanded(child: Text(label, style: base)),
          if (trailing != null) ...<Widget>[
            Text(trailing!, style: RzType.footnote.copyWith(color: c.fgMuted)),
            const SizedBox(width: 8),
          ],
          Text(value, style: base),
        ],
      ),
    );
  }
}

/// Holat yorlig'i: rang + ikonka + matn (holat faqat rang bilan berilmaydi).
class TonePill extends StatelessWidget {
  const TonePill({
    super.key,
    required this.label,
    required this.tone,
    this.icon,
  });

  final String label;
  final StatusTone tone;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    final (Color bg, Color fg) = toneColors(context.rz, tone);
    return DecoratedBox(
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            if (icon != null) ...<Widget>[
              Icon(icon, size: 16, color: fg),
              const SizedBox(width: 4),
            ],
            Text(
              label,
              style: RzType.footnote.copyWith(
                color: fg,
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Holat rangi: (fon, matn).
(Color, Color) toneColors(RzColors c, StatusTone tone) => switch (tone) {
  StatusTone.success => (c.successSoft, c.successSoftFg),
  StatusTone.warning => (c.warningSoft, c.warningSoftFg),
  StatusTone.danger => (c.dangerSoft, c.dangerSoftFg),
  StatusTone.info => (c.infoSoft, c.infoSoftFg),
  StatusTone.neutral => (c.surface2, c.fgMuted),
};

/// Ogohlantirish yoki ma'lumot lentasi (kartochka ichida).
class NoticeBanner extends StatelessWidget {
  const NoticeBanner({
    super.key,
    required this.message,
    this.tone = StatusTone.warning,
    this.icon = Icons.info_outline_rounded,
    this.action,
  });

  final String message;
  final StatusTone tone;
  final IconData icon;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    final (Color bg, Color fg) = toneColors(context.rz, tone);
    return Semantics(
      liveRegion: true,
      child: DecoratedBox(
        decoration: BoxDecoration(
          color: Color.alphaBlend(bg, context.rz.surface),
          borderRadius: BorderRadius.circular(RzRadius.control),
        ),
        child: Padding(
          padding: const EdgeInsets.fromLTRB(12, 10, 12, 10),
          child: Row(
            children: <Widget>[
              Icon(icon, color: fg),
              const SizedBox(width: 10),
              Expanded(
                child: Text(message, style: RzType.body.copyWith(color: fg)),
              ),
              if (action != null) ...<Widget>[
                const SizedBox(width: 8),
                action!,
              ],
            ],
          ),
        ),
      ),
    );
  }
}

/// Sahifa ichida yuklanmoqda.
class LoadingBox extends StatelessWidget {
  const LoadingBox({super.key});

  @override
  Widget build(BuildContext context) => const Padding(
    padding: EdgeInsets.all(32),
    child: Center(child: CircularProgressIndicator()),
  );
}

// --- matn va vaqt ------------------------------------------------------------------------------------------------------

String _pad2(int n) => n.toString().padLeft(2, '0');

/// Qurilma vaqtida "20:15".
String hm(DateTime d) {
  final DateTime l = d.toLocal();
  return '${_pad2(l.hour)}:${_pad2(l.minute)}';
}

/// "22.10" yoki to'liq "22.10.2026".
String dm(DateTime d, {bool year = false}) {
  final DateTime l = d.toLocal();
  return year
      ? '${_pad2(l.day)}.${_pad2(l.month)}.${l.year}'
      : '${_pad2(l.day)}.${_pad2(l.month)}';
}

/// `YYYY-MM-DD` (qurilma kuni).
String isoDay(DateTime d) {
  final DateTime l = d.toLocal();
  return '${l.year}-${_pad2(l.month)}-${_pad2(l.day)}';
}

const List<String> _weekdays = <String>[
  'Dushanba',
  'Seshanba',
  'Chorshanba',
  'Payshanba',
  'Juma',
  'Shanba',
  'Yakshanba',
];

const List<String> _months = <String>[
  'yanvar',
  'fevral',
  'mart',
  'aprel',
  'may',
  'iyun',
  'iyul',
  'avgust',
  'sentabr',
  'oktabr',
  'noyabr',
  'dekabr',
];

/// "Payshanba, 22-oktabr".
String longDay(DateTime d) {
  final DateTime l = d.toLocal();
  return '${_weekdays[l.weekday - 1]}, ${l.day}-${_months[l.month - 1]}';
}

/// "5 daqiqa", "1 soat 20 daqiqa".
String minutesLabel(int minutes) {
  if (minutes < 60) return '$minutes daqiqa';
  final int h = minutes ~/ 60;
  final int m = minutes % 60;
  return m == 0 ? '$h soat' : '$h soat $m daqiqa';
}

/// Karta turlari (`card_type`).
const Map<String, String> cardTypes = <String, String>{
  'uzcard': 'Uzcard',
  'humo': 'Humo',
  'visa': 'Visa',
  'mastercard': 'Mastercard',
  'unionpay': 'UnionPay',
  'mir': 'Mir',
  'other': 'Boshqa',
};

/// To'lov turi nomi (`cash`, `card` ...).
String tenderLabel(String wire) => PosTender.fromWire(wire)?.label ?? wire;

/// Kanal nomi.
String channelLabel(String channel) => switch (channel) {
  'pos_hall' => 'Zal',
  'pos_takeaway' => 'Olib ketish',
  'telegram' => 'Telegram',
  'mobile' => 'Ilova',
  'aggregator' => 'Agregator',
  _ => channel,
};

IconData channelIcon(String channel) => switch (channel) {
  'pos_hall' => Icons.table_restaurant_outlined,
  'pos_takeaway' => Icons.shopping_bag_outlined,
  'telegram' => Icons.send_rounded,
  'mobile' => Icons.phone_iphone_rounded,
  _ => Icons.receipt_long_outlined,
};

// --- xatolar va dialoglar ------------------------------------------------------------------------------------------------

/// Xato matni (snackbar). Tasdiq oynasi yopilgani — jim.
void showPosError(BuildContext context, Object error) {
  if (error is PosApprovalCancelled) return;
  final String text = switch (error) {
    final PosValidationException e => e.firstError,
    final ApiException e when e.isNetworkError => "Aloqa yo'q: amal bajarilmadi. Internetni tekshirib, qayta urinib ko'ring.",
    final ApiException e => e.message,
    _ => 'Xato: $error',
  };
  ScaffoldMessenger.maybeOf(context)
    ?..hideCurrentSnackBar()
    ..showSnackBar(SnackBar(content: Text(text)));
}

/// P3-35: smena shu orada yopildi (`shift_closed`): terminalning joriy smenasi serverdan qayta o'qiladi (yuqori panel
/// va keyingi to'lov eskirgan smenani ishlatmasin).
Future<void> reloadShift(PosScope scope) async {
  try {
    final PosShiftWithReport current = await scope.data.shifts.current();
    scope.session.updateShift(current.shift?.toSummary());
  } on PosException {
    // Aloqa yo'q: keyingi `shift.changed` yoki qayta ulanishda
  }
}

void showPosMessage(BuildContext context, String text) =>
    ScaffoldMessenger.maybeOf(context)
      ?..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(text)));

/// Sabab so'rash (bekor qilish, qaytarish, majburan yopish). Tez tugmalar + matn; bo'sh bo'lmasa qaytadi.
Future<String?> askReason(
  BuildContext context, {
  required String title,
  String? message,
  List<String> presets = const <String>[],
  String confirmLabel = 'Davom etish',
  RzButtonVariant confirmVariant = RzButtonVariant.primary,
}) => showDialog<String>(
  context: context,
  builder: (BuildContext context) => _ReasonDialog(
    title: title,
    message: message,
    presets: presets,
    confirmLabel: confirmLabel,
    confirmVariant: confirmVariant,
  ),
);

class _ReasonDialog extends StatefulWidget {
  const _ReasonDialog({
    required this.title,
    required this.message,
    required this.presets,
    required this.confirmLabel,
    required this.confirmVariant,
  });

  final String title;
  final String? message;
  final List<String> presets;
  final String confirmLabel;
  final RzButtonVariant confirmVariant;

  @override
  State<_ReasonDialog> createState() => _ReasonDialogState();
}

class _ReasonDialogState extends State<_ReasonDialog> {
  final TextEditingController _text = TextEditingController();

  @override
  void dispose() {
    _text.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final bool ok = _text.text.trim().isNotEmpty;
    return AlertDialog(
      title: Text(widget.title),
      content: SizedBox(
        width: 420,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            if (widget.message != null) ...<Widget>[
              Text(
                widget.message!,
                style: RzType.body.copyWith(color: c.fgMuted),
              ),
              const SizedBox(height: 12),
            ],
            if (widget.presets.isNotEmpty) ...<Widget>[
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: <Widget>[
                  for (final String p in widget.presets)
                    ChoiceChip(
                      label: Text(p),
                      selected: _text.text == p,
                      materialTapTargetSize: MaterialTapTargetSize.padded,
                      onSelected: (_) => setState(() => _text.text = p),
                    ),
                ],
              ),
              const SizedBox(height: 12),
            ],
            TextField(
              controller: _text,
              autofocus: widget.presets.isEmpty,
              maxLines: 2,
              minLines: 1,
              decoration: const InputDecoration(labelText: 'Sabab'),
              onChanged: (_) => setState(() {}),
            ),
          ],
        ),
      ),
      actions: <Widget>[
        RzButton(
          label: 'Bekor qilish',
          variant: RzButtonVariant.ghost,
          onPressed: () => Navigator.pop(context),
        ),
        RzButton(
          label: widget.confirmLabel,
          variant: widget.confirmVariant,
          onPressed: ok
              ? () => Navigator.pop(context, _text.text.trim())
              : null,
        ),
      ],
    );
  }
}

/// Ha/yo'q savoli.
Future<bool> confirmAction(
  BuildContext context, {
  required String title,
  required String message,
  String confirmLabel = 'Ha',
  RzButtonVariant confirmVariant = RzButtonVariant.primary,
}) async =>
    await showDialog<bool>(
      context: context,
      builder: (BuildContext context) => AlertDialog(
        title: Text(title),
        content: Text(message),
        actions: <Widget>[
          RzButton(
            label: "Yo'q",
            variant: RzButtonVariant.ghost,
            onPressed: () => Navigator.pop(context, false),
          ),
          RzButton(
            label: confirmLabel,
            variant: confirmVariant,
            onPressed: () => Navigator.pop(context, true),
          ),
        ],
      ),
    ) ??
    false;

// --- pul kiritish --------------------------------------------------------------------------------------------------------

/// Butun so'm: "1 250 000" ko'rinishida yoziladi.
class MoneyInputFormatter extends TextInputFormatter {
  const MoneyInputFormatter();

  @override
  TextEditingValue formatEditUpdate(
    TextEditingValue oldValue,
    TextEditingValue newValue,
  ) {
    final String digits = newValue.text.replaceAll(RegExp(r'\D'), '');
    final String trimmed = digits.replaceFirst(RegExp(r'^0+(?=\d)'), '');
    if (trimmed.length > 12) return oldValue;
    final String text = trimmed.isEmpty
        ? ''
        : groupDigits(int.parse(trimmed), ' ');
    return TextEditingValue(
      text: text,
      selection: TextSelection.collapsed(offset: text.length),
    );
  }
}

/// Matndan summa (bo'sh — `null`).
PosMoney? parseMoney(String text) {
  final String digits = text.replaceAll(RegExp(r'\D'), '');
  return digits.isEmpty ? null : PosMoney.som(int.parse(digits));
}

/// Summani maydonga yozish ("250 000").
String moneyText(PosMoney? m) =>
    m == null || m.isZero ? '' : groupDigits(m.wholeSom, ' ');

/// Summa maydoni (so'm).
class MoneyField extends StatelessWidget {
  const MoneyField({
    super.key,
    required this.controller,
    required this.label,
    this.onChanged,
    this.autofocus = false,
    this.errorText,
    this.helperText,
    this.enabled = true,
    this.fieldKey,
  });

  final TextEditingController controller;
  final String label;
  final ValueChanged<PosMoney?>? onChanged;
  final bool autofocus;
  final String? errorText;
  final String? helperText;
  final bool enabled;
  final Key? fieldKey;

  @override
  Widget build(BuildContext context) => TextField(
    key: fieldKey,
    controller: controller,
    enabled: enabled,
    autofocus: autofocus,
    keyboardType: TextInputType.number,
    inputFormatters: const <TextInputFormatter>[MoneyInputFormatter()],
    style: RzType.headline,
    decoration: InputDecoration(
      labelText: label,
      suffixText: PosMoney.currencyLabel,
      errorText: errorText,
      helperText: helperText,
    ),
    onChanged: (String t) => onChanged?.call(parseMoney(t)),
  );
}

/// Tanlov tugmalari qatori (ChoiceChip, 48dp).
class ChoiceRow<T> extends StatelessWidget {
  const ChoiceRow({
    super.key,
    required this.options,
    required this.selected,
    required this.onSelected,
  });

  final Map<T, String> options;
  final T? selected;
  final ValueChanged<T> onSelected;

  @override
  Widget build(BuildContext context) => Wrap(
    spacing: 8,
    runSpacing: 8,
    children: <Widget>[
      for (final MapEntry<T, String> o in options.entries)
        ChoiceChip(
          label: Text(o.value),
          selected: o.key == selected,
          materialTapTargetSize: MaterialTapTargetSize.padded,
          onSelected: (_) => onSelected(o.key),
        ),
    ],
  );
}

/// Bir martalik amalni bajaradi: xato — snackbar. `true` — muvaffaqiyatli.
Future<bool> runGuarded(
  BuildContext context,
  Future<void> Function() action,
) async {
  try {
    await action();
    return true;
  } on Object catch (e) {
    if (context.mounted) showPosError(context, e);
    return false;
  }
}

/// Real-time hodisa yoki polling bilan ro'yxatni yangilab turadi (sahifa yopilganda to'xtaydi).
mixin RefreshingState<T extends StatefulWidget> on State<T> {
  PosRefreshScheduler? _scheduler;
  StreamSubscription<List<SyncOperation>>? _syncSub;

  /// Sahifa ochilganda va yangilanganda.
  Future<void> refresh();

  /// [offline] va [orderRef] berilsa (P3-35): offlayn navbat serverga yetganda shu buyurtmaning amali bo'lsa sahifa
  /// serverdan qayta yuklanadi (qator server narxida saqlangan bo'lishi mumkin) va [onSynced] chaqiriladi.
  void startRefreshing(
    PosRealtime? realtime, {
    bool Function(PosEvent event)? filter,
    Duration pollInterval = const Duration(seconds: 15),
    PosOffline? offline,
    String? orderRef,
    void Function(List<SyncOperation> ops)? onSynced,
  }) {
    _scheduler = PosRefreshScheduler(
      realtime: realtime,
      eventFilter: filter,
      pollInterval: pollInterval,
      onRefresh: (_) => refresh(),
    )..start();
    if (offline != null && orderRef != null) {
      _syncSub = offline.synced.listen((List<SyncOperation> ops) {
        if (!syncTouchesOrder(ops, orderRef, offline.store.resolve)) return;
        onSynced?.call(ops);
        unawaited(_scheduler?.refreshNow());
      });
    }
  }

  @override
  void dispose() {
    unawaited(_scheduler?.dispose());
    unawaited(_syncSub?.cancel());
    super.dispose();
  }
}

/// Dialog ochadi va unga joriy [PosScope] ni beradi (dialog Navigator'da qobiqdan tashqarida turadi).
Future<T?> showScopedDialog<T>(
  BuildContext context,
  WidgetBuilder builder, {
  bool barrierDismissible = true,
}) {
  final PosScope scope = PosScope.of(context);
  return showDialog<T>(
    context: context,
    barrierDismissible: barrierDismissible,
    builder: (BuildContext _) => PosScope(
      services: scope.services,
      realtime: scope.realtime,
      navigate: scope.navigate,
      child: Builder(builder: builder),
    ),
  );
}
