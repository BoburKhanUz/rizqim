// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'stations10.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Stations10 _$Stations10FromJson(Map<String, dynamic> json) => Stations10(
  stationId: (json['station_id'] as num?)?.toInt(),
  name: json['name'] as String,
  kind: json['kind'] as String?,
  itemsTotal: (json['items_total'] as num).toInt(),
  itemsReady: (json['items_ready'] as num).toInt(),
  done: json['done'] as bool,
);

Map<String, dynamic> _$Stations10ToJson(Stations10 instance) =>
    <String, dynamic>{
      'station_id': instance.stationId,
      'name': instance.name,
      'kind': instance.kind,
      'items_total': instance.itemsTotal,
      'items_ready': instance.itemsReady,
      'done': instance.done,
    };
