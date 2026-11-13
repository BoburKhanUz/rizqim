// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data164.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data164 _$Data164FromJson(Map<String, dynamic> json) => Data164(
  channels: json['channels'] as List<dynamic>,
  settings: (json['settings'] as List<dynamic>)
      .map((e) => Settings5.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data164ToJson(Data164 instance) => <String, dynamic>{
  'channels': instance.channels,
  'settings': instance.settings,
};
