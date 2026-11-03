// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data144.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data144 _$Data144FromJson(Map<String, dynamic> json) => Data144(
  productId: (json['product_id'] as num).toInt(),
  modifierGroups: json['modifier_groups'] as String,
);

Map<String, dynamic> _$Data144ToJson(Data144 instance) => <String, dynamic>{
  'product_id': instance.productId,
  'modifier_groups': instance.modifierGroups,
};
