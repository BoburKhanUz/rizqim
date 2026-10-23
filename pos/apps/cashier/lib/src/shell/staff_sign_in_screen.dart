import 'dart:async';

import 'package:flutter/material.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:rizqim_core/rizqim_core.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import 'package:pos_data/pos_data.dart';

import 'connection_indicator.dart';

/// Terminal ulangan, lekin hech kim kirmagan (ekran qulfi): xodim ismini tanlaydi va PIN teradi
/// (`GET /terminal/staff`, `POST /devices/{uuid}/staff-session`). Terminalning o'zi ulangan qoladi; real-time ham
/// ishlaydi.
///
/// Bu ekranda sozlamalar va "Terminalni uzish" yo'q (PIN'siz kishi kassani uza olmasin): uzish — PIN bilan kirgan
/// menejer yoki ega (`devices.manage`), yuqori paneldagi xodim menyusida.
class StaffSignInScreen extends StatefulWidget {
  const StaffSignInScreen({
    super.key,
    required this.auth,
    required this.terminal,
    required this.connectivity,
    this.realtime,
    this.notice,
    this.clock,
  });

  final AuthSession auth;
  final PairedTerminal terminal;
  final PosRealtime? realtime;
  final PosConnectivity connectivity;

  /// Nima uchun bu ekran ochildi (masalan, "Ekranga uzoq tegilmagani uchun chiqildi").
  final String? notice;
  final DateTime Function()? clock;

  @override
  State<StaffSignInScreen> createState() => _StaffSignInScreenState();
}

class _StaffSignInScreenState extends State<StaffSignInScreen> {
  List<StaffMember>? _staff;
  ApiException? _loadError;
  bool _loading = true;
  StaffMember? _selected;
  PinFailure? _failure;
  final Map<int, DateTime> _lockedUntil = <int, DateTime>{};
  Timer? _ticker;

  DateTime _now() => (widget.clock ?? DateTime.now)();

  @override
  void initState() {
    super.initState();
    unawaited(_load());
  }

  @override
  void dispose() {
    _ticker?.cancel();
    super.dispose();
  }

  Future<void> _load() async {
    if (!_loading || _loadError != null) {
      setState(() {
        _loading = true;
        _loadError = null;
      });
    }
    try {
      final List<StaffMember> staff = await widget.auth.loadStaff();
      // Kassirlar birinchi, keyin menejer va ega, qolganlari; bir rol ichida ism bo'yicha
      int rank(String role) => switch (role) {
        'cashier' => 0,
        'manager' || 'owner' => 1,
        _ => 2,
      };
      staff.sort((StaffMember a, StaffMember b) {
        final int byRole = rank(a.role).compareTo(rank(b.role));
        return byRole != 0
            ? byRole
            : a.name.toLowerCase().compareTo(b.name.toLowerCase());
      });
      if (!mounted) return;
      setState(() {
        _staff = staff;
        _loading = false;
        if (staff.length == 1) _selected = staff.single;
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _loadError = e;
        _loading = false;
      });
    }
  }

  Duration? _lockLeft(StaffMember m) {
    final DateTime? until = _lockedUntil[m.id];
    if (until == null) return null;
    final Duration left = until.difference(_now());
    return left > Duration.zero ? left : null;
  }

  void _startTicker() {
    _ticker?.cancel();
    _ticker = Timer.periodic(const Duration(seconds: 1), (Timer t) {
      if (!mounted) return t.cancel();
      _lockedUntil.removeWhere(
        (int _, DateTime until) => !until.isAfter(_now()),
      );
      if (_lockedUntil.isEmpty) {
        t.cancel();
        _failure = null;
      }
      setState(() {});
    });
  }

