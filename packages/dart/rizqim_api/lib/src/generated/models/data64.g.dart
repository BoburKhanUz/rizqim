// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data64.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data64 _$Data64FromJson(Map<String, dynamic> json) => Data64(
  id: (json['id'] as num).toInt(),
  uuid: json['uuid'] as String,
  productId: (json['product_id'] as num).toInt(),
  productName: json['product_name'] as String?,
  prices: (json['prices'] as List<dynamic>).map((e) => e as String).toList(),
  isActive: json['is_active'] as bool,
  slots: (json['slots'] as List<dynamic>)
      .map((e) => Slots3.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data64ToJson(Data64 instance) => <String, dynamic>{
  'id': instance.id,
  'uuid': instance.uuid,
  'product_id': instance.productId,
  'product_name': instance.productName,
  'prices': instance.prices,
  'is_active': instance.isActive,
  'slots': instance.slots,
};
