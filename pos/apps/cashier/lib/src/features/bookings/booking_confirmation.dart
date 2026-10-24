import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_core/rizqim_core.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../../shell/approval_dialog.dart';
import '../../shell/pos_scope.dart';
import '../billing/cashier_ui.dart';
import '../billing/receipts.dart';

/// P3-38: bron sahifasidagi "Tasdiq" bo'limi — mehmonga boradigan matn (xona puli qatori bilan, serverdan), "Yuborish"
/// (Telegram yoki SMS — server tanlaydi), "Nusxa olish" (kassir o'z Telegram/WhatsApp'iga qo'yadi), "Chop etish" (chek
/// printerida varaqcha) va oxirgi yuborilgan vaqt. Matn faqat serverdan: aloqa yo'q bo'lsa "Internet kerak".
class BookingConfirmationCard extends StatefulWidget {
  const BookingConfirmationCard({
    super.key,
    required this.booking,
    this.onSent,
  });

  final PosBooking booking;

  /// Yuborilgandan keyin (bron qayta o'qiladi: `confirmation_sent_at`).
  final VoidCallback? onSent;

  @override
  State<BookingConfirmationCard> createState() =>
      _BookingConfirmationCardState();
}

class _BookingConfirmationCardState extends State<BookingConfirmationCard> {
  PosBookingConfirmation? _confirmation;
  PosException? _loadError;
  bool _loading = false;
  bool _sending = false;
  bool _printing = false;
  bool _started = false;

  /// Yuborish natijasi yoki rad etish sababi (bo'lim ichida).
  ({String text, StatusTone tone})? _notice;

  /// Shu sahifadan yuborilgani (server bronni hali qayta bermagan bo'lsa ham holat ko'rinsin).
  ({DateTime at, String channel})? _sentHere;

  // A send whose answer was lost goes again with the same Idempotency-Key: the guest gets one message
  final LostRequest _lost = LostRequest();

