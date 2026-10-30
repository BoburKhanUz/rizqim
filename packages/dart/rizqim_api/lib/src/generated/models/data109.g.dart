// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data109.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data109 _$Data109FromJson(Map<String, dynamic> json) => Data109(
  kinds: (json['kinds'] as List<dynamic>)
      .map((e) => Kinds.fromJson(e as Map<String, dynamic>))
      .toList(),
  stations: (json['stations'] as List<dynamic>)
      .map((e) => Stations3.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data109ToJson(Data109 instance) => <String, dynamic>{
  'kinds': instance.kinds,
  'stations': instance.stations,
};
