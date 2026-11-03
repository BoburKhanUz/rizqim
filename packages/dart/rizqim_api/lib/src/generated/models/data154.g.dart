// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data154.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data154 _$Data154FromJson(Map<String, dynamic> json) => Data154(
  channels: json['channels'] as List<dynamic>,
  settings: (json['settings'] as List<dynamic>)
      .map((e) => Settings3.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data154ToJson(Data154 instance) => <String, dynamic>{
  'channels': instance.channels,
  'settings': instance.settings,
};
