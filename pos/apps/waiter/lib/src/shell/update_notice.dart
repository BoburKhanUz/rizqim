import 'dart:async';

import 'package:flutter/material.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_ui/pos_ui.dart' show PosUpdateBanner, PosUpdateTexts;
import 'package:rizqim_ui/rizqim_ui.dart';

import '../services.dart';

// M5 S5: update notices of the waiter's phone (docs/scale/M5_DESIGN.md 6). Only notices: nothing here is on the path
// of an order, a round, a bill request or the sign-in, and every notice can be closed.

/// `yyyy-MM-dd` of the phone's local day: the key of "dismissed today".
String updateDayKey(DateTime time) {
  final DateTime t = time.toLocal();
  String two(int v) => v.toString().padLeft(2, '0');
  return '${t.year}-${two(t.month)}-${two(t.day)}';
}

/// May "Yangilash" open the link now? The waiter's phone has no offline queue and takes no payment (the bill is paid
/// at the till), so there is nothing to wait for: always open. Kept as one function so the rule is in one place.
PosUpdateGate updateGateOf(WaiterServices services) =>
    canUpdateNow(pendingOperations: 0, paymentInProgress: false);

/// Opens the download link of [release] in the system browser.
void openUpdate(WaiterServices services, PosAppRelease release) {
  final Uri? url = Uri.tryParse(release.url);
  if (url != null) unawaited(services.linkOpener(url));
}

/// The banner at the top of the home screen. `update_required`: red and permanent. `update_available`: dismissible
/// and shown at most once a day. The home screen is never an open order, so nothing is covered.
class UpdateBannerSlot extends StatefulWidget {
  const UpdateBannerSlot({super.key, required this.services});

  final WaiterServices services;

  @override
  State<UpdateBannerSlot> createState() => _UpdateBannerSlotState();
}

class _UpdateBannerSlotState extends State<UpdateBannerSlot> {
  @override
  void initState() {
    super.initState();
    unawaited(widget.services.updatePrefs.load());
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
          listenable: widget.services.updatePrefs,
          builder: (BuildContext context, _) {
            final String today = updateDayKey(widget.services.now());
            if (!notice.isRequired &&
                widget.services.updatePrefs.dismissedOn == today) {
              return const SizedBox.shrink();
            }
            return PosUpdateBanner(
              release: notice,
              gate: updateGateOf(widget.services),
              onUpdate: () => openUpdate(widget.services, notice),
              onDismiss: notice.isRequired
                  ? null
                  : () => unawaited(widget.services.updatePrefs.dismiss(today)),
            );
          },
        );
      },
    );
  }
}

/// The full notice for `update_required` at app start. "Davom etish" always closes it; so does a tap outside it and
/// the back button. Nothing happens if there is no required notice, if another notice is open, or if [context] is gone.
Future<void> showUpdateRequiredNotice(
  BuildContext context,
  WaiterServices services,
) async {
  final PosAppRelease? r = services.releases?.notice;
  if (r == null || !r.isRequired || services.noticeOpen || !context.mounted) {
    return;
  }
  services.noticeOpen = true;
  try {
    await showDialog<void>(
      context: context,
      builder: (BuildContext dialog) => AlertDialog(
        key: const ValueKey<String>('update-required-notice'),
        title: const Text(PosUpdateTexts.requiredTitle),
        content: Text(PosUpdateTexts.required(r)),
        actions: <Widget>[
          RzButton(
            label: PosUpdateTexts.update,
            variant: RzButtonVariant.secondary,
            onPressed: updateGateOf(services).allowed
                ? () => openUpdate(services, r)
                : null,
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
