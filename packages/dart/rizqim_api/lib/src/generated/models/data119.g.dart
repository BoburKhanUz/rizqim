// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data119.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data119 _$Data119FromJson(Map<String, dynamic> json) => Data119(
  kinds: (json['kinds'] as List<dynamic>)
      .map((e) => Kinds.fromJson(e as Map<String, dynamic>))
      .toList(),
  stations: (json['stations'] as List<dynamic>)
      .map((e) => Stations4.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data119ToJson(Data119 instance) => <String, dynamic>{
  'kinds': instance.kinds,
  'stations': instance.stations,
};
