// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data79.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data79 _$Data79FromJson(Map<String, dynamic> json) => Data79(
  kinds: (json['kinds'] as List<dynamic>)
      .map((e) => Kinds.fromJson(e as Map<String, dynamic>))
      .toList(),
  stations: (json['stations'] as List<dynamic>)
      .map((e) => Stations.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data79ToJson(Data79 instance) => <String, dynamic>{
  'kinds': instance.kinds,
  'stations': instance.stations,
};
