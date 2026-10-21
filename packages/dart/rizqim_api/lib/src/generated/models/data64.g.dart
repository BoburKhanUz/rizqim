// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data64.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data64 _$Data64FromJson(Map<String, dynamic> json) => Data64(
  productId: (json['product_id'] as num).toInt(),
  modifierGroups: json['modifier_groups'] as String,
);

Map<String, dynamic> _$Data64ToJson(Data64 instance) => <String, dynamic>{
  'product_id': instance.productId,
  'modifier_groups': instance.modifierGroups,
};
