// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data78.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data78 _$Data78FromJson(Map<String, dynamic> json) => Data78(
  kinds: (json['kinds'] as List<dynamic>)
      .map((e) => Kinds.fromJson(e as Map<String, dynamic>))
      .toList(),
  stations: (json['stations'] as List<dynamic>)
      .map((e) => Stations.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data78ToJson(Data78 instance) => <String, dynamic>{
  'kinds': instance.kinds,
  'stations': instance.stations,
};
