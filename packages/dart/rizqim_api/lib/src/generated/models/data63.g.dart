// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data63.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data63 _$Data63FromJson(Map<String, dynamic> json) => Data63(
  id: (json['id'] as num).toInt(),
  uuid: json['uuid'] as String,
  productId: (json['product_id'] as num).toInt(),
  productName: json['product_name'] as String?,
  prices: (json['prices'] as List<dynamic>).map((e) => e as String).toList(),
  isActive: json['is_active'] as bool,
  slots: (json['slots'] as List<dynamic>)
      .map((e) => Slots2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data63ToJson(Data63 instance) => <String, dynamic>{
  'id': instance.id,
  'uuid': instance.uuid,
  'product_id': instance.productId,
  'product_name': instance.productName,
  'prices': instance.prices,
  'is_active': instance.isActive,
  'slots': instance.slots,
};
