// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'stations9.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Stations9 _$Stations9FromJson(Map<String, dynamic> json) => Stations9(
  stationId: (json['station_id'] as num?)?.toInt(),
  name: json['name'] as String,
  dishes: (json['dishes'] as List<dynamic>)
      .map((e) => Dishes5.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Stations9ToJson(Stations9 instance) => <String, dynamic>{
  'station_id': instance.stationId,
  'name': instance.name,
  'dishes': instance.dishes,
};
