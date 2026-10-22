import 'dart:async';

import 'package:flutter/material.dart';
import 'package:pos_data/pos_data.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:rizqim_core/rizqim_core.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import 'waiter_scope.dart';

/// Menejer tasdig'i oynasi: menejer (yoki ega) ismini tanlab o'z PIN'ini teradi → `POST /pos/approvals` → bir martalik
/// token (60 soniya, faqat shu ruxsat va shu terminal uchun). Yopilsa — `null`.
///
/// Odatda to'g'ridan-to'g'ri emas, [withApproval] orqali: amal 403 `approval_required` olsa oyna o'zi ochiladi.
Future<ApprovalToken?> showManagerApproval(
  BuildContext context, {
  required String permission,
  String? reason,
  ApprovalService? approvals,
  DateTime Function()? clock,
}) {
  final ApprovalService service =
      approvals ?? WaiterScope.of(context).data.approvals;
  return showDialog<ApprovalToken>(
    context: context,
    barrierDismissible: false,
    builder: (BuildContext context) => ManagerApprovalDialog(
      approvals: service,
      permission: permission,
      reason: reason,
      clock: clock,
    ),
  );
}

/// Amalni bajaradi; server menejer tasdig'ini talab qilsa ([PosApprovalRequired]) oynani ochadi va amalni token bilan
/// qayta yuboradi ([runWithApproval]: yangi `Idempotency-Key`, token eskirsa yana so'raydi). Oyna yopilsa —
/// [PosApprovalCancelled].
///
/// ```dart
/// final order = await withApproval(context, (token) =>
///     scope.data.orders.voidItem(uuid, itemId, version: v, reason: reason, approvalToken: token));
/// ```
Future<T> withApproval<T>(
  BuildContext context,
  Future<T> Function(String? approvalToken) action, {
  ApprovalService? approvals,
}) {
  final ApprovalService service =
      approvals ?? WaiterScope.of(context).data.approvals;
  return runWithApproval<T>(
    action,
    approve: (String permission, PosException cause) async {
      if (!context.mounted) return null;
      return showManagerApproval(
        context,
        permission: permission,
        approvals: service,
        reason: cause is PosApprovalRejected ? cause.message : null,
      );
    },
  );
}

class ManagerApprovalDialog extends StatefulWidget {
  const ManagerApprovalDialog({
    super.key,
    required this.approvals,
    required this.permission,
    this.reason,
    this.clock,
  });

  final ApprovalService approvals;
  final String permission;

  /// Qo'shimcha izoh (masalan, oldingi tasdiq muddati o'tgani).
  final String? reason;
  final DateTime Function()? clock;

  @override
  State<ManagerApprovalDialog> createState() => _ManagerApprovalDialogState();
}

class _ManagerApprovalDialogState extends State<ManagerApprovalDialog> {
  List<StaffMember>? _approvers;
  ApiException? _loadError;
  StaffMember? _selected;
  PinFailure? _failure;
  DateTime? _lockedUntil;
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
    setState(() {
      _approvers = null;
      _loadError = null;
    });
    try {
      final List<StaffMember> list = await widget.approvals.approvers();
      if (!mounted) return;
      setState(() {
        _approvers = list;
        if (list.length == 1) _selected = list.single;
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _loadError = e);
    }
  }

  Duration? get _lockLeft {
    final DateTime? until = _lockedUntil;
    if (until == null) return null;
    final Duration left = until.difference(_now());
    return left > Duration.zero ? left : null;
  }

