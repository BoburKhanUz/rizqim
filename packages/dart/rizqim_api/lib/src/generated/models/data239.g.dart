// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data239.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data239 _$Data239FromJson(Map<String, dynamic> json) => Data239(
  event: Event9.fromJson(json['event'] as Map<String, dynamic>),
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

Map<String, dynamic> _$Data239ToJson(Data239 instance) => <String, dynamic>{
  'event': instance.event,
  'guests': instance.guests,
  'stations': instance.stations,
  'osh': instance.osh,
  'warnings': instance.warnings,
};
