// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data67.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data67 _$Data67FromJson(Map<String, dynamic> json) => Data67(
  channels: json['channels'] as List<dynamic>,
  settings: (json['settings'] as List<dynamic>)
      .map((e) => Settings.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data67ToJson(Data67 instance) => <String, dynamic>{
  'channels': instance.channels,
  'settings': instance.settings,
};
