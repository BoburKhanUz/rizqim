// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data143.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data143 _$Data143FromJson(Map<String, dynamic> json) => Data143(
  productId: (json['product_id'] as num).toInt(),
  modifierGroups: json['modifier_groups'] as String,
);

Map<String, dynamic> _$Data143ToJson(Data143 instance) => <String, dynamic>{
  'product_id': instance.productId,
  'modifier_groups': instance.modifierGroups,
};
