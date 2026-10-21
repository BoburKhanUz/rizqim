// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data69.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data69 _$Data69FromJson(Map<String, dynamic> json) => Data69(
  channels: json['channels'] as List<dynamic>,
  settings: (json['settings'] as List<dynamic>)
      .map((e) => Settings.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data69ToJson(Data69 instance) => <String, dynamic>{
  'channels': instance.channels,
  'settings': instance.settings,
};
