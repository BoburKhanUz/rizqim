// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data65.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data65 _$Data65FromJson(Map<String, dynamic> json) => Data65(
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

Map<String, dynamic> _$Data65ToJson(Data65 instance) => <String, dynamic>{
  'id': instance.id,
  'uuid': instance.uuid,
  'product_id': instance.productId,
  'product_name': instance.productName,
  'prices': instance.prices,
  'is_active': instance.isActive,
  'slots': instance.slots,
};
