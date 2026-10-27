import 'dart:async';

import 'package:flutter/foundation.dart' show defaultTargetPlatform;
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:rizqim_ui/rizqim_ui.dart';

import '../app_config.dart';
import '../pairing_code_formatter.dart';

/// Terminalni ulash: ega yoki menejer panelda (Sozlamalar → Qurilmalar → "Ulash kodini yaratish") olgan 8 belgili kod.
class PairingScreen extends StatefulWidget {
  const PairingScreen({super.key, required this.auth});

  final AuthSession auth;

  @override
  State<PairingScreen> createState() => _PairingScreenState();
}

class _PairingScreenState extends State<PairingScreen> {
  final TextEditingController _controller = TextEditingController();
  bool _busy = false;
  String? _error;

  bool get _valid => PairingCode.normalize(_controller.text) != null;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (_busy || !_valid) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await widget.auth.pairTerminal(
        _controller.text,
        name: 'Oshxona ekrani',
        platform: defaultTargetPlatform.name,
        appVersion: AppConfig.version,
      );
      // AuthGate ekranni o'zi almashtiradi
    } on InvalidPairingCodeException catch (e) {
      _fail(e.message);
    } on ApiException catch (e) {
      final Duration? wait = e.retryAfter;
      _fail(
        wait == null
            ? e.message
            : '${e.message} ${wait.inSeconds} soniyadan keyin urinib ko\'ring.',
      );
    }
  }

  void _fail(String message) {
    if (!mounted) return;
    setState(() {
      _busy = false;
      _error = message;
    });
  }

  @override
  Widget build(BuildContext context) {
    final RzColors c = context.rz;
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 440),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: <Widget>[
                  Text(
                    'Terminalni ulash',
                    style: RzType.title.copyWith(color: c.fg),
                    textAlign: TextAlign.center,
                  ),
                  const SizedBox(height: 8),
                  Text(
                    "Boshqaruv panelida Sozlamalar → Qurilmalar → «Ulash kodini yaratish» orqali 8 belgili kod oling va shu yerga kiriting.",
                    style: RzType.body.copyWith(color: c.fgMuted),
                    textAlign: TextAlign.center,
                  ),
                  // Saqlangan ulanish o'qilmadi (masalan, Linux'da kalitlar xizmati yo'q): nega yana kod so'ralayotgani
                  if (widget.auth.storageError != null) ...<Widget>[
                    const SizedBox(height: 16),
                    const InlineError(
                      message: "Saqlangan ulanishni o'qib bo'lmadi: qurilmaning kalitlar ombori ishlamayapti (Linux'da gnome-keyring yoki KWallet kerak). Kod bilan ulashingiz mumkin, lekin ilova qayta ishga tushirilsa, kod yana so'raladi.",
                    ),
                  ],
                  const SizedBox(height: 24),
                  TextField(
                    controller: _controller,
                    autofocus: true,
                    enabled: !_busy,
                    textAlign: TextAlign.center,
                    textCapitalization: TextCapitalization.characters,
                    autocorrect: false,
                    enableSuggestions: false,
                    keyboardType: TextInputType.visiblePassword,
                    textInputAction: TextInputAction.done,
                    inputFormatters: const <TextInputFormatter>[
                      PairingCodeFormatter(),
                    ],
                    style: RzType.display.copyWith(
                      color: c.fg,
                      letterSpacing: 4,
                    ),
                    decoration: const InputDecoration(
                      hintText: 'ABCD-EFGH',
                      labelText: 'Ulash kodi',
                    ),
                    onChanged: (_) => setState(() => _error = null),
                    onSubmitted: (_) => unawaited(_submit()),
                  ),
                  if (_error != null) ...<Widget>[
                    const SizedBox(height: 12),
                    InlineError(message: _error!),
                  ],
                  const SizedBox(height: 20),
                  RzButton(
                    label: 'Ulash',
                    size: RzButtonSize.lg,
                    fullWidth: true,
                    loading: _busy,
                    onPressed: _valid ? () => unawaited(_submit()) : null,
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
