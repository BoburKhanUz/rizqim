// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data236.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data236 _$Data236FromJson(Map<String, dynamic> json) => Data236(
  event: Event8.fromJson(json['event'] as Map<String, dynamic>),
  guests: Guests2.fromJson(json['guests'] as Map<String, dynamic>),
  stations: (json['stations'] as List<dynamic>)
      .map((e) => Stations9.fromJson(e as Map<String, dynamic>))
      .toList(),
  osh: json['osh'] == null
      ? null
      : Osh5.fromJson(json['osh'] as Map<String, dynamic>),
  warnings: (json['warnings'] as List<dynamic>)
      .map((e) => Warnings16.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data236ToJson(Data236 instance) => <String, dynamic>{
  'event': instance.event,
  'guests': instance.guests,
  'stations': instance.stations,
  'osh': instance.osh,
  'warnings': instance.warnings,
};
