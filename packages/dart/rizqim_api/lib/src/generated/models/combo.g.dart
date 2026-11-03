// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'combo.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Combo _$ComboFromJson(Map<String, dynamic> json) => Combo(
  optionId: json['option_id'] as String,
  quantity: (json['quantity'] as num?)?.toInt(),
  modifiers: (json['modifiers'] as List<dynamic>?)
      ?.map((e) => e as String)
      .toList(),
);

Map<String, dynamic> _$ComboToJson(Combo instance) => <String, dynamic>{
  'option_id': instance.optionId,
  'quantity': instance.quantity,
  'modifiers': instance.modifiers,
};