  PosScope get _scope => PosScope.of(context);

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!_started) {
      _started = true;
      unawaited(_load());
    }
  }

  @override
  void didUpdateWidget(BookingConfirmationCard old) {
    super.didUpdateWidget(old);
    // Time, room, guests, pre-order or deposits changed: the text is the server's, read it again
    if (old.booking.version != widget.booking.version ||
        old.booking.uuid != widget.booking.uuid) {
      unawaited(_load());
    }
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _loadError = null;
    });
    try {
      final PosBookingConfirmation c = await _scope.data.bookings.confirmation(
        widget.booking.uuid,
      );
      if (!mounted) return;
      setState(() {
        _confirmation = c;
        _loading = false;
      });
    } on PosException catch (e) {
      if (!mounted) return;
      setState(() {
        _loadError = e;
        _loading = false;
      });
    }
  }

  Future<void> _send() async {
    setState(() {
      _sending = true;
      _notice = null;
    });
    final PosScope scope = _scope;
    try {
      final PosConfirmationSent sent = await _lost.run<PosConfirmationSent>(
        context,
        'confirmation ${widget.booking.uuid}',
        (String? _, String? key) => scope.data.bookings.sendConfirmation(
          widget.booking.uuid,
          idempotencyKey: key,
        ),
      );
      if (!mounted) return;
      final String done = sentLabel(sent.channel);
      setState(() {
        _sentHere = (at: scope.services.now(), channel: sent.channel);
        _notice = (text: done, tone: StatusTone.success);
      });
      showPosMessage(context, done);
      widget.onSent?.call();
    } on PosNetworkException {
      if (mounted) {
        setState(
          () => _notice = (
            text: "Aloqa yo'q: tasdiq yuborilgani noma'lum. Qayta bosing — mehmonga ikki marta bormaydi.",
            tone: StatusTone.warning,
          ),
        );
      }
    } on PosException catch (e) {
      if (mounted) {
        setState(
          () => _notice = (
            text: e.reason == PosReasons.noChannel
                ? noChannelText(e.message)
                : e.message,
            tone: StatusTone.danger,
          ),
        );
      }
    } on Object catch (e) {
      if (mounted) showPosError(context, e);
    }
    if (mounted) setState(() => _sending = false);
  }

  Future<void> _copy(PosBookingConfirmation c) async {
    await Clipboard.setData(ClipboardData(text: c.text));
    if (!mounted) return;
    showPosMessage(
      context,
      "Matn nusxalandi: uni Telegram yoki WhatsApp'da mehmonga qo'yib yuboring.",
    );
  }

  Future<void> _print(PosBookingConfirmation c) async {
    final PosScope scope = _scope;
    final ReceiptPrinter printer = ReceiptPrinter(scope);
    final ReceiptHeader header = printer.header;
    final DateTime now = scope.services.now();
    setState(() => _printing = true);
    final bool queued = await printer.print(
      context,
      (PaperWidth paper) => layoutBookingConfirmation(
        c,
        header: header,
        printedAt: now,
        paper: paper,
      ),
    );
    if (!mounted) return;
    setState(() => _printing = false);
    if (queued) showPosMessage(context, 'Tasdiq chop etildi.');
  }

  /// "Tasdiq yuborilgan: 19:42, Telegram" (boshqa kuni — sana bilan); yuborilmagan — `null`.
  String? get _sentStatus {
    DateTime? at = widget.booking.confirmationSentAt;
    String? channel = widget.booking.confirmationChannel;
    final ({DateTime at, String channel})? here = _sentHere;
    if (here != null && (at == null || here.at.isAfter(at))) {
      at = here.at;
      channel = here.channel;
    }
    if (at == null) return null;
    final DateTime now = _scope.services.now();
    final String when = isoDay(at) == isoDay(now)
        ? hm(at)
        : '${dm(at)} ${hm(at)}';
    final String via = PosConfirmationSent.label(channel);
    return via.isEmpty
        ? 'Tasdiq yuborilgan: $when'
        : 'Tasdiq yuborilgan: $when, $via';
  }

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final PosBookingConfirmation? confirmation = _confirmation;
    final String? status = _sentStatus;
    final ({String text, StatusTone tone})? notice = _notice;
    return PosCard(
      title: 'Tasdiq',
      trailing: status == null
          ? null
          : TonePill(
              key: const ValueKey<String>('confirmation-status'),
              label: status,
              tone: StatusTone.success,
              icon: Icons.mark_chat_read_outlined,
            ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          if (status == null)
            Text(
              'Mehmonga tasdiq hali yuborilmagan.',
              style: RzType.footnote.copyWith(color: c.fgMuted),
            ),
          const SizedBox(height: 8),
          if (confirmation != null)
            DecoratedBox(
              decoration: BoxDecoration(
                color: c.surface2,
                borderRadius: BorderRadius.circular(RzRadius.control),
                border: Border.all(color: c.line),
              ),
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: SelectableText(
                  confirmation.text,
                  key: const ValueKey<String>('confirmation-text'),
                  style: RzType.body.copyWith(color: c.fg),
                ),
              ),
            )
          else if (_loadError != null)
            InlineError(
              message: _loadError!.isNetworkError
                  ? "Internet kerak: tasdiq matni serverdan olinadi."
                  : _loadError!.message,
              onRetry: () => unawaited(_load()),
            )
          else if (_loading)
            const Padding(
              padding: EdgeInsets.all(16),
              child: Center(child: CircularProgressIndicator()),
            ),
          if (confirmation != null && !confirmation.channels.any) ...<Widget>[
            const SizedBox(height: 8),
            Text(
              "Mehmon telefoni Telegram botga ulanmagan, SMS o'chirilgan: matnni nusxa olib o'zingiz yuboring yoki chop "
              'eting.',
              style: RzType.footnote.copyWith(color: c.fgMuted),
            ),
          ],
          if (notice != null) ...<Widget>[
            const SizedBox(height: 8),
            NoticeBanner(
              key: const ValueKey<String>('confirmation-notice'),
              message: notice.text,
              tone: notice.tone,
              icon: notice.tone == StatusTone.success
                  ? Icons.check_circle_outline_rounded
                  : Icons.info_outline_rounded,
            ),
          ],
          const SizedBox(height: 12),
          Wrap(
            spacing: 12,
            runSpacing: 12,
            children: <Widget>[
              RzButton(
                key: const ValueKey<String>('confirmation-send'),
                label: 'Yuborish',
                icon: Icons.send_rounded,
                loading: _sending,
                onPressed: confirmation == null || _sending ? null : _send,
              ),
              RzButton(
                key: const ValueKey<String>('confirmation-copy'),
                label: 'Nusxa olish',
                icon: Icons.copy_rounded,
                variant: RzButtonVariant.secondary,
                onPressed: confirmation == null
                    ? null
                    : () => _copy(confirmation),
              ),
              RzButton(
                key: const ValueKey<String>('confirmation-print'),
                label: 'Chop etish',
                icon: Icons.print_outlined,
                variant: RzButtonVariant.secondary,
                loading: _printing,
                onPressed: confirmation == null || _printing
                    ? null
                    : () => _print(confirmation),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

/// `no_channel`: serverning aniq sababi ("telefon raqami yo'q", "SMS yoqilmagan") va kassir nima qilishi.
String noChannelText(String server) {
  final String reason = server.trim().isEmpty
      ? PosReasons.describe(PosReasons.noChannel)!
      : server.trim();
  return reason.toLowerCase().contains('nusxa')
      ? reason
      : '$reason Matnni "Nusxa olish" bilan o\'zingiz yuboring yoki chop etib bering.';
}

/// Yuborish natijasi: "Telegram'ga yuborildi", "SMS yuborildi".
String sentLabel(String channel) => switch (channel) {
  'telegram' => "Telegram'ga yuborildi",
  'sms' => 'SMS yuborildi',
  _ => 'Yuborildi',
};

/// Bron tasdig'i chek printerida (varaqcha, fiskal emas): do'kon sarlavhasi, "BRON TASDIG'I" va server bergan qatorlar
/// (xona puli qatori qalin). Printer jadvalida yo'q belgilar (emoji) tushirib qoldiriladi.
PrintLayout layoutBookingConfirmation(
  PosBookingConfirmation c, {
  required ReceiptHeader header,
  required DateTime printedAt,
  required PaperWidth paper,
}) {
  final PrintWriter w = PrintWriter(paper);
  writeHeader(w, header);
  w.divider('=');
  w.center("BRON TASDIG'I", bold: true, size: PrintTextSize.tall);
  w.divider();
  final int width = w.widthFor();
  for (final PosConfirmationLine line in c.lines) {
    // The header and the store are already on top
    if (line.kind == PosConfirmationLine.header || line.kind == 'place') {
      continue;
    }
    final String label = paperText(line.label);
    final String value = paperText(line.value);
    final bool bold = line.kind == PosConfirmationLine.roomFee;
    if (label.isEmpty) {
      if (value.isNotEmpty) w.text(value, bold: bold);
    } else if (textWidth(label) + 2 + textWidth(value) <= width) {
      w.pair('$label:', value, bold: bold);
    } else {
      w.text('$label:', bold: bold);
      w.text(value, bold: bold, indent: 2);
    }
  }
  w.divider();
  w.center('Chop etildi: ${formatReceiptDateTime(printedAt.toLocal())}');
  w.cut();
  return w.build(title: "Bron tasdig'i");
}

/// Qog'oz uchun matn: emoji va bezak belgilari olib tashlanadi (chek printeri jadvalida yo'q, "?" chiqadi), uzun tire —
/// oddiy "-".
String paperText(String text) {
  final StringBuffer out = StringBuffer();
  for (final int r in text.runes) {
    final bool symbol =
        r >= 0x1F000 ||
        (r >= 0x2190 && r <= 0x21FF) ||
        (r >= 0x2300 && r <= 0x23FF) ||
        (r >= 0x2600 && r <= 0x27BF) ||
        (r >= 0x2B00 && r <= 0x2BFF) ||
        r == 0x2139 ||
        r == 0x200D ||
        r == 0xFE0F ||
        r == 0xFE0E;
    if (!symbol) out.writeCharCode(r);
  }
  // A dash the code page has (an en dash would send the whole slip to the slower image mode)
  return out
      .toString()
      .replaceAll(RegExp('[\u2013\u2014]'), '-')
      .replaceAll(RegExp(' {2,}'), ' ')
      .trim();
}
