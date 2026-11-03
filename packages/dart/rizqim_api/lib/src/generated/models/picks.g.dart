// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'picks.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Picks _$PicksFromJson(Map<String, dynamic> json) => Picks(
  optionId: json['option_id'] as String,
  quantity: (json['quantity'] as num?)?.toInt(),
  modifiers: (json['modifiers'] as List<dynamic>?)
      ?.map((e) => e as String)
      .toList(),
);

Map<String, dynamic> _$PicksToJson(Picks instance) => <String, dynamic>{
  'option_id': instance.optionId,
  'quantity': instance.quantity,
  'modifiers': instance.modifiers,
};
