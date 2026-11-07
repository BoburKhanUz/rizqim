import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:pos_lan/pos_lan.dart';
import 'package:rizqim_core/rizqim_core.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../features/billing/cashier_ui.dart';
import 'cashier_lan_hub.dart';

/// Runs a program and returns its result (tests pass a fake).
typedef FirewallProcessRunner = Future<ProcessResult> Function(
  String executable,
  List<String> arguments,
);

/// P6 Windows: the inbound firewall rule the till's LAN hub needs (TCP for the hub, UDP for the beacon heard by
/// devices on other Windows PCs). Windows Defender Firewall drops inbound connections to a program that has no
/// rule, and if someone pressed "Cancel" on Windows' own "allow access" prompt it adds a *block* rule for the
/// program, which wins over any allow rule. [allow] fixes both in one elevated step (one UAC prompt):
///
/// 1. removes this app's inbound rules (the block rule from that prompt among them) and the old rules of this name;
/// 2. adds an allow rule for TCP and UDP on the hub port and one for the program itself (so Windows does not ask
///    again).
///
/// The same commands, for an administrator to run by hand, are in [manualCommands] (docs/HARDWARE_TEST.md 5a,
/// docs/guides/uskunalar.md).
class WindowsFirewall {
  WindowsFirewall({FirewallProcessRunner? run, bool? windows, this.program})
    : _run = run ?? Process.run,
      supported = windows ?? (!kIsWeb && Platform.isWindows);

  /// The rule name shown in "Windows Defender Firewall → Advanced settings → Inbound rules".
  static const String ruleName = 'Rizqim POS lokal tarmoq';

  final FirewallProcessRunner _run;

  /// Windows only; elsewhere every call is a no-op.
  final bool supported;

  /// The program allowed and cleared of block rules (`null`: this process, [Platform.resolvedExecutable]).
  final String? program;

  String get _program => program ?? Platform.resolvedExecutable;

  /// The rule is there (`null`: not Windows or `netsh` could not be run — say nothing then).
  Future<bool?> hasRule() async {
    if (!supported) return null;
    try {
      final ProcessResult r = await _run('netsh', <String>[
        'advfirewall',
        'firewall',
        'show',
        'rule',
        'name=$ruleName',
      ]);
      // netsh: 0 with the rule listed, 1 with "No rules match the specified criteria"
      return r.exitCode == 0;
    } on Object {
      return null;
    }
  }

  /// Adds the rules after the administrator confirms the UAC prompt; `true` when the rule is there afterwards.
  Future<bool> allow(int port) async {
    if (!supported) return false;
    try {
      await _run('powershell.exe', <String>[
        '-NoProfile',
        '-NonInteractive',
        '-ExecutionPolicy',
        'Bypass',
        '-EncodedCommand',
        encodedCommand(elevatedScript(port, _program)),
      ]);
    } on Object {
      return false;
    }
    return await hasRule() ?? false;
  }

  /// The netsh commands, joined for `cmd /c`.
  static List<String> netshCommands(int port, String? program) => <String>[
    if (program != null)
      'netsh advfirewall firewall delete rule name=all dir=in program="$program"',
    'netsh advfirewall firewall delete rule name="$ruleName"',
    for (final String protocol in <String>['TCP', 'UDP'])
      'netsh advfirewall firewall add rule name="$ruleName" dir=in action=allow protocol=$protocol '
          'localport=$port profile=any',
    if (program != null)
      'netsh advfirewall firewall add rule name="$ruleName" dir=in action=allow program="$program" '
          'enable=yes profile=any',
  ];

  /// What an administrator types by hand (an elevated PowerShell or Command Prompt).
  static List<String> manualCommands(int port) => <String>[
    for (final String c in netshCommands(port, null))
      if (!c.contains(' delete ')) c,
  ];

  /// The PowerShell script that starts `cmd /c <netsh ...>` elevated and waits for it.
  static String elevatedScript(int port, String? program) {
    final String line = netshCommands(port, program)
        .map((String c) => c.contains(' delete ') ? '$c >nul 2>&1' : c)
        .join(' & ');
    // PowerShell single-quoted string: a quote is doubled
    final String quoted = "'/c ${line.replaceAll("'", "''")}'";
    return 'Start-Process -FilePath cmd.exe -ArgumentList $quoted -Verb RunAs -Wait -WindowStyle Hidden';
  }

