// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data36.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data36 _$Data36FromJson(Map<String, dynamic> json) => Data36(
  productId: (json['product_id'] as num).toInt(),
  name: json['name'] as String,
  description: json['description'] as String?,
  imageUrl: json['image_url'] as String?,
  batchTracked: json['batch_tracked'] as bool,
  variants: (json['variants'] as List<dynamic>)
      .map((e) => Variants.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data36ToJson(Data36 instance) => <String, dynamic>{
  'product_id': instance.productId,
  'name': instance.name,
  'description': instance.description,
  'image_url': instance.imageUrl,
  'batch_tracked': instance.batchTracked,
  'variants': instance.variants,
};
