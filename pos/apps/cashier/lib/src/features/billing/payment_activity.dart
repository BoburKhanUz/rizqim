import 'dart:async';

import 'package:flutter/foundation.dart';

/// M5 S5 (review M1): "an order is mid-payment" on this till. The payment page and the counter's pay flow each hold
/// a [PaymentHold] while money may be moving; "Yangilash" waits while any hold is open ([active]).
///
/// Only a signal for the update notice: nothing in the sale reads it.
class PaymentActivity extends ChangeNotifier {
  int _holds = 0;
  bool _disposed = false;

  /// A payment is in progress (at least one open hold).
  bool get active => _holds > 0;

  /// Opens a hold; release it with [PaymentHold.release] (more than once is harmless).
  PaymentHold begin() {
    _holds++;
    _changed();
    return PaymentHold._(this);
  }

  void _end() {
    if (_holds > 0) _holds--;
    _changed();
  }

  // Holds open and close in initState/dispose of pages (inside a frame): listeners hear it after the frame's work
  void _changed() => scheduleMicrotask(() {
    if (!_disposed) notifyListeners();
  });

  @override
  void dispose() {
    _disposed = true;
    super.dispose();
  }
}

/// One open "payment in progress" mark of [PaymentActivity].
class PaymentHold {
  PaymentHold._(this._owner);

  final PaymentActivity _owner;
  bool _released = false;

  void release() {
    if (_released) return;
    _released = true;
    _owner._end();
  }
}
