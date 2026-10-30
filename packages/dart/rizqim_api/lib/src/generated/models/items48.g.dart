// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'items48.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Items48 _$Items48FromJson(Map<String, dynamic> json) => Items48(
  productId: (json['product_id'] as num).toInt(),
  productName: json['product_name'] as String?,
  unit: json['unit'] as String,
  reason: json['reason'] as String,
  reasonLabel: json['reason_label'] as String,
  quantity: json['quantity'] as num,
);

Map<String, dynamic> _$Items48ToJson(Items48 instance) => <String, dynamic>{
  'product_id': instance.productId,
  'product_name': instance.productName,
  'unit': instance.unit,
  'reason': instance.reason,
  'reason_label': instance.reasonLabel,
  'quantity': instance.quantity,
};
