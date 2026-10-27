// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data99.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data99 _$Data99FromJson(Map<String, dynamic> json) => Data99(
  kinds: (json['kinds'] as List<dynamic>)
      .map((e) => Kinds.fromJson(e as Map<String, dynamic>))
      .toList(),
  stations: (json['stations'] as List<dynamic>)
      .map((e) => Stations.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data99ToJson(Data99 instance) => <String, dynamic>{
  'kinds': instance.kinds,
  'stations': instance.stations,
};
