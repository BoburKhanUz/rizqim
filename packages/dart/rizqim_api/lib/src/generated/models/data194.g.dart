// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data194.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data194 _$Data194FromJson(Map<String, dynamic> json) => Data194(
  channels: json['channels'] as List<dynamic>,
  settings: (json['settings'] as List<dynamic>)
      .map((e) => Settings5.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data194ToJson(Data194 instance) => <String, dynamic>{
  'channels': instance.channels,
  'settings': instance.settings,
};
