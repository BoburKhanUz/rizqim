// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data188.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data188 _$Data188FromJson(Map<String, dynamic> json) => Data188(
  channels: json['channels'] as List<dynamic>,
  settings: (json['settings'] as List<dynamic>)
      .map((e) => Settings5.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data188ToJson(Data188 instance) => <String, dynamic>{
  'channels': instance.channels,
  'settings': instance.settings,
};
