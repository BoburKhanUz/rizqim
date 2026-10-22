// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'items24.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Items24 _$Items24FromJson(Map<String, dynamic> json) => Items24(
  orderItemId: (json['order_item_id'] as num).toInt(),
  quantity: json['quantity'] as num,
);

Map<String, dynamic> _$Items24ToJson(Items24 instance) => <String, dynamic>{
  'order_item_id': instance.orderItemId,
  'quantity': instance.quantity,
};
