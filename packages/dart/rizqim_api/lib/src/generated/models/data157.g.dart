// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data157.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data157 _$Data157FromJson(Map<String, dynamic> json) => Data157(
  channels: json['channels'] as List<dynamic>,
  settings: (json['settings'] as List<dynamic>)
      .map((e) => Settings3.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data157ToJson(Data157 instance) => <String, dynamic>{
  'channels': instance.channels,
  'settings': instance.settings,
};
