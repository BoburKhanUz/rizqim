// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

/// P4-31: where a complaint is on the RIZQIM support side (complaints.support_status, Mijozlar va yordam → Shikoyatlar): new → in_progress (someone took it) → answered (support wrote to the customer) → resolved (closed with a note). The customer ↔ store flow (complaints.status: pending, owner_replied, in_review, closed) runs beside it.
///
@JsonEnum()
enum ComplaintSupportStatus {
  /// The name has been replaced because it contains a keyword. Original name: `new`.
  @JsonValue('new')
  valueNew('new'),
  @JsonValue('in_progress')
  inProgress('in_progress'),
  @JsonValue('answered')
  answered('answered'),
  @JsonValue('resolved')
  resolved('resolved'),

  /// Default value for all unparsed values, allows backward compatibility when adding new values on the backend.
  $unknown(null);

  const ComplaintSupportStatus(this.json);

  factory ComplaintSupportStatus.fromJson(String json) =>
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
  static List<ComplaintSupportStatus> get $valuesDefined =>
      values.where((value) => value != $unknown).toList();
}
