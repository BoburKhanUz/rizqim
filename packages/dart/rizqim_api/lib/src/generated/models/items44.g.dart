// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'items44.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Items44 _$Items44FromJson(Map<String, dynamic> json) => Items44(
  productId: (json['product_id'] as num).toInt(),
  productName: json['product_name'] as String?,
  unit: json['unit'] as String,
  reason: json['reason'] as String,
  reasonLabel: json['reason_label'] as String,
  quantity: json['quantity'] as num,
);

Map<String, dynamic> _$Items44ToJson(Items44 instance) => <String, dynamic>{
  'product_id': instance.productId,
  'product_name': instance.productName,
  'unit': instance.unit,
  'reason': instance.reason,
  'reason_label': instance.reasonLabel,
  'quantity': instance.quantity,
};