  Future<bool> _submit(String pin) async {
    final StaffMember? member = _selected;
    if (member == null || _lockLeft(member) != null) return false;
    try {
      await widget.auth.signInStaff(userId: member.id, pin: pin);
      return true; // ekranni AuthGate almashtiradi
    } on ApiException catch (e) {
      final PinFailure failure = PinFailure.from(e);
      if (!mounted) return false;
      setState(() {
        _failure = failure;
        if (failure.lockedFor != null) {
          _lockedUntil[member.id] = _now().add(failure.lockedFor!);
          _startTicker();
        }
      });
      return false;
    }
  }

  void _select(StaffMember m) => setState(() {
    _selected = m;
    _failure = null;
  });

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final String place = <String>[
      ?widget.terminal.store?.name,
      ?widget.terminal.branch?.name,
    ].join(' / ');
    return Scaffold(
      appBar: AppBar(
        toolbarHeight: 64,
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            const Text('Kassa'),
            if (place.isNotEmpty)
              Text(
                place,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: RzType.footnote.copyWith(color: c.fgMuted),
              ),
          ],
        ),
        actions: <Widget>[
          ConnectionIndicator(
            realtime: widget.realtime,
            connectivity: widget.connectivity,
            compact: MediaQuery.sizeOf(context).width < 600,
          ),
          // "Terminalni uzish" bu yerda yo'q: PIN'siz hech kim kassani uza olmasin
          const SizedBox(width: 16),
        ],
      ),
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          // Maxfiy ombor yozmadi (masalan, Linux'da kalitlar xizmati yo'q): ulanish faqat shu ishga tushirishda ishlaydi
          if (widget.auth.storageError != null)
            const Padding(
              padding: EdgeInsets.fromLTRB(16, 12, 16, 0),
              child: InlineError(
                message: "Terminal tokeni qurilmada saqlanmadi. Ilova qayta ishga tushirilsa, qayta ulash kerak bo'ladi.",
              ),
            ),
          if (widget.notice != null)
            _Notice(
              icon: Icons.lock_clock_outlined,
              text: widget.notice!,
              tone: StatusTone.neutral,
            ),
          Expanded(child: _body(context)),
        ],
      ),
    );
  }

  Widget _body(BuildContext context) {
    if (_loading) {
      return const Center(child: CircularProgressIndicator());
    }
    final ApiException? error = _loadError;
    if (error != null) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: InlineError(
            message: error.isNetworkError
                ? "Aloqa yo'q: xodimlar ro'yxatini yuklab bo'lmadi."
                : error.message,
            onRetry: () => unawaited(_load()),
          ),
        ),
      );
    }
    final List<StaffMember> staff = _staff ?? const <StaffMember>[];
    if (staff.isEmpty) {
      return EmptyState(
        icon: Icons.person_off_outlined,
        title: "PIN o'rnatilgan xodim yo'q",
        message: "Boshqaruv panelida Xodimlar bo'limidan kassirga PIN o'rnating, so'ng ro'yxatni yangilang.",
        actionLabel: 'Yangilash',
        onAction: () => unawaited(_load()),
      );
    }
    return LayoutBuilder(
      builder: (BuildContext context, BoxConstraints box) {
        final bool wide = box.maxWidth >= 760;
        final double keySize = (box.maxHeight / 9.5).clamp(60.0, 96.0);
        if (wide) {
          return Row(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              Expanded(child: _staffList(staff, box.maxWidth - 440)),
              SizedBox(width: 440, child: _pinPanel(keySize, showBack: false)),
            ],
          );
        }
        return _selected == null
            ? _staffList(staff, box.maxWidth)
            : _pinPanel(keySize, showBack: staff.length > 1);
      },
    );
  }

  Widget _staffList(List<StaffMember> staff, double width) {
    final RzColors c = context.rz;
    final int columns = (width / 300).floor().clamp(1, 4);
    return ListView(
      padding: const EdgeInsets.all(20),
      children: <Widget>[
        Text('Kim ishlaydi?', style: RzType.title.copyWith(color: c.fg)),
        const SizedBox(height: 4),
        Text(
          'Ismingizni tanlang va PIN kodingizni tering.',
          style: RzType.body.copyWith(color: c.fgMuted),
        ),
        const SizedBox(height: 16),
        GridView.count(
          crossAxisCount: columns,
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          mainAxisSpacing: 12,
          crossAxisSpacing: 12,
          childAspectRatio: (width / columns) / 84,
          children: <Widget>[
            for (final StaffMember m in staff)
              RzStaffTile(
                name: m.name,
                subtitle: StaffRole.labelOf(m.role),
                selected: _selected?.id == m.id,
                onTap: () => _select(m),
              ),
          ],
        ),
      ],
    );
  }

  Widget _pinPanel(double keySize, {required bool showBack}) {
    final RzColors c = context.rz;
    final StaffMember? member = _selected;
    if (member == null) {
      return ColoredBox(
        color: c.surface,
        child: Center(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: <Widget>[
              Icon(Icons.touch_app_outlined, size: 48, color: c.fgMuted),
              const SizedBox(height: 12),
              Text(
                'Ismingizni tanlang',
                style: RzType.headline.copyWith(color: c.fgMuted),
              ),
            ],
          ),
        ),
      );
    }
    final Duration? lockLeft = _lockLeft(member);
    final PinFailure? failure = _failure;
    final String? message = lockLeft != null
        ? '${failure?.message ?? "Kirish vaqtincha bloklandi."} ${formatDuration(lockLeft)} dan keyin qayta urinib ko\'ring.'
        : (failure != null && !failure.isLocked ? failure.message : null);
    return Container(
      color: c.surface,
      child: Center(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: <Widget>[
              if (showBack)
                Align(
                  alignment: Alignment.centerLeft,
                  child: RzButton(
                    label: 'Boshqa xodim',
                    icon: Icons.arrow_back_rounded,
                    variant: RzButtonVariant.ghost,
                    onPressed: () => setState(() {
                      _selected = null;
                      _failure = null;
                    }),
                  ),
                ),
              Text(
                member.name,
                textAlign: TextAlign.center,
                style: RzType.title.copyWith(color: c.fg),
              ),
              Text(
                StaffRole.labelOf(member.role),
                style: RzType.body.copyWith(color: c.fgMuted),
              ),
              const SizedBox(height: 16),
              RzPinEntry(
                key: ValueKey<int>(member.id),
                keySize: keySize,
                enabled: lockLeft == null,
                error: failure != null,
                onSubmit: _submit,
                onChanged: () {
                  if (_failure != null && lockLeft == null) {
                    setState(() => _failure = null);
                  }
                },
              ),
              const SizedBox(height: 12),
              SizedBox(
                width: keySize * 3 + 36,
                child: message == null
                    ? const SizedBox(height: 48)
                    : Semantics(
                        liveRegion: true,
                        child: Text(
                          message,
                          textAlign: TextAlign.center,
                          style: RzType.body.copyWith(color: c.dangerSoftFg),
                        ),
                      ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _Notice extends StatelessWidget {
  const _Notice({required this.icon, required this.text, required this.tone});

  final IconData icon;
  final String text;
  final StatusTone tone;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final (Color bg, Color fg) = switch (tone) {
      StatusTone.info => (c.infoSoft, c.infoSoftFg),
      StatusTone.warning => (c.warningSoft, c.warningSoftFg),
      StatusTone.danger => (c.dangerSoft, c.dangerSoftFg),
      StatusTone.success => (c.successSoft, c.successSoftFg),
      StatusTone.neutral => (c.surface2, c.fg),
    };
    return Semantics(
      liveRegion: true,
      child: Container(
        margin: const EdgeInsets.fromLTRB(16, 12, 16, 0),
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: Color.alphaBlend(bg, c.surface),
          borderRadius: BorderRadius.circular(RzRadius.card),
        ),
        child: Row(
          children: <Widget>[
            Icon(icon, color: fg),
            const SizedBox(width: 12),
            Expanded(
              child: Text(text, style: RzType.headline.copyWith(color: fg)),
            ),
          ],
        ),
      ),
    );
  }
}
