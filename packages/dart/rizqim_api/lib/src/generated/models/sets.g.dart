// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'sets.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Sets _$SetsFromJson(Map<String, dynamic> json) => Sets(
  id: (json['id'] as num).toInt(),
  uuid: json['uuid'] as String,
  productId: (json['product_id'] as num).toInt(),
  productName: json['product_name'] as String,
  prices: (json['prices'] as List<dynamic>).map((e) => e as String).toList(),
  isActive: json['is_active'] as bool,
  parts: (json['parts'] as List<dynamic>)
      .map((e) => Parts2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$SetsToJson(Sets instance) => <String, dynamic>{
  'id': instance.id,
  'uuid': instance.uuid,
  'product_id': instance.productId,
  'product_name': instance.productName,
  'prices': instance.prices,
  'is_active': instance.isActive,
  'parts': instance.parts,
};
