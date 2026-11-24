// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data195.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data195 _$Data195FromJson(Map<String, dynamic> json) => Data195(
  channels: json['channels'] as List<dynamic>,
  settings: (json['settings'] as List<dynamic>)
      .map((e) => Settings5.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data195ToJson(Data195 instance) => <String, dynamic>{
  'channels': instance.channels,
  'settings': instance.settings,
};
