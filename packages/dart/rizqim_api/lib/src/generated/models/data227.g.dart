// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data227.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data227 _$Data227FromJson(Map<String, dynamic> json) => Data227(
  packages: (json['packages'] as List<dynamic>)
      .map((e) => Packages2.fromJson(e as Map<String, dynamic>))
      .toList(),
  extras: (json['extras'] as List<dynamic>)
      .map((e) => Extras3.fromJson(e as Map<String, dynamic>))
      .toList(),
  halls: (json['halls'] as List<dynamic>)
      .map((e) => Halls6.fromJson(e as Map<String, dynamic>))
      .toList(),
  settings: Settings6.fromJson(json['settings'] as Map<String, dynamic>),
  eventTypes: (json['event_types'] as List<dynamic>)
      .map((e) => EventTypes2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data227ToJson(Data227 instance) => <String, dynamic>{
  'packages': instance.packages,
  'extras': instance.extras,
  'halls': instance.halls,
  'settings': instance.settings,
  'event_types': instance.eventTypes,
};
