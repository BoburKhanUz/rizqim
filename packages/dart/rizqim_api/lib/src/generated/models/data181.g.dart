// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data181.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data181 _$Data181FromJson(Map<String, dynamic> json) => Data181(
  productId: (json['product_id'] as num).toInt(),
  modifierGroups: json['modifier_groups'] as String,
);

Map<String, dynamic> _$Data181ToJson(Data181 instance) => <String, dynamic>{
  'product_id': instance.productId,
  'modifier_groups': instance.modifierGroups,
};
