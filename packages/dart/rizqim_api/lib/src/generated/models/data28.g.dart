// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data28.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data28 _$Data28FromJson(Map<String, dynamic> json) => Data28(
  productId: (json['product_id'] as num).toInt(),
  name: json['name'] as String,
  description: json['description'] as String?,
  imageUrl: json['image_url'] as String?,
  variants: (json['variants'] as List<dynamic>)
      .map((e) => Variants.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data28ToJson(Data28 instance) => <String, dynamic>{
  'product_id': instance.productId,
  'name': instance.name,
  'description': instance.description,
  'image_url': instance.imageUrl,
  'variants': instance.variants,
};
