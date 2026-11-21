import 'dart:async';

import 'package:flutter/material.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_ui/pos_ui.dart' show PosUpdateBanner, PosUpdateTexts;
import 'package:rizqim_ui/rizqim_ui.dart';

import '../features/settings/cashier_prefs.dart';
import '../services.dart';

// M5 S5: update notices of the cashier till (docs/scale/M5_DESIGN.md 6). Only notices: nothing here is on the path of
// an order, a payment, a shift, a receipt, a refund or the offline queue, and every notice can be closed.

/// `yyyy-MM-dd` of the till's local day: the key of "dismissed today".
String updateDayKey(DateTime time) {
  final DateTime t = time.toLocal();
  String two(int v) => v.toString().padLeft(2, '0');
  return '${t.year}-${two(t.month)}-${two(t.day)}';
}

/// May "Yangilash" open the link now? The offline queue must be empty (orphans included) and no payment may be in
/// progress ([CashierServices.payments]: the payment page or the counter's pay flow).
PosUpdateGate updateGateOf(CashierServices services) => canUpdateNow(
  pendingOperations: pendingOperationsOf(services.data.offline),
  paymentInProgress: services.payments.active,
);

/// Opens the download link of [release] in the system browser.
void openUpdate(CashierServices services, PosAppRelease release) {
  final Uri? url = Uri.tryParse(release.url);
  if (url != null) unawaited(services.linkOpener(url));
}

/// Rebuilds with the current [PosUpdateGate] whenever the offline queue or the payment signal changes.
class UpdateGateBuilder extends StatelessWidget {
  const UpdateGateBuilder({
    super.key,
    required this.services,
    required this.builder,
  });

  final CashierServices services;
  final Widget Function(BuildContext context, PosUpdateGate gate) builder;

  @override
  Widget build(BuildContext context) {
    final PosOffline? offline = services.data.offline;
    return ListenableBuilder(
      listenable: services.payments,
      builder: (BuildContext context, _) => offline == null
          ? builder(context, updateGateOf(services))
          : StreamBuilder<OfflineStatus>(
              stream: offline.changes,
              builder: (BuildContext context, _) =>
                  builder(context, updateGateOf(services)),
            ),
    );
  }
}

/// The banner at the top of the home screen. `update_required`: red and permanent. `update_available`: dismissible,
/// shown at most once a day, and only when [showAvailable] (the hall map, never a screen with an open order).
class UpdateBannerSlot extends StatefulWidget {
  const UpdateBannerSlot({
    super.key,
    required this.services,
    required this.showAvailable,
  });

  final CashierServices services;
  final bool showAvailable;

  @override
  State<UpdateBannerSlot> createState() => _UpdateBannerSlotState();
}

class _UpdateBannerSlotState extends State<UpdateBannerSlot> {
  @override
  void initState() {
    super.initState();
    unawaited(CashierPrefs.instance.load());
  }

  @override
  Widget build(BuildContext context) {
    final PosReleaseRepository? releases = widget.services.releases;
    if (releases == null) return const SizedBox.shrink();
    return StreamBuilder<PosAppRelease?>(
      stream: releases.changes,
      initialData: releases.release,
      builder: (BuildContext context, _) {
        final PosAppRelease? notice = releases.notice;
        if (notice == null) return const SizedBox.shrink();
        return ListenableBuilder(
          listenable: CashierPrefs.instance,
          builder: (BuildContext context, _) {
            final String today = updateDayKey(widget.services.now());
            if (!notice.isRequired &&
                (!widget.showAvailable ||
                    CashierPrefs.instance.updateDismissedOn == today)) {
              return const SizedBox.shrink();
            }
            return UpdateGateBuilder(
              services: widget.services,
              builder: (BuildContext context, PosUpdateGate gate) =>
                  PosUpdateBanner(
                    release: notice,
                    gate: gate,
                    onUpdate: () => openUpdate(widget.services, notice),
                    onDismiss: notice.isRequired
                        ? null
                        : () => unawaited(
                            CashierPrefs.instance.setUpdateDismissedOn(today),
                          ),
                  ),
            );
          },
        );
      },
    );
  }
}

