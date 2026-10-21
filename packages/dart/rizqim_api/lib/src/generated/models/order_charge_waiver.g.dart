// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'order_charge_waiver.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

OrderChargeWaiver _$OrderChargeWaiverFromJson(Map<String, dynamic> json) =>
    OrderChargeWaiver(
      id: (json['id'] as num).toInt(),
      orderId: (json['order_id'] as num).toInt(),
      charge: json['charge'] as String,
      reason: json['reason'] as String,
      waivedBy: json['waived_by'],
      approvedBy: json['approved_by'],
      createdAt: json['created_at'] == null
          ? null
          : DateTime.parse(json['created_at'] as String),
      updatedAt: json['updated_at'] == null
          ? null
          : DateTime.parse(json['updated_at'] as String),
      order: json['order'] == null
          ? null
          : Order.fromJson(json['order'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$OrderChargeWaiverToJson(OrderChargeWaiver instance) =>
    <String, dynamic>{
      'id': instance.id,
      'order_id': instance.orderId,
      'charge': instance.charge,
      'reason': instance.reason,
      'waived_by': instance.waivedBy,
      'approved_by': instance.approvedBy,
      'created_at': instance.createdAt?.toIso8601String(),
      'updated_at': instance.updatedAt?.toIso8601String(),
      'order': instance.order,
    };
