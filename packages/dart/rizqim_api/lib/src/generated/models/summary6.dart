// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'next_due2.dart';

part 'summary6.g.dart';

@JsonSerializable()
class Summary6 {
  const Summary6({
    required this.total,
    required this.paid,
    required this.outstanding,
    required this.overdue,
    required this.overdueAmount,
    required this.overdueCount,
    required this.nextDue,
  });

  factory Summary6.fromJson(Map<String, Object?> json) =>
      _$Summary6FromJson(json);

  final String total;
  final String paid;
  final String outstanding;
  final bool overdue;
  @JsonKey(name: 'overdue_amount')
  final String overdueAmount;
  @JsonKey(name: 'overdue_count')
  final int overdueCount;
  @JsonKey(name: 'next_due')
  final NextDue2? nextDue;

  Map<String, Object?> toJson() => _$Summary6ToJson(this);
}
