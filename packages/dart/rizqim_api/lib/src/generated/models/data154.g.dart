// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data154.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data154 _$Data154FromJson(Map<String, dynamic> json) => Data154(
  productId: (json['product_id'] as num).toInt(),
  modifierGroups: json['modifier_groups'] as String,
);

Map<String, dynamic> _$Data154ToJson(Data154 instance) => <String, dynamic>{
  'product_id': instance.productId,
  'modifier_groups': instance.modifierGroups,
};
