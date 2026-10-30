// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'combo2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Combo2 _$Combo2FromJson(Map<String, dynamic> json) => Combo2(
  optionId: json['option_id'] as String,
  quantity: (json['quantity'] as num?)?.toInt(),
  modifiers: (json['modifiers'] as List<dynamic>?)
      ?.map((e) => e as String)
      .toList(),
);

Map<String, dynamic> _$Combo2ToJson(Combo2 instance) => <String, dynamic>{
  'option_id': instance.optionId,
  'quantity': instance.quantity,
  'modifiers': instance.modifiers,
};
