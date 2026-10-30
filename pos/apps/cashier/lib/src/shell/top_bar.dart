import 'dart:async';

import 'package:flutter/material.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_core/rizqim_core.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../storage/shell_settings.dart';
import 'clock_button.dart';
import 'connection_indicator.dart';
import 'destinations.dart';
import 'pos_scope.dart';
import 'unpair_dialog.dart';

/// Ish ekranining yuqori paneli: do'kon va filial, smena holati, aloqa belgisi, sinxronlash nishoni, kirgan xodim
/// (menyu: qulflash, mavzu, terminalni uzish) va qulf tugmasi.
class PosTopBar extends StatelessWidget implements PreferredSizeWidget {
  const PosTopBar({super.key, required this.compact});

  /// Tor ekran: yozuvlar qisqaroq.
  final bool compact;

  @override
  Size get preferredSize => const Size.fromHeight(64);

  @override
  Widget build(BuildContext context) {
    final PosScope scope = PosScope.of(context);
    final PosSession session = scope.session;
    final RzColors c = context.rz;
    final String store = session.storeName ?? 'Kassa';
    final String? branch = session.branchName;
    return AppBar(
      toolbarHeight: 64,
      automaticallyImplyLeading: false,
      titleSpacing: 16,
      title: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          Text(store, maxLines: 1, overflow: TextOverflow.ellipsis),
          if (branch != null)
            Text(
              branch,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: RzType.footnote.copyWith(color: c.fgMuted),
            ),
        ],
      ),
      actions: <Widget>[
        ShiftChip(
          shift: session.shift,
          compact: compact,
          onTap: CashierDestination.shift.visibleFor(session)
              ? () => scope.go(CashierDestination.shift)
              : null,
        ),
        const SizedBox(width: 12),
        ConnectionIndicator(
          realtime: scope.realtime,
          connectivity: scope.data.connectivity,
          pollSeconds: scope.services.pollInterval.inSeconds,
          compact: compact,
        ),
        const SizedBox(width: 8),
        SyncBadge(connectivity: scope.data.connectivity),
        const SizedBox(width: 8),
        // P6-23: "Ishga keldim" / "Ishdan ketdim" (bootstrap `staff_pay.clock`; phone: in the staff menu)
        if (!compact) const ClockButton(),
        StaffMenuButton(
          session: session,
          settings: scope.services.settings,
          compact: compact,
        ),
        IconButton(
          tooltip: 'Ekranni qulflash',
          icon: const Icon(Icons.lock_outline_rounded),
          onPressed: () => unawaited(session.lock()),
        ),
        const SizedBox(width: 8),
      ],
    );
  }
}

/// Smena holati: "Smena ochiq · 22.10" yoki "Smena yopiq". Bosilsa — Smena bo'limi.
class ShiftChip extends StatelessWidget {
  const ShiftChip({
    super.key,
    required this.shift,
    this.onTap,
    this.compact = false,
  });

  final PosShiftSummary? shift;
  final VoidCallback? onTap;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    final PosShiftSummary? s = shift;
    final bool open = s != null && s.isOpen;
    final String date = s?.businessDate ?? '';
    final String label = open
        ? (compact || date.length < 10
              ? 'Smena ochiq'
              : 'Smena ochiq · ${date.substring(8, 10)}.${date.substring(5, 7)}')
        : 'Smena yopiq';
    return Tooltip(
      message: open
          ? "Smena ochiq: to'lov qabul qilinadi"
          : "Smena ochilmagan: to'lov qabul qilish uchun smenani oching",
      child: InkWell(
        borderRadius: BorderRadius.circular(RzRadius.control),
        onTap: onTap,
        child: ConstrainedBox(
          constraints: const BoxConstraints(minHeight: 48),
          child: Center(
            child: _Pill(
              label: label,
              tone: open ? StatusTone.success : StatusTone.warning,
              icon: open
                  ? Icons.lock_open_rounded
                  : Icons.point_of_sale_outlined,
            ),
          ),
        ),
      ),
    );
  }
}

class _Pill extends StatelessWidget {
  const _Pill({required this.label, required this.tone, required this.icon});

