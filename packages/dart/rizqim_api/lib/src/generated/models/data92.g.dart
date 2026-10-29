// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data92.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data92 _$Data92FromJson(Map<String, dynamic> json) => Data92(
  kinds: (json['kinds'] as List<dynamic>)
      .map((e) => Kinds.fromJson(e as Map<String, dynamic>))
      .toList(),
  stations: (json['stations'] as List<dynamic>)
      .map((e) => Stations.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data92ToJson(Data92 instance) => <String, dynamic>{
  'kinds': instance.kinds,
  'stations': instance.stations,
};
