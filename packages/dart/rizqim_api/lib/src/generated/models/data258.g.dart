// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data258.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data258 _$Data258FromJson(Map<String, dynamic> json) => Data258(
  qrs: (json['qrs'] as List<dynamic>)
      .map((e) => Qrs.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data258ToJson(Data258 instance) => <String, dynamic>{
  'qrs': instance.qrs,
};
