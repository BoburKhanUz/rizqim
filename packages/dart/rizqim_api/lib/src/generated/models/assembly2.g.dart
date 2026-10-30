// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'assembly2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Assembly2 _$Assembly2FromJson(Map<String, dynamic> json) => Assembly2(
  stationId: (json['station_id'] as num).toInt(),
  requiredValue: json['required'] as bool,
  waiting: json['waiting'] as bool,
  assembledAt: json['assembled_at'] as String?,
  stations: (json['stations'] as List<dynamic>)
      .map((e) => Stations10.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Assembly2ToJson(Assembly2 instance) => <String, dynamic>{
  'station_id': instance.stationId,
  'required': instance.requiredValue,
  'waiting': instance.waiting,
  'assembled_at': instance.assembledAt,
  'stations': instance.stations,
};
