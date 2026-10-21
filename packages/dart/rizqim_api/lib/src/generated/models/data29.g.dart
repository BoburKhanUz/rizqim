// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data29.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data29 _$Data29FromJson(Map<String, dynamic> json) => Data29(
  productId: (json['product_id'] as num).toInt(),
  name: json['name'] as String,
  description: json['description'] as String?,
  imageUrl: json['image_url'] as String?,
  variants: (json['variants'] as List<dynamic>)
      .map((e) => Variants.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data29ToJson(Data29 instance) => <String, dynamic>{
  'product_id': instance.productId,
  'name': instance.name,
  'description': instance.description,
  'image_url': instance.imageUrl,
  'variants': instance.variants,
};
