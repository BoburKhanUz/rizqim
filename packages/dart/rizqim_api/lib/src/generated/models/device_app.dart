// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

/// P6 stage B: which POS app a terminal runs, as the app says when it pairs (POST /devices/pair `app`). A waiter's phone and a cashier till are both `pos` terminals; the branch's default LAN hub is a cashier till, never a waiter's phone (App\Domain\Pos\Lan\BranchLan). Devices paired before this was recorded have none (null). P6 review (S4): the apps also say it on every request (HEADER); GET /pos/bootstrap and GET /pos/lan record it on a terminal that has none, and a terminal saying it runs another app than the cashier's never gets the hub's role.
///
@JsonEnum()
enum DeviceApp {
  @JsonValue('cashier')
  cashier('cashier'),
  @JsonValue('waiter')
  waiter('waiter'),
  @JsonValue('kds')
  kds('kds'),
  @JsonValue('customer_display')
  customerDisplay('customer_display'),

  /// Default value for all unparsed values, allows backward compatibility when adding new values on the backend.
  $unknown(null);

  const DeviceApp(this.json);

  factory DeviceApp.fromJson(String json) =>
      values.firstWhere((e) => e.json == json, orElse: () => $unknown);

  final String? json;
  String toJson() {
    final value = json;
    if (value == null) {
      throw StateError(
        'Cannot convert enum value with null JSON representation to String. '
        'This usually happens for \$unknown or @JsonValue(null) entries.',
      );
    }
    return value as String;
  }

  @override
  String toString() => json?.toString() ?? super.toString();

  /// Returns all defined enum values excluding the $unknown value.
  static List<DeviceApp> get $valuesDefined =>
      values.where((value) => value != $unknown).toList();
}
