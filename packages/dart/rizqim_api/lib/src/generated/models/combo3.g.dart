// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'combo3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Combo3 _$Combo3FromJson(Map<String, dynamic> json) => Combo3(
  optionId: json['option_id'] as String,
  quantity: (json['quantity'] as num?)?.toInt(),
  modifiers: (json['modifiers'] as List<dynamic>?)
      ?.map((e) => e as String)
      .toList(),
);

Map<String, dynamic> _$Combo3ToJson(Combo3 instance) => <String, dynamic>{
  'option_id': instance.optionId,
  'quantity': instance.quantity,
  'modifiers': instance.modifiers,
};
