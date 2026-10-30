// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'fiscal17.dart';

part 'data255.g.dart';

@JsonSerializable()
class Data255 {
  const Data255({
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

  factory Data255.fromJson(Map<String, Object?> json) =>
      _$Data255FromJson(json);

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
  final Fiscal17 fiscal;
  @JsonKey(name: 'created_at')
  final String? createdAt;

  Map<String, Object?> toJson() => _$Data255ToJson(this);
}
