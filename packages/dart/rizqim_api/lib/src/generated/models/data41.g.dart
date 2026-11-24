// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data41.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data41 _$Data41FromJson(Map<String, dynamic> json) => Data41(
  productId: (json['product_id'] as num).toInt(),
  name: json['name'] as String,
  description: json['description'] as String?,
  imageUrl: json['image_url'] as String?,
  batchTracked: json['batch_tracked'] as bool,
  variants: (json['variants'] as List<dynamic>)
      .map((e) => Variants.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data41ToJson(Data41 instance) => <String, dynamic>{
  'product_id': instance.productId,
  'name': instance.name,
  'description': instance.description,
  'image_url': instance.imageUrl,
  'batch_tracked': instance.batchTracked,
  'variants': instance.variants,
};