/// A quiet line in the settings page: what is new (or required) and "Yangilash". Nothing when there is no notice.
class UpdateSettingsLine extends StatelessWidget {
  const UpdateSettingsLine({super.key, required this.services});

  final CashierServices services;

  @override
  Widget build(BuildContext context) {
    final PosReleaseRepository? releases = services.releases;
    if (releases == null) return const SizedBox.shrink();
    return StreamBuilder<PosAppRelease?>(
      stream: releases.changes,
      initialData: releases.release,
      builder: (BuildContext context, _) {
        final PosAppRelease? r = releases.notice;
        if (r == null) return const SizedBox.shrink();
        final RzColors c = context.rz;
        return Padding(
          padding: const EdgeInsets.only(bottom: 16),
          child: UpdateGateBuilder(
            services: services,
            builder: (BuildContext context, PosUpdateGate gate) => Row(
              key: const ValueKey<String>('update-settings-line'),
              children: <Widget>[
                Icon(
                  r.isRequired
                      ? Icons.system_update_rounded
                      : Icons.info_outline_rounded,
                  size: 18,
                  color: r.isRequired ? c.danger : c.fgMuted,
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    <String>[
                      r.isRequired
                          ? PosUpdateTexts.required(r)
                          : PosUpdateTexts.available(r),
                      if (!gate.allowed && gate.reason != null) gate.reason!,
                    ].join(' '),
                    style: RzType.footnote.copyWith(color: c.fgMuted),
                  ),
                ),
                TextButton(
                  onPressed: gate.allowed
                      ? () => openUpdate(services, r)
                      : null,
                  child: const Text(PosUpdateTexts.update),
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}

/// The full notice for `update_required` (app start, after a shift is closed, the shift-open screen). "Davom etish"
/// always closes it; so does a tap outside it and the back button. Nothing happens if there is no required notice, if
/// another notice is already open, if [context] is gone or if its route is not the top one (a payment or another page
/// over it: review M1).
Future<void> showUpdateRequiredNotice(
  BuildContext context,
  CashierServices services,
) async {
  if (!updateNoticeCanOpen(context, services)) return;
  final PosAppRelease r = services.releases!.notice!;
  services.noticeOpen = true;
  try {
    await showDialog<void>(
      context: context,
      builder: (BuildContext dialog) => AlertDialog(
        key: const ValueKey<String>('update-required-notice'),
        title: const Text(PosUpdateTexts.requiredTitle),
        content: UpdateGateBuilder(
          services: services,
          builder: (BuildContext context, PosUpdateGate gate) => Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Text(PosUpdateTexts.required(r)),
              if (!gate.allowed && gate.reason != null) ...<Widget>[
                const SizedBox(height: 8),
                Text(
                  gate.reason!,
                  style: const TextStyle(fontWeight: FontWeight.w600),
                ),
              ],
            ],
          ),
        ),
        actions: <Widget>[
          UpdateGateBuilder(
            services: services,
            builder: (BuildContext context, PosUpdateGate gate) => RzButton(
              label: PosUpdateTexts.update,
              variant: RzButtonVariant.secondary,
              onPressed: gate.allowed ? () => openUpdate(services, r) : null,
            ),
          ),
          RzButton(
            key: const ValueKey<String>('update-notice-continue'),
            label: 'Davom etish',
            onPressed: () => Navigator.of(dialog).pop(),
          ),
        ],
      ),
    );
  } finally {
    services.noticeOpen = false;
  }
}

/// [showUpdateRequiredNotice] would open now: a required notice, none open, and [context]'s route is the top one.
bool updateNoticeCanOpen(BuildContext context, CashierServices services) =>
    context.mounted &&
    services.releases?.notice?.isRequired == true &&
    !services.noticeOpen &&
    ModalRoute.isCurrentOf(context) != false;
