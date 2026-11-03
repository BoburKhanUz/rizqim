// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'stations13.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Stations13 _$Stations13FromJson(Map<String, dynamic> json) => Stations13(
  stationId: (json['station_id'] as num?)?.toInt(),
  name: json['name'] as String,
  kind: json['kind'] as String?,
  itemsTotal: (json['items_total'] as num).toInt(),
  itemsReady: (json['items_ready'] as num).toInt(),
  done: json['done'] as bool,
);

Map<String, dynamic> _$Stations13ToJson(Stations13 instance) =>
    <String, dynamic>{
      'station_id': instance.stationId,
      'name': instance.name,
      'kind': instance.kind,
      'items_total': instance.itemsTotal,
      'items_ready': instance.itemsReady,
      'done': instance.done,
    };
