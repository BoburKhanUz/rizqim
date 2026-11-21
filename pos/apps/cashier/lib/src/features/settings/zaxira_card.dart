import 'dart:async';

import 'package:flutter/material.dart';
import 'package:pos_sync/pos_sync.dart';
import 'package:rizqim_core/rizqim_core.dart' show StatusTone;
import 'package:rizqim_ui/rizqim_ui.dart';

import '../../printing/offline_summary.dart';
import '../../services.dart';
import '../billing/cashier_ui.dart';

/// M5 S4: the text of an export result for the screen ("Saqlandi" and where).
String exportSavedText(SyncExportResult r) =>
    "Navbat faylga saqlandi (${r.operations} ta amal, shundan ${r.payments} ta to'lov):\n${r.paths.join('\n')}";

/// Outcome of [runExport]: [text] is for the screen.
typedef ExportOutcome = ({bool ok, String text, SyncExportResult? result});

/// Runs the export and says the outcome as plain text: a refusal's own message, or a short failure text. Never throws
/// and never blocks anything else; [exporter] answers calls made while it runs with the same answer.
Future<ExportOutcome> runExport(SyncExporter exporter) async {
  try {
    final SyncExportResult r = await exporter.export();
    return (ok: true, text: exportSavedText(r), result: r);
  } on SyncExportRefused catch (e) {
    return (ok: false, text: e.message, result: null);
  } on Object {
    return (
      ok: false,
      text: "Navbatni faylga saqlab bo'lmadi. Keyinroq qayta urinib ko'ring.",
      result: null,
    );
  }
}

String _two(int n) => n.toString().padLeft(2, '0');

String _time(DateTime t) {
  final DateTime l = t.toLocal();
  return '${_two(l.day)}.${_two(l.month)}.${l.year} ${_two(l.hour)}:${_two(l.minute)}';
}

/// Sozlamalardagi "Zaxira" kartochkasi: zaxira papkasi, oxirgi nusxa vaqti, xato, oxirgi tiklash va "Navbatni faylga
/// saqlash". Server `offline.export` demasa butunlay yashirin.
class ZaxiraCard extends StatefulWidget {
  const ZaxiraCard({super.key, required this.services});

  final CashierServices services;

  @override
  State<ZaxiraCard> createState() => _ZaxiraCardState();
}

class _ZaxiraCardState extends State<ZaxiraCard> {
  final List<StreamSubscription<Object?>> _subs =
      <StreamSubscription<Object?>>[];
  bool _busy = false;
  bool? _ok;
  String? _message;

  @override
  void initState() {
    super.initState();
    _subs.add(widget.services.session.changes.listen((_) => _rebuild()));
    final Stream<Object?>? offline = widget.services.data.offline?.changes;
    if (offline != null) _subs.add(offline.listen((_) => _rebuild()));
    final Stream<void>? m = widget.services.mirror?.changes;
    if (m != null) _subs.add(m.listen((_) => _rebuild()));
  }

  @override
  void dispose() {
    for (final StreamSubscription<Object?> s in _subs) {
      unawaited(s.cancel());
    }
    super.dispose();
  }

  void _rebuild() {
    if (mounted) setState(() {});
  }

  Future<void> _printSummary() async {
    setState(() => _busy = true);
    final SummaryPrintOutcome out = await printOfflineSummary(widget.services);
    if (!mounted) return;
    setState(() {
      _busy = false;
      _ok = out.ok;
      _message = out.text;
    });
  }

  Future<void> _save(SyncExporter exporter) async {
    setState(() => _busy = true);
    final ExportOutcome out = await runExport(exporter);
    if (!mounted) return;
    setState(() {
      _busy = false;
      _ok = out.ok;
      _message = out.text;
    });
  }

  @override
  Widget build(BuildContext context) {
    final SyncExporter? exporter = widget.services.exporter;
    if (exporter == null || !exporter.available) return const SizedBox.shrink();
    final RzColors c = context.rz;
    final MirroredSyncStorage? mirror = widget.services.mirror;
    final MirrorRestore? restore = mirror?.lastRestore;
    final DateTime? at = mirror?.lastMirroredAt;
    final TextStyle muted = RzType.body.copyWith(color: c.fgMuted);
    return Padding(
      padding: const EdgeInsets.only(bottom: 16),
      child: PosCard(
        key: const ValueKey<String>('zaxira-card'),
        title: 'Zaxira',
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            Text(
              mirror?.directory == null
                  ? "Zaxira papkasi yo'q: bu qurilmada navbat nusxasi saqlanmaydi."
                  : 'Zaxira papkasi: ${mirror!.directory!.path}',
              style: muted,
            ),
            if (mirror?.directory != null) ...<Widget>[
              const SizedBox(height: 4),
              Text(
                at == null
                    ? 'Oxirgi nusxa: bu ishga tushishda hali yozilmagan'
                    : 'Oxirgi nusxa: ${_time(at)}',
                style: muted,
              ),
            ],
            if (mirror?.mirrorError != null) ...<Widget>[
              const SizedBox(height: 8),
              const NoticeBanner(
                message: "Nusxa yozib bo'lmadi. Zaxira papkasi va diskdagi joyni tekshiring.",
                tone: StatusTone.danger,
              ),
            ],
            if (restore != null && restore.restored > 0) ...<Widget>[
              const SizedBox(height: 8),
              Text(
                '${restore.restored} ta amal zaxiradan tiklandi',
                key: const ValueKey<String>('zaxira-restored'),
                style: RzType.body.copyWith(color: c.fg),
              ),
            ],
            if (restore != null && restore.kept > 0) ...<Widget>[
              const SizedBox(height: 4),
              Text(
                "${restore.kept} ta amal tekshirib bo'lmagani uchun tiklanmadi: ular zaxira papkasida saqlandi",
                key: const ValueKey<String>('zaxira-kept'),
                style: muted,
              ),
            ],
            const SizedBox(height: 12),
            Align(
              alignment: Alignment.centerLeft,
              child: RzButton(
                key: const ValueKey<String>('zaxira-export'),
                label: 'Navbatni faylga saqlash',
                icon: Icons.save_alt_rounded,
                variant: RzButtonVariant.secondary,
                onPressed: _busy ? null : () => unawaited(_save(exporter)),
              ),
            ),
            const SizedBox(height: 8),
            Align(
              alignment: Alignment.centerLeft,
              child: RzButton(
                key: const ValueKey<String>('zaxira-print-summary'),
                label: 'Qisqa hisobotni chop etish',
                icon: Icons.print_rounded,
                variant: RzButtonVariant.secondary,
                onPressed: _busy ? null : () => unawaited(_printSummary()),
              ),
            ),
            if (_message != null) ...<Widget>[
              const SizedBox(height: 8),
              NoticeBanner(
                key: const ValueKey<String>('zaxira-result'),
                message: _message!,
                tone: _ok == true ? StatusTone.success : StatusTone.danger,
              ),
            ],
          ],
        ),
      ),
    );
  }
}
