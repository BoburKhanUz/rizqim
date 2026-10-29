// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data94.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data94 _$Data94FromJson(Map<String, dynamic> json) => Data94(
  kinds: (json['kinds'] as List<dynamic>)
      .map((e) => Kinds.fromJson(e as Map<String, dynamic>))
      .toList(),
  stations: (json['stations'] as List<dynamic>)
      .map((e) => Stations.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data94ToJson(Data94 instance) => <String, dynamic>{
  'kinds': instance.kinds,
  'stations': instance.stations,
};
