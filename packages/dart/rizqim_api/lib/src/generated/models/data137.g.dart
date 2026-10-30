// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data137.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data137 _$Data137FromJson(Map<String, dynamic> json) => Data137(
  channels: json['channels'] as List<dynamic>,
  settings: (json['settings'] as List<dynamic>)
      .map((e) => Settings3.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data137ToJson(Data137 instance) => <String, dynamic>{
  'channels': instance.channels,
  'settings': instance.settings,
};
