// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'assembly3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Assembly3 _$Assembly3FromJson(Map<String, dynamic> json) => Assembly3(
  stationId: (json['station_id'] as num).toInt(),
  requiredValue: json['required'] as bool,
  waiting: json['waiting'] as bool,
  assembledAt: json['assembled_at'] as String?,
  stations: (json['stations'] as List<dynamic>)
      .map((e) => Stations11.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Assembly3ToJson(Assembly3 instance) => <String, dynamic>{
  'station_id': instance.stationId,
  'required': instance.requiredValue,
  'waiting': instance.waiting,
  'assembled_at': instance.assembledAt,
  'stations': instance.stations,
};
