// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data128.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data128 _$Data128FromJson(Map<String, dynamic> json) => Data128(
  productId: (json['product_id'] as num).toInt(),
  modifierGroups: json['modifier_groups'] as String,
);

Map<String, dynamic> _$Data128ToJson(Data128 instance) => <String, dynamic>{
  'product_id': instance.productId,
  'modifier_groups': instance.modifierGroups,
};
