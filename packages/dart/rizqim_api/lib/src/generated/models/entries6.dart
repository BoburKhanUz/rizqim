// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'user26.dart';

part 'entries6.g.dart';

@JsonSerializable()
class Entries6 {
  const Entries6({
    required this.uuid,
    required this.type,
    required this.typeLabel,
    required this.amount,
    required this.balanceAfter,
    required this.businessDate,
    required this.billUuid,
    required this.refundUuid,
    required this.tender,
    required this.branchId,
    required this.shiftId,
    required this.user,
    required this.note,
    required this.createdAt,
  });

  factory Entries6.fromJson(Map<String, Object?> json) =>
      _$Entries6FromJson(json);

  final String uuid;
  final String type;
  @JsonKey(name: 'type_label')
  final String typeLabel;
  final String amount;
  @JsonKey(name: 'balance_after')
  final String balanceAfter;
  @JsonKey(name: 'business_date')
  final String? businessDate;
  @JsonKey(name: 'bill_uuid')
  final String? billUuid;
  @JsonKey(name: 'refund_uuid')
  final String? refundUuid;
  final String? tender;
  @JsonKey(name: 'branch_id')
  final int? branchId;
  @JsonKey(name: 'shift_id')
  final int? shiftId;
  final User26? user;
  final String? note;
  @JsonKey(name: 'created_at')
  final String? createdAt;

  Map<String, Object?> toJson() => _$Entries6ToJson(this);
}
