// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data185.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data185 _$Data185FromJson(Map<String, dynamic> json) => Data185(
  productId: (json['product_id'] as num).toInt(),
  modifierGroups: json['modifier_groups'] as String,
);

Map<String, dynamic> _$Data185ToJson(Data185 instance) => <String, dynamic>{
  'product_id': instance.productId,
  'modifier_groups': instance.modifierGroups,
};
