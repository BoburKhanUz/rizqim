// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'next_due.dart';

part 'summary5.g.dart';

@JsonSerializable()
class Summary5 {
  const Summary5({
    required this.total,
    required this.paid,
    required this.outstanding,
    required this.overdue,
    required this.overdueAmount,
    required this.overdueCount,
    required this.nextDue,
  });

  factory Summary5.fromJson(Map<String, Object?> json) =>
      _$Summary5FromJson(json);

  final String total;
  final String paid;
  final String outstanding;
  final bool overdue;
  @JsonKey(name: 'overdue_amount')
  final String overdueAmount;
  @JsonKey(name: 'overdue_count')
  final int overdueCount;
  @JsonKey(name: 'next_due')
  final NextDue? nextDue;

  Map<String, Object?> toJson() => _$Summary5ToJson(this);
}