  final String label;
  final StatusTone tone;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    final (Color bg, Color fg) = StatusBadge.colorsOf(context.rz, tone);
    return Semantics(
      label: label,
      excludeSemantics: true,
      child: DecoratedBox(
        decoration: BoxDecoration(
          color: Color.alphaBlend(bg, context.rz.surface),
          borderRadius: BorderRadius.circular(RzRadius.control),
        ),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: <Widget>[
              Icon(icon, size: 18, color: fg),
              const SizedBox(width: 6),
              Text(label, style: RzType.body.copyWith(color: fg)),
            ],
          ),
        ),
      ),
    );
  }
}

/// Kirgan xodim: bosh harflar, ism va rol; menyuda qulflash, mavzu va (menejer, ega) terminalni uzish.
class StaffMenuButton extends StatelessWidget {
  const StaffMenuButton({
    super.key,
    required this.session,
    required this.settings,
    this.compact = false,
  });

  final PosSession session;
  final ShellSettingsController settings;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final String name = session.staffName ?? '';
    final String role = StaffRole.labelOf(session.staffRole ?? '');
    return PopupMenuButton<String>(
      tooltip: 'Xodim menyusi',
      position: PopupMenuPosition.under,
      constraints: const BoxConstraints(minWidth: 280),
      onSelected: (String value) => unawaited(_onSelected(context, value)),
      itemBuilder: (BuildContext context) => <PopupMenuEntry<String>>[
        PopupMenuItem<String>(
          enabled: false,
          child: Text(
            '$name · $role',
            style: RzType.body.copyWith(color: c.fgMuted),
          ),
        ),
        const PopupMenuDivider(),
        if (compact && (session.bootstrap?.staffPay?.clock ?? false))
          const PopupMenuItem<String>(
            value: 'clock',
            child: ListTile(
              leading: Icon(Icons.badge_outlined),
              title: Text('Ish vaqti'),
            ),
          ),
        const PopupMenuItem<String>(
          value: 'lock',
          child: ListTile(
            leading: Icon(Icons.lock_outline_rounded),
            title: Text('Ekranni qulflash'),
          ),
        ),
        for (final (ThemeMode mode, String label) in <(ThemeMode, String)>[
          (ThemeMode.system, 'Mavzu: tizim'),
          (ThemeMode.light, "Mavzu: yorug'"),
          (ThemeMode.dark, "Mavzu: qorong'i"),
        ])
          CheckedPopupMenuItem<String>(
            value: 'theme:${mode.name}',
            checked: settings.value == mode,
            child: Text(label),
          ),
        if (session.canUnpair) ...<PopupMenuEntry<String>>[
          const PopupMenuDivider(),
          PopupMenuItem<String>(
            value: 'unpair',
            child: ListTile(
              leading: Icon(Icons.link_off_rounded, color: c.dangerSoftFg),
              title: Text(
                'Terminalni uzish',
                style: TextStyle(color: c.dangerSoftFg),
              ),
            ),
          ),
        ],
      ],
      child: ConstrainedBox(
        constraints: const BoxConstraints(minHeight: 48, minWidth: 48),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 4),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: <Widget>[
              CircleAvatar(
                radius: 18,
                backgroundColor: Color.alphaBlend(c.brandSoft, c.surface),
                foregroundColor: c.brandSoftFg,
                child: Text(RzStaffTile.initials(name), style: RzType.footnote),
              ),
              if (!compact) ...<Widget>[
                const SizedBox(width: 8),
                Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(name, style: RzType.body.copyWith(color: c.fg)),
                    Text(
                      role,
                      style: RzType.caption.copyWith(color: c.fgMuted),
                    ),
                  ],
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _onSelected(BuildContext context, String value) async {
    if (value == 'lock') return session.lock();
    if (value == 'clock') return showClockDialog(context);
    if (value == 'unpair') return confirmUnpair(context, session);
    if (value.startsWith('theme:')) {
      final String name = value.substring(6);
      await settings.setThemeMode(
        ThemeMode.values.firstWhere((ThemeMode m) => m.name == name),
      );
    }
  }
}
