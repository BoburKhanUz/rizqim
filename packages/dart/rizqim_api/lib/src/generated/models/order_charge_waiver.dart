// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'order.dart';

part 'order_charge_waiver.g.dart';

@JsonSerializable()
class OrderChargeWaiver {
  const OrderChargeWaiver({
    required this.id,
    required this.orderId,
    required this.charge,
    required this.reason,
    required this.waivedBy,
    required this.approvedBy,
    required this.createdAt,
    required this.updatedAt,
    this.order,
  });

  factory OrderChargeWaiver.fromJson(Map<String, Object?> json) =>
      _$OrderChargeWaiverFromJson(json);

  final int id;
  @JsonKey(name: 'order_id')
  final int orderId;
  final String charge;
  final String reason;
  @JsonKey(name: 'waived_by')
  final dynamic waivedBy;
  @JsonKey(name: 'approved_by')
  final dynamic approvedBy;
  @JsonKey(name: 'created_at')
  final DateTime? createdAt;
  @JsonKey(name: 'updated_at')
  final DateTime? updatedAt;
  final Order? order;

  Map<String, Object?> toJson() => _$OrderChargeWaiverToJson(this);
}
