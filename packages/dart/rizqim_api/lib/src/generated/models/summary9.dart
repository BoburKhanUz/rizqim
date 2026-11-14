// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'next_due5.dart';

part 'summary9.g.dart';

@JsonSerializable()
class Summary9 {
  const Summary9({
    required this.total,
    required this.paid,
    required this.outstanding,
    required this.overdue,
    required this.overdueAmount,
    required this.overdueCount,
    required this.nextDue,
  });

  factory Summary9.fromJson(Map<String, Object?> json) =>
      _$Summary9FromJson(json);

  final String total;
  final String paid;
  final String outstanding;
  final bool overdue;
  @JsonKey(name: 'overdue_amount')
  final String overdueAmount;
  @JsonKey(name: 'overdue_count')
  final int overdueCount;
  @JsonKey(name: 'next_due')
  final NextDue5? nextDue;

  Map<String, Object?> toJson() => _$Summary9ToJson(this);
}
