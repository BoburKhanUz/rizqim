// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

/// P6-41/P6-42: who takes the money of a dynamic QR shown at the till. Click and Payme confirm it through their webhooks into the store's own merchant account; UzQR (the national interbank QR) through the store's UzQR gateway (UzQrGateway, P3-32-dependent; a sandbox until then).
///
@JsonEnum()
enum QrProvider {
  @JsonValue('click')
  click('click'),
  @JsonValue('payme')
  payme('payme'),
  @JsonValue('uzqr')
  uzqr('uzqr'),

  /// Default value for all unparsed values, allows backward compatibility when adding new values on the backend.
  $unknown(null);

  const QrProvider(this.json);

  factory QrProvider.fromJson(String json) =>
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
  static List<QrProvider> get $valuesDefined =>
      values.where((value) => value != $unknown).toList();
}
