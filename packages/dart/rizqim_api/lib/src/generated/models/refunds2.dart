// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'fiscal8.dart';

part 'refunds2.g.dart';

@JsonSerializable()
class Refunds2 {
  const Refunds2({
    required this.uuid,
    required this.orderUuid,
    required this.billUuid,
    required this.paymentUuid,
    required this.amount,
    required this.method,
    required this.reason,
    required this.approvedBy,
    required this.fiscal,
    required this.createdAt,
  });

  factory Refunds2.fromJson(Map<String, Object?> json) =>
      _$Refunds2FromJson(json);

  final String uuid;
  @JsonKey(name: 'order_uuid')
  final String? orderUuid;
  @JsonKey(name: 'bill_uuid')
  final String? billUuid;
  @JsonKey(name: 'payment_uuid')
  final String? paymentUuid;
  final String amount;
  final String method;
  final String reason;
  @JsonKey(name: 'approved_by')
  final int? approvedBy;
  final Fiscal8 fiscal;
  @JsonKey(name: 'created_at')
  final String? createdAt;

  Map<String, Object?> toJson() => _$Refunds2ToJson(this);
}
