// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'slots3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Slots3 _$Slots3FromJson(Map<String, dynamic> json) => Slots3(
  id: (json['id'] as num).toInt(),
  uuid: json['uuid'] as String,
  name: json['name'] as String,
  minPicks: (json['min_picks'] as num).toInt(),
  maxPicks: (json['max_picks'] as num).toInt(),
  isRequired: json['is_required'] as bool,
  allowModifiers: json['allow_modifiers'] as bool,
  options: (json['options'] as List<dynamic>)
      .map((e) => Options3.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Slots3ToJson(Slots3 instance) => <String, dynamic>{
  'id': instance.id,
  'uuid': instance.uuid,
  'name': instance.name,
  'min_picks': instance.minPicks,
  'max_picks': instance.maxPicks,
  'is_required': instance.isRequired,
  'allow_modifiers': instance.allowModifiers,
  'options': instance.options,
};