  /// `-EncodedCommand`: base64 of the UTF-16LE script, so no quoting survives the command line.
  static String encodedCommand(String script) {
    final List<int> bytes = <int>[];
    for (final int unit in script.codeUnits) {
      bytes
        ..add(unit & 0xFF)
        ..add(unit >> 8);
    }
    return base64.encode(bytes);
  }
}

/// "No device can reach the hub" help in the hub's device list (and the settings' "Lokal tarmoq" section): the same
/// Wi-Fi, the manual address and, on Windows, the firewall rule with a one-tap fix. Shown only while no device is
/// connected — a till without waiters or kitchen screens is normal, so nothing nags elsewhere.
class LanReachHint extends StatefulWidget {
  const LanReachHint({super.key, required this.hub, this.firewall});

  final CashierLanHub hub;

  /// `null`: the real Windows firewall (a no-op outside Windows).
  final WindowsFirewall? firewall;

  /// The bridge's default port (pos_lan README; the branch can change it).
  static const int defaultHubPort = 18480;

  /// The hub port from its link (the default when the hub is not running yet).
  static int portOf(CashierLanHub hub) {
    final String? link = hub.link;
    return (link == null ? null : LanEndpoint.parse(link)?.port) ??
        defaultHubPort;
  }

  @override
  State<LanReachHint> createState() => _LanReachHintState();
}

class _LanReachHintState extends State<LanReachHint> {
  late final WindowsFirewall _firewall = widget.firewall ?? WindowsFirewall();
  bool? _hasRule;
  bool _busy = false;
  String? _result;

  @override
  void initState() {
    super.initState();
    unawaited(_check());
  }

  Future<void> _check() async {
    final bool? has = await _firewall.hasRule();
    if (mounted) setState(() => _hasRule = has);
  }

  Future<void> _allow() async {
    setState(() {
      _busy = true;
      _result = null;
    });
    final bool ok = await _firewall.allow(LanReachHint.portOf(widget.hub));
    if (!mounted) return;
    setState(() {
      _busy = false;
      _hasRule = ok;
      _result = ok
          ? "Ruxsat berildi. Qurilmalarda qayta ulanib ko'ring."
          : "Ruxsat berilmadi. Windows administrator nomidan tasdiqlash kerak (yoki qo'llanmadagi buyruqni "
                "administrator bajarsin).";
    });
  }

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    final int port = LanReachHint.portOf(widget.hub);
    return Column(
      key: const ValueKey<String>('lan-reach-hint'),
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: <Widget>[
        Text(
          "Qurilma markazni topa olmasa: u kassa bilan bitta Wi-Fi yoki routerda bo'lsin (routerda «AP isolation» "
          "o'chiq), qurilma sozlamalarida pastdagi manzilni qo'lda kiriting.",
          style: RzType.footnote.copyWith(color: c.fgMuted),
        ),
        if (_firewall.supported && _hasRule == false) ...<Widget>[
          const SizedBox(height: 8),
          NoticeBanner(
            key: const ValueKey<String>('lan-firewall-missing'),
            message:
                "Windows xavfsizlik devori (firewall) $port-portni to'sib qo'yishi mumkin: boshqa qurilmalar bu "
                "kassaga ulana olmaydi. «Ruxsat berish» ni bosing va Windows so'raganda «Ha» ni tanlang.",
            tone: StatusTone.warning,
          ),
          const SizedBox(height: 8),
          RzButton(
            key: const ValueKey<String>('lan-firewall-allow'),
            label: 'Ruxsat berish',
            icon: Icons.shield_outlined,
            variant: RzButtonVariant.secondary,
            loading: _busy,
            onPressed: _busy ? null : () => unawaited(_allow()),
          ),
        ],
        if (_firewall.supported &&
            _hasRule == true &&
            _result == null) ...<Widget>[
          const SizedBox(height: 8),
          Text(
            'Windows xavfsizlik devorida ruxsat bor («${WindowsFirewall.ruleName}»).',
            key: const ValueKey<String>('lan-firewall-ok'),
            style: RzType.footnote.copyWith(color: c.fgMuted),
          ),
        ],
        if (_result != null) ...<Widget>[
          const SizedBox(height: 8),
          NoticeBanner(
            message: _result!,
            tone: _hasRule == true ? StatusTone.success : StatusTone.danger,
          ),
        ],
      ],
    );
  }
}
