// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'assembly4.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Assembly4 _$Assembly4FromJson(Map<String, dynamic> json) => Assembly4(
  stationId: (json['station_id'] as num).toInt(),
  requiredValue: json['required'] as bool,
  waiting: json['waiting'] as bool,
  assembledAt: json['assembled_at'] as String?,
  stations: (json['stations'] as List<dynamic>)
      .map((e) => Stations12.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Assembly4ToJson(Assembly4 instance) => <String, dynamic>{
  'station_id': instance.stationId,
  'required': instance.requiredValue,
  'waiting': instance.waiting,
  'assembled_at': instance.assembledAt,
  'stations': instance.stations,
};