  Future<bool> _submit(String pin) async {
    final StaffMember? member = _selected;
    if (member == null || _lockLeft != null) return false;
    try {
      final ApprovalToken token = await widget.approvals.request(
        permission: widget.permission,
        userId: member.id,
        pin: pin,
      );
      if (mounted) Navigator.of(context).pop(token);
      return true;
    } on ApiException catch (e) {
      final PinFailure failure = approvalFailure(e);
      if (!mounted) return false;
      setState(() {
        _failure = failure;
        if (failure.lockedFor != null) {
          _lockedUntil = _now().add(failure.lockedFor!);
          _ticker?.cancel();
          _ticker = Timer.periodic(const Duration(seconds: 1), (Timer t) {
            if (!mounted || _lockLeft == null) {
              t.cancel();
              _lockedUntil = null;
            }
            if (mounted) setState(() {});
          });
        }
      });
      return false;
    }
  }

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    return AlertDialog(
      title: const Text("Menejer tasdig'i"),
      content: SizedBox(
        width: 380,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              Text(
                PosPermissions.label(widget.permission),
                style: RzType.headline.copyWith(color: c.fg),
              ),
              const SizedBox(height: 4),
              Text(
                widget.reason ??
                    "Bu amalni menejer yoki ega o'z PIN'i bilan tasdiqlaydi.",
                style: RzType.body.copyWith(color: c.fgMuted),
              ),
              const SizedBox(height: 16),
              _body(context),
            ],
          ),
        ),
      ),
      actions: <Widget>[
        RzButton(
          label: 'Bekor qilish',
          variant: RzButtonVariant.ghost,
          onPressed: () => Navigator.of(context).pop(),
        ),
      ],
    );
  }

  Widget _body(BuildContext context) {
    final RzColors c = context.rz;
    final ApiException? error = _loadError;
    if (error != null) {
      return InlineError(
        message: error.isNetworkError
            ? "Aloqa yo'q: menejerlar ro'yxatini yuklab bo'lmadi."
            : error.message,
        onRetry: () => unawaited(_load()),
      );
    }
    final List<StaffMember>? approvers = _approvers;
    if (approvers == null) {
      return const Padding(
        padding: EdgeInsets.all(24),
        child: Center(child: CircularProgressIndicator()),
      );
    }
    if (approvers.isEmpty) {
      return Text(
        "Bu terminalda PIN o'rnatilgan menejer yo'q. Boshqaruv panelida Xodimlar bo'limidan menejerga PIN o'rnating.",
        style: RzType.body.copyWith(color: c.fg),
      );
    }
    final Duration? lockLeft = _lockLeft;
    final PinFailure? failure = _failure;
    final String? message = lockLeft != null
        ? '${failure?.message ?? "Kirish vaqtincha bloklandi."} ${formatDuration(lockLeft)} dan keyin qayta urinib ko\'ring.'
        : (failure != null && !failure.isLocked ? failure.message : null);
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        if (approvers.length > 1) ...<Widget>[
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: <Widget>[
              for (final StaffMember m in approvers)
                ChoiceChip(
                  label: Text(m.name),
                  avatar: const Icon(Icons.verified_user_outlined, size: 18),
                  selected: _selected?.id == m.id,
                  materialTapTargetSize: MaterialTapTargetSize.padded,
                  onSelected: (_) => setState(() {
                    _selected = m;
                    _failure = null;
                  }),
                ),
            ],
          ),
          const SizedBox(height: 12),
        ] else
          Text(
            '${approvers.single.name} · ${StaffRole.labelOf(approvers.single.role)}',
            textAlign: TextAlign.center,
            style: RzType.headline.copyWith(color: c.fg),
          ),
        const SizedBox(height: 8),
        if (_selected == null)
          Padding(
            padding: const EdgeInsets.all(16),
            child: Text(
              'Kim tasdiqlaydi? Ismni tanlang.',
              textAlign: TextAlign.center,
              style: RzType.body.copyWith(color: c.fgMuted),
            ),
          )
        else
          Center(
            child: RzPinEntry(
              key: ValueKey<int>(_selected!.id),
              keySize: 60,
              enabled: lockLeft == null,
              error: failure != null,
              onSubmit: _submit,
              onChanged: () {
                if (_failure != null && lockLeft == null) {
                  setState(() => _failure = null);
                }
              },
            ),
          ),
        if (message != null) ...<Widget>[
          const SizedBox(height: 12),
          Semantics(
            liveRegion: true,
            child: Text(
              message,
              textAlign: TextAlign.center,
              style: RzType.body.copyWith(color: c.dangerSoftFg),
            ),
          ),
        ],
      ],
    );
  }
}
