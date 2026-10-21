// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

/// Where the booking came from (choyxonas book by phone; Telegram and Instagram messages; a guest booking in person).
///
@JsonEnum()
enum BookingSource {
  @JsonValue('phone')
  phone('phone'),
  @JsonValue('telegram')
  telegram('telegram'),
  @JsonValue('instagram')
  instagram('instagram'),
  @JsonValue('walk_in')
  walkIn('walk_in'),
  @JsonValue('other')
  other('other'),

  /// Default value for all unparsed values, allows backward compatibility when adding new values on the backend.
  $unknown(null);

  const BookingSource(this.json);

  factory BookingSource.fromJson(String json) =>
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
  static List<BookingSource> get $valuesDefined =>
      values.where((value) => value != $unknown).toList();
}
