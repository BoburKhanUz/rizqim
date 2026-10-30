// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'items2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Items2 _$Items2FromJson(Map<String, dynamic> json) => Items2(
  variantId: json['variant_id'] as String,
  quantity: json['quantity'] as num,
  modifiers: (json['modifiers'] as List<dynamic>?)
      ?.map((e) => e as String)
      .toList(),
  combo: (json['combo'] as List<dynamic>?)
      ?.map((e) => Combo.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Items2ToJson(Items2 instance) => <String, dynamic>{
  'variant_id': instance.variantId,
  'quantity': instance.quantity,
  'modifiers': instance.modifiers,
  'combo': instance.combo,
};
