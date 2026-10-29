// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'fiscal16.dart';

part 'data198.g.dart';

@JsonSerializable()
class Data198 {
  const Data198({
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

  factory Data198.fromJson(Map<String, Object?> json) =>
      _$Data198FromJson(json);

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
  final Fiscal16 fiscal;
  @JsonKey(name: 'created_at')
  final String? createdAt;

  Map<String, Object?> toJson() => _$Data198ToJson(this);
}
