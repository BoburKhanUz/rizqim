// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data124.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data124 _$Data124FromJson(Map<String, dynamic> json) => Data124(
  id: (json['id'] as num).toInt(),
  uuid: json['uuid'] as String,
  productId: (json['product_id'] as num).toInt(),
  productName: json['product_name'] as String,
  prices: (json['prices'] as List<dynamic>).map((e) => e as String).toList(),
  isActive: json['is_active'] as bool,
  parts: (json['parts'] as List<dynamic>)
      .map((e) => Parts4.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data124ToJson(Data124 instance) => <String, dynamic>{
  'id': instance.id,
  'uuid': instance.uuid,
  'product_id': instance.productId,
  'product_name': instance.productName,
  'prices': instance.prices,
  'is_active': instance.isActive,
  'parts': instance.parts,
};
