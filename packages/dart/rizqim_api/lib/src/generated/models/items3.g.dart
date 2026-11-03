// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'items3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Items3 _$Items3FromJson(Map<String, dynamic> json) => Items3(
  productId: (json['product_id'] as num).toInt(),
  targetSeconds: (json['target_seconds'] as num?)?.toInt(),
);

Map<String, dynamic> _$Items3ToJson(Items3 instance) => <String, dynamic>{
  'product_id': instance.productId,
  'target_seconds': instance.targetSeconds,
};
