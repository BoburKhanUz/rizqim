// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'links.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Links _$LinksFromJson(Map<String, dynamic> json) => Links(
  productId: (json['product_id'] as num).toInt(),
  isCritical: json['is_critical'] as bool?,
);

Map<String, dynamic> _$LinksToJson(Links instance) => <String, dynamic>{
  'product_id': instance.productId,
  'is_critical': instance.isCritical,
};
